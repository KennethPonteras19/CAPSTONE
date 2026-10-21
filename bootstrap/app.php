<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ActivityLogger;
use App\Http\Middleware\CheckActiveStatus;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo(function () {
            foreach ([
                'superadmin' => 'dashboard.superadmin',
                'admin' => 'dashboard',
                'staff' => 'dashboardstaff',
                'customer' => 'customer.dashboard',
            ] as $guard => $dashboard) {
                if (\Illuminate\Support\Facades\Auth::guard($guard)->check()) {
                    return route($dashboard);
                }
            }

            return route('login');
        });

        $middleware->alias([
            'activity.logger' => ActivityLogger::class,
            'check.active'    => CheckActiveStatus::class,
            'module.access'   => \App\Http\Middleware\CheckModuleAccess::class,
            'login.captcha'   => \App\Http\Middleware\VerifyLoginCaptcha::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $exception, \Illuminate\Http\Request $request) {
            // A stale product form must be reviewed and submitted again with a
            // fresh token. Never replay a write that failed CSRF validation.
            if ($exception->getStatusCode() === 419
                && $request->routeIs('products.store')
                && ! $request->expectsJson()
                && \Illuminate\Support\Facades\Auth::guard('admin')->check()) {
                return redirect()->route('product.management')
                    ->withInput($request->only(['id', 'name', 'category', 'brand', 'stock', 'stock_unit', 'price']))
                    ->withErrors([
                        'session' => 'This form expired. Your product has not been saved. Review the details below, reselect your image if needed, and click Save Product again.',
                    ]);
            }
        });
    })->create();
