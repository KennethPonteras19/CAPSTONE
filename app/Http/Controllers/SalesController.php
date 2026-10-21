<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Order;
use App\Services\StaffOrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();

        $query = Sale::query();

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('receipt_no', 'like', "%{$search}%")
                    ->orWhere('staff_name', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%");
            });
        }

        $sales = $query->latest()->get();

        $products = Product::where('stock', '>', 0)
            ->orderBy('name')
            ->get();

        $todayOrders = Sale::whereDate('created_at', $today)
            ->distinct()
            ->count('receipt_no');

        $todaySales = $todayOrders;

        $todayRevenue = (float) Sale::whereDate('created_at', $today)
            ->sum('total_amount');

        $paymentsCollected = (float) Sale::whereDate('created_at', $today)
            ->sum('paid_amount');

        $outstandingBalance = (float) Sale::whereDate('created_at', $today)
            ->selectRaw(
                'COALESCE(SUM(GREATEST(total_amount - COALESCE(paid_amount, 0), 0)), 0) as balance'
            )
            ->value('balance');

        $averageOrder = $todayOrders > 0
            ? $todayRevenue / $todayOrders
            : 0;

        $topProduct = Sale::select('item_name')
            ->selectRaw('SUM(qty) as total_quantity')
            ->groupBy('item_name')
            ->orderByDesc('total_quantity')
            ->value('item_name');

        $topStaff = Sale::select('staff_name')
            ->selectRaw('SUM(total_amount) as total_sales')
            ->groupBy('staff_name')
            ->orderByDesc('total_sales')
            ->value('staff_name');

        return view('sales', compact(
            'sales',
            'products',
            'todaySales',
            'todayOrders',
            'todayRevenue',
            'paymentsCollected',
            'outstandingBalance',
            'averageOrder',
            'topProduct',
            'topStaff'
        ));
    }

    private function generateReceiptNumber(): string
    {
        do {
            $receipt = (string) random_int(100000, 999999);
        } while (Sale::where('receipt_no', $receipt)->exists());

        return $receipt;
    }

    private function getSaleStaffName(): string
    {
        $user = auth('staff')->user();

        if ($user) {
            return $user->name ?? $user->username ?? 'Staff';
        }

        $admin = auth('admin')->user();

        if ($admin) {
            return $admin->name ?? $admin->username ?? 'Admin';
        }

        return 'Authorized User';
    }

    private function calculatePaidAmount(
        string $paymentStatus,
        float $total,
        $requestedAmount
    ): float {
        if ($paymentStatus === 'Paid') {
            return $total;
        }

        if ($paymentStatus === 'Unpaid') {
            return 0;
        }

        $paid = round((float) $requestedAmount, 2);

        if ($paid <= 0 || $paid >= $total) {
            throw new \InvalidArgumentException(
                'Partial payment must be greater than zero and less than the total.'
            );
        }

        return $paid;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'payment_status' => [
                'required',
                Rule::in(['Paid', 'Partial', 'Unpaid']),
            ],
            'paid_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        try {
            $receipt = DB::transaction(function () use ($validated) {
                $product = Product::whereKey($validated['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (int) $validated['qty'];

                if ($qty > $product->stock) {
                    throw new \RuntimeException(
                        "Insufficient stock for {$product->name}. Available: {$product->stock}."
                    );
                }

                $total = round((float) $product->price * $qty, 2);

                $paid = $this->calculatePaidAmount(
                    $validated['payment_status'],
                    $total,
                    $validated['paid_amount'] ?? 0
                );

                $receipt = $this->generateReceiptNumber();

                Sale::create([
                    'receipt_no' => $receipt,
                    'staff_name' => $this->getSaleStaffName(),
                    'status' => 'Completed',
                    'item_name' => $product->name,
                    'qty' => $qty,
                    'total_amount' => $total,
                    'payment_status' => $validated['payment_status'],
                    'paid_amount' => $paid,
                ]);

                $product->stock -= $qty;
                $product->save();

                return $receipt;
            });

            return back()->with(
                'success',
                "Sale recorded successfully. Receipt #{$receipt}."
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Unable to record sale', [
                'message' => $e->getMessage(),
            ]);

            return back()->withInput()->with(
                'error',
                'Unable to record the sale. No changes were saved.'
            );
        }
    }

    public function storeMultiple(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'array', 'min:1'],
            'product_id.*' => [
                'required', 'integer', 'distinct', 'exists:products,id',
            ],
            'qty' => ['required', 'array', 'min:1'],
            'qty.*' => ['required', 'integer', 'min:1'],
            'payment_status' => [
                'required',
                Rule::in(['Paid', 'Partial', 'Unpaid']),
            ],
            'paid_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        if (count($validated['product_id']) !== count($validated['qty'])) {
            return back()->withInput()->with(
                'error',
                'Each product must have a matching quantity.'
            );
        }

        try {
            $receipt = DB::transaction(function () use ($validated) {
                $lines = [];
                $grandTotal = 0;

                $productIds = $validated['product_id'];
                sort($productIds);

                $lockedProducts = Product::whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($validated['product_id'] as $index => $productId) {
                    $product = $lockedProducts->get($productId);
                    $qty = (int) $validated['qty'][$index];

                    if (!$product) {
                        throw new \RuntimeException(
                            'A selected product no longer exists.'
                        );
                    }

                    if ($qty > $product->stock) {
                        throw new \RuntimeException(
                            "Insufficient stock for {$product->name}. Available: {$product->stock}."
                        );
                    }

                    $lineTotal = round((float) $product->price * $qty, 2);
                    $grandTotal = round($grandTotal + $lineTotal, 2);

                    $lines[] = [
                        'product' => $product,
                        'qty' => $qty,
                        'total' => $lineTotal,
                    ];
                }

                $grandPaid = $this->calculatePaidAmount(
                    $validated['payment_status'],
                    $grandTotal,
                    $validated['paid_amount'] ?? 0
                );

                $receipt = $this->generateReceiptNumber();
                $remainingPaid = $grandPaid;
                $remainingTotal = $grandTotal;
                $lastIndex = count($lines) - 1;

                foreach ($lines as $index => $line) {
                    if ($validated['payment_status'] === 'Paid') {
                        $linePaid = $line['total'];
                    } elseif ($validated['payment_status'] === 'Unpaid') {
                        $linePaid = 0;
                    } elseif ($index === $lastIndex) {
                        $linePaid = $remainingPaid;
                    } else {
                        $linePaid = $remainingTotal > 0
                            ? round(
                                $remainingPaid * $line['total'] / $remainingTotal,
                                2
                            )
                            : 0;

                        $linePaid = min($linePaid, $line['total']);
                    }

                    Sale::create([
                        'receipt_no' => $receipt,
                        'staff_name' => $this->getSaleStaffName(),
                        'status' => 'Completed',
                        'item_name' => $line['product']->name,
                        'qty' => $line['qty'],
                        'total_amount' => $line['total'],
                        'payment_status' => $validated['payment_status'],
                        'paid_amount' => $linePaid,
                    ]);

                    $line['product']->stock -= $line['qty'];
                    $line['product']->save();

                    $remainingPaid = round($remainingPaid - $linePaid, 2);
                    $remainingTotal = round(
                        $remainingTotal - $line['total'],
                        2
                    );
                }

                return $receipt;
            });

            return back()->with(
                'success',
                "Sale recorded successfully. Receipt #{$receipt}."
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Unable to record multi-product sale', [
                'message' => $e->getMessage(),
            ]);

            return back()->withInput()->with(
                'error',
                'Unable to record the sale. No changes were saved.'
            );
        }
    }

    /**
     * Receive an additional payment for a receipt.
     * Updates every line under the receipt consistently.
     */
    public function receivePayment(Request $request, string $receipt)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            DB::transaction(function () use ($validated, $receipt) {
                $receiptSales = Sale::where('receipt_no', $receipt)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($receiptSales->isEmpty()) {
                    throw new \RuntimeException('Receipt not found.');
                }

                $total = round((float) $receiptSales->sum('total_amount'), 2);
                $paid = round((float) $receiptSales->sum('paid_amount'), 2);
                $balance = round(max(0, $total - $paid), 2);
                $amount = round((float) $validated['amount'], 2);

                if ($balance <= 0) {
                    throw new \RuntimeException('This receipt is already fully paid.');
                }

                if ($amount > $balance) {
                    throw new \RuntimeException(
                        'Payment cannot exceed the remaining balance of ₱'
                        . number_format($balance, 2) . '.'
                    );
                }

                // POS-linked receipts must be paid through the order service.
                $orderIds = $receiptSales->pluck('order_id')
                    ->filter()
                    ->unique()
                    ->values();

                if ($orderIds->isNotEmpty()) {
                    if ($orderIds->count() !== 1) {
                        throw new \RuntimeException(
                            'This receipt is linked to multiple orders. '
                            . 'Please process payment through Staff Orders.'
                        );
                    }

                    $order = Order::findOrFail($orderIds->first());

                    app(StaffOrderService::class)->pay(
                        $order,
                        [
                            'request_key' => (string) Str::uuid(),
                            'method' => 'Cash',
                            'amount' => $amount,
                            'reference' => 'Additional payment from admin Sales',
                        ],
                        auth('admin')->user()
                    );

                    return;
                }

                // Apply the additional payment only to outstanding line balances.
                $remainingPayment = $amount;

                foreach ($receiptSales as $sale) {
                    if ($remainingPayment <= 0) {
                        break;
                    }

                    $lineTotal = round((float) $sale->total_amount, 2);
                    $linePaid = round((float) $sale->paid_amount, 2);
                    $lineBalance = round(max(0, $lineTotal - $linePaid), 2);

                    if ($lineBalance <= 0) {
                        continue;
                    }

                    $applied = min($remainingPayment, $lineBalance);

                    $sale->paid_amount = round($linePaid + $applied, 2);
                    $sale->save();

                    $remainingPayment = round($remainingPayment - $applied, 2);
                }

                // Recalculate from the updated database records to avoid double-counting.
                $updatedSales = Sale::where('receipt_no', $receipt)->get();
                $updatedPaid = round((float) $updatedSales->sum('paid_amount'), 2);
                $updatedTotal = round((float) $updatedSales->sum('total_amount'), 2);

                $paymentStatus = $updatedPaid >= $updatedTotal
                    ? 'Paid'
                    : ($updatedPaid > 0 ? 'Partial' : 'Unpaid');

                foreach ($updatedSales as $sale) {
                    $sale->payment_status = $paymentStatus;
                    $sale->save();
                }
            });

            return back()->with(
                'success',
                "Payment received for receipt #{$receipt}."
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Unable to receive sale payment', [
                'receipt_no' => $receipt,
                'message' => $e->getMessage(),
            ]);

            return back()->with(
                'error',
                'Unable to receive payment. Please check the logs and try again.'
            );
        }
    }

    /**
     * Keep the existing mark-paid route working.
     */
    public function markPaid($id)
    {
        $sale = Sale::findOrFail($id);

        if ($sale->order_id) {
            $order = Order::findOrFail($sale->order_id);

            app(StaffOrderService::class)->pay(
                $order,
                [
                    'request_key' => (string) Str::uuid(),
                    'method' => 'Cash',
                    'amount' => round(
                        (float) $sale->total_amount - (float) $sale->paid_amount,
                        2
                    ),
                    'reference' => 'Marked paid from admin Sales',
                ],
                auth('admin')->user()
            );

            return back()->with(
                'success',
                'Payment updated and synced to the order.'
            );
        }

        return $this->receivePayment(
            new Request([
                'amount' => max(
                    0,
                    round(
                        (float) $sale->total_amount - (float) $sale->paid_amount,
                        2
                    )
                ),
            ]),
            (string) $sale->receipt_no
        );
    }

    public function destroy($id)
    {
        $sale = Sale::findOrFail($id);

        if ($sale->order_id) {
            return back()->with(
                'error',
                'This sale belongs to a POS order. Cancel it through Staff Orders to preserve order history and restore stock correctly.'
            );
        }

        return DB::transaction(function () use ($sale) {
            $receipt = $sale->receipt_no;
            $receiptSales = Sale::where('receipt_no', $receipt)->get();

            if ($receiptSales->contains(
                fn ($item) => (float) $item->paid_amount > 0
            )) {
                return back()->with(
                    'error',
                    'This receipt has a recorded payment. Use your authorized cancellation/refund process instead.'
                );
            }

            foreach ($receiptSales as $receiptSale) {
                $product = Product::where('name', $receiptSale->item_name)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $product->stock += $receiptSale->qty;
                    $product->save();
                }

                $receiptSale->delete();
            }

            return back()->with(
                'success',
                "Unpaid receipt #{$receipt} was deleted and stock was restored where matching products were found."
            );
        });
    }
}