@if (config('services.recaptcha.enabled'))
    @include('partials.login-recaptcha')
@else
<div class="input-group captcha-group" data-login-captcha data-refresh="{{ route('login.captcha.refresh') }}" hidden>
    <label for="captcha">Security code</label>
    <div class="captcha-image-row">
        <img alt="CAPTCHA verification code" width="240" height="96" data-captcha-image hidden>
        <button type="button" class="captcha-refresh" data-captcha-refresh>New code</button>
    </div>
    <input type="hidden" name="captcha_id" value="" data-captcha-id disabled>
    <div class="input-box">
        <input id="captcha" type="text" name="captcha" placeholder="Enter the {{ \App\Services\LoginCaptcha::LENGTH }} characters" required disabled
               minlength="{{ \App\Services\LoginCaptcha::LENGTH }}" maxlength="{{ \App\Services\LoginCaptcha::LENGTH }}" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="captcha-help">
    </div>
    <p id="captcha-help" class="captcha-help">Type the characters shown above. Uppercase or lowercase is accepted.</p>
    <p class="captcha-status" data-captcha-status role="status" aria-live="polite"></p>
</div>
<noscript><p class="error">Enable JavaScript to complete the CAPTCHA after pressing Login.</p></noscript>
<script src="{{ asset('js/login-captcha.js') }}" defer></script>
@endif
