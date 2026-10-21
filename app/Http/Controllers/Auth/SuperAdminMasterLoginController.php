<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminMasterLogin;
use Illuminate\Http\Request;

class SuperAdminMasterLoginController extends Controller
{
    public function showLogin()
    {
        abort_unless(config('auth.superadmin_master.password_hash') && config('auth.superadmin_master.account_id'), 404);

        return view('login_superadmin', ['masterLogin' => true]);
    }

    public function login(Request $request, SuperAdminMasterLogin $masterLogin)
    {
        return $masterLogin->login($request);
    }
}
