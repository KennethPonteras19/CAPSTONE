# Google reCAPTCHA login setup

All five login forms support Google's reCAPTCHA v2 checkbox. Until enabled, the existing image CAPTCHA remains active. Once enabled, missing keys, invalid responses, unexpected hostnames, and Google connection failures block login; they do not fall back to the image CAPTCHA.

1. Register a **reCAPTCHA v2 / “I'm not a robot” Checkbox** site in the [Google reCAPTCHA console](https://www.google.com/recaptcha/admin/create). Use keys compatible with `api.js` and `siteverify`, not v3 or an Enterprise assessment integration.
2. Add the hostnames used to access the app to Google's allowed domains. For local development add `localhost`; add the deployed domain when deploying. Keep Google's domain validation enabled.
3. Set these values in your local `.env` (never commit the secret key):

   ```dotenv
   RECAPTCHA_ENABLED=true
   RECAPTCHA_SITE_KEY=your_site_key
   RECAPTCHA_SECRET_KEY=your_secret_key
   RECAPTCHA_HOSTNAMES=localhost,127.0.0.1
   ```

   Set `RECAPTCHA_HOSTNAMES` to the exact hostnames you use, separated by commas, without protocols, ports, or paths. Include each deployed hostname, such as `example.com,www.example.com`. If omitted, it defaults to the hostname in `APP_URL`.

4. Run `php artisan config:clear` and reload the login page. If deployment uses cached configuration, rebuild it with `php artisan config:cache` after updating the environment.
5. Complete the checkbox and log in. Try submitting without verification, then verify that **Remember me** and logout still work. Both the browser and PHP server need internet access. Live verification requires your registered keys; automated tests fake Google's responses.

Set `RECAPTCHA_ENABLED=false` to explicitly return to the original image CAPTCHA. This is not an automatic fallback during Google outages.

Google documents the [widget](https://developers.google.com/recaptcha/docs/display), [server verification](https://developers.google.com/recaptcha/docs/verify), and [domain validation](https://developers.google.com/recaptcha/docs/domain_validation). Tokens expire after two minutes and can only be verified once, so failed login attempts require completing a fresh check.
