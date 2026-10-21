<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockIn;
use App\Models\Consumable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INVENTORY PAGE
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | PRODUCTS
        |--------------------------------------------------------------------------
        */

        $query = Product::query();

        if ($request->filled('search')) {

            $query->where(
                'name',
                'like',
                '%' . $request->search . '%'
            );
        }

        $products = $query
            ->latest()
            ->get();


        /*
        |--------------------------------------------------------------------------
        | PURCHASE ORDERS THAT CAN BE RECEIVED
        |--------------------------------------------------------------------------
        */

        $purchaseOrders = PurchaseOrder::with('items.product')
            ->whereIn('status', [
                'Ordered',
                'Partially Received'
            ])
            ->latest('order_date')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | STOCK-IN HISTORY
        |--------------------------------------------------------------------------
        */

        $stockIns = StockIn::with('product')
            ->latest('received_at')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | CONSUMABLES
        |--------------------------------------------------------------------------
        */

        $consumables = Consumable::latest()
            ->get();


        /*
        |--------------------------------------------------------------------------
        | RETURN INVENTORY VIEW
        |--------------------------------------------------------------------------
        */

        return view(
            'inventorymanagement',
            compact(
                'products',
                'purchaseOrders',
                'stockIns',
                'consumables'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RECEIVE STOCK
    |--------------------------------------------------------------------------
    */

    public function receiveStock(Request $request)
    {
        $validated = $request->validate([

            'product_id' => [
                'required',
                'exists:products,id'
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1'
            ],

            'purchase_order_id' => [
                'required',
                'exists:purchase_orders,id'
            ],

        ]);


        DB::transaction(function () use ($validated) {

            /*
            |--------------------------------------------------------------------------
            | GET PRODUCT
            |--------------------------------------------------------------------------
            */

            $product = Product::lockForUpdate()
                ->findOrFail(
                    $validated['product_id']
                );


            /*
            |--------------------------------------------------------------------------
            | GET PURCHASE ORDER
            |--------------------------------------------------------------------------
            */

            $purchaseOrder = PurchaseOrder::with('items')
                ->lockForUpdate()
                ->findOrFail(
                    $validated['purchase_order_id']
                );


            /*
            |--------------------------------------------------------------------------
            | FIND PRODUCT INSIDE PO
            |--------------------------------------------------------------------------
            */

            $item = $purchaseOrder
                ->items()
                ->where(
                    'product_id',
                    $product->id
                )
                ->lockForUpdate()
                ->first();


            if (!$item) {

                abort(
                    422,
                    'This product is not included in the selected Purchase Order.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | REMAINING QUANTITY
            |--------------------------------------------------------------------------
            */

            $remaining =
                $item->quantity
                - $item->received_quantity;


            if ($validated['quantity'] > $remaining) {

                abort(
                    422,
                    'Received quantity cannot be greater than the remaining quantity in the Purchase Order.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE RECEIVED QUANTITY
            |--------------------------------------------------------------------------
            */

            $item->received_quantity +=
                $validated['quantity'];

            $item->save();


            /*
            |--------------------------------------------------------------------------
            | UPDATE PRODUCT STOCK
            |--------------------------------------------------------------------------
            */

            $product->stock +=
                $validated['quantity'];


            /*
            |--------------------------------------------------------------------------
            | UPDATE PRODUCT STATUS
            |--------------------------------------------------------------------------
            */

            if ($product->stock <= 5) {

                $product->status = 'Low Stock';

            } else {

                $product->status = 'Available';
            }


            $product->save();


            /*
            |--------------------------------------------------------------------------
            | CHECK PURCHASE ORDER STATUS
            |--------------------------------------------------------------------------
            */

            $allReceived = $purchaseOrder
                ->items()
                ->whereColumn(
                    'received_quantity',
                    '<',
                    'quantity'
                )
                ->doesntExist();


            $anyReceived = $purchaseOrder
                ->items()
                ->where(
                    'received_quantity',
                    '>',
                    0
                )
                ->exists();


            if ($allReceived) {

                $purchaseOrder->status = 'Received';

            } elseif ($anyReceived) {

                $purchaseOrder->status =
                    'Partially Received';

            } else {

                $purchaseOrder->status = 'Ordered';
            }


            $purchaseOrder->save();


            /*
            |--------------------------------------------------------------------------
            | STOCK-IN HISTORY
            |--------------------------------------------------------------------------
            */

            StockIn::create([

                'product_id' =>
                    $product->id,

                'quantity' =>
                    $validated['quantity'],

                'supplier' =>
                    $purchaseOrder->supplier,

                'po_number' =>
                    $purchaseOrder->po_number,

                'received_at' =>
                    now(),

            ]);
        });


        return back()->with(
            'success',
            'Stock received successfully. Purchase Order updated.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADD CONSUMABLE
    |--------------------------------------------------------------------------
    */

    public function storeConsumable(Request $request)
    {
        $validated = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'unit' => [
                'required',
                'string',
                'max:50'
            ],

            'quantity' => [
                'required',
                'numeric',
                'min:0'
            ],

        ]);


        Consumable::create([

            'name' =>
                $validated['name'],

            'unit' =>
                $validated['unit'],

            'quantity' =>
                $validated['quantity'],

        ]);


        return back()->with(
            'success',
            'Consumable added successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE CONSUMABLE
    |--------------------------------------------------------------------------
    */

    public function updateConsumable(
        Request $request,
        $id
    ) {

        $validated = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'unit' => [
                'required',
                'string',
                'max:50'
            ],

            'quantity' => [
                'required',
                'numeric',
                'min:0'
            ],

        ]);


        $consumable =
            Consumable::findOrFail($id);


        $consumable->update([

            'name' =>
                $validated['name'],

            'unit' =>
                $validated['unit'],

            'quantity' =>
                $validated['quantity'],

        ]);


        return back()->with(
            'success',
            'Consumable updated successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE CONSUMABLE
    |--------------------------------------------------------------------------
    */

    public function deleteConsumable($id)
    {
        $consumable =
            Consumable::findOrFail($id);


        $consumable->delete();


        return back()->with(
            'success',
            'Consumable deleted successfully.'
        );
    }
}