<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminLoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Show Admin Login
    |--------------------------------------------------------------------------
    */
    public function showLogin()
    {
        return view('login_admin');
    }


    /*
    |--------------------------------------------------------------------------
    | Admin Login
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        $username = $request->input('username');
        $password = $request->input('password');
        $remember = $request->boolean('remember');


        /*
        |--------------------------------------------------------------------------
        | 1. CHECK EXISTING ADMINS TABLE
        |--------------------------------------------------------------------------
        */

        if (Auth::guard('admin')->attempt([
            'username' => $username,
            'password' => $password,
        ], $remember)) {

            $request->session()->regenerate();

            $admin = Auth::guard('admin')->user();

            /*
            | Check if existing admin is active
            */

            if (isset($admin->is_active) && !$admin->is_active) {

                Auth::guard('admin')->logout();

                return back()
                    ->withErrors([
                        'username' => 'This admin account is inactive.',
                    ])
                    ->withInput(
                        $request->only('username', 'remember')
                    );
            }


            /*
            | Audit Log
            */

            $adminIdentity =
                $admin->username
                ?? $admin->email
                ?? $admin->name
                ?? ('id_' . $admin->id);

            Log::info(
                "Admin logged in from admins table ({$adminIdentity})"
            );


            return redirect()->route('dashboard');
        }


        /*
        |--------------------------------------------------------------------------
        | 2. CHECK NEW ACCOUNTS FROM USERS TABLE
        |--------------------------------------------------------------------------
        */

        $user = User::where('username', $username)
            ->where('role', 'Admin')
            ->where('status', 'Active')
            ->first();


        /*
        |--------------------------------------------------------------------------
        | Check Password
        |--------------------------------------------------------------------------
        */

        if ($user && Hash::check($password, $user->password)) {

            /*
            |--------------------------------------------------------------------------
            | Login Using Admin Guard
            |--------------------------------------------------------------------------
            */

            Auth::guard('admin')->login($user, $remember);

            $request->session()->regenerate();


            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            $adminIdentity =
                $user->username
                ?? $user->email
                ?? $user->name
                ?? ('id_' . $user->id);

            Log::info(
                "Admin logged in from users table ({$adminIdentity})"
            );


            return redirect()->route('dashboard');
        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN FAILED
        |--------------------------------------------------------------------------
        */

        return back()
            ->withErrors([
                'username' => 'Invalid credentials or account is deactivated.',
            ])
            ->withInput(
                $request->only('username', 'remember')
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        if (Auth::guard('admin')->check()) {

            $admin = Auth::guard('admin')->user();

            $adminIdentity =
                $admin->username
                ?? $admin->email
                ?? $admin->name
                ?? ('id_' . $admin->id);

            Log::info(
                "Admin logged out ({$adminIdentity})"
            );

            Auth::guard('admin')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}