@extends('staff.layout')
@section('title', 'Receipt')
@section('subtitle', $sales->first()->receipt_no)
@section('actions')@moduleRoute('staff', 'transactions')<a class="button secondary" href="{{ route('transactions') }}">Payment History</a>@endmoduleRoute
@moduleRoute('staff', 'staff.receipts.legacy')<a class="button" href="{{ route('staff.receipts.legacy', ['receipt' => $sales->first()->receipt_no]) }}" target="_blank" rel="noopener">Print Receipt</a>@endmoduleRoute
@endsection
@section('content')
<section class="panel"><h2>TMTA POS</h2><p>{{ $sales->first()->created_at->format('M d, Y h:i A') }} · {{ $sales->first()->staff_name }}</p>
<div class="table-scroll"><table class="workspace-table"><thead><tr><th>Item</th><th>Quantity</th><th>Total</th><th>Paid</th></tr></thead><tbody>
@foreach($sales as $sale)<tr><td>{{ $sale->item_name }}</td><td>{{ $sale->qty }}</td><td>₱{{ number_format($sale->total_amount, 2) }}</td><td>₱{{ number_format($sale->paid_amount, 2) }}</td></tr>@endforeach
</tbody></table></div><div class="summary-list"><div><span>Total</span><strong>₱{{ number_format($sales->sum('total_amount'), 2) }}</strong></div><div><span>Paid</span><strong>₱{{ number_format($sales->sum('paid_amount'), 2) }}</strong></div><div><span>Balance</span><strong>₱{{ number_format(max(0, $sales->sum('total_amount') - $sales->sum('paid_amount')), 2) }}</strong></div></div>
<p class="muted">This receipt was entered through the earlier sales system. Customer and payment-method details were not recorded. An administrator can update its payment in Sales.</p>
</section>
@endsection
