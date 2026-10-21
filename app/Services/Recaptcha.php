<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Recaptcha
{
    public function verify(mixed $token): bool
    {
        if (! is_string($token) || trim($token) === '' || strlen($token) > 10000
            || ! config('services.recaptcha.site_key') || ! config('services.recaptcha.secret_key')) {
            return false;
        }

        try {
            // Google tokens are single-use; do not retry a verification request.
            $response = Http::asForm()->connectTimeout(3)->timeout(8)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                ]);
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful()
            && $response->json('success') === true
            && in_array($response->json('hostname'), config('services.recaptcha.hostnames', []), true);
    }
}
