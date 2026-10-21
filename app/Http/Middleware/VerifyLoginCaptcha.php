<?php

namespace App\Http\Middleware;

use App\Services\LoginCaptcha;
use App\Services\Recaptcha;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VerifyLoginCaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST')) {
            $google = config('services.recaptcha.enabled');
            $verified = $google
                ? app(Recaptcha::class)->verify($request->input('g-recaptcha-response'))
                : app(LoginCaptcha::class)->verify(
                    $request->session(), $request->input('captcha_id'), $request->input('captcha')
                );

            if (! $verified) {
                throw ValidationException::withMessages([
                    'captcha' => $google
                        ? 'reCAPTCHA could not be verified. Complete the "I’m not a robot" check and try again.'
                        : 'The CAPTCHA is incorrect or expired. Press Login to get a new code and try again.',
                ]);
            }
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
