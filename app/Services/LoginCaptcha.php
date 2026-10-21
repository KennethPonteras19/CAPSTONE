<?php

namespace App\Services;

use App\Support\CaptchaImage;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LoginCaptcha
{
    public const LENGTH = 5;

    public function issue(Session $session): array
    {
        if (! $session->has('captcha_binding')) {
            $session->put('captcha_binding', Str::random(64));
        }

        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= CaptchaImage::ALPHABET[random_int(0, strlen(CaptchaImage::ALPHABET) - 1)];
        }
        $id = (string) Str::uuid();
        $binding = $session->get('captcha_binding');
        Cache::put('login_captcha:'.$id, [
            'binding' => hash('sha256', $binding),
            'answer' => hash_hmac('sha256', $code, $binding),
        ], now()->addMinutes(5));

        return ['id' => $id, 'image' => 'data:image/png;base64,'.base64_encode(app(CaptchaImage::class)->render($code))];
    }

    public function verify(Session $session, mixed $id, mixed $answer): bool
    {
        if (! is_string($id) || ! Str::isUuid($id) || ! $session->has('captcha_binding')) {
            return false;
        }

        // A shared cache lock prevents concurrent requests from reusing a code,
        // including when the application uses file-based sessions.
        return (bool) Cache::lock('login_captcha_lock:'.$id, 5)->get(function () use ($session, $id, $answer) {
            $key = 'login_captcha:'.$id;
            $challenge = Cache::get($key);
            $binding = $session->get('captcha_binding');
            if (! $challenge || ! hash_equals($challenge['binding'], hash('sha256', $binding))) {
                return false;
            }

            // Every attempt consumes the challenge, including incorrect answers.
            Cache::forget($key);

            return is_string($answer) && strlen($answer) <= 32
                && hash_equals($challenge['answer'], hash_hmac('sha256', strtoupper(trim($answer)), $binding));
        });
    }
}
