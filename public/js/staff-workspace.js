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
