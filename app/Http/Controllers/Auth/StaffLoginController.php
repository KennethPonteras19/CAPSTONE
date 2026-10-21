<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StaffLoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Show Staff Login
    |--------------------------------------------------------------------------
    */
    public function showLogin()
    {
        return view('login_staff');
    }


    /*
    |--------------------------------------------------------------------------
    | Staff Login
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Find Account
        |--------------------------------------------------------------------------
        */

        $credentials = [
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'is_active' => true,
        ];


        /*
        |--------------------------------------------------------------------------
        | Login
        |--------------------------------------------------------------------------
        */

        if (Auth::guard('staff')->attempt(
            $credentials,
            $request->boolean('remember')
        )) {

            $request->session()->regenerate();

            $staff = Auth::guard('staff')->user();


            /*
            |--------------------------------------------------------------------------
            | Check Staff Role
            |--------------------------------------------------------------------------
            */

            $allowedRoles = [
                'Staff',
                'Cashier',
                'Inventory Staff',
                'Manager',
            ];

            if (!in_array($staff->role, $allowedRoles)) {

                Auth::guard('staff')->logout();

                return back()
                    ->withErrors([
                        'username' => 'This account is not authorized for staff login.',
                    ])
                    ->withInput(
                        $request->only('username', 'remember')
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            $staffIdentity =
                $staff->username
                ?? $staff->email
                ?? $staff->name
                ?? ('id_' . $staff->id);

            Log::info(
                "Staff logged in ({$staffIdentity})"
            );


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            return redirect()->route('dashboardstaff');
        }


        /*
        |--------------------------------------------------------------------------
        | Login Failed
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
}
