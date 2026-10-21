<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomerLoginController extends Controller
{
    public function showLogin()
    {
        return view('login_customer');
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

        if (Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $customer = Auth::guard('customer')->user();
            $customerIdentity = $customer->username ?? $customer->email ?? $customer->name ?? ('id_' . $customer->id);
            Log::info("Customer logged in ({$customerIdentity})");

            return redirect()->route('customer.dashboard');
        }

        return back()->withErrors([
            'username' => 'Invalid credentials or account is deactivated.',
        ])->withInput($request->only('username', 'remember'));
    }
}
