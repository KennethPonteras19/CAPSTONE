<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Templates · TMTA</title>
    <link rel="stylesheet" href="{{ asset('css/receipts.css') }}?v={{ filemtime(public_path('css/receipts.css')) }}">
</head>
<body class="receipt-library">
<main class="library">
    <nav class="toolbar">
        @if($prefix === 'sales.receipts')
            @moduleRoute('admin', 'sales')<a href="{{ route('sales') }}">← Sales</a>@endmoduleRoute
        @else
            @moduleRoute('staff', 'pos')<a href="{{ route('pos') }}">← Point of Sale</a>@endmoduleRoute
            @moduleRoute('staff', 'orders')<a href="{{ route('orders') }}">Orders</a>@endmoduleRoute
            @moduleRoute('staff', 'transactions')<a href="{{ route('transactions') }}">Payment History</a>@endmoduleRoute
        @endif
    </nav>
    <h1>Receipt Templates</h1>
    <p>TMTA Construction &amp; Hardware Supply · Seven document formats for printing or saving as PDF.</p>
    @if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
    @if($order)
    <section class="library-panel">
        <h2>{{ $order->number }}</h2>
        <p>{{ $order->customer_name }} · {{ $order->status }} · {{ $order->payment_status }} · ₱{{ number_format($order->total_amount, 2) }}</p>
        <div class="template-grid">
        @foreach($available as $key => $definition)
            <article class="template-card">
                <h3>{{ $definition['label'] }}</h3>
                @if($key === 'payment')
                    <form action="{{ route($prefix.'.show', ['order' => $order, 'template' => $key]) }}" method="GET" target="_blank">
                        <label for="payment">Recorded payment</label>
                        <select id="payment" name="payment" required>
                            @foreach($order->payments->sortByDesc('id') as $payment)
                            <option value="{{ $payment->id }}">#{{ $payment->id }} · {{ $payment->created_at->format('M d, Y H:i') }} · {{ $payment->method }} · ₱{{ number_format($payment->amount, 2) }}</option>
                            @endforeach
                        </select>
                        <button>Open Payment Receipt</button>
                    </form>
                @elseif($key === 'return')
                    <p>Select returned quantities from this sale. The slip remains pending approval; printing does not save a return, refund, or stock adjustment.</p>
                    <form action="{{ route($prefix.'.show', ['order' => $order, 'template' => $key]) }}" method="GET" target="_blank">
                        @foreach($order->items as $item)
                            <label for="returnQty{{ $item->id }}">{{ $item->name }} (sold: {{ $item->quantity }})</label>
                            <input class="return-quantity" id="returnQty{{ $item->id }}" name="quantities[{{ $item->id }}]" type="number" min="0" max="{{ $item->quantity }}" step="1" value="0" required>
                        @endforeach
                        <label for="returnReason">Reason for return</label>
                        <input id="returnReason" name="reason" maxlength="500" required>
                        <p><button>Prepare Return Slip</button></p>
                    </form>
                    <a href="{{ route($prefix.'.show', ['order' => $order, 'template' => $key]) }}" target="_blank" rel="noopener">Open blank return form</a>
                @else
                    @if($key === 'invoice-preview')<p>Internal preview; not valid for invoice issuance.</p>@endif
                    <a class="button" href="{{ route($prefix.'.show', ['order' => $order, 'template' => $key]) }}" target="_blank" rel="noopener">Open Receipt</a>
                @endif
            </article>
        @endforeach
        </div>
        <p class="muted">Formats are shown according to this order’s status, payment method, and recorded payments.</p>
    </section>
    @endif
    <section class="library-panel">
        <h2>Select an order</h2>
        <form class="toolbar" method="GET" action="{{ route($prefix.'.index') }}">
            <label for="receiptSearch">Order or customer</label>
            <input id="receiptSearch" name="search" value="{{ request('search') }}" maxlength="255" placeholder="Search orders…">
            <button>Search</button>
        </form>
        <div class="table-scroll"><table>
            <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Action</th></tr></thead>
            <tbody>@forelse($orders as $entry)
                <tr><td>{{ $entry->number }}</td><td>{{ $entry->customer_name }}</td><td>{{ $entry->status }}</td><td>₱{{ number_format($entry->total_amount, 2) }}</td><td><a href="{{ route($prefix.'.index', ['order' => $entry->id]) }}">Choose receipts</a></td></tr>
            @empty<tr><td colspan="5">No orders found. Create an order or quotation in Point of Sale to fill a receipt.</td></tr>@endforelse</tbody>
        </table></div>
        <nav class="toolbar" aria-label="Order pages">
            @if($orders->previousPageUrl())<a href="{{ $orders->previousPageUrl() }}">Previous</a>@endif
            <span>Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}</span>
            @if($orders->nextPageUrl())<a href="{{ $orders->nextPageUrl() }}">Next</a>@endif
        </nav>
    </section>
    <section class="library-panel">
        <h2>Original sample PDFs</h2>
        <p>These are the supplied examples with sample names and amounts. Select an order above to print actual transaction data.</p>
        <div class="template-grid">
        @foreach($templates as $key => $definition)
            <article class="template-card"><h3>{{ $definition['label'] }}</h3><a href="{{ route($prefix.'.sample', $key) }}" target="_blank" rel="noopener">Open sample PDF</a></article>
        @endforeach
        </div>
    </section>
</main>
</body>
</html>
