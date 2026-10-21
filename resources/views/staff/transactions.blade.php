@extends('staff.layout')
@section('title', 'Payment History')
@section('actions')@moduleRoute('staff', 'staff.receipts.index')<a class="button secondary" href="{{ route('staff.receipts.index') }}">Receipt Templates</a>@endmoduleRoute @endsection
@section('subtitle', 'Receipts, payments, and outstanding balances across the store.')
@section('content')
<div data-auto-refresh>
@include('staff.partials.metrics')
<form class="workspace-toolbar workspace-filters" method="GET">
    <input name="search" value="{{ request('search') }}" placeholder="Search receipt or customer…" aria-label="Search transactions" maxlength="255">
    <select name="method" aria-label="Payment method"><option value="">All methods</option>@foreach(['Cash', 'Online', 'Cheque', 'Charge'] as $method)<option @selected(request('method') === $method)>{{ $method }}</option>@endforeach</select>
    <select name="payment_status" aria-label="Payment status"><option value="">All payments</option>@foreach(['Paid', 'Partial', 'Unpaid'] as $status)<option @selected(request('payment_status') === $status)>{{ $status }}</option>@endforeach</select>
    <label>From<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label>
    <button>Filter</button>@moduleRoute('staff', 'transactions')<a href="{{ route('transactions') }}">Clear</a>@endmoduleRoute
</form>
<section class="panel"><h2>All Transactions</h2><div class="table-scroll"><table class="workspace-table">
<thead><tr><th>Receipt</th><th>Date / Time</th><th>Customer</th><th>Initial Method</th><th>Status</th><th>Total</th><th>Paid</th><th>Balance</th><th></th></tr></thead>
<tbody>@forelse($receipts as $receipt)<tr>
<td class="receipt-number">{{ $receipt->receipt_no }}</td><td>{{ \Carbon\Carbon::parse($receipt->sold_at)->format('M d, Y h:i A') }}</td><td>{{ $receipt->customer_name ?? 'Not recorded' }}</td><td>{{ $receipt->payment_method ?? 'Not recorded' }}</td>
<td><span class="badge">{{ $receipt->paid >= $receipt->total ? 'Paid' : ($receipt->paid > 0 ? 'Partial' : 'Unpaid') }}</span></td>
<td>₱{{ number_format($receipt->total, 2) }}</td><td>₱{{ number_format($receipt->paid, 2) }}</td><td>₱{{ number_format(max(0, $receipt->total - $receipt->paid), 2) }}</td>
<td>@moduleRoute('staff', 'staff.orders.show')<a href="{{ $receipt->order_id ? route('staff.orders.show', $receipt->order_id) : route('staff.receipt', ['receipt' => $receipt->receipt_no]) }}">View</a>@endmoduleRoute</td></tr>
@empty<tr><td colspan="9" class="empty-state">No transactions match these filters.</td></tr>@endforelse</tbody>
</table></div>@include('staff.partials.pagination', ['paginator' => $receipts])</section>
</div>
@endsection
