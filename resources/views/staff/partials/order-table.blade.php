<div class="table-scroll"><table class="workspace-table">
<thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Date</th><th>Status</th><th>Payment</th><th>Total</th><th></th></tr></thead>
<tbody>@forelse($orders as $order)
<tr><td class="receipt-number">{{ $order->number }}</td><td>{{ $order->customer_name }}</td><td>{{ $order->type }}</td>
<td>{{ $order->created_at->format('M d, Y h:i A') }}</td><td><span class="badge">{{ $order->status }}</span></td>
<td>{{ $order->isDraft() ? '—' : $order->payment_status }}</td><td>₱{{ number_format($order->total_amount, 2) }}</td>
<td>@moduleRoute('staff', 'staff.orders.show')<a href="{{ route('staff.orders.show', $order) }}">View</a>@endmoduleRoute</td></tr>
@empty<tr><td colspan="8" class="empty-state">No orders found.</td></tr>@endforelse</tbody>
</table></div>
