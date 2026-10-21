<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/superadmin.css') . '?v=' . filemtime(public_path('css/superadmin.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @include('partials.admin-sidebar-assets')
</head>
<body class="superadmin-workspace admin-workspace">

@include('superadmin.partials.sidebar')

<main class="main-content">
    <header class="topbar">
        <div class="page-title">
            <h2>Super Admin Dashboard</h2>
            <p>Control center for configuration, users, logs, and platform settings.</p>
        </div>
        <div class="topbar-right">
            <div id="datetime" class="datetime"></div>
            <div class="user-pill">{{ Auth::guard('superadmin')->user()->name ?? 'Super Admin' }}</div>
        </div>
    </header>

    <section class="cards">
        <article class="card">
            <div class="label">System Modules</div>
            <div class="value">{{ $systemModules ?? 4 }}</div>
            <div class="hint">Core superadmin modules are active.</div>
        </article>

        <article class="card">
            <div class="label">Active Users</div>
            <div class="value">{{ $activeUsersCount ?? 0 }}</div>
            <div class="hint">Accounts currently enabled in system access.</div>
        </article>

        <article class="card">
            <div class="label">Warnings Today</div>
            <div class="value">{{ $warningsToday ?? 0 }}</div>
            <div class="hint">Review recent logs for flagged events.</div>
        </article>

        <article class="card">
            <div class="label">Inactive Accounts</div>
            <div class="value">{{ $inactiveUsersCount ?? 0 }}</div>
            <div class="hint">Accounts currently disabled in system.</div>
        </article>
    </section>

    <section class="layout-two">
        <article class="card">
            <h3 class="section-title">Quick Access</h3>
            <p class="note">Use these shortcuts to manage key super admin modules.</p>
            <div class="actions">
                <a class="btn primary" href="{{ route('superadmin.system.config') }}">
                    <i class="fa-solid fa-sliders"></i> Open System Config
                </a>
                <a class="btn success" href="{{ route('superadmin.system.users') }}">
                    <i class="fa-solid fa-users-gear"></i> Manage Users
                </a>
                <a class="btn info" href="{{ route('superadmin.system.logs') }}">
                    <i class="fa-solid fa-file-lines"></i> View Logs
                </a>
                <a class="btn warning" href="{{ route('superadmin.settings') }}">
                    <i class="fa-solid fa-gear"></i> Update Settings
                </a>
            </div>
        </article>

        <article class="card">
            <h3 class="section-title">Recent System Events</h3>
            <ul class="list">
                @forelse($recentEvents ?? [] as $event)
                    @php
                        $severity = strtolower($event['severity'] ?? 'info');
                        $badgeClass = match($severity) {
                            'warning' => 'warning',
                            'error', 'danger' => 'danger',
                            'notice' => 'info',
                            default => 'success',
                        };
                    @endphp
                    <li>
                        <span>{{ $event['event'] }} <small style="opacity: 0.7;">({{ $event['timestamp'] }})</small></span>
                        <span class="badge {{ $badgeClass }}">{{ ucfirst($severity) }}</span>
                    </li>
                @empty
                    <li><span>No recent log events recorded.</span><span class="badge info">Info</span></li>
                @endforelse
            </ul>
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
</script>

</body>
</html>
