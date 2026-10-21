<?php

namespace App\Http\Controllers;

use App\Services\SystemAccountCreator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        return view('addaccount');
    }

    public function create()
    {
        return view('addaccount');
    }

    public function store(Request $request, SystemAccountCreator $accounts)
    {
        $account = $accounts->create($request->all());
        $admin = $request->user('admin');
        $adminName = $admin->username ?? $admin->name;
        Log::info("ACCOUNT CREATED | Admin: {$adminName} | Account: {$account->username} | Role: {$request->input('role')}");

        return redirect()->route('add.account')
            ->with('success', 'Account created successfully. It is now available in the super admin System User page.');
    }
}