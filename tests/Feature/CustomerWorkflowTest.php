<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerCart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Staff;
use App\Services\CustomerOrderService;
use App\Services\StaffOrderService;
use Illuminate\Support\Str;
use Tests\AuthenticationTestCase;

class CustomerWorkflowTest extends AuthenticationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['name' => 'Customer One', 'username' => 'buyer',
            'email' => 'buyer@example.test', 'password' => 'password123', 'phone' => '09171234567',
            'address' => 'Silang, Cavite', 'is_active' => true]);
        $this->actingAs($this->customer, 'customer');
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge(['name' => 'Cement', 'category' => 'Building Materials',
            'price' => '100.25', 'stock' => 10, 'stock_unit' => 'bag', 'status' => 'Available'], $attributes));
    }

    private function saveCart(Product $product, int $quantity = 2): array
    {
        $cart = app(CustomerOrderService::class)->cart($this->customer);

        return $this->putJson('/customer/order/cart', ['version' => $cart->version,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]]])->assertOk()->json();
    }

    private function checkoutData(array $cart, array $overrides = []): array
    {
        return array_merge(['request_key' => $cart['checkout_key'], 'version' => $cart['version'],
            'fulfillment' => 'pickup', 'name' => 'Customer One', 'phone' => '09171234567',
            'date' => today()->addDay()->toDateString(), 'time' => '08:15',
            'items' => array_map(fn ($item) => ['product_id' => $item['id'], 'quantity' => $item['quantity'],
                'expected_price' => $item['price']], $cart['items'])], $overrides);
    }

    public function test_catalog_uses_database_products_and_cart_survives_navigation(): void
    {
        $product = $this->product(['name' => 'Builder\'s <script>alert(1)</script> Cement']);
        $this->get('/customer/order')->assertOk()->assertViewHas('products', fn ($products) => $products->first()['id'] === $product->id && $products->first()['unit'] === 'bag')
            ->assertDontSee('<script>alert(1)</script>', false);
        $cart = $this->saveCart($product);
        foreach (['order', 'cart', 'checkout'] as $page) {
            $this->get('/customer/'.$page.'?cart=untrusted')->assertOk()
                ->assertViewHas('customerCart', fn ($saved) => $saved === $cart);
        }
        $this->assertDatabaseCount('customer_carts', 1);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_cart_updates_removals_and_stale_windows_are_handled(): void
    {
        $product = $this->product();
        $cart = $this->saveCart($product);
        $new = $this->putJson('/customer/cart', ['version' => $cart['version'],
            'items' => [['product_id' => $product->id, 'quantity' => 3]]])->assertOk()->json();
        $this->putJson('/customer/cart', ['version' => $cart['version'], 'items' => []])->assertConflict();
        $this->assertSame(3, CustomerCart::first()->items[0]['quantity']);
        $this->putJson('/customer/cart', ['version' => $new['version'], 'items' => []])->assertOk()->assertJsonPath('items', []);
    }

    public function test_cart_is_scoped_to_signed_in_customer_and_ignores_client_prices(): void
    {
        $product = $this->product();
        $cart = $this->saveCart($product);
        $other = Customer::create(['name' => 'Other', 'is_active' => true]);
        $this->actingAs($other, 'customer')->get('/customer/cart')->assertOk()
            ->assertViewHas('customerCart', fn ($snapshot) => $snapshot['items'] === []);
        $this->putJson('/customer/cart', ['version' => 1, 'customer_id' => $this->customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 0.01]]])
            ->assertOk()->assertJsonPath('items.0.price', 100.25);
        $this->assertSame($cart['items'][0]['quantity'], CustomerCart::where('customer_id', $this->customer->id)->first()->items[0]['quantity']);
    }

    public function test_unavailable_items_can_be_removed_one_at_a_time(): void
    {
        $first = $this->product();
        $second = $this->product(['name' => 'Steel']);
        $cart = $this->putJson('/customer/cart', ['version' => 1, 'items' => [
            ['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 2],
        ]])->assertOk()->json();
        $first->update(['stock' => 0]);
        $second->delete();
        $cart = $this->putJson('/customer/cart', ['version' => $cart['version'], 'items' => [
            ['product_id' => $second->id, 'quantity' => 2],
        ]])->assertOk()->assertJsonPath('items.0.stock', 0)->json();
        $this->putJson('/customer/cart', ['version' => $cart['version'], 'items' => []])
            ->assertOk()->assertJsonPath('items', []);
    }

    public function test_pickup_checkout_creates_real_unpaid_order_and_clears_cart_once(): void
    {
        $product = $this->product();
        $data = $this->checkoutData($this->saveCart($product), ['total_amount' => 0.01,
            'delivery_fee' => 900, 'customer_id' => 999, 'payment_status' => 'Paid']);
        $first = $this->postJson('/customer/checkout', $data)->assertOk()->json();
        $this->postJson('/customer/checkout', $data)->assertOk()->assertJsonPath('number', $first['number']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_payments', 0);
        $order = Order::firstOrFail();
        $this->assertSame($this->customer->id, $order->customer_id);
        $this->assertSame('Pending', $order->status);
        $this->assertSame('Unpaid', $order->payment_status);
        $this->assertEquals('200.50', $order->total_amount);
        $this->assertEquals('0.00', $order->paid_amount);
        $this->assertSame($data['date'].' 08:15', $order->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame([], CustomerCart::first()->items);
        $this->assertDatabaseHas('sales', ['order_id' => $order->id, 'qty' => 2, 'total_amount' => 200.50, 'paid_amount' => 0]);
        $staff = Staff::create(['name' => 'Cashier', 'username' => 'cashier', 'password' => 'password123', 'role' => 'Staff', 'is_active' => true]);
        $this->actingAs($staff, 'staff')->get('/orders')->assertOk()->assertSee($order->number);
        $this->get('/staff/orders/'.$order->id)->assertOk()->assertSee($data['date']);
        $this->post('/staff/orders/'.$order->id.'/payments', ['request_key' => (string) Str::uuid(),
            'method' => 'Cash', 'amount' => '200.50'])->assertSessionHasNoErrors();
        $this->assertSame('Paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('sales', ['order_id' => $order->id, 'paid_amount' => 200.50]);
    }

    public function test_delivery_fee_and_address_are_saved_and_cancellation_restores_stock(): void
    {
        $product = $this->product();
        $data = $this->checkoutData($this->saveCart($product), ['fulfillment' => 'delivery', 'address' => 'Delivery address', 'delivery_fee' => 0]);
        $this->postJson('/customer/checkout', $data)->assertOk();
        $order = Order::firstOrFail();
        $this->assertEquals('250.50', $order->total_amount);
        $this->assertEquals('50.00', $order->delivery_fee);
        $this->assertSame('Delivery address', $order->address);
        app(StaffOrderService::class)->changeStatus($order, 'Cancelled', 1);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_stock_failure_rolls_back_all_items_and_preserves_cart(): void
    {
        $first = $this->product();
        $second = $this->product(['name' => 'Steel']);
        $cart = $this->putJson('/customer/order/cart', ['version' => 1, 'items' => [
            ['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 2],
        ]])->assertOk()->json();
        $second->update(['stock' => 1]);
        $this->postJson('/customer/checkout', $this->checkoutData($cart))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $first->fresh()->stock);
        $this->assertCount(2, CustomerCart::first()->items);
    }

    public function test_price_changes_require_review_before_order_can_be_placed(): void
    {
        $product = $this->product();
        $data = $this->checkoutData($this->saveCart($product));
        $product->update(['price' => 110]);
        $this->postJson('/customer/checkout', $data)->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->assertDatabaseCount('orders', 0);
        $snapshot = $this->get('/customer/checkout')->assertOk()->viewData('customerCart');
        $this->postJson('/customer/checkout', $this->checkoutData($snapshot))->assertOk()->assertJsonPath('total', '220.00');
    }

    public function test_invalid_quantities_duplicates_and_unknown_products_are_rejected(): void
    {
        $product = $this->product();
        foreach ([0, -1, 1.5, 11] as $quantity) {
            $this->putJson('/customer/cart', ['version' => 1, 'items' => [
                ['product_id' => $product->id, 'quantity' => $quantity],
            ]])->assertUnprocessable();
        }
        $this->putJson('/customer/cart', ['version' => 1, 'items' => [
            ['product_id' => $product->id, 'quantity' => 1], ['product_id' => $product->id, 'quantity' => 1],
        ]])->assertUnprocessable();
        $this->putJson('/customer/cart', ['version' => 1, 'items' => [
            ['product_id' => 999999, 'quantity' => 1],
        ]])->assertUnprocessable();
        $this->assertDatabaseCount('customer_carts', 0);
    }

    public function test_checkout_validates_contact_delivery_address_and_schedule(): void
    {
        $data = $this->checkoutData($this->saveCart($this->product()));
        foreach ([['date' => today()->toDateString()], ['time' => '03:00'], ['time' => '09:03'],
            ['name' => ''], ['phone' => ''], ['fulfillment' => 'delivery'], ['fulfillment' => 'invalid']] as $invalid) {
            $this->postJson('/customer/checkout', array_merge($data, $invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_stale_carts_deleted_products_and_empty_carts(): void
    {
        $product = $this->product();
        $cart = $this->saveCart($product);
        $data = $this->checkoutData($cart);
        $this->postJson('/customer/checkout', array_merge($data, ['request_key' => (string) Str::uuid()]))->assertConflict();
        $product->delete();
        $this->get('/customer/cart')->assertOk()->assertViewHas('customerCart', fn ($snapshot) => $snapshot['items'][0]['stock'] === 0);
        $this->postJson('/customer/checkout', $data)->assertUnprocessable();
        $empty = $this->putJson('/customer/cart', ['version' => $cart['version'], 'items' => []])->assertOk()->json();
        $this->postJson('/customer/checkout', array_merge($data, ['version' => $empty['version'],
            'request_key' => $empty['checkout_key']]))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customers_cannot_replay_another_customers_order(): void
    {
        $data = $this->checkoutData($this->saveCart($this->product()));
        $this->postJson('/customer/checkout', $data)->assertOk();
        $other = Customer::create(['name' => 'Other', 'is_active' => true]);
        $this->actingAs($other, 'customer')->postJson('/customer/checkout', $data)->assertForbidden();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_customer_endpoints_require_customer_authentication(): void
    {
        auth('customer')->logout();
        $this->getJson('/customer/order')->assertUnauthorized();
        $this->putJson('/customer/cart', ['version' => 1, 'items' => []])->assertUnauthorized();
        $this->postJson('/customer/checkout', [])->assertUnauthorized();
    }
}
