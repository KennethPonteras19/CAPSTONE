<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Account | TMTA POS</title>

    
    <link rel="stylesheet"
          href="{{ asset('css/addaccount.css') }}">

   
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="{{ asset('css/account-create.css') }}">
    @include('partials.password-toggle-assets')
    @include('partials.admin-sidebar-assets')
</head>

<body class="admin-workspace">

<div class="layout">

    {{-- =====================================================
         SIDEBAR
         ===================================================== --}}

    @include('partials.admin-sidebar')


    {{-- =====================================================
         MAIN CONTENT
         ===================================================== --}}

    <main class="main-content">
        <header class="topbar">
            <h1>Create Account</h1>
            <span class="admin-pill">Admin</span>
        </header>
        <p class="account-help">Create an account here. Super admin manages passwords, account status and module permissions in System User.</p>
        @include('users.partials.create-form')
    </main>
</div>
</body>
</html>