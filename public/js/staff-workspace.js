(() => {
    const clock = document.getElementById('dateTime');
    const dateFormat = new Intl.DateTimeFormat('en-US', {timeZone: 'Asia/Manila', year: 'numeric', month: 'numeric', day: 'numeric'});
    const timeFormat = new Intl.DateTimeFormat('en-US', {timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true});
    const updateClock = () => {
        if (!clock) return;
        const now = new Date();
        clock.textContent = `${dateFormat.format(now)} | ${timeFormat.format(now)}`;
        clock.dateTime = now.toISOString();
    };
    updateClock();
    document.querySelectorAll('[data-customer-action]').forEach(link => {
        link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            link.setAttribute('aria-busy', 'true');
            // Native navigation retains the server's validation and error responses.
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-customer-action]').forEach(link => link.removeAttribute('aria-busy'));
        if (location.hash === '#customerForm') document.getElementById('customerForm')?.focus();
    });
    setInterval(updateClock, 1000);
    let dirty = false;
    document.addEventListener('input', () => { dirty = true; });
    document.addEventListener('change', () => { dirty = true; });
    document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
    document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    }));
    // Refresh read-only lists without interrupting filters, customer edits, or payments.
    if (document.querySelector('[data-auto-refresh]')) {
        setInterval(() => { if (!dirty && !document.hidden) window.location.reload(); }, 60000);
    }
})();
