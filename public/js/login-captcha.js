document.querySelectorAll('[data-login-captcha]').forEach(container => {
    const button = container.querySelector('[data-captcha-refresh]');
    const status = container.querySelector('[data-captcha-status]');
    const form = container.closest('form');
    const submit = form.querySelector('[type="submit"]');
    const input = container.querySelector('[name="captcha"]');
    const id = container.querySelector('[data-captcha-id]');
    const image = container.querySelector('[data-captcha-image]');
    let loading = false;
    let ready = false;
    let submitting = false;

    async function loadChallenge() {
        if (loading) return;
        loading = true;
        ready = false;
        container.hidden = false;
        const previousId = id.value;
        id.value = '';
        id.disabled = true;
        input.value = '';
        input.disabled = true;
        image.hidden = true;
        button.disabled = true;
        submit.disabled = true;
        submit.textContent = 'Loading CAPTCHA…';
        status.textContent = 'Loading a new code…';
        try {
            const response = await fetch(container.dataset.refresh, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                },
                body: JSON.stringify({captcha_id: previousId}),
            });
            if (!response.ok) {
                throw new Error(response.status === 429 ? 'Too many requests. Wait a minute before trying again.' : 'Unable to load a new code. Please reload the login page.');
            }
            const challenge = await response.json();
            if (typeof challenge.id !== 'string' || typeof challenge.image !== 'string' || !challenge.image.startsWith('data:image/png;base64,')) {
                throw new Error('Unable to load a new code. Please try again.');
            }
            image.src = challenge.image;
            image.hidden = false;
            id.value = challenge.id;
            id.disabled = false;
            input.disabled = false;
            ready = true;
            input.focus();
            status.textContent = 'Enter the code, then press Verify & log in.';
        } catch (error) {
            status.textContent = error.message;
        } finally {
            loading = false;
            button.disabled = false;
            submit.disabled = false;
            submit.textContent = ready ? 'Verify & log in' : 'Retry CAPTCHA';
        }
    }

    button.addEventListener('click', loadChallenge);
    form.addEventListener('submit', event => {
        if (submitting || loading) {
            event.preventDefault();
            return;
        }
        if (!ready) {
            event.preventDefault();
            loadChallenge();
            return;
        }
        // The second submit uses the regular login endpoint and server verification.
        submitting = true;
        submit.disabled = true;
        button.disabled = true;
        submit.textContent = 'Verifying…';
    });
});
