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

    <section class="ui-metrics">
        <x-metric label="System Modules" :value="$systemModules ?? 4" tone="info" icon="fa-layer-group" hint="Core superadmin modules are active." />

        <x-metric label="Active Users" :value="$activeUsersCount ?? 0" tone="success" icon="fa-users" hint="Accounts currently enabled in system access." />

        <x-metric label="Warnings Today" :value="$warningsToday ?? 0" tone="warning" icon="fa-triangle-exclamation" hint="Review recent logs for flagged events." />

        <x-metric label="Inactive Accounts" :value="$inactiveUsersCount ?? 0" tone="neutral" icon="fa-user-slash" hint="Accounts currently disabled in system." />
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
