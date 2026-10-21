<?php

namespace Tests\Feature;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthenticationTestCase;

class SuperAdminMasterLoginTest extends AuthenticationTestCase
{
    private const KEY = 'test-only-master-password';

    private SuperAdmin $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = SuperAdmin::create([
            'name' => 'Master Login Test', 'username' => 'master.test',
            'email' => 'master@example.test', 'password' => 'normal-password', 'is_active' => true,
        ]);
        config([
            'auth.superadmin_master.password_hash' => Hash::make(self::KEY),
            'auth.superadmin_master.account_id' => $this->account->id,
        ]);
    }

    public function test_master_form_only_requires_password_and_security_code(): void
    {
        $normalLogin = $this->get('/login')->assertOk()->assertDontSee(self::KEY);
        $document = new \DOMDocument;
        @$document->loadHTML($normalLogin->getContent());
        $username = (new \DOMXPath($document))->query('//input[@name="username"]')->item(0);
        $this->assertNotNull($username);
        $this->assertFalse($username->hasAttribute('required'));
        $this->get('/login/superadmin/master')->assertOk()
            ->assertSee('name="password"', false)->assertSee('data-login-captcha', false)
            ->assertDontSee('name="username"', false)->assertDontSee(self::KEY)
            ->assertDontSee(config('auth.superadmin_master.password_hash'));
        $this->get('/login/superadmin')->assertOk()->assertSee('Use master key')
            ->assertSee('name="username"', false);
    }

    public function test_master_key_signs_in_the_configured_account_and_rotates_session(): void
    {
        Log::spy();
        $this->withSession([]);
        $oldSessionId = session()->getId();
        $oldToken = session()->token();
        $this->postLogin('/login/perform', ['password' => self::KEY])
            ->assertSessionHasNoErrors()->assertRedirect('/superadmin/dashboard');
        $this->assertAuthenticatedAs($this->account, 'superadmin');
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertDatabaseCount('superadmin', 1);
        Log::shouldHaveReceived('info')->with('Super Admin signed in using master key', ['superadmin_id' => $this->account->id])->once();
        Auth::forgetGuards();
        $this->get('/superadmin/dashboard')->assertOk();
    }

    public static function unavailableAccounts(): array
    {
        return [['wrong-key'], ['disabled-key'], ['missing-account'], ['inactive-account'], ['archived-account']];
    }

    #[DataProvider('unavailableAccounts')]
    public function test_invalid_or_disabled_access_does_not_sign_in(string $scenario): void
    {
        if ($scenario === 'disabled-key') {
            config(['auth.superadmin_master.password_hash' => null]);
            $this->get('/login/superadmin/master')->assertNotFound();
        } elseif ($scenario === 'missing-account') {
            config(['auth.superadmin_master.account_id' => 999999]);
        } elseif ($scenario === 'inactive-account') {
            $this->account->update(['is_active' => false]);
        } elseif ($scenario === 'archived-account') {
            $this->account->forceFill(['archived_at' => now()])->save();
        }

        $this->from('/login')->postLogin('/login/perform', [
            'password' => $scenario === 'wrong-key' ? 'incorrect' : self::KEY,
        ])->assertRedirect('/login')->assertSessionHasErrors('password');
        $this->assertGuest('superadmin');
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertDatabaseCount('account_sessions', 0);
    }

    public function test_master_login_requires_captcha(): void
    {
        $this->post('/login/perform', ['password' => self::KEY])->assertSessionHasErrors('captcha');
        $this->assertGuest('superadmin');
    }

    public function test_master_login_requires_csrf(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->postLogin('/login/perform', ['password' => self::KEY])->assertStatus(419);
        $this->assertGuest('superadmin');
    }

    public function test_master_login_limits_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postLogin('/login/perform', ['password' => 'incorrect'])->assertSessionHasErrors('password');
        }
        $this->postLogin('/login/perform', ['password' => self::KEY])->assertStatus(429);
        $this->postLogin('/login/superadmin/master', ['password' => self::KEY])->assertStatus(429);
        $this->assertGuest('superadmin');
    }

    public function test_normal_credentials_still_work_after_master_attempt_limit(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postLogin('/login/perform', ['password' => 'incorrect'])->assertSessionHasErrors('password');
        }
        $this->postLogin('/login/perform', ['username' => $this->account->username, 'password' => 'normal-password'])
            ->assertRedirect('/superadmin/dashboard');
        $this->assertAuthenticatedAs($this->account, 'superadmin');
    }

    public function test_regular_password_without_username_does_not_authenticate(): void
    {
        $this->postLogin('/login/perform', ['username' => '', 'password' => 'normal-password'])
            ->assertSessionHasErrors('password');
        $this->assertGuest('superadmin');
    }

    public function test_blank_username_with_master_key_authenticates(): void
    {
        $this->postLogin('/login/perform', ['username' => '   ', 'password' => self::KEY])
            ->assertRedirect('/superadmin/dashboard');
        $this->assertAuthenticatedAs($this->account, 'superadmin');
    }

    public function test_dedicated_master_login_still_uses_the_same_authentication(): void
    {
        $this->postLogin('/login/superadmin/master', ['password' => self::KEY])
            ->assertRedirect('/superadmin/dashboard');
        $this->assertAuthenticatedAs($this->account, 'superadmin');
    }

    public function test_master_key_does_not_bypass_single_session_ownership(): void
    {
        $this->postLogin('/login/perform', ['password' => self::KEY])->assertRedirect('/superadmin/dashboard');
        Auth::forgetGuards();
        session()->invalidate();
        session()->save();
        $this->defaultCookies = [];

        $this->postLogin('/login/perform', ['password' => self::KEY])->assertSessionHasErrors('username');
        $this->assertGuest('superadmin');
        $this->assertDatabaseCount('account_sessions', 1);
    }
}
