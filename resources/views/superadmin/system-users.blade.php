<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System User | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/superadmin.css') . '?v=' . filemtime(public_path('css/superadmin.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .module-options fieldset { border: 1px solid #dce3ed; border-radius: 10px; padding: 16px; margin: 12px 0; }
        .module-options legend { font-weight: 600; padding: 0 6px; }
        .module-options [hidden] { display: none !important; }
        .module-selection-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .module-count { font-size: 13px; color: #586579; }
        .module-description { display: block; margin-top: 4px; font-size: 12px; line-height: 1.5; color: #586579; }
    </style>
    @include('partials.password-toggle-assets')
    @include('partials.admin-sidebar-assets')
</head>
<body class="superadmin-workspace admin-workspace">

@include('superadmin.partials.sidebar')

<main class="main-content">
    <header class="topbar">
        <div class="page-title">
            <h2>System User</h2>
            <p>Manage access roles and monitor account status.</p>
        </div>
        <div class="topbar-right">
            <input id="userSearch" type="text" class="search" placeholder="Search user...">
            <div class="user-pill">Super Admin</div>
        </div>
    </header>

    @if(session('success'))
        <div class="alert success-alert">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert error-alert">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert error-alert">
            <ul class="error-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="layout-two">
        <article class="card">
            <div class="section-head">
                <h3 class="section-title"><i class="fa-solid fa-users"></i> User Directory</h3>
                <button class="btn primary btn-sm" id="openAddUserModal">
                    <i class="fa-solid fa-user-plus"></i> Add New Account
                </button>
            </div>

            <div class="table-wrap">
                <table class="table" id="usersTable">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Permissions</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php
                                $isActive = strtolower($user['status']) === 'active';
                                $statusClass = $isActive ? 'success' : 'warning';
                                $permissionList = $user['permissions'] ?? [];
                                $permissionText = $user['role_key'] === 'superadmin' ? 'Full system access' : (count($permissionList)
                                    ? implode(', ', array_map(fn ($key) => $moduleCatalog[$user['role_key']][$key]['label'], $permissionList))
                                    : 'No modules enabled');
                            @endphp
                            <tr>
                                <td>{{ $user['username'] }}</td>
                                <td>{{ $user['role'] }}</td>
                                <td><span class="badge {{ $statusClass }}">{{ $user['status'] }}</span></td>
                                <td class="permission-cell" title="{{ $permissionText }}">{{ $permissionText }}</td>
                                <td>{{ $user['last_login'] }}</td>
                                <td>
                                    <div class="row-actions">
                                        <!-- Toggle Status Form -->
                                        <form method="POST" action="{{ route('superadmin.system.users.toggle-status') }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="role_key" value="{{ $user['role_key'] }}">
                                            <input type="hidden" name="id" value="{{ $user['id'] }}">
                                            @if($isActive)
                                                <button class="btn warning btn-xs" type="submit">
                                                    <i class="fa-solid fa-user-slash"></i> Deactivate
                                                </button>
                                            @else
                                                <button class="btn success btn-xs" type="submit">
                                                    <i class="fa-solid fa-user-check"></i> Activate
                                                </button>
                                            @endif
                                        </form>

                                        <!-- Permissions Modal Button -->
                                        @if($user['role_key'] !== 'superadmin')
                                        <button 
                                            class="btn info btn-xs permission-btn" 
                                            type="button"
                                            data-id="{{ $user['id'] }}"
                                            data-role-key="{{ $user['role_key'] }}"
                                            data-username="{{ $user['username'] }}"
                                            data-permissions="{{ implode('|', $permissionList) }}"
                                        >
                                            <i class="fa-solid fa-key"></i> Permissions
                                        </button>
                                        @endif

                                        <!-- Archive Account Form -->
                                        <button class="btn info btn-xs password-btn" type="button"
                                                data-id="{{ $user['id'] }}" data-role-key="{{ $user['role_key'] }}"
                                                data-username="{{ $user['username'] }}">
                                            <i class="fa-solid fa-lock"></i> Edit Password
                                        </button>

                                        <form method="POST" action="{{ route('superadmin.system.users.archive') }}" class="archive-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="role_key" value="{{ $user['role_key'] }}">
                                            <input type="hidden" name="id" value="{{ $user['id'] }}">
                                            <button class="btn danger btn-xs archive-btn" type="button">
                                                <i class="fa-solid fa-box-archive"></i> Archive
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="card">
            <h3 class="section-title"><i class="fa-solid fa-chart-pie"></i> User Summary</h3>
            <ul class="list">
                <li>
                    <span>Total System Users</span>
                    <span class="badge info">{{ $users->count() }}</span>
                </li>
                <li>
                    <span>Active Accounts</span>
                    <span class="badge success">{{ $users->where('status', 'Active')->count() }}</span>
                </li>
                <li>
                    <span>Inactive Accounts</span>
                    <span class="badge warning">{{ $users->where('status', 'Inactive')->count() }}</span>
                </li>
            </ul>
            <p class="note">Includes all super admins, admins, staff, and customers in the system.</p>
        </article>
    </section>
</main>

<!-- Add User Modal -->
<div class="modal hidden" id="addUserModal">
    <div class="modal-panel">
        <div class="modal-header">
            <h3>Add New Account</h3>
            <button type="button" class="icon-btn close-modal" data-target="addUserModal">&times;</button>
        </div>
        <form method="POST" action="{{ route('superadmin.system.users.store') }}" class="modal-form">
            @csrf
            <input type="hidden" name="_form" value="create">
            <div class="form-grid">
                <div class="form-group">
                    <label>Role</label>
                    <select class="select-field" name="role" id="createRole" required>
                        <option value="">Select role</option>
                        <option value="Super Admin" @selected(old('role') === 'Super Admin')>Super Admin</option>
                        <option value="Admin" @selected(old('role') === 'Admin')>Admin</option>
                        <option value="Staff" @selected(old('role') === 'Staff')>Staff</option>
                        <option value="Customer" @selected(old('role') === 'Customer')>Customer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input class="input-field" type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input class="input-field" type="text" name="username" value="{{ old('username') }}" required>
                </div>
                <div class="form-group" id="emailGroup">
                    <label>Email</label>
                    <input class="input-field" type="email" name="email" value="{{ old('email') }}">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input class="input-field" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input class="input-field" type="password" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
            </div>

            <div class="form-group">
                <label>Module Permissions</label>
                @include('superadmin.partials.module-options')
            </div>

            <div class="modal-actions">
                <button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Create Account</button>
                <button class="btn warning close-modal" type="button" data-target="addUserModal"><i class="fa-solid fa-xmark"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Permissions Modal -->
<div class="modal hidden" id="permissionsModal">
    <div class="modal-panel">
        <div class="modal-header">
            <h3>Update Permissions: <span id="permissionsUsername"></span></h3>
            <button type="button" class="icon-btn close-modal" data-target="permissionsModal">&times;</button>
        </div>
        <form method="POST" action="{{ route('superadmin.system.users.permissions') }}" class="modal-form" id="permissionsForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="permissions">
            <input type="hidden" name="role_key" id="permissionsRoleKey">
            <input type="hidden" name="id" id="permissionsUserId">

            @include('superadmin.partials.module-options')

            <div class="modal-actions">
                <button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Permissions</button>
                <button class="btn warning close-modal" type="button" data-target="permissionsModal"><i class="fa-solid fa-xmark"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal hidden" id="passwordModal" role="dialog" aria-modal="true" aria-labelledby="passwordModalTitle">
    <div class="modal-panel">
        <div class="modal-header">
            <h3 id="passwordModalTitle">Edit Password: <span id="passwordUsername"></span></h3>
            <button type="button" class="icon-btn close-modal" data-target="passwordModal" aria-label="Close password editor">&times;</button>
        </div>
        <form method="POST" action="{{ route('superadmin.system.users.password') }}" class="modal-form" id="passwordForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="password">
            <input type="hidden" name="role_key" id="passwordRoleKey">
            <input type="hidden" name="id" id="passwordUserId">
            <p class="note">Current passwords are stored as one-way hashes and cannot be viewed. To restore access, set a new password below. The eye icon shows only the new password you type.</p>
            <div class="form-grid">
                <div class="form-group">
                    <label for="newUserPassword">New password</label>
                    <input class="input-field" id="newUserPassword" type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirmUserPassword">Confirm new password</label>
                    <input class="input-field" id="confirmUserPassword" type="password" name="password_confirmation" minlength="8" maxlength="72" required autocomplete="new-password">
                </div>
            </div>
            <p class="note">Use at least 8 characters. This account will need to sign in again after the password changes.</p>
            <div class="modal-actions">
                <button class="btn primary" type="submit">Save Password</button>
                <button class="btn warning close-modal" type="button" data-target="passwordModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Search Functionality
    const searchInput = document.getElementById('userSearch');
    const table = document.getElementById('usersTable');
    if (searchInput && table) {
        const tbody = table.querySelector('tbody');
        searchInput.addEventListener('input', function () {
            const value = this.value.toLowerCase();
            Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                const rowText = row.innerText.toLowerCase();
                row.style.display = rowText.includes(value) ? '' : 'none';
            });
        });
    }

    // Modal Control Handlers
    const addUserModal = document.getElementById('addUserModal');
    const permissionsModal = document.getElementById('permissionsModal');
    const openAddUserModal = document.getElementById('openAddUserModal');
    const closeModalButtons = document.querySelectorAll('.close-modal');
    const createRole = document.getElementById('createRole');
    const emailGroup = document.getElementById('emailGroup');

    if (openAddUserModal && addUserModal) {
        openAddUserModal.addEventListener('click', () => {
            addUserModal.classList.remove('hidden');
        });
    }

    closeModalButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.target;
            const modal = document.getElementById(target);
            if (modal) modal.classList.add('hidden');
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target.classList.contains('modal')) {
            event.target.classList.add('hidden');
        }
    });

    if (createRole && emailGroup) {
        function toggleEmailField() {
            const selectedRole = createRole.value;
            const hideEmail = selectedRole === 'Staff';
            emailGroup.style.display = hideEmail ? 'none' : '';
            const emailInput = emailGroup.querySelector('input[name="email"]');
            if (emailInput) emailInput.required = !hideEmail;
        }
        createRole.addEventListener('change', toggleEmailField);
        toggleEmailField();
    }

    // Archive Button Confirmation Handler
    const archiveButtons = document.querySelectorAll('.archive-btn');
    archiveButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (confirm('Archive this account? Sign-in access will be disabled. You can restore it from the Archive page.')) {
                this.closest('form').submit();
            }
        });
    });
});
</script>

@if($errors->any())
    @php($previousModuleInput = ['form' => old('_form'), 'role' => old('role_key'), 'id' => old('id'), 'permissions' => old('permissions', [])])
    <script id="previousModuleInput" type="application/json">@json($previousModuleInput)</script>
@endif
<script src="{{ asset('js/module-permissions.js') }}" defer></script>
<script src="{{ asset('js/user-password.js') }}" defer></script>
</body>
</html>
