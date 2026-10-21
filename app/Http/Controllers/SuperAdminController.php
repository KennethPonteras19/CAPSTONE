<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Support\ModuleAccess;
use App\Services\SystemLogReader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    public function __construct(private readonly SystemLogReader $logReader)
    {
    }

    public function dashboard()
    {
        $admins = Admin::whereNull('archived_at')->get();
        $staff = Staff::whereNull('archived_at')->get();
        $customers = Customer::whereNull('archived_at')->get();
        $superAdmins = SuperAdmin::whereNull('archived_at')->get();

        $allUsers = collect()
            ->merge($this->mapUsers($superAdmins, 'Super Admin', 'superadmin'))
            ->merge($this->mapUsers($admins, 'Admin', 'admin'))
            ->merge($this->mapUsers($staff, 'Staff', 'staff'))
            ->merge($this->mapUsers($customers, 'Customer', 'customer'));

        $activeUsersCount = $allUsers->where('status', 'Active')->count();
        $inactiveUsersCount = $allUsers->where('status', 'Inactive')->count();

        $logs = $this->logReader->readLogEntries(150);
        $warningsToday = collect($logs)
            ->filter(function (array $log) {
                return in_array($log['severity'], ['warning', 'error'], true)
                    && Str::contains($log['timestamp'], now()->toDateString());
            })->count();

        $recentEvents = collect($logs)->take(5)->values();

        return view('dashboardsuperadmin', [
            'systemModules' => 4,
            'activeUsersCount' => $activeUsersCount,
            'inactiveUsersCount' => $inactiveUsersCount,
            'warningsToday' => $warningsToday,
            'recentEvents' => $recentEvents,
        ]);
    }

    public function systemUsers()
    {
        $admins = Admin::whereNull('archived_at')->get();
        $staff = Staff::whereNull('archived_at')->get();
        $customers = Customer::whereNull('archived_at')->get();
        $superAdmins = SuperAdmin::whereNull('archived_at')->get();

        $users = collect()
            ->merge($this->mapUsers($superAdmins, 'Super Admin', 'superadmin'))
            ->merge($this->mapUsers($admins, 'Admin', 'admin'))
            ->merge($this->mapUsers($staff, 'Staff', 'staff'))
            ->merge($this->mapUsers($customers, 'Customer', 'customer'))
            ->sortBy('username')
            ->values();

        return view('superadmin.system-users', [
            'users' => $users,
            'moduleCatalog' => ModuleAccess::catalogs(),
        ]);
    }

    public function storeSystemUser(Request $request, \App\Services\SystemAccountCreator $accounts)
    {
        $accounts->create($request->all(), bySuperAdmin: true);

        return redirect()->route('superadmin.system.users')
            ->with('success', 'New account created successfully.');
    }

    public function toggleSystemUserStatus(Request $request)
    {
        $validated = $request->validate([
            'role_key' => ['required', Rule::in(['superadmin', 'admin', 'staff', 'customer'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $modelClass = $this->resolveModelClassByKey($validated['role_key']);
        DB::transaction(function () use ($modelClass, $validated) {
            $user = $modelClass::whereNull('archived_at')->lockForUpdate()->findOrFail($validated['id']);
            $user->is_active = !$this->isUserActive($user);
            $user->save();

            foreach ($this->legacyUsers($user) as $mirror) {
                $mirror->status = $user->is_active ? 'Active' : 'Inactive';
                $mirror->save();
                if (!$user->is_active) {
                    $this->revokeAccountSessions($mirror);
                }
            }
            if (!$user->is_active) {
                $this->revokeAccountSessions($user);
            }
        });

        return redirect()
            ->route('superadmin.system.users')
            ->with('success', 'Account status updated.');
    }

    public function archiveSystemUser(Request $request)
    {
        $validated = $request->validate([
            'role_key' => ['required', Rule::in(['superadmin', 'admin', 'staff', 'customer'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $modelClass = $this->resolveModelClassByKey($validated['role_key']);
        $user = $modelClass::whereNull('archived_at')->findOrFail($validated['id']);

        $currentSuperAdmin = auth('superadmin')->user();
        if (
            $validated['role_key'] === 'superadmin' &&
            $currentSuperAdmin &&
            (int) $currentSuperAdmin->id === (int) $user->id
        ) {
            return redirect()
                ->route('superadmin.system.users')
                ->with('error', 'You cannot archive your own account.');
        }

        DB::transaction(function () use ($user, $modelClass) {
            $user = $modelClass::whereNull('archived_at')->lockForUpdate()->findOrFail($user->id);
            foreach ($this->legacyUsers($user) as $mirror) {
                $this->revokeAccountSessions($mirror);
                $mirror->archived_at = now();
                $mirror->status = 'Inactive';
                $mirror->save();
            }
            $this->revokeAccountSessions($user);
            $user->archived_at = now();
            $user->is_active = false;
            $user->save();
        });

        return redirect()
            ->route('superadmin.system.users')
            ->with('success', 'Account archived successfully.');
    }

    public function archivedUsers(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
        ]);
        $users = collect();
        foreach (['superadmin' => 'Super Admin', 'admin' => 'Admin', 'staff' => 'Staff', 'customer' => 'Customer'] as $key => $label) {
            $model = $this->resolveModelClassByKey($key);
            $query = $model::whereNotNull('archived_at');
            if ($search = trim($filters['search'] ?? '')) {
                $query->where(fn ($query) => $query->where('username', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%'));
            }
            $users = $users->merge($this->mapUsers($query->get(), $label, $key));
        }

        $users = $users->sortByDesc('archived_at')->values();
        $page = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($users->count() / 25)));

        return view('superadmin.archive', ['users' => new LengthAwarePaginator(
            $users->forPage($page, 25)->values(), $users->count(), 25, $page,
            ['path' => route('superadmin.archive'), 'query' => $request->only('search')]
        )]);
    }

    public function restoreSystemUser(Request $request)
    {
        $validated = $request->validate([
            'role_key' => ['required', Rule::in(['superadmin', 'admin', 'staff', 'customer'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);
        $modelClass = $this->resolveModelClassByKey($validated['role_key']);
        DB::transaction(function () use ($modelClass, $validated) {
            $user = $modelClass::whereNotNull('archived_at')->lockForUpdate()->findOrFail($validated['id']);
            $user->archived_at = null;
            $user->is_active = false;
            $this->revokeAccountSessions($user);
            foreach ($this->legacyUsers($user) as $mirror) {
                $mirror->archived_at = null;
                $mirror->status = 'Inactive';
                $this->revokeAccountSessions($mirror);
            }
        });

        return redirect()->route('superadmin.archive')
            ->with('success', 'Account restored as inactive. Activate it in System User when access is needed.');
    }

    public function updateSystemUserPermissions(Request $request)
    {
        $roleKey = $request->input('role_key');
        $available = is_string($roleKey) ? array_keys(ModuleAccess::catalog($roleKey)) : [];

        $validated = $request->validate([
            'role_key' => ['required', Rule::in(['admin', 'staff', 'customer'])],
            'id' => ['required', 'integer', 'min:1'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in($available)],
        ]);

        $modelClass = $this->resolveModelClassByKey($validated['role_key']);
        $user = $modelClass::whereNull('archived_at')->findOrFail($validated['id']);
        $user->permissions = $validated['permissions'] ?? [];
        $user->save();

        return redirect()
            ->route('superadmin.system.users')
            ->with('success', 'Access permissions updated.');
    }

    public function updateSystemUserPassword(Request $request)
    {
        $validated = $request->validate([
            'role_key' => ['required', Rule::in(['superadmin', 'admin', 'staff', 'customer'])],
            'id' => ['required', 'integer', 'min:1'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $modelClass = $this->resolveModelClassByKey($validated['role_key']);
        DB::transaction(function () use ($modelClass, $validated) {
            $user = $modelClass::whereNull('archived_at')->lockForUpdate()->findOrFail($validated['id']);
            $user->password = Hash::make($validated['password']);
            $user->remember_token = Str::random(60);
            $user->save();

            DB::table('account_sessions')->where('account_key', hash('sha256', $modelClass.':'.$user->getAuthIdentifier()))->delete();

            // Admin-created accounts also have a legacy users-table record.
            // Keep it synchronized so the admin login fallback cannot accept
            // the previous password after a reset.
            if (in_array($modelClass, [Admin::class, Staff::class], true)) {
                $role = $modelClass === Admin::class ? 'Admin' : $user->role;
                $mirrors = \App\Models\User::where('username', $user->username)->where('role', $role)->get();
                foreach ($mirrors as $mirror) {
                    $mirror->password = $user->password;
                    $mirror->remember_token = Str::random(60);
                    $mirror->save();
                    DB::table('account_sessions')->where('account_key', hash('sha256', get_class($mirror).':'.$mirror->id))->delete();
                }
            }
        });

        return redirect()->route('superadmin.system.users')
            ->with('success', 'Password updated. This account must sign in again with the new password.');
    }

    public function systemLogs(Request $request)
    {
        return view('superadmin.system-logs', [
            'logs' => $this->logReader->paginateLogs($request, 'superadmin.system.logs'),
        ]);
    }

    public function systemLogsStream(Request $request)
    {
        $logs = $this->logReader->paginateLogs($request, 'superadmin.system.logs');

        return response()->json([
            'logs' => $logs->items(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
            'html' => view('superadmin.partials.log-results', compact('logs'))->render(),
        ])->header('Cache-Control', 'no-store');
    }

    public function clearSystemLogs()
    {
        foreach ($this->logReader->logPaths() as $logPath) {
            File::put($logPath, '', true);
        }

        return response()->json([
            'success' => true,
            'message' => 'All log records deleted.',
        ]);
    }

    private function mapUsers($collection, string $role, string $roleKey)
    {
        return $collection->map(function ($user) use ($role, $roleKey) {
            return [
                'id' => $user->id,
                'name' => $user->name ?? '',
                'email' => $user->email ?? null,
                'username' => $user->username ?? $user->name ?? ('id_' . $user->id),
                'role' => $roleKey === 'staff' ? ($user->role ?? 'Staff') : $role,
                'role_key' => $roleKey,
                'status' => $this->resolveStatus($user),
                'permissions' => $roleKey === 'superadmin' ? [] : ModuleAccess::permissions($user, $roleKey),
                'archived_at' => $user->archived_at,
                'last_login' => $user->updated_at ? $user->updated_at->format('Y-m-d h:i A') : 'Never',
            ];
        });
    }

    private function resolveStatus($user): string
    {
        return $this->isUserActive($user) ? 'Active' : 'Inactive';
    }

    private function isUserActive($user): bool
    {
        foreach (['is_active', 'active', 'status'] as $field) {
            if (isset($user->{$field})) {
                $value = $user->{$field};

                if (is_bool($value)) {
                    return $value;
                }

                if (is_numeric($value)) {
                    return ((int) $value) === 1;
                }

                $normalized = strtolower((string) $value);

                if (in_array($normalized, ['active', 'enabled', '1', 'true'], true)) {
                    return true;
                }

                if (in_array($normalized, ['inactive', 'disabled', '0', 'false', 'locked'], true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function normalizePermissions($permissions): array
    {
        if (is_array($permissions)) {
            return array_values(array_filter($permissions, fn ($permission) => is_string($permission) && $permission !== ''));
        }

        return [];
    }

    private function resolveModelClassByKey(string $roleKey): string
    {
        return match ($roleKey) {
            'superadmin' => SuperAdmin::class,
            'admin' => Admin::class,
            'staff' => Staff::class,
            'customer' => Customer::class,
            default => throw new \InvalidArgumentException('Invalid role key'),
        };
    }

    private function legacyUsers($user)
    {
        if (!$user instanceof Admin && !$user instanceof Staff) {
            return collect();
        }

        return \App\Models\User::where('username', $user->username)
            ->where('role', $user instanceof Admin ? 'Admin' : $user->role)->get();
    }

    private function revokeAccountSessions($user): void
    {
        DB::table('account_sessions')
            ->where('account_key', hash('sha256', get_class($user).':'.$user->getAuthIdentifier()))->delete();
        $user->remember_token = Str::random(60);
        $user->save();
    }
}
