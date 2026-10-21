<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ModuleAccess
{
    public static function catalog(string $role): array
    {
        return ModuleCatalog::forRole($role);
    }

    public static function catalogs(): array
    {
        return [
            'admin' => self::catalog('admin'),
            'staff' => self::catalog('staff'),
            'customer' => self::catalog('customer'),
        ];
    }

    public static function permissions($user, string $role): array
    {
        $available = array_keys(self::catalog($role));

        // Null means the account predates permission management. An explicitly
        // saved empty array means that the super admin has disabled every module.
        if ($user->permissions === null) {
            return $available;
        }

        $permissions = is_array($user->permissions) ? $user->permissions : [];

        // Preserve the old staff "Sales" selection using the actual staff modules.
        if ($role === 'staff' && in_array('sales', $permissions, true)) {
            $permissions = array_merge($permissions, ['pos', 'transactions']);
        }

        return array_values(array_intersect($available, $permissions));
    }

    public static function allows(string $role, string $module): bool
    {
        $user = Auth::guard($role)->user();

        return $user && in_array($module, self::permissions($user, $role), true);
    }

    public static function allowsRoute(string $role, string $route): bool
    {
        foreach (self::catalog($role) as $module => $definition) {
            if (Str::is($definition['routes'], $route) && self::allows($role, $module)) {
                return true;
            }
        }

        return false;
    }

    public static function landingRoute(string $role): string
    {
        foreach (self::catalog($role) as $module => $definition) {
            if (self::allows($role, $module)) {
                return $definition['route'];
            }
        }

        return $role.'.access';
    }
}
