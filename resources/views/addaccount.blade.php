
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/addaccount.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/account-create.css') }}">

    @include('partials.password-toggle-assets')
    @include('partials.admin-sidebar-assets')
</head>

<body class="admin-workspace">

    <div class="layout">

        @include('partials.admin-sidebar')

        <main class="main-content">

            @moduleRoute('admin', 'dashboard')
                <x-back-link :fallback="route('dashboard')" label="Back to Dashboard" />
            @endmoduleRoute

            <header class="topbar">

                <h1>Create Account</h1>

                <div class="topbar-right">
                    <div id="datetime" class="datetime"></div>
                    <span class="admin-pill">Admin</span>
                </div>

            </header>

            <p class="account-help">
                Create an account here. Super admin manages passwords,
                account status and module permissions in System User.
            </p>

            @include('users.partials.create-form')

        </main>

    </div>

    <script>
        function updateDateTime() {
            const now = new Date();

            const date = now.toLocaleDateString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric'
            });

            const time = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });

            const element = document.getElementById('datetime');

            if (element) {
                element.textContent = date + ' | ' + time;
            }
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>

</body>
</html>
