<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · TMTA POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/staff/staff-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/staff/staff-workspace.css') }}">
    @stack('styles')
    @include('partials.admin-sidebar-assets')
</head>
<body class="admin-workspace">
<div class="dashboard-layout">
    @include('staff.partials.sidebar')
    <main class="workspace-main">
        <header class="workspace-topbar no-print">
            <time id="dateTime" class="workspace-clock">{{ now('Asia/Manila')->format('n/j/Y | g:i:s A') }}</time>
        </header>
        <div class="workspace-heading"><div><h1>@yield('title')</h1><p>@yield('subtitle')</p></div><div class="no-print">@yield('actions')</div></div>
        @if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('js/staff-workspace.js') }}" defer></script>
@stack('scripts')
</body>
</html>
