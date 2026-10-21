document.querySelectorAll('[data-login-recaptcha]').forEach(container => {
    const form = container.closest('form');
    const submit = form.querySelector('[type="submit"]');
    const widget = container.querySelector('[data-recaptcha-widget]');
    const status = container.querySelector('[data-recaptcha-status]');
    let widgetId;
    let submitting = false;

    form.addEventListener('submit', event => {
        if (submitting || widgetId === undefined || !window.grecaptcha.getResponse(widgetId)) {
            event.preventDefault();
            if (status) status.textContent = 'Complete the “I’m not a robot” check before logging in. If it does not load, check your connection and reload this page.';
            return;
        }

        submitting = true;
        submit.disabled = true;
        submit.textContent = 'Verifying…';
    });

    if (!widget) {
        submit.disabled = true;
        return;
    }

    const loadError = () => {
        status.textContent = 'Unable to load reCAPTCHA. Check your connection and reload this page.';
    };
    const timeout = window.setTimeout(loadError, 15000);

    window.loginRecaptchaReady = () => {
        window.clearTimeout(timeout);
        try {
            widgetId = window.grecaptcha.render(widget, {
                sitekey: widget.dataset.sitekey,
                size: container.clientWidth < 304 ? 'compact' : 'normal',
                callback: () => { status.textContent = 'Verification complete. You can log in.'; },
                'expired-callback': () => { status.textContent = 'Verification expired. Complete the check again.'; },
                'error-callback': loadError,
            });
            status.textContent = 'Complete the “I’m not a robot” check to continue.';
        } catch {
            loadError();
        }
    };

    const script = document.createElement('script');
    script.src = 'https://www.google.com/recaptcha/api.js?onload=loginRecaptchaReady&render=explicit';
    script.async = true;
    script.onerror = () => {
        window.clearTimeout(timeout);
        loadError();
    };
    document.head.appendChild(script);
});
