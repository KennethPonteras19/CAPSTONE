document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('systemLogs');
    if (!container) return;

    const results = document.getElementById('logResults');
    const status = document.getElementById('logsStatus');
    const clearButton = document.getElementById('clearAllBtn');
    const query = new URLSearchParams(window.location.search);
    let refreshing = false;
    let clearing = false;
    // Each poll requires authentication and a database connection on shared hosting.
    const refreshInterval = 60000;
    let retryDelay = refreshInterval;

    async function refreshLogs() {
        // Preserve older pages while the administrator is reviewing them.
        if (container.dataset.page !== '1' || refreshing || clearing || document.hidden) return;
        refreshing = true;
        try {
            const response = await fetch(`${container.dataset.streamUrl}?${query}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Unable to refresh logs');
            const payload = await response.json();
            if (typeof payload.html !== 'string') throw new Error('Invalid log response');
            retryDelay = refreshInterval;
            if (!clearing) {
                // HTML is rendered by Blade, which escapes every log value.
                // Avoid replacing focused pagination controls during keyboard navigation.
                if (!results.contains(document.activeElement)) results.innerHTML = payload.html;
                status.textContent = '';
            }
        } catch (error) {
            retryDelay = Math.min(retryDelay * 2, 300000);
            if (!clearing) status.textContent = 'Live updates are unavailable. Your displayed records are preserved; retrying shortly.';
        } finally {
            refreshing = false;
        }
    }

    clearButton?.addEventListener('click', async () => {
        if (!confirm('Delete all system log records, including older pages and archived log files? This cannot be undone.')) return;
        clearing = true;
        clearButton.disabled = true;
        try {
            const response = await fetch(container.dataset.clearUrl, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': container.dataset.csrfToken,
                },
            });
            if (!response.ok) throw new Error('Unable to delete logs');
            window.location.assign(container.dataset.indexUrl);
        } catch (error) {
            status.textContent = 'Failed to delete logs. Please try again.';
            clearing = false;
            clearButton.disabled = false;
        }
    });

    async function pollLogs() {
        await refreshLogs();
        setTimeout(pollLogs, retryDelay);
    }

    setTimeout(pollLogs, refreshInterval);
});
