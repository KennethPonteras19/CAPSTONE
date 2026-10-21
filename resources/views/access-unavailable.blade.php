<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Access | TMTA POS</title>
    <link rel="stylesheet" href="{{ asset('css/superadmin.css') }}">
</head>
<body>
    <main style="max-width: 640px; margin: 12vh auto; padding: 24px;">
        <section class="card">
            <h1>{{ ($denied ?? false) ? 'Module access restricted' : 'No modules assigned' }}</h1>
            <p>{{ ($denied ?? false) ? 'You do not have permission to access this module.' : 'Your account is signed in, but no modules are currently enabled.' }}</p>
            <p>Please contact your super admin to update your permissions.</p>
            @if(\App\Support\ModuleAccess::landingRoute($role) !== $role.'.access')
                <p><a class="btn primary" href="{{ route(\App\Support\ModuleAccess::landingRoute($role)) }}">Go to my available modules</a></p>
            @endif
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="btn warning" type="submit">Logout</button>
            </form>
        </section>
    </main>
</body>
</html>
