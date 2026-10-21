<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Staff;
use Illuminate\Support\Str;
use Tests\AuthenticationTestCase;

class StaffDeliveryZoneTest extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Staff::create([
            'name' => 'Delivery Cashier', 'username' => 'delivery.cashier',
            'password' => 'password123', 'role' => 'Staff',
            'is_active' => true, 'permissions' => ['pos'],
        ]), 'staff');
    }

    public function test_lookup_returns_configured_zone_fees_and_rejects_unverified_addresses(): void
    {
        foreach (config('delivery.zones') as $id => $zone) {
            $this->postJson(route('delivery-zone.lookup'), ['address' => $zone['name']])
                ->assertOk()->assertJsonPath('zone.zone_id', $id)
                ->assertJsonPath('zone.fee', $zone['fee']);
        }

        foreach (['Outside the service area', 'Silang, Imus'] as $address) {
            $this->postJson(route('delivery-zone.lookup'), compact('address'))
                ->assertUnprocessable()->assertJsonPath('success', false);
        }
        $this->postJson(route('delivery-zone.lookup'), [])->assertUnprocessable();
    }

    public function test_checkout_recalculates_the_fee_and_total_from_the_verified_address(): void
    {
        $product = Product::create([
            'name' => 'Cement', 'category' => 'Building Materials',
            'stock' => 10, 'price' => 100, 'status' => 'Available',
        ]);
        $customer = Customer::create([
            'name' => 'Delivery Buyer', 'address' => 'Silang, Cavite', 'is_active' => true,
        ]);
        $payload = [
            'request_key' => (string) Str::uuid(), 'action' => 'checkout', 'type' => 'Delivery',
            'customer_id' => $customer->id, 'address' => 'Imus, Cavite',
            'delivery_fee' => 1, 'payment_method' => 'Cash', 'amount_tendered' => 1100,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ];

        $this->postJson(route('pos.checkout'), $payload)->assertOk();
        $order = Order::firstOrFail();
        $this->assertEquals(900, $order->delivery_fee);
        $this->assertEquals(1100, $order->total_amount);
        $this->assertEquals(1100, $order->paid_amount);
        $this->assertSame(8, $product->fresh()->stock);

        $payload['request_key'] = (string) Str::uuid();
        $payload['address'] = 'Outside the service area';
        $this->postJson(route('pos.checkout'), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('order');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $product->fresh()->stock);

        $payload['action'] = 'hold';
        $this->postJson(route('pos.checkout'), $payload)->assertOk();
        $draft = Order::where('status', 'Held')->firstOrFail();
        $this->assertEquals(0, $draft->delivery_fee);
        $this->assertEquals(200, $draft->total_amount);
    }
}
