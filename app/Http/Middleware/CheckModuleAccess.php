<?php

namespace App\Http\Middleware;

use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $route = $request->route()->getName();

        if ($route === $role.'.access' && ModuleAccess::landingRoute($role) !== $route) {
            return redirect()->route(ModuleAccess::landingRoute($role));
        }

        if ($route === $role.'.access' || ModuleAccess::allowsRoute($role, $route ?? '')) {
            return $next($request);
        }

        // Login controllers and existing home links may point to a disabled dashboard.
        $dashboard = ModuleAccess::catalog($role)['dashboard']['route'];
        if ($request->isMethod('GET') && ! $request->expectsJson() && $route === $dashboard) {
            return redirect()->route(ModuleAccess::landingRoute($role));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have permission to access this module.'], 403);
        }

        return response()->view('access-unavailable', ['role' => $role, 'denied' => true], 403);
    }
}
