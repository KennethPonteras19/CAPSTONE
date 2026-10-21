@extends('staff.layout')
@section('title', 'Inventory')
@section('subtitle', 'Current products, prices, and stock. Low stock means 1–5 units remaining.')
@section('content')
<div data-auto-refresh>
@include('staff.partials.metrics')
<form class="workspace-toolbar" method="GET">
    <input name="search" value="{{ request('search') }}" placeholder="Search product…" aria-label="Search product" maxlength="255">
    <select name="category" aria-label="Category"><option value="">All categories</option>@foreach($categories as $category)<option @selected(request('category') === $category)>{{ $category }}</option>@endforeach</select>
    <select name="stock" aria-label="Stock status"><option value="">All stock</option>@foreach(['available' => 'Available', 'low' => 'Low Stock', 'out' => 'Out of Stock'] as $value => $label)<option value="{{ $value }}" @selected(request('stock') === $value)>{{ $label }}</option>@endforeach</select>
    <select name="sort" aria-label="Sort"><option value="name">A–Z</option><option value="stock" @selected(request('sort') === 'stock')>Lowest stock</option></select>
    <button>Filter</button>@moduleRoute('staff', 'staff.inventory')<a href="{{ route('staff.inventory') }}">Clear</a>@endmoduleRoute
</form>
<section class="panel"><div class="table-scroll"><table class="workspace-table">
<thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Unit Price</th><th>Status</th></tr></thead>
<tbody>@forelse($products as $product)<tr><td>@if($product->image_url)<img class="product-thumbnail" src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">@endif{{ $product->name }}</td><td>{{ $product->category }}</td><td>{{ $product->stock }}</td><td>₱{{ number_format($product->price, 2) }}</td><td><span class="badge {{ $product->stock <= 0 ? 'out' : ($product->stock <= 5 ? 'low' : 'good') }}">{{ $product->stock <= 0 ? 'Out of Stock' : ($product->stock <= 5 ? 'Low Stock' : 'Available') }}</span></td></tr>
@empty<tr><td colspan="5" class="empty-state">No products match these filters.</td></tr>@endforelse</tbody>
</table></div>@include('staff.partials.pagination', ['paginator' => $products])</section>
<p class="muted">Stock refreshes every minute. Contact an administrator to receive stock or edit products.</p>
</div>
@endsection
