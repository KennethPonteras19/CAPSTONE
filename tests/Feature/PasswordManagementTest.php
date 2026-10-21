<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthenticationTestCase;

class PasswordManagementTest extends AuthenticationTestCase
{
    private function account(string $role)
    {
        $model = match ($role) {
            'admin' => Admin::class, 'staff' => Staff::class,
            'customer' => Customer::class, 'superadmin' => SuperAdmin::class,
        };
        $attributes = ['name' => 'Password Test', 'username' => 'test.'.Str::random(8),
            'password' => 'original-password', 'is_active' => true];
        $attributes[$role === 'staff' ? 'role' : 'email'] = $role === 'staff' ? 'Staff' : Str::random(8).'@example.test';

        return $model::create($attributes);
    }

    public static function roles(): array
    {
        return [['admin'], ['staff'], ['customer'], ['superadmin']];
    }

    #[DataProvider('roles')]
    public function test_superadmin_can_replace_passwords_for_each_role(string $role): void
    {
        $user = $this->account($role);
        $this->actingAs($this->account('superadmin'), 'superadmin')
            ->put('/superadmin/system-users/password', [
                'role_key' => $role, 'id' => $user->id,
                'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
            ])->assertRedirect('/superadmin/system-users')->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('replacement-password', $user->password));
        $this->assertFalse(Hash::check('original-password', $user->password));
        $this->assertNotSame('replacement-password', $user->password);
        $this->assertNotEmpty($user->remember_token);
        $this->get('/superadmin/system-users')->assertOk()->assertSee('Edit Password')
            ->assertDontSee($user->password)->assertDontSee('replacement-password');
    }

    public function test_reset_rejects_an_existing_session_and_allows_the_new_password(): void
    {
        $staff = $this->account('staff');
        $this->postLogin('/login/staff/process', ['username' => $staff->username, 'password' => 'original-password'])
            ->assertRedirect('/dashboardstaff');
        $this->actingAs($this->account('superadmin'), 'superadmin')
            ->put('/superadmin/system-users/password', ['role_key' => 'staff', 'id' => $staff->id,
                'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])
            ->assertSessionHasNoErrors();
        Auth::forgetGuards();
        $this->get('/dashboardstaff')->assertRedirect('/login');
        $this->postLogin('/login/staff/process', ['username' => $staff->username, 'password' => 'replacement-password'])
            ->assertRedirect('/dashboardstaff');
    }

    public function test_admin_legacy_login_cannot_accept_the_old_password_after_reset(): void
    {
        $admin = $this->account('admin');
        $mirror = User::create(['username' => $admin->username, 'name' => $admin->name, 'email' => $admin->email,
            'password' => 'original-password', 'role' => 'Admin', 'status' => 'Active']);
        $this->actingAs($this->account('superadmin'), 'superadmin')
            ->put('/superadmin/system-users/password', ['role_key' => 'admin', 'id' => $admin->id,
                'password' => 'replacement-password', 'password_confirmation' => 'replacement-password']);
        $this->assertTrue(Hash::check('replacement-password', $mirror->fresh()->password));
        $this->postLogin('/login/admin/process', ['username' => $admin->username, 'password' => 'original-password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest('admin');
    }

    public function test_invalid_passwords_do_not_change_the_account_or_flash_passwords(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($this->account('superadmin'), 'superadmin');
        foreach ([['short', 'short'], ['replacement-password', 'different'], ['', '']] as [$password, $confirmation]) {
            $this->from('/superadmin/system-users')->put('/superadmin/system-users/password', [
                '_form' => 'password', 'role_key' => 'admin', 'id' => $admin->id,
                'password' => $password, 'password_confirmation' => $confirmation,
            ])->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');
            $this->assertTrue(Hash::check('original-password', $admin->fresh()->password));
        }
        $this->get('/superadmin/system-users')->assertOk()->assertDontSee('replacement-password');
    }

    public function test_other_roles_and_guests_cannot_reset_passwords(): void
    {
        $target = $this->account('admin');
        $payload = ['role_key' => 'admin', 'id' => $target->id,
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'];
        $this->put('/superadmin/system-users/password', $payload)->assertRedirect('/login');
        foreach (['admin', 'staff', 'customer'] as $role) {
            $this->actingAs($this->account($role), $role)->put('/superadmin/system-users/password', $payload)->assertRedirect('/login');
        }
        $this->assertTrue(Hash::check('original-password', $target->fresh()->password));
    }
}
