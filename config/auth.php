<?php

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [

        'web' => [
            'driver' => 'single-session',
            'provider' => 'users',
        ],

        'admin' => [
            'driver' => 'single-session',
            'provider' => 'admins',
        ],

        'superadmin' => [
            'driver' => 'single-session',
            'provider' => 'superadmins',
        ],

        'staff' => [ 
            'driver' => 'single-session',
            'provider' => 'staffs',
        ],

        'customer' => [
            'driver' => 'single-session',
            'provider' => 'customers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],

        'superadmins' => [
            'driver' => 'eloquent',
            'model' => App\Models\SuperAdmin::class,
        ],

        'staffs' => [ 
            'driver' => 'eloquent',
            'model' => App\Models\Staff::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Customer::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    'superadmin_master' => [
        'password_hash' => env('SUPERADMIN_MASTER_PASSWORD_HASH'),
        'account_id' => (int) env('SUPERADMIN_MASTER_ACCOUNT_ID', 0),
    ],

];
