<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request)) {
            $identity = $this->resolveActor($request);
            $role = $identity['role'];
            $name = $identity['name'];

            $page = $this->resolvePageName($request);
            Log::info("{$role} ({$name}) accessed {$page}");
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if ($request->isMethod('OPTIONS') || $request->is('livewire/*')) {
            return false;
        }

        if (
            $request->is('superadmin/system-logs/stream') ||
            $request->is('admin/system-logs/stream') ||
            $request->is('css/*') ||
            $request->is('js/*') ||
            $request->is('images/*') ||
            $request->is('favicon.ico')
        ) {
            return false;
        }

        return true;
    }

    private function resolveActor(Request $request): array
    {
        $route = $request->route();
        $middleware = $route ? $route->gatherMiddleware() : [];

        $routeGuard = null;
        foreach (['superadmin', 'admin', 'staff', 'customer'] as $guard) {
            if (in_array("auth:{$guard}", $middleware, true)) {
                $routeGuard = $guard;
                break;
            }
        }

        if ($routeGuard && Auth::guard($routeGuard)->check()) {
            $user = Auth::guard($routeGuard)->user();

            return [
                'role' => ucfirst($routeGuard),
                'name' => $user->username ?? $user->name ?? ('id_' . $user->id),
            ];
        }

        foreach (['superadmin', 'admin', 'staff', 'customer'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                return [
                    'role' => ucfirst($guard),
                    'name' => $user->username ?? $user->name ?? ('id_' . $user->id),
                ];
            }
        }

        return [
            'role' => 'Guest',
            'name' => $request->ip(),
        ];
    }

    private function resolvePageName(Request $request): string
    {
        $routeName = $request->route()?->getName();

        $map = [
            'dashboard' => 'Dashboard',
            'product.management' => 'Product Management',
            'sales' => 'Sales',
            'reports' => 'Reports',
            'inventory.management' => 'Inventory Management',
            'inventory' => 'Inventory',
            'settings' => 'Settings',
            'dashboard.superadmin' => 'Superadmin Dashboard',
            'superadmin.system.config' => 'System Config',
            'superadmin.system.users' => 'System Users',
            'superadmin.system.logs' => 'System Logs',
            'admin.system.logs' => 'System Logs',
            'superadmin.settings' => 'Superadmin Settings',
            'dashboardstaff' => 'Staff Dashboard',
            'newsales' => 'New Sales',
            'transactions' => 'Transactions',
            'staff.inventory' => 'Staff Inventory',
            'customer.dashboard' => 'Customer Dashboard',
        ];

        if ($routeName && isset($map[$routeName])) {
            return $map[$routeName];
        }

        if ($routeName) {
            return (new Stringable($routeName))->replace('.', ' ')->title()->toString();
        }

        return (new Stringable(trim($request->path(), '/')))->replace('-', ' ')->replace('/', ' / ')->title()->toString() ?: 'Home';
    }
}
