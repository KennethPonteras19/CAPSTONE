<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SingleSessionGuard extends SessionGuard
{
    public const ALREADY_LOGGED_IN = 'This account is already logged in on another browser or device. Please log out there or wait for that session to expire.';

    public function login(Authenticatable $user, $remember = false)
    {
        if (! $this->claimSession($user)) {
            throw ValidationException::withMessages(['username' => self::ALREADY_LOGGED_IN]);
        }

        parent::login($user, $remember);
    }

    public function user()
    {
        if ($this->loggedOut || $this->user !== null) {
            return parent::user();
        }

        $user = parent::user();
        $ownership = $this->session->get($this->ownershipKey());
        $revoked = $user && $ownership && ! DB::table('account_sessions')->where($ownership)->exists();

        // Check ownership and refresh inactivity once when loading this request's user.
        // This also enrolls sessions created before single-session login was enabled.
        if ($revoked || ($user && ! $this->claimSession($user))) {
            $this->clearUserDataFromStorage();
            $this->user = null;
            $this->loggedOut = true;

            return null;
        }

        return $user;
    }

    protected function userFromRecaller($recaller)
    {
        $user = parent::userFromRecaller($recaller);

        // A remembered browser must acquire ownership before Laravel signs it in.
        // The encrypted recaller also carries this browser's ownership token,
        // allowing it to resume its own lock after the session has disappeared.
        $ownershipToken = $recaller->segments()[3] ?? null;
        if ($user && ! $this->claimSession($user, $ownershipToken)) {
            $this->viaRemember = false;
            $this->clearUserDataFromStorage();

            return null;
        }

        return $user;
    }

    protected function createRecaller($value)
    {
        return parent::createRecaller($value.'|'.$this->session->get($this->ownershipKey().'.token'));
    }

    protected function claimSession(Authenticatable $user, ?string $rememberedToken = null): bool
    {
        // Include the model: different role tables can contain the same numeric ID.
        $accountKey = hash('sha256', get_class($user).':'.$user->getAuthIdentifier());
        $current = $this->session->get($this->ownershipKey());
        $token = $rememberedToken ?: (($current['account_key'] ?? null) === $accountKey
            ? $current['token']
            : Str::random(64));

        $claimed = DB::transaction(function () use ($accountKey, $token) {
            $now = now()->timestamp;
            $expiresBefore = $now - (int) config('session.lifetime', 120) * 60;

            // The unique key and row lock serialize simultaneous login attempts.
            DB::table('account_sessions')->insertOrIgnore([
                'account_key' => $accountKey,
                'token' => $token,
                'last_activity' => $now,
            ]);

            $active = DB::table('account_sessions')
                ->where('account_key', $accountKey)->lockForUpdate()->first();

            if ($active->token !== $token && $active->last_activity > $expiresBefore) {
                return false;
            }

            DB::table('account_sessions')->where('account_key', $accountKey)->update([
                'token' => $token,
                'last_activity' => $now,
            ]);

            return true;
        }, 3);

        if ($claimed) {
            if ($current && $current['account_key'] !== $accountKey) {
                $this->releaseSession();
            }

            // A separate ownership token survives Laravel's session ID regeneration.
            $this->session->put($this->ownershipKey(), [
                'account_key' => $accountKey,
                'token' => $token,
            ]);
        }

        return $claimed;
    }

    protected function clearUserDataFromStorage()
    {
        $this->releaseSession();
        parent::clearUserDataFromStorage();
    }

    protected function releaseSession(): void
    {
        $current = $this->session->pull($this->ownershipKey());

        if ($current) {
            // A stale browser cannot release another browser's newer login.
            DB::table('account_sessions')->where($current)->delete();
        }
    }

    protected function ownershipKey(): string
    {
        return 'account_session_'.$this->name;
    }

    public function getName()
    {
        return 'login_'.$this->name.'_'.sha1(SessionGuard::class);
    }

    public function getRecallerName()
    {
        return 'remember_'.$this->name.'_'.sha1(SessionGuard::class);
    }
}
