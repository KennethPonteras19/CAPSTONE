<div class="input-group" data-login-recaptcha>
    @if (config('services.recaptcha.site_key') && config('services.recaptcha.secret_key'))
        <div data-recaptcha-widget data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
        <p class="captcha-status" data-recaptcha-status role="status" aria-live="polite">Loading verification…</p>
    @else
        <p class="error">Verification is temporarily unavailable. Please try again later.</p>
    @endif
</div>
<noscript><p class="error">Enable JavaScript to complete reCAPTCHA and log in.</p></noscript>
<script src="{{ asset('js/login-recaptcha.js') }}" defer></script>
