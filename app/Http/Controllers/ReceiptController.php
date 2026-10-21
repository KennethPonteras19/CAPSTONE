<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Support\ReceiptTemplates;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReceiptController extends Controller
{
    private function routePrefix(Request $request): string
    {
        return $request->routeIs('sales.*') ? 'sales.receipts' : 'staff.receipts';
    }

    public function index(Request $request)
    {
        $data = $request->validate(['order' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:255']]);
        $order = isset($data['order']) ? Order::with(['items', 'payments'])->findOrFail($data['order']) : null;
        $orders = Order::query()->when($data['search'] ?? null, function ($query, $search) {
            $query->where(fn ($query) => $query->where('number', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%'));
        })->latest('id')->paginate(15)->withQueryString();

        return response()->view('receipts.index', [
            'templates' => ReceiptTemplates::all(), 'order' => $order, 'orders' => $orders,
            'available' => $order ? ReceiptTemplates::forOrder($order) : [],
            'prefix' => $this->routePrefix($request),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function sample(string $template)
    {
        $definition = ReceiptTemplates::all()[$template] ?? null;
        abort_unless($definition, 404);

        return response()->file(resource_path('receipts/'.$definition['file']), [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function printOrder(Request $request, Order $order)
    {
        $order->load('payments');
        $template = ReceiptTemplates::defaultForOrder($order);
        $parameters = ['order' => $order, 'template' => $template, 'print' => 1];
        if ($template === 'payment') {
            $parameters['payment'] = $order->payments->sortBy('id')->last()->id;
        }

        return redirect()->route($this->routePrefix($request).'.show', $parameters)
            ->header('Cache-Control', 'private, no-store');
    }

    public function printLegacy(Request $request)
    {
        $data = $request->validate(['receipt' => ['required', 'string', 'max:255']]);
        $sales = Sale::whereNull('order_id')->where('receipt_no', $data['receipt'])->orderBy('id')->get();
        abort_if($sales->isEmpty(), 404);
        // Legacy records have no payment method or original unit price. Do not infer cash payments.
        $order = new Order([
            'number' => $sales->first()->receipt_no, 'staff_name' => $sales->first()->staff_name,
            'customer_name' => 'Not recorded', 'status' => $sales->first()->status ?? 'Legacy sale',
            'created_at' => $sales->first()->created_at, 'subtotal' => $sales->sum('total_amount'),
            'total_amount' => $sales->sum('total_amount'), 'paid_amount' => $sales->sum('paid_amount'),
        ]);
        $order->payment_status = $order->balance == 0 ? 'Paid' : ($order->paid_amount > 0 ? 'Partial' : 'Unpaid');
        $order->setRelation('items', $sales->map(fn ($sale) => new OrderItem([
            'name' => $sale->item_name, 'quantity' => $sale->qty, 'total_amount' => $sale->total_amount,
        ])));
        $template = $order->balance > 0 ? 'charge-sale' : 'invoice-preview';

        return response()->view('receipts.document', [
            'order' => $order, 'template' => $template, 'definition' => ReceiptTemplates::all()[$template],
            'payment' => null, 'autoPrint' => true,
            'legacy' => true, 'prefix' => $this->routePrefix($request),
            'backUrl' => $request->routeIs('sales.*') ? route('sales') : route('staff.receipt', ['receipt' => $data['receipt']]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Order $order, string $template)
    {
        $order->load(['items', 'payments' => fn ($query) => $query->orderBy('id')]);
        $definition = ReceiptTemplates::forOrder($order)[$template] ?? null;
        abort_unless($definition, 404, 'This receipt format is not available for this order.');
        $payment = null;
        $previousBalance = null;
        $remainingBalance = null;
        $returnLines = [];
        $returnTotal = 0;
        $returnReason = null;
        $handledBy = null;
        if ($template === 'return' && $request->has('quantities')) {
            $data = $request->validate([
                'quantities' => ['required', 'array', 'max:100'],
                'quantities.*' => ['required', 'integer', 'min:0', 'max:1000000'],
                'reason' => ['required', 'string', 'max:500'],
            ]);
            foreach ($data['quantities'] as $id => $quantity) {
                $item = $order->items->firstWhere('id', $id);
                if (! $item || $quantity > $item->quantity) {
                    throw ValidationException::withMessages(['quantities' => 'Return quantities must belong to this order and cannot exceed the quantity sold.']);
                }
                if ($quantity > 0) {
                    $cents = (int) round(round($item->total_amount * 100) * $quantity / $item->quantity);
                    $returnLines[] = ['item' => $item, 'quantity' => (int) $quantity, 'amount' => $cents / 100];
                    $returnTotal += $cents;
                }
            }
            if (! $returnLines) {
                throw ValidationException::withMessages(['quantities' => 'Choose at least one item to return.']);
            }
            $returnTotal /= 100;
            $returnReason = $data['reason'];
            $handledBy = $request->user($request->routeIs('sales.*') ? 'admin' : 'staff')->name;
        }
        if ($template === 'payment') {
            $data = $request->validate(['payment' => ['required', 'integer', 'min:1']]);
            $payment = $order->payments->firstWhere('id', $data['payment']);
            abort_unless($payment, 404);
            $previousPaid = $order->payments->where('id', '<', $payment->id)->sum('amount');
            $previousBalance = max(0, round($order->total_amount - $previousPaid, 2));
            $remainingBalance = max(0, round($previousBalance - $payment->amount, 2));
        }

        return response()->view('receipts.document', [
            'order' => $order, 'template' => $template, 'definition' => $definition,
            'payment' => $payment, 'previousBalance' => $previousBalance, 'remainingBalance' => $remainingBalance,
            'returnLines' => $returnLines, 'returnTotal' => $returnTotal, 'returnReason' => $returnReason, 'handledBy' => $handledBy,
            'prefix' => $this->routePrefix($request),
            'autoPrint' => $request->boolean('print'),
        ])->header('Cache-Control', 'private, no-store');
    }
}
