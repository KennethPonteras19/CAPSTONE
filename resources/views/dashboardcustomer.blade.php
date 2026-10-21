<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link rel="stylesheet" href="{{ asset('css/superadmin.css') }}">
</head>
<body>
    <main style="max-width: 720px; margin: 10vh auto; padding: 24px;">
        <section class="card">
            <h1>Customer Dashboard</h1>
            <p>Welcome, {{ auth('customer')->user()->name }}.</p>
            <h2>Available modules</h2>
            <ul>
                @foreach(\App\Support\ModuleAccess::catalog('customer') as $module => $definition)
                    @module('customer', $module)
                        <li><a href="{{ route($definition['route']) }}">{{ $definition['label'] }}</a></li>
                    @endmodule
                @endforeach
            </ul>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="btn warning" type="submit">Logout</button>
            </form>
        </section>
    </main>
</body>
</html>
