<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Settings | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/superadmin.css') . '?v=' . filemtime(public_path('css/superadmin.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @include('partials.admin-sidebar-assets')
</head>
<body class="superadmin-workspace admin-workspace">

@include('superadmin.partials.sidebar')

<main class="main-content">
    <header class="topbar">
        <div class="page-title">
            <h2>Settings</h2>
            <p>Platform preferences, notifications, and maintenance tools.</p>
        </div>
        <div class="topbar-right">
            <div id="datetime" class="datetime"></div>
            <div class="user-pill">Super Admin</div>
        </div>
    </header>

    <section class="ui-metrics">
        <x-metric label="Email Alerts" value="Enabled" value-id="emailState" icon="fa-gear" hint="Receive warnings and system events via email." />
        <x-metric label="Auto Backup" value="Enabled" value-id="backupState" icon="fa-gear" hint="Nightly database and logs backup protection." />
        <x-metric label="Audit Lock" value="Disabled" value-id="auditState" icon="fa-gear" hint="Freeze critical settings before audit periods." />
    </section>

    <section class="layout-two">
        <article class="card">
            <h3 class="section-title"><i class="fa-solid fa-sliders"></i> Preferences</h3>

            <div class="form-group">
                <label><input type="checkbox" id="emailToggle" checked> Enable Email Alerts</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" id="backupToggle" checked> Enable Auto Backup</label>
            </div>
            <div class="form-group">
                <label><input type="checkbox" id="auditToggle"> Enable Audit Lock</label>
            </div>

            <div class="actions">
                <button class="btn primary" id="savePrefsBtn"><i class="fa-solid fa-floppy-disk"></i> Save Preferences</button>
            </div>
            <p class="note" id="prefsNote">No changes saved yet.</p>
        </article>

        <article class="card">
            <h3 class="section-title"><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance</h3>
            <div class="actions">
                <button class="btn warning" id="clearCacheBtn"><i class="fa-solid fa-broom"></i> Clear Cache</button>
                <button class="btn success" id="backupNowBtn"><i class="fa-solid fa-database"></i> Backup Now</button>
                <button class="btn danger" id="restartSvcBtn"><i class="fa-solid fa-power-off"></i> Restart Services</button>
            </div>
            <p class="note" id="maintenanceNote">Maintenance tools are in demo mode.</p>
        </article>
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

const emailToggle = document.getElementById('emailToggle');
const backupToggle = document.getElementById('backupToggle');
const auditToggle = document.getElementById('auditToggle');

const emailState = document.getElementById('emailState');
const backupState = document.getElementById('backupState');
const auditState = document.getElementById('auditState');

function syncStates() {
    emailState.innerText = emailToggle.checked ? 'Enabled' : 'Disabled';
    backupState.innerText = backupToggle.checked ? 'Enabled' : 'Disabled';
    auditState.innerText = auditToggle.checked ? 'Enabled' : 'Disabled';
}

[emailToggle, backupToggle, auditToggle].forEach(function (el) {
    el.addEventListener('change', syncStates);
});

document.getElementById('savePrefsBtn').addEventListener('click', function () {
    syncStates();
    document.getElementById('prefsNote').innerText = 'Preferences saved successfully (demo mode).';
});

document.getElementById('clearCacheBtn').addEventListener('click', function () {
    document.getElementById('maintenanceNote').innerText = 'Cache clear triggered (demo mode).';
});

document.getElementById('backupNowBtn').addEventListener('click', function () {
    document.getElementById('maintenanceNote').innerText = 'Backup started (demo mode).';
});

document.getElementById('restartSvcBtn').addEventListener('click', function () {
    document.getElementById('maintenanceNote').innerText = 'Service restart requested (demo mode).';
});

syncStates();
</script>

</body>
</html>
