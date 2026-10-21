<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\SuperAdmin;
use Tests\AuthenticationTestCase as TestCase;

class SuperAdminSystemUsersTest extends TestCase
{
    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = SuperAdmin::create([
            'name' => 'Main Super Admin',
            'username' => 'root.superadmin',
            'email' => 'root@tmta.test',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'permissions' => ['system_users', 'system_logs'],
        ]);
    }

    public function test_superadmin_can_view_system_users_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.system.users'));

        $response->assertOk();
        $response->assertSee('System User');
    }

    public function test_superadmin_can_create_new_admin_account(): void
    {
        $payload = [
            'role' => 'Admin',
            'name' => 'New Admin',
            'username' => 'new.admin',
            'email' => 'new.admin@tmta.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'permissions' => ['dashboard', 'reports'],
        ];

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.system.users.store'), $payload);

        $response->assertRedirect(route('superadmin.system.users'));

        $this->assertDatabaseHas('admins', [
            'username' => 'new.admin',
            'email' => 'new.admin@tmta.test',
            'is_active' => 1,
        ]);
    }

    public function test_superadmin_can_toggle_user_status(): void
    {
        $admin = Admin::create([
            'name' => 'Managed Admin',
            'username' => 'managed.admin',
            'email' => 'managed.admin@tmta.test',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'permissions' => ['dashboard'],
        ]);

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->patch(route('superadmin.system.users.toggle-status'), [
                'role_key' => 'admin',
                'id' => $admin->id,
            ]);

        $response->assertRedirect(route('superadmin.system.users'));

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'is_active' => 0,
        ]);
    }

    public function test_superadmin_can_update_permissions(): void
    {
        $staff = Staff::create([
            'name' => 'Managed Staff',
            'username' => 'managed.staff',
            'password' => bcrypt('password123'),
            'role' => 'Staff',
            'is_active' => true,
            'permissions' => ['sales'],
        ]);

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->put(route('superadmin.system.users.permissions'), [
                'role_key' => 'staff',
                'id' => $staff->id,
                'permissions' => ['pos', 'inventory', 'orders'],
            ]);

        $response->assertRedirect(route('superadmin.system.users'));

        $staff->refresh();
        $this->assertSame(['pos', 'inventory', 'orders'], $staff->permissions);
    }

    public function test_superadmin_can_archive_user_account(): void
    {
        $customer = Customer::create([
            'name' => 'Managed Customer',
            'username' => 'managed.customer',
            'email' => 'managed.customer@tmta.test',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'permissions' => ['dashboard'],
        ]);

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->patch(route('superadmin.system.users.archive'), [
                'role_key' => 'customer',
                'id' => $customer->id,
            ]);

        $response->assertRedirect(route('superadmin.system.users'));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => 0]);
        $this->assertNotNull($customer->fresh()->archived_at);
    }

    public function test_superadmin_cannot_archive_own_account(): void
    {
        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->patch(route('superadmin.system.users.archive'), [
                'role_key' => 'superadmin',
                'id' => $this->superAdmin->id,
            ]);

        $response->assertRedirect(route('superadmin.system.users'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('superadmin', ['id' => $this->superAdmin->id]);
    }

    public function test_create_user_requires_valid_payload(): void
    {
        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.system.users.store'), [
                'role' => 'InvalidRole',
                'name' => '',
                'username' => '',
                'password' => '123',
                'password_confirmation' => '456',
            ]);

        $response->assertSessionHasErrors(['role', 'name', 'username', 'password']);
    }

    public function test_archive_preserves_customer_order_history_and_restore_requires_explicit_activation(): void
    {
        $customer = Customer::create(['name' => 'Archived buyer', 'username' => 'archived.buyer',
            'email' => 'buyer@example.test', 'password' => 'password123', 'is_active' => true,
            'permissions' => ['dashboard']]);
        $order = \App\Models\Order::create(['number' => 'ARCHIVE-1', 'request_key' => 'archive-test',
            'customer_id' => $customer->id, 'staff_name' => 'Cashier', 'type' => 'Walk-in',
            'status' => 'Completed', 'subtotal' => 100, 'total_amount' => 100]);
        $this->actingAs($this->superAdmin, 'superadmin')
            ->patch(route('superadmin.system.users.archive'), ['role_key' => 'customer', 'id' => $customer->id])
            ->assertSessionHas('success');
        $this->assertSame($customer->id, $order->fresh()->customer->id);
        $this->get(route('superadmin.system.users'))->assertDontSee('archived.buyer');
        $this->get(route('superadmin.archive'))->assertOk()->assertSee('archived.buyer');
        $this->get(route('superadmin.archive', ['search' => 'nobody']))->assertDontSee('archived.buyer');
        $this->patch(route('superadmin.system.users.toggle-status'), ['role_key' => 'customer', 'id' => $customer->id])->assertNotFound();
        $this->put(route('superadmin.system.users.permissions'), ['role_key' => 'customer', 'id' => $customer->id, 'permissions' => ['dashboard']])->assertNotFound();
        $this->put(route('superadmin.system.users.password'), ['role_key' => 'customer', 'id' => $customer->id,
            'password' => 'newpassword', 'password_confirmation' => 'newpassword'])->assertNotFound();
        $this->patch(route('superadmin.archive.restore'), ['role_key' => 'customer', 'id' => $customer->id])
            ->assertRedirect(route('superadmin.archive'));
        $this->assertNull($customer->fresh()->archived_at);
        $this->assertFalse($customer->fresh()->is_active);
        $this->assertSame(['dashboard'], $customer->fresh()->permissions);
        $this->get(route('superadmin.archive'))->assertDontSee('archived.buyer');
        $this->get(route('superadmin.system.users'))->assertSee('archived.buyer');
        $this->patch(route('superadmin.system.users.toggle-status'), ['role_key' => 'customer', 'id' => $customer->id]);
        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_archiving_all_account_roles_revokes_sessions_and_blocks_login(): void
    {
        foreach (['admin' => Admin::class, 'staff' => Staff::class, 'customer' => Customer::class, 'superadmin' => SuperAdmin::class] as $role => $model) {
            $account = $model::create(['name' => 'Archive '.$role, 'username' => 'archive.'.$role,
                'email' => $role.'@example.test', 'password' => 'password123', 'role' => 'Staff', 'is_active' => true]);
            $mirror = in_array($role, ['admin', 'staff']) ? \App\Models\User::create([
                'name' => $account->name, 'username' => $account->username, 'email' => $role.'.mirror@example.test',
                'password' => 'password123', 'role' => ucfirst($role), 'status' => 'Active',
            ]) : null;
            $oldToken = 'old-remember-token';
            $account->remember_token = $oldToken;
            $account->save();
            $key = hash('sha256', $model.':'.$account->id);
            \Illuminate\Support\Facades\DB::table('account_sessions')->insert([
                'account_key' => $key, 'token' => str_repeat('a', 64), 'last_activity' => time(),
            ]);
            $this->actingAs($this->superAdmin, 'superadmin')->patch(route('superadmin.system.users.archive'), [
                'role_key' => $role, 'id' => $account->id,
            ])->assertSessionHas('success');
            $this->assertDatabaseMissing('account_sessions', ['account_key' => $key]);
            $this->assertNotSame($oldToken, $account->fresh()->remember_token);
            $this->assertNotNull($account->fresh()->archived_at);
            if ($mirror) {
                $this->assertNotNull($mirror->fresh()->archived_at);
                $this->assertSame('Inactive', $mirror->fresh()->status);
            }
            \Illuminate\Support\Facades\Auth::forgetGuards();
            $this->postLogin('/login/perform', ['username' => $account->username, 'password' => 'password123'])
                ->assertSessionHasErrors('username');
            $this->assertGuest($role);
            $this->actingAs($this->superAdmin, 'superadmin')->patch(route('superadmin.archive.restore'), [
                'role_key' => $role, 'id' => $account->id,
            ])->assertSessionHas('success');
            if ($mirror) {
                $this->assertNull($mirror->fresh()->archived_at);
                $this->assertSame('Inactive', $mirror->fresh()->status);
            }
        }
    }

    public function test_user_management_routes_require_superadmin_auth(): void
    {
        $this->get(route('superadmin.archive'))->assertRedirect(route('login'));
        $this->patch(route('superadmin.archive.restore'), [])->assertRedirect(route('login'));
        $this->post(route('superadmin.system.users.store'), [])
            ->assertRedirect(route('login'));

        $this->patch(route('superadmin.system.users.toggle-status'), [])
            ->assertRedirect(route('login'));

        $this->put(route('superadmin.system.users.permissions'), [])
            ->assertRedirect(route('login'));

        $this->patch(route('superadmin.system.users.archive'), [])
            ->assertRedirect(route('login'));
    }
}
