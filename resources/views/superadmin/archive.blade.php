<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive | TMTA POS</title>
    <link rel="stylesheet" href="{{ asset('css/superadmin.css') . '?v=' . filemtime(public_path('css/superadmin.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @include('partials.admin-sidebar-assets')
</head>
<body class="superadmin-workspace admin-workspace">
@include('superadmin.partials.sidebar')

<main class="main-content">
    <header class="topbar">
        <div class="page-title"><h2>Archive</h2><p>Archived accounts retain their records and cannot sign in.</p></div>
        <div class="user-pill">Super Admin</div>
    </header>
    @if(session('success'))<div class="alert success-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error-alert">{{ $errors->first() }}</div>@endif
    <section class="card">
        <div class="section-head">
            <h3 class="section-title">Archived accounts ({{ $users->total() }})</h3>
            <form method="GET" action="{{ route('superadmin.archive') }}">
                <input class="search" type="search" name="search" value="{{ request('search') }}" maxlength="255" placeholder="Name or username" aria-label="Search archived accounts">
                <button class="btn primary btn-sm">Search</button>
                <a class="btn btn-sm" href="{{ route('superadmin.archive') }}">Clear</a>
            </form>
        </div>
        <p>Restored accounts return to System User as inactive. Activate them there to allow sign-in again.</p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Archived on</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user['username'] }}</td><td>{{ $user['name'] }}</td><td>{{ $user['role'] }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($user['archived_at'])->format('M d, Y h:i A') }}</td>
                        <td>
                            <form method="POST" action="{{ route('superadmin.archive.restore') }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="role_key" value="{{ $user['role_key'] }}">
                                <input type="hidden" name="id" value="{{ $user['id'] }}">
                                <button class="btn success btn-xs"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No archived accounts found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <nav class="row-actions" aria-label="Archive pages">
                @if($users->previousPageUrl())<a class="btn btn-sm" href="{{ $users->previousPageUrl() }}">Previous</a>@endif
                <span>Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>
                @if($users->nextPageUrl())<a class="btn btn-sm" href="{{ $users->nextPageUrl() }}">Next</a>@endif
            </nav>
        @endif
    </section>
</main>
</body>
</html>
