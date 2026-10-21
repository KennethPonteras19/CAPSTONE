<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\SuperAdmin;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthenticationTestCase;

class RecaptchaLoginTest extends AuthenticationTestCase
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
            'services.recaptcha.hostnames' => ['localhost'],
        ]);
        Http::preventStrayRequests();
    }

    public static function endpoints(): array
    {
        return LoginCaptchaTest::endpoints();
    }

    public static function loginCases(): array
    {
        return RoleLoginTest::loginCases();
    }

    public function test_all_forms_show_google_verification_without_exposing_the_secret(): void
    {
        foreach (['/login', '/login/admin', '/login/staff', '/login/superadmin', '/login/customer'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('data-login-recaptcha', false)
                ->assertSee('data-sitekey="test-site-key"', false)
                ->assertSee('js/login-recaptcha.js')
                ->assertDontSee('test-secret-key')
                ->assertDontSee('js/login-captcha.js')
                ->assertDontSee('name="captcha_id"', false);
        }
        $this->post('/login/captcha')->assertNotFound();
        Http::assertNothingSent();
    }

    #[DataProvider('endpoints')]
    public function test_missing_token_blocks_every_endpoint_before_credentials_are_checked(string $endpoint): void
    {
        Event::fake([Attempting::class]);
        $this->postJson($endpoint, ['username' => 'admin', 'password' => 'password123'])
            ->assertUnprocessable()->assertJsonValidationErrors('captcha');
        Event::assertNotDispatched(Attempting::class);
        Http::assertNothingSent();
    }

    #[DataProvider('endpoints')]
    public function test_rejected_token_cannot_be_bypassed_with_a_local_captcha(string $endpoint): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
        Event::fake([Attempting::class]);
        $this->postJson($endpoint, $this->captchaFields() + ['g-recaptcha-response' => 'invalid-token'])
            ->assertUnprocessable()->assertJsonValidationErrors('captcha');
        Event::assertNotDispatched(Attempting::class);
        $this->assertDatabaseCount('account_sessions', 0);
    }

    #[DataProvider('loginCases')]
    public function test_valid_google_verification_allows_login_and_remember_me(string $guard, string $endpoint): void
    {
        $model = match ($guard) {
            'admin' => Admin::class,
            'superadmin' => SuperAdmin::class,
            'staff' => Staff::class,
            'customer' => Customer::class,
        };
        $account = $model::create([
            'name' => 'Google Test', 'username' => 'google.'.$guard,
            'password' => 'password123', 'is_active' => true,
        ] + ($guard === 'staff' ? ['role' => 'Staff'] : ['email' => $guard.'@example.test']));
        Http::fake([self::VERIFY_URL => Http::response(['success' => true, 'hostname' => 'localhost'])]);

        $response = $this->post($endpoint, [
            'username' => $account->username, 'password' => 'password123',
            'remember' => '1', 'g-recaptcha-response' => 'valid-token',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($account, $guard);
        Http::assertSent(fn ($request) => $request->url() === self::VERIFY_URL
            && $request->method() === 'POST'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'valid-token');

        $cookieName = Auth::guard($guard)->getRecallerName();
        $cookie = $response->getCookie($cookieName);
        Auth::forgetGuards();
        session()->invalidate();
        session()->save();
        $this->withCookie($cookieName, $cookie->getValue())->get('/login')
            ->assertRedirect($response->headers->get('Location'));
        $this->assertAuthenticatedAs($account, $guard);
        Http::assertSentCount(1);
    }

    public static function failedResponses(): array
    {
        return [
            'expired or reused' => [['success' => false, 'error-codes' => ['timeout-or-duplicate']], 200],
            'wrong hostname' => [['success' => true, 'hostname' => 'another.example'], 200],
            'missing hostname' => [['success' => true], 200],
            'non-boolean success' => [['success' => 'true', 'hostname' => 'localhost'], 200],
            'Google unavailable' => [['success' => true, 'hostname' => 'localhost'], 503],
            'invalid response' => ['not json', 200],
        ];
    }

    #[DataProvider('failedResponses')]
    public function test_unsuccessful_verification_blocks_authentication(array|string $body, int $status): void
    {
        Http::fake([self::VERIFY_URL => Http::response($body, $status)]);
        Event::fake([Attempting::class]);
        $this->postJson('/login/perform', ['g-recaptcha-response' => 'token'])
            ->assertUnprocessable()->assertJsonValidationErrors('captcha');
        Event::assertNotDispatched(Attempting::class);
    }

    public function test_connection_failure_blocks_authentication_without_a_server_error(): void
    {
        Http::fake([self::VERIFY_URL => Http::failedConnection()]);
        Event::fake([Attempting::class]);
        $this->postJson('/login/perform', ['g-recaptcha-response' => 'token'])
            ->assertUnprocessable()->assertJsonValidationErrors('captcha');
        Event::assertNotDispatched(Attempting::class);
    }

    public function test_missing_configuration_keeps_verification_required(): void
    {
        config(['services.recaptcha.secret_key' => null]);
        $this->get('/login')->assertOk()->assertSee('Verification is temporarily unavailable.');
        $this->postJson('/login/perform', ['g-recaptcha-response' => 'token'])
            ->assertUnprocessable()->assertJsonValidationErrors('captcha');
        Http::assertNothingSent();
    }

    public function test_malformed_token_is_rejected_without_contacting_google(): void
    {
        $this->postJson('/login/perform', ['g-recaptcha-response' => ['token']])
            ->assertUnprocessable()->assertJsonValidationErrors('captcha');
        Http::assertNothingSent();
    }
}
