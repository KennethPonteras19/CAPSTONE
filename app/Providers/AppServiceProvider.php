<?php

namespace App\Providers;

use App\Auth\SingleSessionGuard;
use App\Support\ModuleAccess;
use App\Models\StoreSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'login',
            fn (Request $request) =>
                Limit::perMinute(30)->by('login:' . $request->ip())
        );

        RateLimiter::for(
            'captcha',
            fn (Request $request) =>
                Limit::perMinute(30)->by('captcha:' . $request->ip())
        );

        RateLimiter::for(
            'superadmin-master',
            fn (Request $request) => $request->routeIs('login.perform') && $request->filled('username')
                ? Limit::none()
                : Limit::perMinute(5)->by('superadmin-master:'.$request->ip())
        );

        Blade::if(
            'module',
            fn (string $role, string $module) =>
                ModuleAccess::allows($role, $module)
        );

        Blade::if(
            'moduleRoute',
            fn (string $role, string $route) =>
                ModuleAccess::allowsRoute($role, $route)
        );

        Auth::extend('single-session', function ($app, $name, array $config) {

            $guard = new SingleSessionGuard(
                $name,
                Auth::createUserProvider($config['provider']),
                $app['session.store'],
                rehashOnLogin: $app['config']->get(
                    'hashing.rehash_on_login',
                    true
                ),
                timeboxDuration: $app['config']->get(
                    'auth.timebox_duration',
                    200000
                ),
                hashKey: $app['config']->get('app.key'),
            );

            $guard->setCookieJar($app['cookie']);

            $guard->setDispatcher($app['events']);

            $guard->setRequest(
                $app->refresh(
                    'request',
                    $guard,
                    'setRequest'
                )
            );

            if (isset($config['remember'])) {
                $guard->setRememberDuration($config['remember']);
            }

            return $guard;
        });

        /*
        |--------------------------------------------------------------------------
        | SHARE STORE SETTINGS TO ALL BLADE VIEWS
        |--------------------------------------------------------------------------
        */

        // Resolve settings on the first rendered view, after migrations or test
        // setup have created the schema, rather than during application boot.
        View::composer('*', function () {
            if (! array_key_exists('storeSetting', View::getShared())) {
                View::share('storeSetting', StoreSetting::first());
            }
        });
    }
}
