<?php
namespace App\Services;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffOrderService
{
    public function __construct(private readonly DeliveryZoneService $deliveryZoneService) {}

    public static function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'order' => $message,
        ]);
    }

    public function save(
        array $data,
        Staff $staff
    ): Order {
        return DB::transaction(function () use (
            $data,
            $staff
        ) {
            // Prevent simultaneous duplicate saves by the same staff member.
            Staff::whereKey($staff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($existing = Order::where(
                'request_key',
                $data['request_key']
            )->first()) {
                abort_unless($existing->staff_id === $staff->id, 403);

                return $existing;
            }

            $order = isset($data['order_id'])
                ? Order::lockForUpdate()->findOrFail($data['order_id'])
                : new Order();

            if (
                $order->exists &&
                (
                    !$order->isDraft() ||
                    $order->version !== (int) ($data['version'] ?? 0)
                )
            ) {
                $this->fail(
                    'This order has changed. Open it again before continuing.'
                );
            }

            $draft = $data['action'] !== 'checkout';

            $customer = !empty($data['customer_id'])
                ? Customer::where('is_active', true)
                    ->find($data['customer_id'])
                : null;

            if (!empty($data['customer_id']) && !$customer) {
                $this->fail('Select an active customer.');
            }

            $address = $data['address'] ?? $customer?->address;

            if (
                !$draft &&
                $data['type'] === 'Delivery' &&
                (!$customer || !filled($address))
            ) {
                $this->fail(
                    'Delivery orders need a customer and delivery address.'
                );
            }

            if (
                !$draft &&
                $data['payment_method'] === 'Charge' &&
                !$customer
            ) {
                $this->fail('Charge orders need a customer.');
            }

            /*
             * Calculate the delivery fee on the server.
             * Never trust the fee amount submitted by the browser.
             */
            $fee = 0;

            if ($data['type'] === 'Delivery' && filled($address)) {
                $zone = $this->deliveryZoneService->findZone($address);

                if (!$zone) {
                    if (!$draft) {
                        $this->fail(
                            'Delivery zone could not be verified. ' .
                            'Please check the delivery address.'
                        );
                    }

                    // Do not save an unverified fee on a draft.
                    $fee = 0;
                } else {
                    $fee = self::cents($zone['fee']);
                }
            } elseif ($data['type'] === 'Delivery' && !$draft) {
                $this->fail('Enter a delivery address.');
            }

            $products = Product::whereIn(
                'id',
                array_column($data['items'], 'product_id')
            )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $subtotal = 0;
            $discount = 0;

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (!$product) {
                    $this->fail(
                        'A selected product is no longer available.'
                    );
                }

                if (!$draft && $item['quantity'] > $product->stock) {
                    $this->fail(
                        "{$product->name}: only {$product->stock} available. " .
                        'Refresh the catalog and adjust the quantity.'
                    );
                }

                $price = self::cents($product->price);

                if (
                    isset($item['expected_price']) &&
                    self::cents($item['expected_price']) !== $price
                ) {
                    $this->fail(
                        "{$product->name} has a new price. " .
                        'Refresh the catalog and review the total before saving.'
                    );
                }

                if ($price < 0) {
                    $this->fail(
                        'A product has an invalid price. ' .
                        'Ask an administrator to correct it.'
                    );
                }

                // Use integer cents to avoid rounding drift.
                $gross = $price * $item['quantity'];
                $percent = $item['discount_percent'] ?? 0;

                $lineDiscount = (int) round(
                    $gross * (float) $percent / 100
                );

                $subtotal += $gross;
                $discount += $lineDiscount;

                $lines[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $price / 100,
                    'discount_percent' => $percent,
                    'discount_amount' => $lineDiscount / 100,
                    'total_amount' => ($gross - $lineDiscount) / 100,
                ];
            }

            $total = $subtotal - $discount + $fee;

            if ($subtotal > 9999999999 || $total > 9999999999) {
                $this->fail(
                    'Order total exceeds the supported amount.'
                );
            }

            $tendered = $draft
                ? 0
                : self::cents($data['amount_tendered'] ?? 0);

            $paid = (
                $draft ||
                $data['payment_method'] === 'Charge'
            )
                ? 0
                : min($tendered, $total);

            if (
                !$draft &&
                $total > 0 &&
                $data['payment_method'] !== 'Charge' &&
                $paid <= 0
            ) {
                $this->fail(
                    'Enter the amount received, or select Charge for an unpaid order.'
                );
            }

            if (
                !$draft &&
                $data['payment_method'] !== 'Cash' &&
                $tendered > $total
            ) {
                $this->fail(
                    'Only cash payments can include change.'
                );
            }

            if (!$draft && $paid < $total && !$customer) {
                $this->fail(
                    'Select a customer for an order with an outstanding balance.'
                );
            }

            $order->fill([
                'number' => $order->number ??
                    'ORD-' . now()->format('Ymd') . '-' .
                    strtoupper((string) Str::ulid()),

                'request_key' => $data['request_key'],
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name ?? 'Walk-in',
                'phone' => $customer?->phone,
                'address' => $address,
                'type' => $data['type'],

                'status' => $draft
                    ? (
                        $data['action'] === 'quote'
                            ? 'Quotation'
                            : 'Held'
                    )
                    : (
                        $data['type'] === 'Walk-in'
                            ? 'Completed'
                            : 'Pending'
                    ),

                'payment_method' => $draft
                    ? null
                    : $data['payment_method'],

                'payment_status' => $draft
                    ? 'Unpaid'
                    : (
                        $paid === $total
                            ? 'Paid'
                            : ($paid > 0 ? 'Partial' : 'Unpaid')
                    ),

                'subtotal' => $subtotal / 100,
                'discount_amount' => $discount / 100,
                'delivery_fee' => $fee / 100,
                'total_amount' => $total / 100,
                'paid_amount' => $paid / 100,

                'notes' => $data['notes'] ?? null,
                'placed_at' => $draft ? null : now(),

                'completed_at' => (
                    !$draft && $data['type'] === 'Walk-in'
                )
                    ? now()
                    : null,

                'version' => $order->exists
                    ? $order->version + 1
                    : 1,
            ])->save();

            $order->items()->delete();
            $order->items()->createMany($lines);

            if (!$draft) {
                foreach ($lines as $line) {
                    $product = $products[$line['product_id']];
                    $product->stock -= $line['quantity'];

                    $this->saveStock($product);
                }

                if ($paid > 0) {
                    $order->payments()->create([
                        'request_key' => $data['request_key'],
                        'staff_name' => $staff->name,
                        'method' => $data['payment_method'],
                        'reference' => $data['reference'] ?? null,
                        'amount' => $paid / 100,
                        'tendered' => $tendered / 100,
                    ]);
                }

                $this->syncSales($order);
            }

            Log::info(
                "Staff ({$staff->username}) saved order " .
                "{$order->number}: {$order->status}"
            );

            return $order;
        }, 3);
    }

    public function saveStock(Product $product): void
    {
        $product->status = $product->stock <= 0
            ? 'Out of Stock'
            : ($product->stock <= 5 ? 'Low Stock' : 'Available');

        $product->save();
    }

    public function syncSales(Order $order): void
    {
        $remainingPaid = self::cents($order->paid_amount);

        foreach (
            $order->items()->orderBy('id')->get() as $index => $item
        ) {
            // Allocate the delivery fee once for existing reports.
            $total = self::cents($item->total_amount)
                + (
                    $index === 0
                        ? self::cents($order->delivery_fee)
                        : 0
                );

            $paid = min($remainingPaid, $total);
            $remainingPaid -= $paid;

            Sale::updateOrCreate(
                ['order_item_id' => $item->id],
                [
                    'order_id' => $order->id,
                    'receipt_no' => $order->number,
                    'staff_name' => $order->staff_name,
                    'status' => $order->status,
                    'item_name' => $item->name,
                    'qty' => $item->quantity,
                    'total_amount' => $total / 100,
                    'paid_amount' => $paid / 100,
                    'payment_status' => $paid === $total
                        ? 'Paid'
                        : ($paid > 0 ? 'Partial' : 'Unpaid'),
                ]
            );
        }
    }

    public function changeStatus(
        Order $order,
        string $status,
        int $version
    ): void {
        DB::transaction(function () use ($order, $status, $version) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->version !== $version) {
                $this->fail(
                    'This order has changed. Refresh it before updating.'
                );
            }

            $allowed = match ($order->status) {
                'Held', 'Quotation' => ['Cancelled'],
                'Pending' => ['Processing', 'Cancelled'],
                'Processing' => ['Ready', 'Cancelled'],
                'Ready' => [
                    $order->type === 'Delivery'
                        ? 'Out for Delivery'
                        : 'Completed',
                    'Cancelled',
                ],
                'Out for Delivery' => ['Completed'],
                default => [],
            };

            if (!in_array($status, $allowed, true)) {
                $this->fail(
                    'That status transition is not available.'
                );
            }

            if (
                $status === 'Cancelled' &&
                self::cents($order->paid_amount) > 0
            ) {
                $this->fail(
                    'An order with received payments cannot be cancelled. ' .
                    'Ask an administrator to arrange a refund first.'
                );
            }

            if ($status === 'Cancelled' && !$order->isDraft()) {
                $items = $order->items()
                    ->orderBy('product_id')
                    ->get();

                foreach ($items as $item) {
                    $product = Product::lockForUpdate()
                        ->find($item->product_id);

                    if (!$product) {
                        $this->fail(
                            'A product was removed. Ask an administrator ' .
                            'to reconcile its stock before cancelling.'
                        );
                    }

                    $product->stock += $item->quantity;
                    $this->saveStock($product);
                }

                // Cancelled unpaid orders are not revenue.
                $order->sales()->delete();
            }

            $order->update([
                'status' => $status,
                'version' => $order->version + 1,
                'completed_at' => $status === 'Completed' ? now() : null,
            ]);

            $order->sales()->update(['status' => $status]);

            Log::info(
                "Order {$order->number} status changed to {$status}"
            );
        }, 3);
    }

    public function pay(
        Order $order,
        array $data,
        Staff|Admin $staff
    ): void {
        DB::transaction(function () use ($order, $data, $staff) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (
                $order->payments()
                    ->where('request_key', $data['request_key'])
                    ->exists()
            ) {
                return;
            }

            if (
                OrderPayment::where(
                    'request_key',
                    $data['request_key']
                )->exists()
            ) {
                $this->fail(
                    'This payment reference was already submitted for another order. ' .
                    'Reload and try again.'
                );
            }

            if ($order->isDraft() || $order->status === 'Cancelled') {
                $this->fail('This order cannot receive payments.');
            }

            $balance = self::cents($order->balance);
            $tendered = self::cents($data['amount']);

            if (
                $balance <= 0 ||
                $tendered <= 0 ||
                (
                    $data['method'] !== 'Cash' &&
                    $tendered > $balance
                )
            ) {
                $this->fail(
                    'Enter a valid payment for the remaining balance.'
                );
            }

            $paid = min($tendered, $balance);

            $order->payments()->create([
                'request_key' => $data['request_key'],
                'staff_name' => $staff->name,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'amount' => $paid / 100,
                'tendered' => $tendered / 100,
            ]);

            $order->update([
                'paid_amount' => (
                    self::cents($order->paid_amount) + $paid
                ) / 100,
                'payment_status' => $paid === $balance
                    ? 'Paid'
                    : 'Partial',
                'version' => $order->version + 1,
            ]);

            $this->syncSales($order);

            Log::info(
                "Payment recorded by {$staff->name} for " .
                "{$order->number}: " . ($paid / 100)
            );
        }, 3);
    }
}
