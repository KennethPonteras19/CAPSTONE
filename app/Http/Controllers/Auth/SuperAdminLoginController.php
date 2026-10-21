<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SuperAdminLoginController extends Controller
{
    public function showLogin()
    {
        return view('login_superadmin');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        $credentials = [
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'is_active' => true,
        ];

        if (Auth::guard('superadmin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $superAdmin = Auth::guard('superadmin')->user();
            $superAdminIdentity = $superAdmin->username ?? $superAdmin->email ?? $superAdmin->name ?? ('id_' . $superAdmin->id);
            Log::info("Superadmin logged in ({$superAdminIdentity})");

            return redirect()->route('dashboard.superadmin');
        }

        return back()->withErrors([
            'username' => 'Invalid credentials or account is deactivated.',
        ])->withInput($request->only('username', 'remember'));
    }
}
