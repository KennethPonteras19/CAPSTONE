document.querySelectorAll('input[type="password"]').forEach((input, index) => {
    if (input.closest('.password-control')) return;
    if (!input.id) input.id = `password-input-${index}`;
    const wrapper = document.createElement('div');
    wrapper.className = 'password-control';
    input.before(wrapper);
    wrapper.append(input);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-controls', input.id);
    button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path data-password-slash d="m3 3 18 18" hidden/></svg>';
    const label = input.name.endsWith('_confirmation') ? 'password confirmation' : 'password';
    function setVisible(visible) {
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${label}`);
        button.setAttribute('aria-pressed', String(visible));
        button.title = button.getAttribute('aria-label');
        button.querySelector('[data-password-slash]').toggleAttribute('hidden', !visible);
    }
    setVisible(false);
    button.addEventListener('click', () => setVisible(input.type === 'password'));
    input.form?.addEventListener('reset', () => setVisible(false));
    input.form?.addEventListener('submit', () => setVisible(false));
    wrapper.append(button);
});
