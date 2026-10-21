<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthenticationTestCase as TestCase;

class RoleLoginTest extends TestCase
{
    public static function loginCases(): array
    {
        $cases = [];
        foreach (['admin', 'superadmin', 'staff', 'customer'] as $guard) {
            foreach (['/login/perform', "/login/{$guard}/process"] as $endpoint) {
                $cases["{$guard} via {$endpoint}"] = [$guard, $endpoint];
            }
        }

        return $cases;
    }

    private function createAccount(string $guard, bool $active = true)
    {
        $models = [
            'admin' => Admin::class,
            'superadmin' => SuperAdmin::class,
            'staff' => Staff::class,
            'customer' => Customer::class,
        ];

        $attributes = [
            'name' => "Test {$guard}",
            'username' => "test.{$guard}",
            'password' => 'password123',
            'is_active' => $active,
        ];

        if ($guard === 'staff') {
            $attributes['role'] = 'Staff';
        } else {
            $attributes['email'] = "{$guard}@example.test";
        }

        return $models[$guard]::create($attributes);
    }

    #[DataProvider('loginCases')]
    public function test_login_opens_the_correct_dashboard(string $guard, string $endpoint): void
    {
        $user = $this->createAccount($guard);
        [$path, $view] = match ($guard) {
            'admin' => ['/dashboard', 'dashboard'],
            'superadmin' => ['/superadmin/dashboard', 'dashboardsuperadmin'],
            'staff' => ['/dashboardstaff', 'staff.dashboard'],
            'customer' => ['/customer/dashboard', 'dashboardcustomer'],
        };

        $this->postLogin($endpoint, [
            'username' => $user->username,
            'password' => 'password123',
        ])->assertRedirect($path);

        $this->assertAuthenticatedAs($user, $guard);

        // Resolve the user from the session again, as a new HTTP request would.
        Auth::forgetGuards();
        if ($guard === 'customer') {
            $this->get($path)->assertRedirect(route('customer.home'));
            $this->get(route('customer.home'))->assertOk()->assertViewIs('customer.home');
        } else {
            $this->get($path)->assertOk()->assertViewIs($view);
        }
        foreach (array_diff(['admin', 'superadmin', 'staff', 'customer'], [$guard]) as $otherGuard) {
            $this->assertGuest($otherGuard);
        }
    }

    #[DataProvider('loginCases')]
    public function test_inactive_accounts_cannot_log_in(string $guard, string $endpoint): void
    {
        $user = $this->createAccount($guard, false);

        $this->from('/login')->postLogin($endpoint, [
            'username' => $user->username,
            'password' => 'password123',
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        $this->assertGuest($guard);
    }

    public function test_staff_pages_require_staff_login_and_keep_navigation_in_staff_section(): void
    {
        $pages = [
            '/dashboardstaff' => 'staff.dashboard',
            '/pos' => 'staff.pos',
            '/transactions' => 'staff.transactions',
            '/staff/inventory' => 'staff.inventory',
            '/orders' => 'staff.orders',
            '/customers' => 'staff.customers',
        ];

        foreach ($pages as $path => $view) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->post('/pos/checkout', [])->assertRedirect('/login');

        $this->actingAs($this->createAccount('staff'), 'staff');
        foreach ($pages as $path => $view) {
            $response = $this->get($path)->assertOk()->assertViewIs($view);
            if ($path !== '/pos') {
                $response->assertSee(route('dashboardstaff'))
                    ->assertSee(route('staff.inventory'))
                    ->assertDontSee('href="'.route('dashboard').'"', false)
                    ->assertSee('action="'.route('logout').'"', false);
            }
        }

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/inventory')->assertRedirect('/login');
        $this->get('/superadmin/dashboard')->assertRedirect('/login');
    }

    public function test_admin_inventory_is_preserved_and_admin_cannot_access_staff_pages(): void
    {
        $this->actingAs($this->createAccount('admin'), 'admin');

        $this->get('/inventory')->assertOk()->assertViewIs('inventory');
        $this->get('/dashboardstaff')->assertRedirect('/login');
        $this->get('/superadmin/dashboard')->assertRedirect('/login');
    }

    public function test_staff_can_log_out_without_an_admin_session(): void
    {
        $this->actingAs($this->createAccount('staff'), 'staff');

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest('staff');
        $this->get('/dashboardstaff')->assertRedirect('/login');
    }

    public function test_an_inactive_session_for_another_role_does_not_block_superadmin(): void
    {
        $this->actingAs($this->createAccount('admin', false), 'admin');
        $this->actingAs($this->createAccount('superadmin'), 'superadmin');

        $this->get('/superadmin/dashboard')->assertOk();
    }

    public function test_deactivated_staff_session_is_rejected(): void
    {
        $this->actingAs($this->createAccount('staff', false), 'staff');

        $this->get('/dashboardstaff')->assertRedirect('/login')
            ->assertSessionHasErrors('username');
        $this->assertGuest('staff');
    }

    public function test_cashier_can_use_both_login_forms(): void
    {
        $staff = $this->createAccount('staff');
        $staff->update(['role' => 'Cashier']);

        foreach (['/login/perform', '/login/staff/process'] as $endpoint) {
            $this->postLogin($endpoint, [
                'username' => $staff->username,
                'password' => 'password123',
            ])->assertRedirect('/dashboardstaff');
            $this->post('/logout');
        }
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->createAccount('superadmin');

        $this->from('/login')->postLogin('/login/perform', [
            'username' => 'test.superadmin',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        foreach (['admin', 'superadmin', 'staff', 'customer'] as $guard) {
            $this->assertGuest($guard);
        }
    }
}
