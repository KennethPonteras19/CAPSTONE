<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $definition['label'] }} · {{ $order->number }}</title>
    <link rel="stylesheet" href="{{ asset('css/receipts.css') }}?v={{ filemtime(public_path('css/receipts.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="receipt-preview">
    <nav class="print-toolbar no-print">
        <x-back-link :fallback="$backUrl ?? route($prefix.'.index', ['order' => $order->id])" :label="isset($backUrl) ? 'Back' : 'Back to Receipt Templates'" />
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </nav>
    @if($template === 'return')<p class="preview-note no-print">Return form pending approval. Verify previous returns and obtain authorization before processing. No return, refund, or stock adjustment is saved by this form.</p>@endif
    <article class="receipt-paper">
        <header class="receipt-header">
            <h1>TMTA</h1>
            <p>CONSTRUCTION &amp; HARDWARE SUPPLY</p>
            <h2>{{ $definition['title'] }}</h2>
            <p>{{ $template === 'return' ? 'MANUAL FORM / PENDING APPROVAL' : 'INTERNAL RECORD' }}</p>
            <p>NOT A BIR TAX INVOICE</p>
        </header>
        <dl class="receipt-meta">
            @if($legacy ?? false)<div><dt>Payment method</dt><dd>Not recorded</dd></div>@endif
            <div class="strong"><dt>Reference No.</dt><dd>{{ $template === 'payment' ? 'PAY-'.$payment->id : ($template === 'return' ? '________________' : ($template === 'invoice-preview' ? 'PREVIEW-'.$order->number : $order->number)) }}</dd></div>
            @if($template === 'delivery')
                <div><dt>Sales reference</dt><dd>{{ $order->number }}</dd></div>
                <div><dt>Delivery date</dt><dd>{{ $order->completed_at?->format('d M Y') ?? 'Not recorded' }}</dd></div>
                <div><dt>Customer</dt><dd>{{ $order->customer_name }}</dd></div>
                <div><dt>Address</dt><dd>{{ $order->address ?? 'Not recorded' }}</dd></div>
                <div><dt>Delivery status</dt><dd>{{ $order->status === 'Completed' ? 'Delivered' : $order->status }}</dd></div>
            @elseif($template === 'quotation')
                <div><dt>Prepared</dt><dd>{{ $order->created_at->format('d M Y') }}</dd></div>
                <div><dt>Valid until</dt><dd>Not specified</dd></div>
                <div><dt>Customer</dt><dd>{{ $order->customer_name }}</dd></div>
                <div><dt>Prepared by</dt><dd>{{ $order->staff_name }}</dd></div>
            @elseif($template === 'return')
                <div><dt>Date / Time</dt><dd>{{ $returnLines ? now('Asia/Manila')->format('d M Y h:i A') : '________________' }}</dd></div>
                <div><dt>Original sale</dt><dd>{{ $order->number }}</dd></div>
                <div><dt>Customer</dt><dd>{{ $order->customer_name }}</dd></div>
                <div><dt>Handled by</dt><dd>{{ $handledBy ?? '________________' }}</dd></div>
                <div><dt>Reason</dt><dd>{{ $returnReason ?? '________________' }}</dd></div>
            @else
                @if($template === 'invoice-preview')
                    <div><dt>Prototype</dt><dd>NOT VALID FOR ISSUANCE</dd></div>
                    <div><dt>Sales reference</dt><dd>{{ $order->number }}</dd></div>
                    <div><dt>Order status</dt><dd>{{ $order->status }}</dd></div>
                @endif
                <div><dt>Date / Time</dt><dd>{{ ($payment?->created_at ?? $order->placed_at ?? $order->created_at)->format('d M Y h:i A') }}</dd></div>
                @if($template === 'cash-sale')
                    <div><dt>Cashier</dt><dd>{{ $order->staff_name }}</dd></div>
                    <div><dt>Payment</dt><dd>Cash</dd></div>
                @else
                    <div><dt>Customer</dt><dd>{{ $order->customer_name }}</dd></div>
                @endif
                @if($template === 'charge-sale')
                    <div><dt>Due date</dt><dd>Not specified</dd></div>
                    <div><dt>Status</dt><dd>{{ strtoupper($order->payment_status) }}</dd></div>
                @elseif($template === 'payment')
                    <div><dt>Sales reference</dt><dd>{{ $order->number }}</dd></div>
                    <div><dt>Payment method</dt><dd>{{ $payment->method }}</dd></div>
                    <div><dt>Received by</dt><dd>{{ $payment->staff_name }}</dd></div>
                    @if($payment->reference)<div><dt>Payment reference</dt><dd>{{ $payment->reference }}</dd></div>@endif
                @endif
            @endif
        </dl>
        <section class="receipt-items">
            <h3>{{ $template === 'delivery' ? 'ITEM / QUANTITY' : 'ITEM / QTY / UNIT PRICE' }}</h3>
            @if($template === 'payment')
                <div class="receipt-item"><strong>Payment toward {{ $order->number }}</strong><div class="receipt-line"><span>1 × —</span><span>₱{{ number_format($payment->amount, 2) }}</span></div></div>
            @elseif($template === 'return')
                @if($returnLines)
                    @foreach($returnLines as $line)
                        <div class="receipt-item"><strong>{{ $line['item']->name }}</strong><div class="receipt-line"><span>{{ $line['quantity'] }} × ₱{{ number_format($line['item']->unit_price, 2) }}</span><span>₱{{ number_format($line['amount'], 2) }}</span></div>
                        @if($line['item']->discount_amount > 0)<div>Original sale discount included in return value.</div>@endif</div>
                    @endforeach
                @else
                @for($row = 0; $row < 3; $row++)<div class="return-line"><div>Item: __________________________</div><div>Qty: _____ Unit price: __________</div><div>Return amount: _________________</div></div>@endfor
                @endif
            @else
                @foreach($order->items as $item)
                    <div class="receipt-item"><strong>{{ $item->name }}</strong>
                        @if($template === 'delivery')<div class="receipt-line"><span>Quantity</span><span>{{ $item->quantity }}</span></div>
                        @else<div class="receipt-line"><span>{{ $item->quantity }} × {{ $item->unit_price === null ? 'Not recorded' : '₱'.number_format($item->unit_price, 2) }}</span><span>₱{{ number_format($item->total_amount, 2) }}</span></div>
                            @if($item->discount_amount > 0)<div class="receipt-line"><span>Item discount (included)</span><span>−₱{{ number_format($item->discount_amount, 2) }}</span></div>@endif
                        @endif
                    </div>
                @endforeach
            @endif
        </section>
        <dl class="receipt-totals">
            @if($template === 'delivery')
                @foreach(['Driver', 'Received by', 'Signature', 'Date / Time'] as $label)<div><dt>{{ $label }}</dt><dd>________________</dd></div>@endforeach
            @elseif($template === 'return')
                <div><dt>Return value</dt><dd>{{ $returnLines ? '₱'.number_format($returnTotal, 2) : '________________' }}</dd></div>
                <div><dt>Resolution</dt><dd>Pending approval</dd></div>
                <div><dt>Authorized by</dt><dd>________________</dd></div>
            @elseif($template === 'payment')
                <div><dt>Previous balance</dt><dd>₱{{ number_format($previousBalance, 2) }}</dd></div>
                <div><dt>Amount received</dt><dd>₱{{ number_format($payment->amount, 2) }}</dd></div>
                <div class="strong"><dt>BALANCE REMAINING</dt><dd>₱{{ number_format($remainingBalance, 2) }}</dd></div>
                <div><dt>Tendered</dt><dd>₱{{ number_format($payment->tendered, 2) }}</dd></div>
                <div><dt>Change</dt><dd>₱{{ number_format(max(0, $payment->tendered - $payment->amount), 2) }}</dd></div>
            @else
                <div><dt>Subtotal</dt><dd>₱{{ number_format($order->subtotal, 2) }}</dd></div>
                @if($order->discount_amount > 0)<div><dt>Discount</dt><dd>−₱{{ number_format($order->discount_amount, 2) }}</dd></div>@endif
                @if($order->delivery_fee > 0)<div><dt>Delivery fee</dt><dd>₱{{ number_format($order->delivery_fee, 2) }}</dd></div>@endif
                <div class="strong"><dt>{{ $template === 'quotation' ? 'ESTIMATED TOTAL' : ($template === 'invoice-preview' ? 'TRANSACTION TOTAL' : 'TOTAL') }}</dt><dd>₱{{ number_format($order->total_amount, 2) }}</dd></div>
                @if($template === 'cash-sale')
                    <div><dt>Cash tendered</dt><dd>₱{{ number_format($order->payments->sum('tendered'), 2) }}</dd></div>
                    <div><dt>Change</dt><dd>₱{{ number_format(max(0, $order->payments->sum('tendered') - $order->payments->sum('amount')), 2) }}</dd></div>
                @elseif($template === 'charge-sale')
                    <div><dt>Payments received</dt><dd>₱{{ number_format($order->paid_amount, 2) }}</dd></div>
                    <div class="strong"><dt>BALANCE DUE</dt><dd>₱{{ number_format($order->balance, 2) }}</dd></div>
                @endif
            @endif
        </dl>
        <footer class="receipt-footer"><p>{{ $definition['note'] }}</p><p>Generated for TMTA capstone demonstration</p></footer>
    </article>
    @if($autoPrint ?? false)<script src="{{ asset('js/receipt-print.js') }}" defer></script>@endif
</body>
</html>
