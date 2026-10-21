<?php

namespace App\Services;

use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SuperAdminMasterLogin
{
    public function login(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:1024']]);
        $hash = config('auth.superadmin_master.password_hash');
        $accountId = config('auth.superadmin_master.account_id');

        if (! $hash || ! $accountId || ! Hash::check($data['password'], $hash)) {
            throw ValidationException::withMessages(['password' => 'Invalid master key or account unavailable.']);
        }

        $superAdmin = SuperAdmin::whereKey($accountId)->where('is_active', true)->whereNull('archived_at')->first();
        if (! $superAdmin) {
            throw ValidationException::withMessages(['password' => 'Invalid master key or account unavailable.']);
        }

        // Keep account ownership and single-session rules on every sign-in path.
        Auth::guard('superadmin')->login($superAdmin);
        $request->session()->regenerate();
        Log::info('Super Admin signed in using master key', ['superadmin_id' => $superAdmin->id]);

        return redirect()->route('dashboard.superadmin');
    }
}
