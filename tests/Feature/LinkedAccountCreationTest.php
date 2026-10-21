<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthenticationTestCase;

class LinkedAccountCreationTest extends AuthenticationTestCase
{
    private function admin(): Admin
    {
        return Admin::create(['name' => 'Creating Admin', 'username' => 'creator',
            'email' => 'creator@example.test', 'password' => 'password123',
            'is_active' => true, 'permissions' => ['accounts']]);
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::create(['name' => 'Super Admin', 'username' => 'supervisor',
            'email' => 'supervisor@example.test', 'password' => 'password123', 'is_active' => true]);
    }

    private function payload(string $role = 'Cashier'): array
    {
        return ['name' => 'Created Account', 'username' => 'new.account', 'role' => $role,
            'email' => $role === 'Admin' ? 'new@example.test' : null,
            'password' => 'password123', 'password_confirmation' => 'password123'];
    }

    public static function roles(): array
    {
        return [['Admin'], ['Cashier'], ['Inventory Staff'], ['Manager']];
    }

    #[DataProvider('roles')]
    public function test_admin_created_accounts_are_the_same_records_managed_by_superadmin(string $role): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/users', $this->payload($role))
            ->assertRedirect('/add-account')->assertSessionHasNoErrors();
        $class = $role === 'Admin' ? Admin::class : Staff::class;
        $account = $class::where('username', 'new.account')->sole();
        $this->assertTrue($account->is_active);
        $this->assertTrue(Hash::check('password123', $account->password));
        $this->assertDatabaseMissing('users', ['username' => 'new.account']);

        $response = $this->actingAs($this->superAdmin(), 'superadmin')
            ->get('/superadmin/system-users')->assertOk()->assertSee('new.account');
        $listed = $response->viewData('users')->where('username', 'new.account')->sole();
        $this->assertSame($account->id, $listed['id']);
        $this->assertSame($role, $listed['role']);
        $roleKey = $role === 'Admin' ? 'admin' : 'staff';
        $this->assertSame($roleKey, $listed['role_key']);
        $this->put('/superadmin/system-users/permissions', [
            'role_key' => $roleKey, 'id' => $account->id, 'permissions' => ['dashboard'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['dashboard'], $account->fresh()->permissions);
        $this->put('/superadmin/system-users/password', [
            'role_key' => $roleKey, 'id' => $account->id,
            'password' => 'changed-password', 'password_confirmation' => 'changed-password',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('changed-password', $account->fresh()->password));
        $this->patch('/superadmin/system-users/toggle-status', ['role_key' => $roleKey, 'id' => $account->id])
            ->assertSessionHasNoErrors();
        $this->assertFalse($account->fresh()->is_active);
    }

    #[DataProvider('roles')]
    public function test_new_accounts_can_sign_in_without_a_duplicate_users_record(string $role): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/users', $this->payload($role))->assertSessionHasNoErrors();
        Auth::forgetGuards();
        $guard = $role === 'Admin' ? 'admin' : 'staff';
        $this->postLogin('/login/'.$guard.'/process', ['username' => 'new.account', 'password' => 'password123'])
            ->assertSessionHasNoErrors()->assertRedirect($role === 'Admin' ? '/dashboard' : '/dashboardstaff');
        $this->assertAuthenticatedAs(($role === 'Admin' ? Admin::class : Staff::class)::where('username', 'new.account')->sole(), $guard);
    }

    public function test_admin_pages_offer_creation_only_and_old_mutation_routes_are_removed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        foreach (['/add-account', '/users/create'] as $url) {
            $this->get($url)->assertOk()->assertSee('Create Account')
                ->assertSee('action="'.route('users.store').'"', false)
                ->assertDontSee('name="status"', false)->assertDontSee('name="permissions"', false)
                ->assertDontSee('Edit Password')->assertDontSee('Deactivate')->assertDontSee('Delete Account');
        }
        $this->get('/users/'.$admin->id.'/edit')->assertNotFound();
        $this->put('/users/'.$admin->id, ['name' => 'Unauthorized change'])->assertNotFound();
        $this->delete('/users/'.$admin->id)->assertNotFound();
        $this->put('/superadmin/system-users/password', [])->assertRedirect('/login');
        $this->patch('/superadmin/system-users/toggle-status', [])->assertRedirect('/login');
        $this->patch('/superadmin/system-users/archive', [])->assertRedirect('/login');
        $this->assertSame('Creating Admin', $admin->fresh()->name);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_assign_permissions_status_or_superadmin_role_during_creation(): void
    {
        $this->actingAs($this->admin(), 'admin');
        foreach (['permissions' => ['dashboard'], 'status' => 'Inactive', 'is_active' => false, 'role' => 'Super Admin'] as $field => $value) {
            $this->post('/users', array_replace($this->payload(), [$field => $value]))->assertSessionHasErrors($field);
            $this->assertDatabaseMissing('staffs', ['username' => 'new.account']);
            $this->assertDatabaseMissing('superadmin', ['username' => 'new.account']);
        }
    }

    public function test_both_creation_screens_reject_usernames_created_in_the_other_screen(): void
    {
        $admin = $this->admin();
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin, 'superadmin')->post('/superadmin/system-users', $this->payload('Staff'))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post('/users', $this->payload('Admin'))->assertSessionHasErrors('username');

        $other = array_replace($this->payload('Admin'), ['username' => 'admin.created']);
        $this->post('/users', $other)->assertSessionHasNoErrors();
        $this->actingAs($superAdmin, 'superadmin')->post('/superadmin/system-users', array_replace($other, ['role' => 'Staff']))
            ->assertSessionHasErrors('username');
        $this->assertDatabaseMissing('staffs', ['username' => 'admin.created']);
    }

    public function test_deactivation_and_archiving_also_update_legacy_admin_created_records(): void
    {
        $admin = $this->admin();
        $mirror = User::create(['name' => $admin->name, 'username' => $admin->username,
            'email' => $admin->email, 'password' => 'password123', 'role' => 'Admin', 'status' => 'Active']);
        $this->actingAs($this->superAdmin(), 'superadmin');
        $this->patch('/superadmin/system-users/toggle-status', ['role_key' => 'admin', 'id' => $admin->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('Inactive', $mirror->fresh()->status);
        $this->patch('/superadmin/system-users/toggle-status', ['role_key' => 'admin', 'id' => $admin->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('Active', $mirror->fresh()->status);
        $this->patch('/superadmin/system-users/archive', ['role_key' => 'admin', 'id' => $admin->id])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($admin->fresh()->archived_at);
        $this->assertNotNull($mirror->fresh()->archived_at);
        Auth::forgetGuards();
        $this->postLogin('/login/admin/process', ['username' => $admin->username, 'password' => 'password123'])
            ->assertSessionHasErrors('username');
        $this->assertGuest('admin');
    }

    public function test_admin_email_is_required_and_invalid_creation_does_not_save_an_account(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/users', array_replace($this->payload('Admin'), ['email' => null]))
            ->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('admins', ['username' => 'new.account']);
        $this->assertDatabaseMissing('users', ['username' => 'new.account']);
    }
}
