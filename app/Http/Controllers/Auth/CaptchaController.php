<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginCaptcha;
use Illuminate\Http\Request;

class CaptchaController extends Controller
{
    public function refresh(Request $request, LoginCaptcha $captcha)
    {
        abort_if(config('services.recaptcha.enabled'), 404);

        // Refresh invalidates this tab's previous challenge, without affecting
        // other open login forms belonging to the same browser.
        $captcha->verify($request->session(), $request->input('captcha_id'), null);

        return response()->json($captcha->issue($request->session()))
            ->header('Cache-Control', 'no-store, private');
    }
}
