<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Support\ModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SystemAccountCreator
{
    public function create(array $input, bool $bySuperAdmin = false): Model
    {
        $role = $input['role'] ?? null;
        $modelClass = match ($role) {
            'Super Admin' => SuperAdmin::class,
            'Admin' => Admin::class,
            'Customer' => Customer::class,
            default => Staff::class,
        };
        $roleKey = match ($modelClass) {
            SuperAdmin::class => 'superadmin',
            Admin::class => 'admin',
            Customer::class => 'customer',
            default => 'staff',
        };
        $usernameRules = ['required', 'string', 'max:255'];
        // The shared login accepts a username without a role selector.
        foreach (['admins', 'staffs', 'superadmin', 'customers', 'users'] as $table) {
            $usernameRules[] = Rule::unique($table, 'username');
        }

        $rules = [
            'role' => ['required', Rule::in($bySuperAdmin
                ? ['Super Admin', 'Admin', 'Staff', 'Customer']
                : ['Admin', 'Cashier', 'Inventory Staff', 'Manager'])],
            'name' => ['required', 'string', 'max:255'],
            'username' => $usernameRules,
            'email' => $modelClass === Staff::class ? ['nullable', 'email', 'max:255']
                : ['required', 'email', 'max:255', Rule::unique((new $modelClass)->getTable(), 'email')],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'permissions' => $bySuperAdmin ? ['nullable', 'array'] : ['prohibited'],
            'status' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
        if ($bySuperAdmin) {
            $rules['permissions.*'] = ['string', 'distinct', Rule::in(array_keys(ModuleAccess::catalog($roleKey)))];
        }
        $validated = Validator::make($input, $rules)->validate();

        $attributes = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'permissions' => $bySuperAdmin ? ($validated['permissions'] ?? []) : array_keys(ModuleAccess::catalog($roleKey)),
        ];
        if ($modelClass === Staff::class) {
            $attributes['role'] = $validated['role'];
        } else {
            $attributes['email'] = $validated['email'];
        }

        // Both creation screens write directly to the same authentication record.
        return $modelClass::create($attributes);
    }
}
