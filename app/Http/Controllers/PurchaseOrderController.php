<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

class PurchaseOrderController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW PURCHASE ORDERS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = PurchaseOrder::with('items.product')
            ->latest();

        /*
         * SEARCH PO NUMBER
         */

        if ($request->filled('search')) {
            $query->where(
                'po_number',
                'like',
                '%' . $request->search . '%'
            );
        }

        /*
         * DATE FROM
         */

        if ($request->filled('date_from')) {
            $query->whereDate(
                'order_date',
                '>=',
                $request->date_from
            );
        }

        /*
         * DATE TO
         */

        if ($request->filled('date_to')) {
            $query->whereDate(
                'order_date',
                '<=',
                $request->date_to
            );
        }

        $purchaseOrders = $query->get();

        /*
         * STATISTICS
         */

        $pendingCount = PurchaseOrder::where(
            'status',
            'Pending'
        )->count();

        $orderedCount = PurchaseOrder::where(
            'status',
            'Ordered'
        )->count();

        $receivedCount = PurchaseOrder::where(
            'status',
            'Received'
        )->count();

        $totalCount = PurchaseOrder::count();

        /*
         * PRODUCTS
         */

        $products = Product::orderBy('name')->get();

        /*
         * ACTIVE SUPPLIERS
         *
         * Only active suppliers will appear
         * in the New Purchase Order dropdown.
         */

        $suppliers = Supplier::where(
            'is_active',
            true
        )
        ->orderBy('name')
        ->get();

        /*
         * RETURN VIEW
         */

        return view(
            'purchaseorder',
            compact(
                'purchaseOrders',
                'products',
                'suppliers',
                'pendingCount',
                'orderedCount',
                'receivedCount',
                'totalCount'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE PURCHASE ORDER
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([

            'supplier' =>
                'required|string|max:255',

            'products' =>
                'required|array|min:1',

            'products.*.product_id' =>
                'required|exists:products,id',

            'products.*.quantity' =>
                'required|integer|min:1',

            'products.*.unit_price' =>
                'required|numeric|min:0',

        ]);


        DB::transaction(function () use ($request) {

            /*
             * GENERATE PO NUMBER
             */

            $lastPO = PurchaseOrder::latest('id')->first();

            if ($lastPO) {

                $lastNumber = intval(
                    str_replace(
                        'PO-',
                        '',
                        $lastPO->po_number
                    )
                );

                $nextNumber = $lastNumber + 1;

            } else {

                $nextNumber = 1;

            }


            $poNumber =
                'PO-' . str_pad(
                    $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );


            /*
             * CALCULATE TOTAL
             */

            $totalAmount = 0;

            foreach ($request->products as $item) {

                $quantity =
                    (int) $item['quantity'];

                $unitPrice =
                    (float) $item['unit_price'];

                $totalAmount +=
                    $quantity * $unitPrice;
            }


            /*
             * CREATE PURCHASE ORDER
             */

            $purchaseOrder =
                PurchaseOrder::create([

                    'po_number' =>
                        $poNumber,

                    'supplier' =>
                        $request->supplier,

                    'total_amount' =>
                        $totalAmount,

                    'status' =>
                        'Pending',

                    'order_date' =>
                        now()->toDateString(),

                ]);


            /*
             * CREATE PURCHASE ORDER ITEMS
             *
             * IMPORTANT:
             * Do NOT use received_quantity here
             * because your purchase_order_items table
             * currently does not have that column.
             */

            foreach ($request->products as $item) {

                $quantity =
                    (int) $item['quantity'];

                $unitPrice =
                    (float) $item['unit_price'];

                PurchaseOrderItem::create([

                    'purchase_order_id' =>
                        $purchaseOrder->id,

                    'product_id' =>
                        $item['product_id'],

                    'quantity' =>
                        $quantity,

                    'unit_price' =>
                        $unitPrice,

                    'subtotal' =>
                        $quantity * $unitPrice,

                ]);
            }
        });


        return redirect()
            ->route('purchase.order')
            ->with(
                'success',
                'Purchase Order created successfully.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | MARK AS ORDERED
    |--------------------------------------------------------------------------
    */

    public function markOrdered($id)
    {
        $purchaseOrder =
            PurchaseOrder::findOrFail($id);

        if (
            $purchaseOrder->status !== 'Pending'
        ) {

            return back()->with(
                'error',
                'Only pending purchase orders can be ordered.'
            );
        }

        $purchaseOrder->update([

            'status' =>
                'Ordered'

        ]);

        return back()->with(
            'success',
            'Purchase Order marked as Ordered.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */

    public function cancel($id)
    {
        $purchaseOrder =
            PurchaseOrder::findOrFail($id);

        if (
            $purchaseOrder->status === 'Received'
        ) {

            return back()->with(
                'error',
                'Received purchase orders cannot be cancelled.'
            );
        }

        $purchaseOrder->update([

            'status' =>
                'Cancelled'

        ]);

        return back()->with(
            'success',
            'Purchase Order cancelled.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VIEW PURCHASE ORDER
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $purchaseOrder =
            PurchaseOrder::with(
                'items.product'
            )->findOrFail($id);

        return view(
            'purchaseorder-show',
            compact('purchaseOrder')
        );
    }
    public function details(\App\Models\PurchaseOrder $purchaseOrder)
{
    $purchaseOrder->load('items.product');

    return response()->json([
        'po_number' => $purchaseOrder->po_number,
        'supplier' => $purchaseOrder->supplier,
        'status' => $purchaseOrder->status,
        'order_date' => $purchaseOrder->order_date
            ? \Carbon\Carbon::parse($purchaseOrder->order_date)->format('M d, Y')
            : 'N/A',
        'total_amount' => (float) $purchaseOrder->total_amount,

        'items' => $purchaseOrder->items->map(function ($item) {
            $ordered = (int) $item->quantity;
            $received = (int) ($item->received_quantity ?? 0);

            return [
                'product' => $item->product->name ?? 'Unknown Product',
                'ordered' => $ordered,
                'received' => $received,
                'remaining' => max(0, $ordered - $received),
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ];
        })->values(),
    ]);
}
}