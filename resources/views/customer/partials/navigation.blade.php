<nav class="navigation" aria-label="Customer pages">

    @php($customerModules = \App\Support\ModuleAccess::catalog('customer'))

    @foreach($customerModules as $module => $definition)

        @if(in_array($module, ['dashboard', 'cart', 'checkout']))
            @continue
        @endif

        @if($module === 'more' && isset($customerModules['cart']))
            @module('customer', 'cart')
                <a href="{{ route($customerModules['cart']['route']) }}"
                   class="nav-item {{ request()->routeIs($customerModules['cart']['route']) ? 'active' : '' }}"
                   @if(request()->routeIs($customerModules['cart']['route'])) aria-current="page" @endif>
                    @include('customer.partials.icon', ['icon' => 'cart'])
                    <span>{{ $customerModules['cart']['label'] }}</span>
                </a>
            @endmodule
        @endif

        @module('customer', $module)
            <a href="{{ route($definition['route']) }}"
               class="nav-item {{ request()->routeIs($definition['route']) ? 'active' : '' }}"
               @if(request()->routeIs($definition['route'])) aria-current="page" @endif
            >
                @include('customer.partials.icon', ['icon' => $module])
                <span>{{ $definition['label'] }}</span>
            </a>
        @endmodule

    @endforeach

</nav>

{{-- CUSTOMER PROFILE / LOGOUT --}}
<div class="customer-account">

    <div class="customer-info">
        <div class="customer-icon" aria-hidden="true">
            {{ mb_strtoupper(mb_substr(Auth::guard('customer')->user()->name ?? 'C', 0, 1)) }}
        </div>

        <div class="customer-details">
            <strong>{{ Auth::guard('customer')->user()->name ?? 'Customer' }}</strong>
            <small>Customer</small>
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit" class="customer-logout">
            <span>⇥</span>
            Logout
        </button>
    </form>

</div>
