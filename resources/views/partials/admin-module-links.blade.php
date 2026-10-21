@php
    $moduleRole = $moduleRole ?? 'admin';
    $moduleIcons = [
        'dashboard' => 'fa-gauge', 'sales' => 'fa-chart-line', 'products' => 'fa-box',
        'reports' => 'fa-chart-pie', 'inventory' => 'fa-warehouse', 'purchase_orders' => 'fa-file-invoice',
        'suppliers' => 'fa-truck',
        'accounts' => 'fa-user-plus', 'system_logs' => 'fa-file-lines', 'settings' => 'fa-gear',
        'pos' => 'fa-cash-register', 'transactions' => 'fa-receipt',
        'orders' => 'fa-clipboard-list', 'customers' => 'fa-users',
    ];
@endphp
@foreach(\App\Support\ModuleAccess::catalog($moduleRole) as $moduleKey => $definition)
    @module($moduleRole, $moduleKey)
        <li class="{{ request()->routeIs(...$definition['routes']) ? 'active' : '' }}">
            <a href="{{ route($definition['route']) }}" @if(request()->routeIs(...$definition['routes'])) aria-current="page" @endif><i class="fa-solid {{ $moduleIcons[$moduleKey] ?? 'fa-layer-group' }}" aria-hidden="true"></i><span>{{ $definition['label'] }}</span></a>
        </li>
    @endmodule
@endforeach
