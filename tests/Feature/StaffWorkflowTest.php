<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Staff;
use Illuminate\Support\Str;
use Tests\AuthenticationTestCase as TestCase;

class StaffWorkflowTest extends TestCase
{
    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = Staff::create(['name' => 'Test Cashier', 'username' => 'cashier', 'password' => 'password123', 'role' => 'Staff', 'is_active' => true]);
        $this->actingAs($this->staff, 'staff');
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge(['name' => 'Cement', 'category' => 'Building Materials', 'stock' => 10, 'price' => '100.25', 'status' => 'Available'], $attributes));
    }

    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge(['request_key' => (string) Str::uuid(), 'action' => 'checkout', 'type' => 'Walk-in',
            'payment_method' => 'Cash', 'amount_tendered' => '1000.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'discount_percent' => 0]]], $overrides);
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Buyer One', 'phone' => '09171234567', 'address' => 'Silang, Cavite', 'is_active' => true]);
    }

    public function test_order_back_link_keeps_listing_filters_and_has_a_safe_fallback(): void
    {
        $this->postJson(route('pos.checkout'), $this->payload($this->product()))->assertOk();
        $order = Order::firstOrFail();
        $listing = route('orders', ['status' => 'Completed', 'page' => 2]);
        $this->from($listing)->get(route('staff.orders.show', $order))
            ->assertOk()->assertSee('href="'.e($listing).'"', false);
        $this->from('https://outside.example/orders')->get(route('staff.orders.show', $order))
            ->assertOk()->assertSee('href="'.route('orders').'"', false)
            ->assertDontSee('outside.example');
    }

    public function test_checkout_uses_database_prices_quantities_cashier_and_updates_every_screen(): void
    {
        $product = $this->product();
        $data = $this->payload($product);
        $data['items'][0]['price'] = '0.01';
        $data['total_amount'] = '0.01';
        $this->postJson('/pos/checkout', $data)->assertOk()->assertJsonStructure(['redirect', 'number']);
        $order = Order::firstOrFail();
        $this->assertEquals('200.50', $order->total_amount);
        $this->assertEquals('200.50', $order->paid_amount);
        $this->assertSame('Test Cashier', $order->staff_name);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertDatabaseHas('sales', ['order_id' => $order->id, 'qty' => 2, 'total_amount' => 200.50, 'paid_amount' => 200.50]);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount' => 200.50, 'tendered' => 1000]);
        $this->get('/dashboardstaff')->assertOk()->assertViewHas('metrics', fn ($metrics) => $metrics['Items Sold Today'] == 2 && $metrics['Transactions Today'] == 1);
        $this->get('/transactions')->assertOk()->assertSee($order->number)->assertSee('200.50');
        $this->get('/staff/orders/'.$order->id)->assertOk()->assertSee('Cement')->assertSee('799.50');
        $this->get('/staff/inventory')->assertOk()->assertSee('Cement');
        $this->getJson('/staff/catalog')->assertOk()->assertJsonFragment(['stock' => 8]);
    }

    public function test_insufficient_stock_rolls_back_the_entire_cart(): void
    {
        $first = $this->product();
        $second = $this->product(['name' => 'Steel', 'stock' => 1]);
        $data = $this->payload($first);
        $data['items'][] = ['product_id' => $second->id, 'quantity' => 2];
        $this->postJson('/pos/checkout', $data)->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertSame(10, $first->fresh()->stock);
        $this->assertSame(1, $second->fresh()->stock);
    }

    public function test_checkout_retry_is_idempotent_and_receipts_are_unique(): void
    {
        $product = $this->product();
        $data = $this->payload($product);
        $first = $this->postJson('/pos/checkout', $data)->assertOk()->json('number');
        $this->postJson('/pos/checkout', $data)->assertOk()->assertJsonPath('number', $first);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_payments', 1);
        $this->assertSame(8, $product->fresh()->stock);
        $second = $this->postJson('/pos/checkout', $this->payload($product))->assertOk()->json('number');
        $this->assertNotSame($first, $second);
    }

    public function test_duplicate_products_invalid_quantities_and_discounts_are_rejected(): void
    {
        $product = $this->product();
        $data = $this->payload($product);
        $data['items'][] = $data['items'][0];
        $this->postJson('/pos/checkout', $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $data['items'] = [['product_id' => $product->id, 'quantity' => -1, 'discount_percent' => 101]];
        $this->postJson('/pos/checkout', $data)->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity', 'items.0.discount_percent']);
        $data['items'][0]['quantity'] = 1.5;
        $this->postJson('/pos/checkout', $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_held_order_and_quotation_are_saved_without_sales_and_can_be_resumed(): void
    {
        $product = $this->product();
        $this->postJson('/pos/checkout', $this->payload($product, ['action' => 'hold']))->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame('Held', $order->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->get('/pos?order='.$order->id)->assertOk()->assertSee($order->number);
        $product->update(['price' => '125.00']);
        $this->postJson('/pos/checkout', $this->payload($product, ['order_id' => $order->id, 'version' => 1]))->assertOk();
        $this->assertEquals('250.00', $order->fresh()->total_amount);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
        $this->postJson('/pos/checkout', $this->payload($product, ['order_id' => $order->id, 'version' => 1]))->assertUnprocessable();
        $this->postJson('/pos/checkout', $this->payload($product, ['action' => 'quote']))->assertOk();
        $this->assertDatabaseHas('orders', ['status' => 'Quotation', 'paid_amount' => 0]);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_discounts_delivery_partial_payment_and_followup_payment_reconcile(): void
    {
        $product = $this->product(['price' => 100]);
        $second = $this->product(['name' => 'Steel', 'price' => 50]);
        $customer = $this->customer();
        $data = $this->payload($product, ['type' => 'Delivery', 'customer_id' => $customer->id, 'delivery_fee' => 30, 'amount_tendered' => 100,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'discount_percent' => 10], ['product_id' => $second->id, 'quantity' => 1]]]);
        $this->postJson('/pos/checkout', $data)->assertOk();
        $order = Order::firstOrFail();
        // The configured Silang delivery charge overrides the submitted fee.
        $this->assertEquals('500.00', $order->delivery_fee);
        $this->assertEquals('730.00', $order->total_amount);
        $this->assertEquals('20.00', $order->discount_amount);
        $this->assertSame('Partial', $order->payment_status);
        $this->assertEquals(730, Sale::sum('total_amount'));
        $this->assertEquals(100, Sale::sum('paid_amount'));
        $payment = ['request_key' => (string) Str::uuid(), 'method' => 'Cash', 'amount' => 700];
        $this->post(route('staff.orders.payment', $order), $payment)->assertSessionHasNoErrors();
        $this->post(route('staff.orders.payment', $order), $payment)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('order_payments', 2);
        $this->assertEquals(730, Sale::sum('paid_amount'));
        $this->assertSame('Paid', $order->fresh()->payment_status);
        $this->get('/customers?customer='.$customer->id)->assertOk()->assertSee($order->number);
        $this->get('/transactions?payment_status=Paid')->assertOk()->assertSee($order->number);
        $this->get('/transactions?payment_status=Partial')->assertOk()->assertDontSee($order->number);
    }

    public function test_unpaid_cancellation_restores_stock_once_and_removes_revenue(): void
    {
        $product = $this->product(['stock' => 2]);
        $this->postJson('/pos/checkout', $this->payload($product, ['type' => 'Pickup', 'customer_id' => $this->customer()->id, 'payment_method' => 'Charge', 'amount_tendered' => 0]))->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame('Out of Stock', $product->fresh()->status);
        $this->patch(route('staff.orders.status', $order), ['status' => 'Cancelled', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame('Low Stock', $product->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
        $this->patchJson(route('staff.orders.status', $order), ['status' => 'Cancelled', 'version' => 2])->assertUnprocessable();
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_paid_order_cannot_be_cancelled_and_fulfillment_follows_valid_transitions(): void
    {
        $product = $this->product();
        $this->postJson('/pos/checkout', $this->payload($product, ['type' => 'Delivery', 'customer_id' => $this->customer()->id]))->assertOk();
        $order = Order::firstOrFail();
        $this->patchJson(route('staff.orders.status', $order), ['status' => 'Cancelled', 'version' => 1])->assertUnprocessable();
        $this->patchJson(route('staff.orders.status', $order), ['status' => 'Completed', 'version' => 1])->assertUnprocessable();
        foreach (['Processing', 'Ready', 'Out for Delivery', 'Completed'] as $index => $status) {
            $this->patch(route('staff.orders.status', $order), ['status' => $status, 'version' => $index + 1])->assertSessionHasNoErrors();
        }
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertDatabaseHas('sales', ['order_id' => $order->id, 'status' => 'Completed']);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_delivery_charge_partial_and_electronic_payments_validate_required_details(): void
    {
        $product = $this->product();
        foreach ([['type' => 'Delivery'], ['payment_method' => 'Charge', 'amount_tendered' => 0], ['amount_tendered' => 1]] as $overrides) {
            $this->postJson('/pos/checkout', $this->payload($product, $overrides))->assertUnprocessable();
        }
        $this->postJson('/pos/checkout', $this->payload($product, ['payment_method' => 'Online']))->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->postJson('/pos/checkout', $this->payload($product, ['payment_method' => 'Online', 'reference' => 'REF-1']))->assertUnprocessable();
        $this->postJson('/pos/checkout', $this->payload($product, ['payment_method' => 'Online', 'reference' => 'REF-1', 'amount_tendered' => 200.50]))->assertOk();
    }

    public function test_customer_contact_creation_update_and_escaping(): void
    {
        $this->post('/staff/customers', ['name' => '<script>alert(1)</script>', 'phone' => '09170000000', 'address' => 'Cavite'])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->assertNull($customer->username);
        $this->assertNull($customer->password);
        $this->get('/customers?customer='.$customer->id)->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->put('/staff/customers/'.$customer->id, ['name' => 'Updated Buyer', 'email' => 'buyer@example.test', 'phone' => '123', 'address' => 'Manila', 'password' => 'injected', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame('Updated Buyer', $customer->fresh()->name);
        $this->assertNull($customer->fresh()->password);
        $this->assertTrue($customer->fresh()->is_active);
        $this->get('/customers?search=Manila')->assertOk()->assertSee('Updated Buyer');
    }

    public function test_legacy_sales_are_grouped_and_inventory_filters_use_actual_stock(): void
    {
        foreach ([['qty' => 3, 'total_amount' => 100], ['qty' => 2, 'total_amount' => 200]] as $line) {
            Sale::create(array_merge(['receipt_no' => 'OLD-1', 'staff_name' => 'Admin', 'item_name' => 'Old Product', 'status' => 'Completed', 'payment_status' => 'Paid', 'paid_amount' => $line['total_amount']], $line));
        }
        $this->get('/transactions')->assertOk()->assertViewHas('receipts', fn ($receipts) => $receipts->total() === 1 && $receipts->first()->total == 300);
        $this->get('/staff/receipt?receipt=OLD-1')->assertOk()->assertSee('300.00');
        $this->get('/dashboardstaff')->assertViewHas('metrics', fn ($metrics) => $metrics['Transactions Today'] == 1 && $metrics['Items Sold Today'] == 5);
        $this->product(['name' => 'Low Cement', 'stock' => 3]);
        $this->product(['name' => 'Abundant Steel', 'stock' => 20]);
        $this->get('/staff/inventory?stock=low')->assertOk()->assertSee('Low Cement')->assertDontSee('Abundant Steel');
    }

    public function test_staff_write_endpoints_reject_an_admin_session(): void
    {
        auth('staff')->logout();
        $admin = Admin::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@example.test', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($admin, 'admin');
        $this->postJson('/pos/checkout', [])->assertUnauthorized();
        $this->postJson('/staff/customers', ['name' => 'Invalid'])->assertUnauthorized();
        $this->getJson('/staff/catalog')->assertUnauthorized();
    }

    public function test_admin_payment_syncs_to_staff_order_and_linked_sales_cannot_be_deleted(): void
    {
        $product = $this->product();
        $this->postJson('/pos/checkout', $this->payload($product, ['customer_id' => $this->customer()->id,
            'payment_method' => 'Charge', 'amount_tendered' => 0]))->assertOk();
        $sale = Sale::firstOrFail();
        $admin = Admin::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@example.test', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($admin, 'admin');
        $this->post('/sales/'.$sale->id.'/paid')->assertSessionHasNoErrors();
        $this->assertSame('Paid', Order::first()->payment_status);
        $this->assertEquals('200.50', Order::first()->paid_amount);
        $this->assertDatabaseCount('order_payments', 1);
        $this->delete('/sales/'.$sale->id)->assertSessionHas('error');
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_price_changes_after_catalog_load_require_review_before_checkout(): void
    {
        $product = $this->product();
        $data = $this->payload($product);
        $data['items'][0]['expected_price'] = 100.25;
        $product->update(['price' => 120]);
        $this->postJson('/pos/checkout', $data)->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
        $data['items'][0]['expected_price'] = 120;
        $this->postJson('/pos/checkout', $data)->assertOk();
        $this->assertEquals('240.00', Order::first()->total_amount);
    }
}
