@php
    $superAdminLinks = [
        ['dashboard.superadmin', 'dashboard.superadmin', 'fa-gauge', 'Dashboard'],
        ['superadmin.system.config', 'superadmin.system.config', 'fa-sliders', 'System Config'],
        ['superadmin.system.users', 'superadmin.system.users', 'fa-users-gear', 'System User'],
        ['superadmin.archive', 'superadmin.archive*', 'fa-box-archive', 'Archive'],
        ['superadmin.system.logs', 'superadmin.system.logs*', 'fa-file-lines', 'System Logs'],
        ['superadmin.settings', 'superadmin.settings', 'fa-gear', 'Settings'],
    ];
@endphp
@foreach($superAdminLinks as [$landingRoute, $activeRoutes, $icon, $label])
    <li class="{{ request()->routeIs($activeRoutes) ? 'active' : '' }}">
        <a href="{{ route($landingRoute) }}" @if(request()->routeIs($activeRoutes)) aria-current="page" @endif>
            <i class="fa-solid {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
        </a>
    </li>
@endforeach
