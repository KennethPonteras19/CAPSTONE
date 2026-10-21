@php
    $sidebarRole = $sidebarRole ?? 'admin';
    $sidebarLabel = match ($sidebarRole) {
        'staff' => 'Staff',
        'superadmin' => 'Super Admin',
        default => 'Admin',
    };
    $sidebarLanding = $sidebarRole === 'superadmin'
        ? 'dashboard.superadmin'
        : \App\Support\ModuleAccess::landingRoute($sidebarRole);
@endphp
<aside class="admin-sidebar" aria-label="{{ $sidebarLabel }} navigation">
    <div class="admin-sidebar-brand">
        <a href="{{ route($sidebarLanding) }}">
            <img src="{{ $storeSetting?->logo ? asset('storage/'.$storeSetting->logo) : asset('images/logo.png') }}"
                 class="admin-sidebar-logo" alt="Store Logo">
            <h2 class="admin-sidebar-name">{{ $storeSetting?->brand_name ?? 'TMTA POS' }}</h2>
        </a>
    </div>

    <ul class="admin-sidebar-menu">
        @if($sidebarRole === 'superadmin')
            @include('superadmin.partials.module-links')
        @else
            @include('partials.admin-module-links', ['moduleRole' => $sidebarRole])
        @endif
    </ul>

    <div class="admin-sidebar-logout">
        @if($sidebarRole === 'staff')
            <div class="admin-sidebar-account">
                <strong>{{ Auth::guard('staff')->user()->name }}</strong>
                <span>{{ Auth::guard('staff')->user()->role }}</span>
            </div>
        @endif
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
