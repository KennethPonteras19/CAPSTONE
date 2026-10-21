<section class="account-create-card">
    @if(session('success'))
        <div class="success-message" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="account-errors" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <h2>Account Information</h2>
    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="account-create-grid">
            <label for="name">Full Name
                <input id="name" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required>
            </label>
            <label for="username">Username
                <input id="username" name="username" value="{{ old('username') }}" maxlength="255" autocomplete="off" required>
            </label>
            <label for="role">Role
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    @foreach(['Admin', 'Cashier', 'Inventory Staff', 'Manager'] as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </label>
            <label for="email" id="accountEmailGroup">Email (required for Admin)
                <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email">
            </label>
            <label for="password">Password
                <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </label>
            <label for="password_confirmation">Confirm Password
                <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required>
            </label>
        </div>
        <p class="account-help">Use at least 8 characters. New accounts start active.</p>
        <button class="add-btn" type="submit"><i class="fa-solid fa-user-plus"></i> Create Account</button>
    </form>
</section>
<script>
    const accountRole = document.getElementById('role');
    const accountEmail = document.getElementById('email');
    function updateAccountEmail() {
        const needsEmail = accountRole.value === 'Admin';
        document.getElementById('accountEmailGroup').hidden = !needsEmail;
        accountEmail.disabled = !needsEmail;
        accountEmail.required = needsEmail;
    }
    accountRole.addEventListener('change', updateAccountEmail);
    updateAccountEmail();
</script>
