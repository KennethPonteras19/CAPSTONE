<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOrderService
{
    public function cart(Customer $customer): CustomerCart
    {
        return CustomerCart::firstOrCreate(['customer_id' => $customer->id], [
            'items' => [], 'version' => 1, 'checkout_key' => (string) Str::uuid(),
        ]);
    }

    public function product(Product $product): array
    {
        return ['id' => $product->id, 'name' => $product->name, 'category' => $product->category,
            'price' => (float) $product->price, 'stock' => max(0, (int) $product->stock),
            'unit' => $product->stock_unit ?: 'piece', 'image_url' => $product->image_url];
    }

    public function snapshot(CustomerCart $cart): array
    {
        $products = Product::whereIn('id', array_column($cart->items, 'product_id'))->get()->keyBy('id');
        $items = array_map(function ($item) use ($products) {
            $product = $products->get($item['product_id']);

            return array_merge($product ? $this->product($product) : [
                'id' => $item['product_id'], 'name' => 'Unavailable product', 'category' => 'Others',
                'price' => 0, 'stock' => 0, 'unit' => 'piece',
            ], ['quantity' => $item['quantity']]);
        }, $cart->items);

        return ['items' => $items, 'version' => $cart->version, 'checkout_key' => $cart->checkout_key];
    }

    public function updateCart(Customer $customer, array $data): array
    {
        return DB::transaction(function () use ($customer, $data) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $cart = $this->cart($customer);
            abort_if($cart->version !== (int) $data['version'], 409, 'Your cart changed in another window. Reload this page.');
            $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
            $previous = collect($cart->items)->keyBy('product_id');
            $items = [];
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $increasing = $item['quantity'] > ($previous->get($item['product_id'])['quantity'] ?? 0);
                // Always allow removing/reducing stale lines. Otherwise two
                // unavailable products could prevent either one being removed.
                if ($increasing && (! $product || $product->stock < $item['quantity'])) {
                    $this->fail('A product is unavailable or has insufficient stock. Reload and review your cart.');
                }
                $items[] = ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity']];
            }
            $cart->update(['items' => $items, 'version' => $cart->version + 1, 'checkout_key' => (string) Str::uuid()]);

            return $this->snapshot($cart);
        }, 3);
    }

    public function checkout(Customer $customer, array $data, StaffOrderService $workflow): Order
    {
        return DB::transaction(function () use ($customer, $data, $workflow) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($existing = Order::where('request_key', $data['request_key'])->first()) {
                abort_unless($existing->customer_id === $customer->id && $existing->staff_id === null, 403);

                return $existing;
            }
            $cart = $this->cart($customer);
            abort_if($cart->checkout_key !== $data['request_key'] || $cart->version !== (int) $data['version'],
                409, 'Your cart changed. Reload checkout and review your order.');
            if (! count($cart->items)) {
                $this->fail('Your cart is empty.');
            }
            $expected = collect($data['items'])->keyBy('product_id');
            if ($expected->count() !== count($cart->items)) {
                $this->fail('Your cart changed. Reload checkout and review your order.');
            }
            $products = Product::whereIn('id', array_column($cart->items, 'product_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lines = [];
            $subtotal = 0;
            foreach ($cart->items as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || $product->stock < $item['quantity']) {
                    $this->fail('A product is unavailable or has insufficient stock. Return to your cart and review it.');
                }
                $price = StaffOrderService::cents($product->price);
                $reviewed = $expected->get($product->id);
                if (! $reviewed || (int) $reviewed['quantity'] !== $item['quantity']
                    || StaffOrderService::cents($reviewed['expected_price']) !== $price) {
                    $this->fail('Product prices or quantities changed. Reload checkout to review the current total.');
                }
                if ($price < 0) {
                    $this->fail('A product price is invalid. Please contact the store.');
                }
                $total = $price * $item['quantity'];
                $subtotal += $total;
                $lines[] = ['product_id' => $product->id, 'name' => $product->name,
                    'quantity' => $item['quantity'], 'unit_price' => $price / 100, 'total_amount' => $total / 100];
            }
            $fee = $data['fulfillment'] === 'delivery' ? StaffOrderService::cents(config('customer.delivery_fee')) : 0;
            if ($subtotal + $fee > 9999999999) {
                $this->fail('Order total exceeds the supported amount.');
            }
            $schedule = $data['date'].' '.$data['time'].':00';
            $order = Order::create([
                'number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper((string) Str::ulid()),
                'request_key' => $data['request_key'], 'staff_name' => 'Customer order',
                'customer_id' => $customer->id, 'customer_name' => $data['name'], 'phone' => $data['phone'],
                'address' => $data['fulfillment'] === 'delivery' ? $data['address'] : null,
                'type' => $data['fulfillment'] === 'delivery' ? 'Delivery' : 'Pickup',
                'status' => 'Pending', 'payment_method' => 'Charge', 'payment_status' => 'Unpaid',
                'subtotal' => $subtotal / 100, 'delivery_fee' => $fee / 100,
                'total_amount' => ($subtotal + $fee) / 100, 'paid_amount' => 0,
                'placed_at' => now(), 'scheduled_at' => $schedule,
                // The existing staff receipt already displays notes.
                'notes' => 'Customer scheduled '.$data['fulfillment'].' for '.$data['date'].' at '.$data['time'].'.',
            ]);
            $order->items()->createMany($lines);
            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);
                $product->stock -= $line['quantity'];
                $workflow->saveStock($product);
            }
            $workflow->syncSales($order);
            $cart->update(['items' => [], 'version' => $cart->version + 1, 'checkout_key' => (string) Str::uuid()]);
            Log::info("Customer ({$customer->username}) placed order {$order->number}");

            return $order;
        }, 3);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['order' => $message]);
    }
}
