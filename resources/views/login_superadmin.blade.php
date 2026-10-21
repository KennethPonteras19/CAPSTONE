@include('auth.login-page', [
    'loginTitle' => ($masterLogin ?? false) ? 'Super Admin Master Login' : 'Super Admin Login',
    'loginAction' => route(($masterLogin ?? false) ? 'login.superadmin.master.process' : 'login.superadmin.process'),
    'role' => 'superadmin',
    'masterLogin' => $masterLogin ?? false,
])
