<aside class="sidebar" aria-label="Customer navigation">
    <div class="brand">
        <img class="customer-brand-logo"
             src="{{ $storeSetting?->logo ? asset('storage/'.$storeSetting->logo) : asset('images/logo.png') }}"
             alt="Store logo">
        <div>
            <h2>{{ $storeSetting?->brand_name ?? 'TMTA Construction Supply' }}</h2>
            <p>Customer workspace</p>
        </div>
    </div>
    @include('customer.partials.navigation')
    <div class="sidebar-footer">© {{ date('Y') }} TMTA Construction Supply</div>
</aside>
