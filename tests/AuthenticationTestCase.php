<?php

namespace Tests;

use App\Services\LoginCaptcha;
use App\Support\CaptchaImage;

abstract class AuthenticationTestCase extends TestCase
{
    protected function captchaFields(): array
    {
        $this->withSession([]);
        $answer = null;
        $this->mock(CaptchaImage::class)
            ->shouldReceive('render')->andReturnUsing(function (string $code) use (&$answer) {
                $answer = $code;

                return (new CaptchaImage)->render($code);
            });
        $challenge = app(LoginCaptcha::class)->issue($this->app['session.store']);

        return ['captcha_id' => $challenge['id'], 'captcha' => $answer];
    }

    protected function postLogin(string $uri, array $data = [], array $headers = [])
    {
        return $this->post($uri, array_merge($data, $this->captchaFields()), $headers);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Keep existing authentication tests independent of local Google keys.
        config(['services.recaptcha.enabled' => false]);

        // The existing purchase-order-item migrations alter a table they never
        // create. Authentication tests do not need those unrelated tables.
        // TestCase enforces a fresh SQLite :memory: database for each application.
        foreach (glob(database_path('migrations/*.php')) as $path) {
            if (! str_contains(basename($path), 'purchase_order_items')) {
                (require $path)->up();
            }
        }
    }
}
