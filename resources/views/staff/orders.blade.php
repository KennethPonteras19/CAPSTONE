@extends('staff.layout')
@section('title', 'Order Fulfillment')
@section('subtitle', 'Manage saved orders, quotations, pickups, and deliveries.')
@section('actions')@moduleRoute('staff', 'pos')<a class="button" href="{{ route('pos') }}">New Order</a>@endmoduleRoute
@moduleRoute('staff', 'staff.receipts.index')<a class="button secondary" href="{{ route('staff.receipts.index') }}">Receipt Templates</a>@endmoduleRoute
@endsection
@section('content')
<div data-auto-refresh>
@include('staff.partials.metrics')
<form class="workspace-toolbar workspace-filters" method="GET">
    <input name="search" value="{{ request('search') }}" placeholder="Search order, customer, or item…" aria-label="Search orders" maxlength="255">
    <select name="type" aria-label="Order type"><option value="">All types</option>@foreach(['Walk-in', 'Pickup', 'Delivery'] as $type)<option @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select>
    <select name="status" aria-label="Order status"><option value="">All statuses</option>@foreach(['Held', 'Quotation', 'Pending', 'Processing', 'Ready', 'Out for Delivery', 'Completed', 'Cancelled'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
    <label>From<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label>
    <button>Filter</button>@moduleRoute('staff', 'orders')<a href="{{ route('orders') }}">Reset</a>@endmoduleRoute
</form>
<section class="panel">@include('staff.partials.order-table')@include('staff.partials.pagination', ['paginator' => $orders])</section>
</div>
@endsection
