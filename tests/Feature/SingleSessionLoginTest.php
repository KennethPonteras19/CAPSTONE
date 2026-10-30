<?php

namespace Tests\Feature;

use App\Auth\SingleSessionGuard;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\SuperAdmin;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthenticationTestCase as TestCase;

class SingleSessionLoginTest extends TestCase
{
    public static function loginCases(): array
    {
        return RoleLoginTest::loginCases();
    }

    private function createAccount(string $guard = 'admin', string $suffix = '')
    {
        $model = match ($guard) {
            'admin' => Admin::class,
            'superadmin' => SuperAdmin::class,
            'staff' => Staff::class,
            'customer' => Customer::class,
        };

        $attributes = [
            'name' => 'Session Test',
            'username' => $guard.$suffix,
            'password' => 'password123',
            'is_active' => true,
        ];

        if ($guard === 'staff') {
            $attributes['role'] = 'Staff';
        } else {
            $attributes['email'] = $guard.$suffix.'@example.test';
        }

        return $model::create($attributes);
    }

    private function credentials($account): array
    {
        return ['username' => $account->username, 'password' => 'password123'];
    }

    private function assertDashboardAccessible(string $dashboard, string $guard): void
    {
        $response = $this->get($dashboard);
        if ($guard === 'customer') {
            $response->assertRedirect(route('customer.home'));
            $this->get(route('customer.home'))->assertOk()->assertViewIs('customer.home');
        } else {
            $response->assertOk();
        }
    }

    private function switchBrowser(array $session = []): array
    {
        $previous = session()->all();
        Auth::forgetGuards();
        session()->invalidate();
        session()->put($session);
        session()->save();
        $this->defaultCookies = [];

        return $previous;
    }

    #[DataProvider('loginCases')]
    public function test_second_browser_is_rejected_and_first_browser_stays_logged_in(string $guard, string $endpoint): void
    {
        $account = $this->createAccount($guard);
        $firstLogin = $this->postLogin($endpoint, $this->credentials($account));
        $firstLogin->assertRedirect();
        $dashboard = $firstLogin->headers->get('Location');
        $this->assertAuthenticatedAs($account, $guard);
        $firstBrowser = $this->switchBrowser();
        $originalLock = DB::table('account_sessions')->first();

        $this->from('/login')->postLogin($endpoint, $this->credentials($account) + ['remember' => '1'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => SingleSessionGuard::ALREADY_LOGGED_IN])
            ->assertSessionHasInput('username', $account->username)
            ->assertCookieMissing(Auth::guard($guard)->getRecallerName());

        $this->assertGuest($guard);
        $this->assertEquals($originalLock, DB::table('account_sessions')->first());

        $this->switchBrowser($firstBrowser);
        $this->assertDashboardAccessible($dashboard, $guard);
        $this->assertAuthenticatedAs($account, $guard);
    }

    #[DataProvider('loginCases')]
    public function test_logout_allows_another_browser_to_log_in(string $guard, string $endpoint): void
    {
        $account = $this->createAccount($guard);
        $this->postLogin($endpoint, $this->credentials($account))->assertRedirect();
        Auth::forgetGuards();
        $this->post('/logout')->assertRedirect('/');
        $this->assertDatabaseCount('account_sessions', 0);

        $this->switchBrowser();
        $this->postLogin($endpoint, $this->credentials($account))->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($account, $guard);
    }

    public function test_expired_session_can_be_replaced_and_stale_browser_cannot_release_new_login(): void
    {
        $account = $this->createAccount();
        $this->postLogin('/login/perform', $this->credentials($account));
        $oldBrowser = $this->switchBrowser();

        $this->travel((int) config('session.lifetime') + 1)->minutes();
        $this->postLogin('/login/perform', $this->credentials($account))->assertRedirect('/dashboard');
        $newBrowser = $this->switchBrowser($oldBrowser);
        $newLock = DB::table('account_sessions')->first();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/logout');
        $this->assertEquals($newLock, DB::table('account_sessions')->first());

        $this->switchBrowser($newBrowser);
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($account, 'admin');
    }

    public function test_activity_extends_the_lock_and_session_regeneration_keeps_ownership(): void
    {
        $account = $this->createAccount();
        $this->postLogin('/login/perform', $this->credentials($account));
        $this->travel((int) config('session.lifetime') - 1)->minutes();
        Auth::forgetGuards();
        $this->get('/dashboard')->assertOk();
        session()->regenerate();
        session()->save();
        $this->travel(2)->minutes();
        Auth::forgetGuards();
        $this->get('/dashboard')->assertOk();

        $this->switchBrowser();
        $this->from('/login')->postLogin('/login/perform', $this->credentials($account))
            ->assertSessionHasErrors(['username' => SingleSessionGuard::ALREADY_LOGGED_IN]);
    }

    public function test_different_accounts_and_matching_ids_in_different_role_tables_can_log_in(): void
    {
        foreach ([$this->createAccount(), $this->createAccount('admin', '2'), $this->createAccount('staff')] as $account) {
            $this->switchBrowser();
            $this->postLogin('/login/perform', $this->credentials($account))->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('account_sessions', 3);
    }

    public function test_wrong_password_does_not_disclose_or_change_the_active_login(): void
    {
        $account = $this->createAccount();
        $this->postLogin('/login/perform', $this->credentials($account));
        $this->switchBrowser();
        $originalLock = DB::table('account_sessions')->first();

        $this->from('/login')->postLogin('/login/perform', [
            'username' => $account->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['username' => 'Invalid credentials or account is deactivated.']);

        $this->assertEquals($originalLock, DB::table('account_sessions')->first());
    }

    public function test_remember_cookie_cannot_bypass_an_active_login_but_can_restore_after_expiry(): void
    {
        $account = $this->createAccount();
        $this->postLogin('/login/perform', $this->credentials($account) + ['remember' => '1']);
        $account->refresh();
        $guard = Auth::guard('admin');
        $cookieName = $guard->getRecallerName();
        $cookieValue = $account->id.'|'.$account->getRememberToken().'|'.$guard->hashPasswordForCookie($account->password);
        $firstBrowser = $this->switchBrowser();

        $this->withCookie($cookieName, $cookieValue)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest('admin');

        $this->switchBrowser($firstBrowser);
        $this->get('/dashboard')->assertOk();
        $this->switchBrowser();
        $this->travel((int) config('session.lifetime') + 1)->minutes();
        $this->withCookie($cookieName, $cookieValue)->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($account, 'admin');
    }

    public function test_existing_session_is_enrolled_when_it_next_accesses_the_app(): void
    {
        $account = $this->createAccount();
        $legacyKey = 'login_admin_'.sha1(SessionGuard::class);
        $this->withSession([$legacyKey => $account->id])->get('/dashboard')->assertOk();
        $this->assertDatabaseCount('account_sessions', 1);

        $this->switchBrowser();
        $this->from('/login')->postLogin('/login/perform', $this->credentials($account))
            ->assertSessionHasErrors(['username' => SingleSessionGuard::ALREADY_LOGGED_IN]);
    }

    #[DataProvider('loginCases')]
    public function test_remember_me_restores_the_same_browser_without_waiting_for_expiry(string $guard, string $endpoint): void
    {
        $account = $this->createAccount($guard);
        $response = $this->postLogin($endpoint, $this->credentials($account) + ['remember' => '1']);
        $sessionCookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($sessionCookie);
        $this->assertSame(0, $sessionCookie->getExpiresTime());
        $dashboard = $response->headers->get('Location');
        $cookieName = Auth::guard($guard)->getRecallerName();
        $cookie = $response->getCookie($cookieName);
        $this->assertNotNull($cookie);
        $this->assertGreaterThan(now()->timestamp, $cookie->getExpiresTime());
        $originalLock = DB::table('account_sessions')->first();
        $this->switchBrowser();

        $this->withCookie($cookieName, $cookie->getValue())->get('/login')->assertRedirect($dashboard);
        $this->assertAuthenticatedAs($account, $guard);
        $this->assertTrue(Auth::guard($guard)->viaRemember());
        $this->assertSame($originalLock->token, DB::table('account_sessions')->first()->token);

        Auth::forgetGuards();
        $this->assertDashboardAccessible($dashboard, $guard);
        $this->get('/login/'.$guard)->assertRedirect($dashboard);

        // The same cookie can restore a session after a longer absence too.
        $this->switchBrowser();
        $this->travel((int) config('session.lifetime') + 1)->minutes();
        $this->withCookie($cookieName, $cookie->getValue());
        $this->assertDashboardAccessible($dashboard, $guard);
        $this->assertAuthenticatedAs($account, $guard);

        // A new browser still cannot log in while this remembered session is active.
        $this->switchBrowser();
        $this->postLogin($endpoint, $this->credentials($account))
            ->assertSessionHasErrors(['username' => SingleSessionGuard::ALREADY_LOGGED_IN]);
    }

    #[DataProvider('loginCases')]
    public function test_unchecked_remember_me_does_not_restore_a_lost_session(string $guard, string $endpoint): void
    {
        $account = $this->createAccount($guard);
        $response = $this->postLogin($endpoint, $this->credentials($account));
        $response->assertCookieMissing(Auth::guard($guard)->getRecallerName());
        $sessionCookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($sessionCookie);
        $this->assertSame(0, $sessionCookie->getExpiresTime());
        $dashboard = $response->headers->get('Location');
        $this->switchBrowser();

        $this->get($dashboard)->assertRedirect('/login');
        $this->assertGuest($guard);
    }

    #[DataProvider('loginCases')]
    public function test_logout_revokes_the_remember_cookie(string $guard, string $endpoint): void
    {
        $account = $this->createAccount($guard);
        $response = $this->postLogin($endpoint, $this->credentials($account) + ['remember' => '1']);
        $cookieName = Auth::guard($guard)->getRecallerName();
        $cookie = $response->getCookie($cookieName);
        $this->switchBrowser();

        $this->withCookie($cookieName, $cookie->getValue())->post('/logout')
            ->assertRedirect('/')->assertCookieExpired($cookieName);
        $this->assertDatabaseCount('account_sessions', 0);
        $this->switchBrowser();

        $this->withCookie($cookieName, $cookie->getValue())->get('/login')->assertOk();
        $this->assertGuest($guard);
    }

    public function test_old_remembered_browser_cannot_take_over_a_new_active_login(): void
    {
        $account = $this->createAccount();
        $response = $this->postLogin('/login/perform', $this->credentials($account) + ['remember' => '1']);
        $cookieName = Auth::guard('admin')->getRecallerName();
        $cookie = $response->getCookie($cookieName);
        $this->switchBrowser();
        $this->travel((int) config('session.lifetime') + 1)->minutes();
        $this->postLogin('/login/perform', $this->credentials($account))->assertRedirect('/dashboard');
        $newLock = DB::table('account_sessions')->first();
        $this->switchBrowser();

        $this->withCookie($cookieName, $cookie->getValue())->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest('admin');
        $this->assertEquals($newLock, DB::table('account_sessions')->first());
    }

    public function test_deactivated_account_cannot_return_using_remember_me(): void
    {
        $account = $this->createAccount();
        $response = $this->postLogin('/login/perform', $this->credentials($account) + ['remember' => '1']);
        $cookieName = Auth::guard('admin')->getRecallerName();
        $cookie = $response->getCookie($cookieName);
        $account->update(['is_active' => false]);
        $this->switchBrowser();

        $this->withCookie($cookieName, $cookie->getValue())->get('/dashboard')
            ->assertRedirect('/login')->assertSessionHasErrors('username');
        $this->assertGuest('admin');
    }
}
