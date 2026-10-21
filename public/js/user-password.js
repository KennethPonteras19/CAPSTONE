document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('passwordModal');
    const form = document.getElementById('passwordForm');
    let opener;
    function open(button) {
        opener = button;
        form.reset();
        document.getElementById('passwordUsername').textContent = button.dataset.username;
        document.getElementById('passwordUserId').value = button.dataset.id;
        document.getElementById('passwordRoleKey').value = button.dataset.roleKey;
        modal.classList.remove('hidden');
        document.getElementById('newUserPassword').focus();
    }
    function close() {
        modal.classList.add('hidden');
        form.reset();
        opener?.focus();
    }
    document.querySelectorAll('.password-btn').forEach(button => button.addEventListener('click', () => open(button)));
    modal.querySelectorAll('.close-modal').forEach(button => button.addEventListener('click', close));
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
        if (event.key === 'Tab') {
            const controls = Array.from(modal.querySelectorAll('button, input:not([type="hidden"])')).filter(element => !element.disabled);
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    const previousInput = document.getElementById('previousModuleInput');
    if (previousInput) {
        const previous = JSON.parse(previousInput.textContent);
        if (previous.form === 'password') {
            const button = Array.from(document.querySelectorAll('.password-btn')).find(button =>
                button.dataset.roleKey === previous.role && button.dataset.id === String(previous.id));
            if (button) open(button);
        }
    }
});
