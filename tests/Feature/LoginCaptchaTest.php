<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\LoginCaptcha;
use App\Support\CaptchaImage;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthenticationTestCase;

class LoginCaptchaTest extends AuthenticationTestCase
{
    public static function endpoints(): array
    {
        return array_map(fn ($path) => [$path], [
            '/login/perform', '/login/admin/process', '/login/staff/process',
            '/login/superadmin/process', '/login/customer/process',
        ]);
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'CAPTCHA Test', 'username' => 'captcha.admin',
            'email' => 'captcha@example.test', 'password' => 'password123', 'is_active' => true]);
    }

    #[DataProvider('endpoints')]
    public function test_every_login_endpoint_requires_captcha_before_checking_credentials(string $endpoint): void
    {
        Event::fake([Attempting::class]);
        $this->from('/login')->post($endpoint, ['username' => 'some.account', 'password' => 'password123'])
            ->assertRedirect('/login')->assertSessionHasErrors('captcha');
        Event::assertNotDispatched(Attempting::class);
        $this->assertDatabaseCount('account_sessions', 0);
    }

    #[DataProvider('endpoints')]
    public function test_wrong_answers_are_rejected_on_every_login_endpoint(string $endpoint): void
    {
        $fields = $this->captchaFields();
        $fields['captcha'] = 'WRONG!';
        $this->from('/login')->post($endpoint, $fields + ['username' => 'some.account', 'password' => 'password123'])
            ->assertSessionHasErrors('captcha');
    }

    public function test_login_forms_do_not_generate_or_show_a_challenge_before_login_is_pressed(): void
    {
        $this->mock(CaptchaImage::class)->shouldNotReceive('render');
        foreach (['/login', '/login/admin', '/login/staff', '/login/superadmin', '/login/customer'] as $path) {
            $response = $this->get($path)->assertOk();
            $response->assertDontSee('data:image/png;base64,', false)->assertSee('name="captcha_id" value="" data-captcha-id disabled', false)
                ->assertSee('name="captcha"', false)->assertSee('required disabled', false)
                ->assertSee('data-refresh="'.route('login.captcha.refresh').'" hidden', false);
            $this->assertFalse(session()->has('captcha_binding'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_requesting_a_challenge_generates_five_characters_without_authenticating(): void
    {
        Event::fake([Attempting::class]);
        $answer = '';
        $this->mock(CaptchaImage::class)->shouldReceive('render')->once()->andReturnUsing(function ($code) use (&$answer) {
            $answer = $code;

            return (new CaptchaImage)->render($code);
        });
        $response = $this->postJson('/login/captcha')->assertOk()->assertJsonStructure(['id', 'image']);
        $this->assertSame(5, strlen($answer));
        $this->assertMatchesRegularExpression('/^['.CaptchaImage::ALPHABET.']{5}$/', $answer);
        $response->assertDontSee($answer);
        Event::assertNotDispatched(Attempting::class);
        $this->assertDatabaseCount('account_sessions', 0);
        $this->assertTrue(app(LoginCaptcha::class)->verify($this->app['session.store'], $response->json('id'), $answer));
    }

    public function test_correct_answer_is_case_insensitive_and_allows_login(): void
    {
        $admin = $this->admin();
        $fields = $this->captchaFields();
        $fields['captcha'] = strtolower($fields['captcha']);
        $this->post('/login/perform', $fields + ['username' => $admin->username, 'password' => 'password123'])
            ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_expired_challenge_is_rejected(): void
    {
        $fields = $this->captchaFields();
        $this->travel(6)->minutes();
        $this->postJson('/login/perform', $fields)->assertUnprocessable()->assertJsonValidationErrors('captcha');
    }

    public function test_a_solved_challenge_cannot_be_reused_after_wrong_credentials(): void
    {
        $admin = $this->admin();
        $fields = $this->captchaFields();
        $this->post('/login/perform', $fields + ['username' => $admin->username, 'password' => 'wrong'])
            ->assertSessionHasErrors('username');
        $this->post('/login/perform', $fields + ['username' => $admin->username, 'password' => 'password123'])
            ->assertSessionHasErrors('captcha');
        $this->assertGuest('admin');
    }

    public function test_wrong_answer_consumes_the_challenge(): void
    {
        $fields = $this->captchaFields();
        $this->postJson('/login/perform', array_replace($fields, ['captcha' => 'WRONG!']))
            ->assertJsonValidationErrors('captcha');
        $this->postJson('/login/perform', $fields)->assertJsonValidationErrors('captcha');
    }

    public function test_challenges_are_bound_to_the_browser_session(): void
    {
        $fields = $this->captchaFields();
        $originalBinding = session()->get('captcha_binding');
        $this->withSession(['captcha_binding' => 'another-browser']);
        $this->postJson('/login/perform', $fields)->assertJsonValidationErrors('captcha');
        $this->withSession(['captcha_binding' => $originalBinding]);
        $this->assertTrue(app(LoginCaptcha::class)->verify($this->app['session.store'], $fields['captcha_id'], $fields['captcha']));
    }

    public function test_refresh_invalidates_only_the_replaced_challenge(): void
    {
        $firstTab = $this->captchaFields();
        $otherTab = $this->captchaFields();
        $response = $this->postJson('/login/captcha', ['captcha_id' => $firstTab['captcha_id']])
            ->assertOk()->assertJsonStructure(['id', 'image']);
        $this->assertNotSame($firstTab['captcha_id'], $response->json('id'));
        $this->assertStringStartsWith('data:image/png;base64,', $response->json('image'));
        $this->assertSame(['id', 'image'], array_keys($response->json()));
        $captcha = app(LoginCaptcha::class);
        $this->assertFalse($captcha->verify($this->app['session.store'], $firstTab['captcha_id'], $firstTab['captcha']));
        $this->assertTrue($captcha->verify($this->app['session.store'], $otherTab['captcha_id'], $otherTab['captcha']));
    }

    public function test_login_attempts_are_rate_limited_across_role_endpoints(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/login/perform', ['username' => 'captcha.test'])->assertUnprocessable();
        }
        $this->postJson('/login/staff/process', [])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_png_renderer_produces_a_valid_raster_image(): void
    {
        $png = (new CaptchaImage)->render('2AB7Y');
        $size = getimagesizefromstring($png);
        $this->assertSame(240, $size[0]);
        $this->assertSame(96, $size[1]);
        $this->assertSame('image/png', $size['mime']);
        $this->assertStringNotContainsString('2AB7Y', $png);
    }
}
