<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Config | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/superadmin.css') . '?v=' . filemtime(public_path('css/superadmin.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @include('partials.admin-sidebar-assets')
</head>
<body class="superadmin-workspace admin-workspace">

@include('superadmin.partials.sidebar')

<main class="main-content">
    <header class="topbar">
        <div class="page-title">
            <h2>System Config</h2>
            <p>Manage core module behavior and system-wide controls.</p>
        </div>
        <div class="topbar-right">
            <div id="datetime" class="datetime"></div>
            <div class="user-pill">Super Admin</div>
        </div>
    </header>

    <section class="card">
        <h3 class="section-title"><i class="fa-solid fa-sliders"></i> Configuration Controls</h3>

        <form id="configForm">
            <div class="form-grid">
                <div class="form-group">
                    <label>System Name</label>
                    <input type="text" class="input-field" id="systemName" value="TMTA POS">
                </div>

                <div class="form-group">
                    <label>Timezone</label>
                    <select id="timezone" class="select-field">
                        <option value="Asia/Manila" selected>Asia/Manila</option>
                        <option value="UTC">UTC</option>
                        <option value="Asia/Singapore">Asia/Singapore</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Maintenance Window</label>
                    <input type="text" class="input-field" id="maintenanceWindow" value="Sundays 01:00-02:00">
                </div>

                <div class="form-group">
                    <label>Session Timeout (minutes)</label>
                    <input type="number" class="input-field" id="sessionTimeout" value="30" min="5">
                </div>
            </div>

            <div class="form-group">
                <label>Security Notice</label>
                <textarea id="securityNotice" class="textarea-field">Enable 2FA and enforce role-based permissions for all modules.</textarea>
            </div>

            <div class="actions">
                <button type="submit" class="btn primary"><i class="fa-solid fa-floppy-disk"></i> Save Config</button>
                <button type="button" class="btn warning" id="resetBtn"><i class="fa-solid fa-rotate-left"></i> Reset</button>
            </div>
            <p class="note" id="statusMsg">No pending changes.</p>
        </form>
    </section>
</main>

<script>
function updateDateTime() {
    const now = new Date();
    document.getElementById('datetime').innerText =
        now.toLocaleDateString() + ' | ' + now.toLocaleTimeString();
}
setInterval(updateDateTime, 1000);
updateDateTime();

const defaults = {
    systemName: 'TMTA POS',
    timezone: 'Asia/Manila',
    maintenanceWindow: 'Sundays 01:00-02:00',
    sessionTimeout: '30',
    securityNotice: 'Enable 2FA and enforce role-based permissions for all modules.'
};

const statusMsg = document.getElementById('statusMsg');

document.getElementById('configForm').addEventListener('submit', function (e) {
    e.preventDefault();
    statusMsg.innerText = 'Configuration saved successfully (demo mode).';
});

document.getElementById('resetBtn').addEventListener('click', function () {
    document.getElementById('systemName').value = defaults.systemName;
    document.getElementById('timezone').value = defaults.timezone;
    document.getElementById('maintenanceWindow').value = defaults.maintenanceWindow;
    document.getElementById('sessionTimeout').value = defaults.sessionTimeout;
    document.getElementById('securityNotice').value = defaults.securityNotice;
    statusMsg.innerText = 'Configuration reset to defaults.';
});
</script>

</body>
</html>
