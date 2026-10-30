@php
    $masterLogin = $masterLogin ?? false;
    $role = $role ?? null;
    $allowMaster = !$role && config('auth.superadmin_master.password_hash') && config('auth.superadmin_master.account_id');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#080f24">
    <title>{{ $loginTitle }} - TMTA POS</title>
    @include('partials.password-toggle-assets')
    <link rel="stylesheet" href="{{ asset('css/login-animation.css') }}?v={{ filemtime(public_path('css/login-animation.css')) }}">
    <script src="{{ asset('js/login-animation.js') }}?v={{ filemtime(public_path('js/login-animation.js')) }}" defer></script>
</head>
<body class="tmta-login">
    <div class="login-intro" data-login-intro data-skip="{{ $errors->any() || old('username') || $masterLogin ? 'true' : 'false' }}" hidden>
        <div class="login-intro-media" aria-hidden="true">
            <video data-intro-video data-src="{{ asset('videos/tmta-login-animation.mp4') }}" muted playsinline preload="none" tabindex="-1"></video>
        </div>
        <button class="skip-intro" type="button" data-skip-intro>Skip intro <span aria-hidden="true">→</span></button>
    </div>

    <main class="login-card" data-login-content aria-labelledby="login-heading">
        <section class="login-form-panel">
            <h1 id="login-heading">{{ $masterLogin ? 'Master sign in' : 'Sign in' }}</h1>
            <div class="heading-accent" aria-hidden="true"></div>
            @if($role)<p class="role-caption">{{ $masterLogin ? 'Super Admin Master Login' : $loginTitle }}</p>@endif

            <form action="{{ $loginAction }}" method="POST" class="login-form">
                @csrf
                @unless($masterLogin)
                <div class="input-group">
                    <label class="sr-only" for="username">Username</label>
                    <div class="input-box">
                        <input id="username" type="text" name="username" value="{{ old('username') }}" placeholder="Username" autocomplete="username" autocapitalize="none" spellcheck="false" @required(!$allowMaster)>
                        <svg class="user-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                    </div>
                </div>
                @endunless
                <div class="input-group">
                    <label class="sr-only" for="password">{{ $masterLogin ? 'Master key' : 'Password' }}</label>
                    <div class="input-box">
                        <input id="password" type="password" name="password" placeholder="{{ $masterLogin ? 'Master key' : 'Password' }}" autocomplete="current-password" required>
                    </div>
                </div>

                @include('partials.login-captcha')

                <div class="login-options">
                    @unless($masterLogin)<label class="remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in</label>@endunless
                    <details class="password-help"><summary>Forgot password?</summary><p>Contact your administrator to reset your password.</p></details>
                </div>
                @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
                <button type="submit" class="login-btn">Sign in</button>
            </form>

            @if($role)
            <nav class="roles" aria-label="Sign in role">
                @foreach(['admin' => 'Admin', 'superadmin' => 'Super Admin', 'staff' => 'Staff', 'customer' => 'Customer'] as $key => $label)
                <a href="{{ route('login.'.$key) }}" @if($role === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            @endif
            @if($role === 'superadmin')
                @if($masterLogin)<a class="master-link" href="{{ route('login.superadmin') }}">Use username and password</a>
                @elseif(config('auth.superadmin_master.password_hash') && config('auth.superadmin_master.account_id'))<a class="master-link" href="{{ route('login.superadmin.master') }}">Use master key</a>@endif
            @endif
        </section>

        <aside class="login-brand" aria-label="TMTA Hardware">
            <div class="brand-art" aria-hidden="true"><img src="{{ asset('images/logo.png') }}" alt=""></div>
            <div class="brand-copy">
                <img class="brand-logo" src="{{ asset('images/logo.png') }}" alt="TMTA Hardware logo">
                <p class="brand-eyebrow">TMTA HARDWARE</p>
                <h2>Welcome<br><em>back.</em></h2>
                <p class="brand-description">Your orders, quotes and supplies are exactly where you left them.</p>
            </div>
        </aside>
    </main>
</body>
</html>
