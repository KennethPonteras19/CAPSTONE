```blade
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Supplier Management | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @include('partials.admin-sidebar-assets')

    <style>
        /* PAGE */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header h2 {
            margin: 0;
        }

        .page-header p {
            margin-top: 5px;
            color: #777;
        }

        /* BUTTONS */
        .add-btn {
            background: #218838;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
        }

        .add-btn:hover,
        .save-btn:hover {
            background: #1b7030;
        }

        /* SEARCH */
        .search-container {
            margin-bottom: 20px;
        }

        .search-container form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-container input {
            width: 350px;
            max-width: 100%;
            padding: 11px 14px;
            border: 1px solid #ccc;
            border-radius: 7px;
            box-sizing: border-box;
        }

        .search-btn {
            padding: 10px 18px;
            background: #333;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        .search-btn:hover {
            background: #222;
        }

        /* TABLE */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow-x: auto;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container th {
            background: #f5f5f5;
            padding: 15px;
            text-align: left;
            white-space: nowrap;
        }

        .table-container td {
            padding: 14px;
            border-top: 1px solid #eee;
            vertical-align: middle;
        }

        /* STATUS */
        .status-active,
        .status-inactive {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 13px;
            white-space: nowrap;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        /* ACTIONS */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .action-buttons button {
            border: none;
            padding: 7px 10px;
            border-radius: 6px;
            cursor: pointer;
            color: white;
            white-space: nowrap;
        }

        .edit-btn {
            background: #007bff;
        }

        .edit-btn:hover {
            background: #0069d9;
        }

        .toggle-btn {
            background: #f39c12;
        }

        .toggle-btn:hover {
            background: #d98200;
        }

        .delete-btn {
            background: #dc3545;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        /* MODALS */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            width: 750px;
            max-width: 100%;
            padding: 30px;
            border-radius: 14px;
            box-sizing: border-box;
            margin: auto;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }

        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 24px;
            font-size: 23px;
        }

        /* FORM */
        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            font-size: 16px;
        }

        .form-group input,
        .form-group textarea {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 13px 14px;
            border: 1px solid #ccc;
            border-radius: 9px;
            font-size: 15px;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 90px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.10);
        }

        /* VALIDATION */
        .validation-message {
            display: block;
            margin-top: 5px;
            color: #dc3545;
            font-size: 12px;
            line-height: 1.4;
        }

        .validation-message:empty {
            display: none;
        }

        .form-group input.input-error,
        .form-group textarea.input-error {
            border-color: #dc3545;
            background-color: #fff8f8;
        }

        .form-group input.input-error:focus,
        .form-group textarea.input-error:focus {
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.10);
        }

        .form-group input.input-valid,
        .form-group textarea.input-valid {
            border-color: #198754;
        }

        /* MODAL BUTTONS */
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .save-btn,
        .cancel-btn {
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        .save-btn {
            background: #218838;
        }

        .cancel-btn {
            background: #777;
        }

        .cancel-btn:hover {
            background: #666;
        }

        /* ALERTS */
        .alert-success,
        .alert-error {
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-error ul {
            margin: 0;
            padding-left: 20px;
        }

        @media (max-width: 600px) {
            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .modal {
                padding: 10px;
            }

            .modal-content {
                padding: 20px;
            }

            .search-container input {
                width: 100%;
            }
        }
    </style>
</head>

<body class="admin-workspace">

    @include('partials.admin-sidebar')

    <div class="main">

        <!-- TOPBAR -->
        <div class="topbar">
            <div></div>

            <div class="right-section">
                <div id="datetime" class="datetime"></div>
                <div class="user">Admin</div>
            </div>
        </div>

        <!-- SUCCESS MESSAGE -->
        @if(session('success'))
            <div class="alert-success">
                {{ session('success') }}
            </div>
        @endif

        <!-- SERVER VALIDATION ERRORS -->
        @if($errors->any())
            <div class="alert-error" role="alert">
                <strong>Please correct the following errors:</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h2>Supplier Management</h2>
                <p>Manage your suppliers and supplier information.</p>
            </div>

            <button type="button" class="add-btn" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i>
                Add Supplier
            </button>
        </div>

        <!-- SEARCH -->
        <div class="search-container">
            <form method="GET" action="{{ route('suppliers') }}">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search supplier..."
                    aria-label="Search supplier"
                >

                <button type="submit" class="search-btn">
                    <i class="fa-solid fa-search"></i>
                    Search
                </button>
            </form>
        </div>

        <!-- SUPPLIER TABLE -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>
                                <strong>{{ $supplier->name }}</strong>
                            </td>

                            <td>{{ $supplier->contact_person ?: '-' }}</td>
                            <td>{{ $supplier->phone ?: '-' }}</td>
                            <td>{{ $supplier->email ?: '-' }}</td>
                            <td>{{ $supplier->address ?: '-' }}</td>

                            <td>
                                @if($supplier->is_active)
                                    <span class="status-active">Active</span>
                                @else
                                    <span class="status-inactive">Inactive</span>
                                @endif
                            </td>

                            <td>
                                <div class="action-buttons">

                                    <!-- EDIT -->
                                    <button
                                        type="button"
                                        class="edit-btn"
                                        onclick='openEditModal(@json($supplier))'
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                        Edit
                                    </button>

                                    <!-- ACTIVATE / DEACTIVATE -->
                                    <form
                                        action="{{ route('suppliers.toggle-status', $supplier->id) }}"
                                        method="POST"
                                        onsubmit="return confirmStatusChange(this);"
                                        data-active="{{ $supplier->is_active ? '1' : '0' }}"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit" class="toggle-btn">
                                            @if($supplier->is_active)
                                                <i class="fa-solid fa-ban"></i>
                                                Deactivate
                                            @else
                                                <i class="fa-solid fa-check"></i>
                                                Activate
                                            @endif
                                        </button>
                                    </form>

                                    <!-- DELETE -->
                                    <form
                                        action="{{ route('suppliers.delete', $supplier->id) }}"
                                        method="POST"
                                        onsubmit="return confirmDeleteSupplier();"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="delete-btn">
                                            <i class="fa-solid fa-trash"></i>
                                            Delete
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px;">
                                No suppliers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ADD SUPPLIER MODAL -->
    <div id="addSupplierModal" class="modal">
        <div class="modal-content" role="dialog" aria-modal="true"
             aria-labelledby="addSupplierTitle">

            <h3 id="addSupplierTitle">
                <i class="fa-solid fa-truck"></i>
                Add Supplier
            </h3>

            <form
                id="addSupplierForm"
                action="{{ route('suppliers.store') }}"
                method="POST"
                novalidate
            >
                @csrf

                <div class="form-group">
                    <label for="name">Supplier Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter supplier name"
                        minlength="2"
                        maxlength="100"
                        autocomplete="organization"
                        required
                    >
                    <small class="validation-message" id="name_error"></small>
                </div>

                <div class="form-group">
                    <label for="contact_person">Contact Person</label>
                    <input
                        type="text"
                        id="contact_person"
                        name="contact_person"
                        placeholder="Enter contact person"
                        minlength="2"
                        maxlength="100"
                        autocomplete="name"
                        required
                    >
                    <small class="validation-message"
                           id="contact_person_error"></small>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                        maxlength="13"
                        autocomplete="tel"
                        required
                    >
                    <small class="validation-message" id="phone_error"></small>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter email address"
                        maxlength="254"
                        autocomplete="email"
                        required
                    >
                    <small class="validation-message" id="email_error"></small>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea
                        id="address"
                        name="address"
                        rows="3"
                        placeholder="Enter supplier address"
                        minlength="10"
                        maxlength="255"
                        autocomplete="street-address"
                        required
                    ></textarea>
                    <small class="validation-message" id="address_error"></small>
                </div>

                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-btn"
                        onclick="closeAddModal()"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="save-btn">
                        <i class="fa-solid fa-save"></i>
                        Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT SUPPLIER MODAL -->
    <div id="editSupplierModal" class="modal">
        <div class="modal-content" role="dialog" aria-modal="true"
             aria-labelledby="editSupplierTitle">

            <h3 id="editSupplierTitle">
                <i class="fa-solid fa-pen"></i>
                Edit Supplier
            </h3>

            <form id="editSupplierForm" method="POST" novalidate>
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="edit_name">Supplier Name</label>
                    <input
                        type="text"
                        name="name"
                        id="edit_name"
                        placeholder="Enter supplier name"
                        minlength="2"
                        maxlength="100"
                        required
                    >
                    <small class="validation-message"
                           id="edit_name_error"></small>
                </div>

                <div class="form-group">
                    <label for="edit_contact_person">Contact Person</label>
                    <input
                        type="text"
                        name="contact_person"
                        id="edit_contact_person"
                        placeholder="Enter contact person"
                        minlength="2"
                        maxlength="100"
                        required
                    >
                    <small class="validation-message"
                           id="edit_contact_person_error"></small>
                </div>

                <div class="form-group">
                    <label for="edit_phone">Phone</label>
                    <input
                        type="tel"
                        name="phone"
                        id="edit_phone"
                        placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                        maxlength="13"
                        required
                    >
                    <small class="validation-message"
                           id="edit_phone_error"></small>
                </div>

                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="edit_email"
                        placeholder="Enter email address"
                        maxlength="254"
                        required
                    >
                    <small class="validation-message"
                           id="edit_email_error"></small>
                </div>

                <div class="form-group">
                    <label for="edit_address">Address</label>
                    <textarea
                        name="address"
                        id="edit_address"
                        rows="3"
                        placeholder="Enter supplier address"
                        minlength="10"
                        maxlength="255"
                        required
                    ></textarea>
                    <small class="validation-message"
                           id="edit_address_error"></small>
                </div>

                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-btn"
                        onclick="closeEditModal()"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="save-btn">
                        <i class="fa-solid fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        /* =========================
           MODAL FUNCTIONS
        ========================= */

        function openAddModal() {
            document.getElementById('addSupplierModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addSupplierModal').style.display = 'none';
        }

        function openEditModal(supplier) {
            document.getElementById('edit_name').value =
                supplier.name ?? '';

            document.getElementById('edit_contact_person').value =
                supplier.contact_person ?? '';

            document.getElementById('edit_phone').value =
                supplier.phone ?? '';

            document.getElementById('edit_email').value =
                supplier.email ?? '';

            document.getElementById('edit_address').value =
                supplier.address ?? '';

            document.getElementById('editSupplierForm').action =
                "{{ url('/suppliers') }}/" + supplier.id;

            clearValidation('editSupplierForm');

            document.getElementById('editSupplierModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editSupplierModal').style.display = 'none';
        }

        /* =========================
           VALIDATION HELPERS
        ========================= */

        function clearValidation(formId) {
            const form = document.getElementById(formId);

            form.querySelectorAll('.validation-message').forEach(function (el) {
                el.textContent = '';
            });

            form.querySelectorAll('input, textarea').forEach(function (el) {
                el.classList.remove('input-error', 'input-valid');
                el.removeAttribute('aria-invalid');
            });
        }

        function getSupplierFieldError(field, value) {
            if (!value) {
                return 'This field is required.';
            }

            switch (field) {
                case 'name':
                    if (value.length < 2) {
                        return 'Supplier name must be at least 2 characters.';
                    }

                    if (value.length > 100) {
                        return 'Supplier name cannot exceed 100 characters.';
                    }

                    if (!/^[A-Za-z0-9][A-Za-z0-9 .&'()\-]*$/.test(value)) {
                        return 'Use letters, numbers, spaces, and common punctuation only.';
                    }

                    return '';

                case 'contact_person':
                    if (value.length < 2) {
                        return 'Contact person must be at least 2 characters.';
                    }

                    if (value.length > 100) {
                        return 'Contact person cannot exceed 100 characters.';
                    }

                    if (!/^[A-Za-z][A-Za-z .'-]*$/.test(value)) {
                        return 'Enter a valid name using letters, spaces, hyphens, or apostrophes.';
                    }

                    return '';

                case 'phone':
                    if (!/^(09\d{9}|\+639\d{9})$/.test(value)) {
                        return 'Enter a valid PH mobile number: 09XXXXXXXXX or +639XXXXXXXXX.';
                    }

                    return '';

                case 'email':
                    if (value.length > 254) {
                        return 'Email address cannot exceed 254 characters.';
                    }

                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
                        return 'Enter a valid email address, e.g. supplier@example.com.';
                    }

                    return '';

                case 'address':
                    if (value.length < 10) {
                        return 'Address must be at least 10 characters.';
                    }

                    if (value.length > 255) {
                        return 'Address cannot exceed 255 characters.';
                    }

                    if (!/[A-Za-z0-9]/.test(value)) {
                        return 'Enter a valid supplier address.';
                    }

                    return '';

                default:
                    return '';
            }
        }

        /* =========================
           SET UP FORM VALIDATION
        ========================= */

        function setupSupplierValidation(formId, prefix) {
            const form = document.getElementById(formId);

            if (!form) return;

            const fieldNames = [
                'name',
                'contact_person',
                'phone',
                'email',
                'address'
            ];

            function validateField(fieldName) {
                const input = document.getElementById(prefix + fieldName);
                const error = document.getElementById(prefix + fieldName + '_error');

                if (!input || !error) return true;

                const value = input.value.trim();
                const message = getSupplierFieldError(fieldName, value);

                error.textContent = message;

                input.classList.toggle('input-error', Boolean(message));
                input.classList.toggle(
                    'input-valid',
                    !message && Boolean(value)
                );

                input.setAttribute(
                    'aria-invalid',
                    message ? 'true' : 'false'
                );

                return message === '';
            }

            fieldNames.forEach(function (fieldName) {
                const input = document.getElementById(prefix + fieldName);

                if (!input) return;

                input.addEventListener('input', function () {
                    validateField(fieldName);
                });

                input.addEventListener('blur', function () {
                    validateField(fieldName);
                });
            });

            form.addEventListener('submit', function (event) {
                let formIsValid = true;
                let firstInvalidInput = null;

                fieldNames.forEach(function (fieldName) {
                    const valid = validateField(fieldName);

                    if (!valid) {
                        formIsValid = false;

                        if (!firstInvalidInput) {
                            firstInvalidInput =
                                document.getElementById(prefix + fieldName);
                        }
                    }
                });

                if (!formIsValid) {
                    event.preventDefault();

                    if (firstInvalidInput) {
                        firstInvalidInput.focus();
                    }
                }
            });
        }

        setupSupplierValidation('addSupplierForm', '');
        setupSupplierValidation('editSupplierForm', 'edit_');

        /* =========================
           STATUS AND DELETE CONFIRMATION
        ========================= */

        function confirmStatusChange(form) {
            const isActive = form.dataset.active === '1';

            return confirm(
                isActive
                    ? 'Deactivate this supplier?'
                    : 'Activate this supplier?'
            );
        }

        function confirmDeleteSupplier() {
            return confirm(
                'Are you sure you want to permanently delete this supplier?'
            );
        }

        /* =========================
           CLOSE MODALS ON OUTSIDE CLICK
        ========================= */

        window.addEventListener('click', function (event) {
            const addModal = document.getElementById('addSupplierModal');
            const editModal = document.getElementById('editSupplierModal');

            if (event.target === addModal) {
                closeAddModal();
            }

            if (event.target === editModal) {
                closeEditModal();
            }
        });

        /* =========================
           DATE AND TIME
        ========================= */

        function updateDateTime() {
            const element = document.getElementById('datetime');

            if (element) {
                const now = new Date();

                element.textContent =
                    now.toLocaleDateString() + ' | ' +
                    now.toLocaleTimeString();
            }
        }

        setInterval(updateDateTime, 1000);
        updateDateTime();
    </script>

</body>
</html>
```
