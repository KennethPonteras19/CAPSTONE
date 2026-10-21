<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ModuleCatalog
{
    public static function forRole(string $role): array
    {
        if (! in_array($role, ['admin', 'staff', 'customer'], true)) {
            return [];
        }

        // Explicit definitions own their routes, labels and saved permission keys.
        $catalog = config('modules.'.$role, []);
        $patterns = array_merge([], ...array_column($catalog, 'routes'));
        $groups = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            $middleware = $route->gatherMiddleware();
            if (! $name || $name === $role.'.access'
                || ! in_array('auth:'.$role, $middleware, true)
                || ! in_array('module.access:'.$role, $middleware, true)
                || Str::is($patterns, $name)) {
                continue;
            }

            // Convention: [role.]module or [role.]module.index is the landing
            // page; [role.]module.* contains its pages, APIs and write actions.
            $localName = Str::startsWith($name, $role.'.') ? Str::after($name, $role.'.') : $name;
            $module = Str::before($localName, '.');
            if (array_key_exists($module, $catalog)) {
                continue;
            }

            $groups[$module]['routes'][] = $name;
            if (in_array('GET', $route->methods(), true)
                && ! str_contains($route->uri(), '{')
                && ! str_contains($route->getDomain() ?? '', '{')
                && in_array($localName, [$module, $module.'.index'], true)) {
                // Prefer the bare landing name when both it and .index exist.
                if (! isset($groups[$module]['route']) || $localName === $module) {
                    $groups[$module]['route'] = $name;
                }
            }
        }

        foreach ($groups as $module => $definition) {
            // An API/write action alone must not become a sidebar link.
            if (isset($definition['route'])) {
                $catalog[$module] = [
                    'label' => Str::headline($module),
                    'route' => $definition['route'],
                    'routes' => array_values(array_unique($definition['routes'])),
                ];
            }
        }

        return $catalog;
    }
}
