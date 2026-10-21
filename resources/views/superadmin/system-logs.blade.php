<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/superadmin.css') . '?v=' . filemtime(public_path('css/superadmin.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @include('partials.admin-sidebar-assets')
</head>
<body class="superadmin-workspace admin-workspace">

@include('superadmin.partials.sidebar')

<main class="main-content">
    <header class="topbar">
        <div class="page-title">
            <h2>System Logs</h2>
            <p>Track system events and filter operational activities.</p>
        </div>
        <form class="topbar-right log-filters" method="GET" action="{{ route('superadmin.system.logs') }}">
            <label class="log-search">
                <span>Search logs</span>
                <input name="search" id="searchInput" class="select-field" maxlength="255" value="{{ request('search') }}" placeholder="Date, time, user or event">
            </label>
            <label>
                <span>Remarks</span>
                <select name="severity" id="severityFilter" class="select-field">
                    <option value="all" @selected(request('severity', 'all') === 'all')>All Remarks</option>
                    <option value="info" @selected(request('severity') === 'info')>Info</option>
                    <option value="warning" @selected(request('severity') === 'warning')>Warning</option>
                    <option value="danger" @selected(request('severity') === 'danger')>Error</option>
                </select>
            </label>
            <button type="submit" class="btn primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        </form>
    </header>

    <section class="card" id="systemLogs" data-stream-url="{{ route('superadmin.system.logs.stream') }}" data-clear-url="{{ route('superadmin.system.logs.clear') }}" data-index-url="{{ route('superadmin.system.logs') }}" data-csrf-token="{{ csrf_token() }}" data-page="{{ $logs->currentPage() }}">
        <h3 class="section-title"><i class="fa-solid fa-scroll"></i> Event Stream</h3>
        <p class="note">25 records per page, newest first. Search includes all available history. Live updates run every minute on page 1; older pages stay still while you read.</p>
        <div id="logResults">
            @include('superadmin.partials.log-results')
        </div>
        <div class="actions">
            <a href="{{ route('superadmin.system.logs') }}" class="btn primary"><i class="fa-solid fa-filter-circle-xmark"></i> Clear Filter</a>
            <button id="clearAllBtn" type="button" class="btn danger"><i class="fa-solid fa-trash"></i> Delete All Records</button>
        </div>
        <p class="note" id="logsStatus" role="status"></p>
    </section>
</main>
<script src="{{ asset('js/system-logs.js') . '?v=' . filemtime(public_path('js/system-logs.js')) }}" defer></script>
</body>
</html>
