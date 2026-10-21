<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminMasterLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AutoLoginController extends Controller
{
    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $request, SuperAdminMasterLogin $masterLogin)
    {
        $request->validate([
            'username' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! $request->filled('username')) {
            return $masterLogin->login($request);
        }

        $dashboards = [
            'superadmin' => 'dashboard.superadmin',
            'admin' => 'dashboard',
            'staff' => 'dashboardstaff',
            'customer' => 'customer.dashboard',
        ];

        foreach ($dashboards as $guard => $dashboard) {
            $credentials = $request->only('username', 'password');
            $credentials['is_active'] = true;

            if ($guard === 'staff') {
                $credentials['role'] = ['Staff', 'Cashier', 'Inventory Staff', 'Manager'];
            }

            if (!Auth::guard($guard)->attempt($credentials, $request->boolean('remember'))) {
                continue;
            }

            $request->session()->regenerate();

            $user = Auth::guard($guard)->user();
            Log::info(strtoupper($guard)." LOGIN | Username: {$user->username}");

            return redirect()->route($dashboard);
        }

        return back()->withErrors([
            'username' => 'Invalid credentials or account is deactivated.',
        ])->withInput($request->only('username', 'remember'));
    }

    public function logout(Request $request)
    {
        foreach (['superadmin', 'admin', 'staff', 'customer'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                Log::info(strtoupper($guard)." LOGOUT | Username: {$user->username}");
                Auth::guard($guard)->logout();
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
