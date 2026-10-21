@extends('staff.layout')
@section('title', 'Staff Dashboard')
@section('subtitle', 'Today’s store activity, orders, and inventory.')
@section('actions')@moduleRoute('staff', 'pos')<a class="button" href="{{ route('pos') }}">New Sale</a>@endmoduleRoute
@endsection
@section('content')
<div data-auto-refresh>
    @include('staff.partials.metrics')
    <div class="two-columns">
        <section class="panel"><h2>Top Sellers · Last 30 Days</h2>
            <ul class="simple-list">@forelse($topProducts as $product)<li><span>{{ $product->item_name }}</span><strong>{{ $product->quantity }} sold</strong></li>
            @empty<li>No sales recorded in the last 30 days.</li>@endforelse</ul>
        </section>
        
<section class="panel inventory-alerts-panel">
    <div class="inventory-alerts-header">
        <div>
            <h2>Inventory Alerts</h2>
            <p class="muted">
                Products requiring replenishment or stock monitoring
            </p>
        </div>

        <span class="inventory-alerts-count">
            {{ $lowStock->count() }} {{ $lowStock->count() === 1 ? 'item' : 'items' }}
        </span>
    </div>

    <ul class="simple-list inventory-alerts-list">
        @forelse($lowStock as $product)
            @php
                $isOutOfStock = $product->stock <= 0;
            @endphp

            <li class="inventory-alert-item {{ $isOutOfStock ? 'is-out-of-stock' : 'is-low-stock' }}">
                <span class="inventory-alert-icon" aria-hidden="true">
                    @if($isOutOfStock)
                        <i class="fa-solid fa-circle-xmark"></i>
                    @else
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    @endif
                </span>

                <div class="inventory-alert-details">
                    @module('staff', 'inventory')
                        <a
                            class="inventory-alert-product"
                            href="{{ route('staff.inventory', ['search' => $product->name]) }}"
                        >
                            {{ $product->name }}
                        </a>
                    @else
                        <strong class="inventory-alert-product">
                            {{ $product->name }}
                        </strong>
                    @endmodule

                    <span class="inventory-alert-quantity">
                        {{ $isOutOfStock ? '0 units in stock' : $product->stock . ' units remaining' }}
                    </span>
                </div>

                <span class="inventory-alert-status">
                    {{ $isOutOfStock ? 'Out of stock' : 'Low stock' }}
                </span>
            </li>
        @empty
            <li class="inventory-alert-empty">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>No inventory alerts. All products have sufficient stock.</span>
            </li>
        @endforelse
    </ul>

    @moduleRoute('staff', 'inventory')
        <div class="inventory-alerts-footer">
            <a href="{{ route('staff.inventory') }}">
                View inventory <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    @endmoduleRoute
</section>
    </div>
    <section class="panel"><h2>Recent Orders</h2>@include('staff.partials.order-table', ['orders' => $recentOrders])</section>
    <p class="muted">Verify customer details before processing charge and delivery orders. This dashboard refreshes every minute.</p>
</div>
@endsection
