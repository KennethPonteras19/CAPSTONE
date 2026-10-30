@extends('staff.layout')
@section('title', $order->isDraft() ? $order->status : 'Order & Receipt')
@section('subtitle', $order->number)
@section('actions')
@moduleRoute('staff', 'orders')<a class="button secondary" href="{{ route('orders') }}">All Orders</a>@endmoduleRoute
@moduleRoute('staff', 'staff.receipts.print')<a class="button" href="{{ route('staff.receipts.print', $order) }}" target="_blank" rel="noopener">Print Receipt</a>@endmoduleRoute
@endsection
@section('content')
@moduleRoute('staff', 'orders')<x-back-link :fallback="route('orders')" label="Back to Orders" />@endmoduleRoute
<section class="panel">
    <h2>TMTA POS <span class="badge">{{ $order->status }}</span></h2>
    <dl class="detail-grid">
        <div><dt>Customer</dt><dd>{{ $order->customer_name }}</dd></div><div><dt>Cashier</dt><dd>{{ $order->staff_name }}</dd></div>
        <div><dt>Order Type</dt><dd>{{ $order->type }}</dd></div><div><dt>Created</dt><dd>{{ $order->created_at->format('M d, Y h:i A') }}</dd></div>
        <div><dt>Phone</dt><dd>{{ $order->phone ?? '—' }}</dd></div><div><dt>Address</dt><dd>{{ $order->address ?? '—' }}</dd></div>
    </dl>
    <div class="table-scroll"><table class="workspace-table"><thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Total</th></tr></thead><tbody>
    @foreach($order->items as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->quantity }}</td><td>₱{{ number_format($item->unit_price, 2) }}</td><td>₱{{ number_format($item->discount_amount, 2) }}</td><td>₱{{ number_format($item->total_amount, 2) }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="summary-list">
        <div><span>Subtotal</span><strong>₱{{ number_format($order->subtotal, 2) }}</strong></div>
        <div><span>Discount</span><strong>−₱{{ number_format($order->discount_amount, 2) }}</strong></div>
        <div><span>Delivery Fee</span><strong>₱{{ number_format($order->delivery_fee, 2) }}</strong></div>
        <div class="grand-total"><span>Total</span><strong>₱{{ number_format($order->total_amount, 2) }}</strong></div>
        @if(!$order->isDraft() && $order->status !== 'Cancelled')
        <div><span>Amount Paid · {{ $order->payment_status }}</span><strong>₱{{ number_format($order->paid_amount, 2) }}</strong></div>
        <div><span>Balance Due</span><strong>₱{{ number_format($order->balance, 2) }}</strong></div>
        @endif
    </div>
    @if($order->notes)<p class="preserve-lines">{{ $order->notes }}</p>@endif
    @if($order->isDraft())<p class="muted">This {{ strtolower($order->status) }} order has not reserved stock. Prices and availability are checked again at checkout.</p>@endif
</section>
@if($order->payments->isNotEmpty())
<section class="panel"><h2>Payments Received</h2><div class="table-scroll"><table class="workspace-table"><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Received By</th><th>Applied</th><th>Tendered</th><th>Change</th><th class="no-print">Receipt</th></tr></thead><tbody>
@foreach($order->payments as $payment)<tr><td>{{ $payment->created_at->format('M d, Y h:i A') }}</td><td>{{ $payment->method }}</td><td>{{ $payment->reference ?? '—' }}</td><td>{{ $payment->staff_name }}</td><td>₱{{ number_format($payment->amount, 2) }}</td><td>₱{{ number_format($payment->tendered, 2) }}</td><td>₱{{ number_format($payment->tendered - $payment->amount, 2) }}</td><td class="no-print">
@if(isset(\App\Support\ReceiptTemplates::forOrder($order)['payment']))
@moduleRoute('staff', 'staff.receipts.show')<a href="{{ route('staff.receipts.show', ['order' => $order, 'template' => 'payment', 'payment' => $payment->id, 'print' => 1]) }}" target="_blank" rel="noopener">Print Receipt</a>@endmoduleRoute
@endif
</td></tr>@endforeach
</tbody></table></div></section>
@endif
<div class="two-columns no-print">
    @if(!in_array($order->status, ['Completed', 'Cancelled']) && (\App\Support\ModuleAccess::allows('staff', 'orders') || ($order->isDraft() && \App\Support\ModuleAccess::allows('staff', 'pos'))))
    <section class="panel"><h2>Order Actions</h2>
        @if($order->isDraft())@moduleRoute('staff', 'pos')<a class="button" href="{{ route('pos', ['order' => $order->id]) }}">Resume in POS</a>@endmoduleRoute
        @else
            @php($next = match($order->status) { 'Pending' => 'Processing', 'Processing' => 'Ready', 'Ready' => $order->type === 'Delivery' ? 'Out for Delivery' : 'Completed', 'Out for Delivery' => 'Completed', default => null })
            @if($next && \App\Support\ModuleAccess::allows('staff', 'orders'))<form method="POST" action="{{ route('staff.orders.status', $order) }}">@csrf @method('PATCH')
                <input type="hidden" name="version" value="{{ $order->version }}"><input type="hidden" name="status" value="{{ $next }}"><button>Mark {{ $next }}</button>
            </form>@endif
        @endif
        @if($order->paid_amount == 0 && $order->status !== 'Out for Delivery' && \App\Support\ModuleAccess::allows('staff', 'orders'))
        <form class="form-actions" method="POST" action="{{ route('staff.orders.status', $order) }}" data-confirm="Cancel this order? Any deducted stock will be restored.">@csrf @method('PATCH')
            <input type="hidden" name="version" value="{{ $order->version }}"><input type="hidden" name="status" value="Cancelled"><button class="danger">Cancel Order</button>
        </form>
        @elseif($order->paid_amount > 0)<p class="muted">Orders with received payments need a refund arranged by an administrator before cancellation.</p>@endif
    </section>
    @endif
    @if(!$order->isDraft() && $order->status !== 'Cancelled' && $order->balance > 0 && \App\Support\ModuleAccess::allows('staff', 'orders'))
    <section class="panel"><h2>Record Payment</h2><form method="POST" action="{{ route('staff.orders.payment', $order) }}">@csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="form-grid"><label>Method<select name="method">@foreach(['Cash', 'Online', 'Cheque'] as $method)<option @selected(old('method') === $method)>{{ $method }}</option>@endforeach</select></label>
        <label>Amount Received<input name="amount" type="number" min="0.01" max="99999999" step="0.01" required value="{{ old('amount', $order->balance) }}"></label>
        <label class="full">Reference (required for Online / Cheque)<input name="reference" maxlength="255" value="{{ old('reference') }}"></label></div>
        <div class="form-actions"><button>Record Payment</button></div>
    </form></section>
    @endif
</div>
@endsection
