document.addEventListener('DOMContentLoaded', () => {
    function updateCount(container) {
        const available = container.querySelectorAll('fieldset:not([disabled]) input[type="checkbox"]');
        const count = Array.from(available).filter(input => input.checked).length;
        container.querySelector('.module-count').textContent = `${count} of ${available.length} modules enabled`;
    }

    function showModules(container, role, selected = null) {
        container.querySelectorAll('[data-module-role]').forEach(group => {
            const active = group.dataset.moduleRole === role;
            group.hidden = !active;
            group.disabled = !active;
            group.querySelectorAll('input[type="checkbox"]').forEach(input => {
                input.checked = active && (selected === null || selected.includes(input.value));
            });
        });
        const editable = ['admin', 'staff', 'customer'].includes(role);
        container.querySelector('.module-selection-actions').hidden = !editable;
        container.querySelector('.module-help').textContent = role === 'superadmin'
            ? 'Super admins have full system access and manage permissions for other accounts.'
            : editable
                ? 'Enable the modules this account can use. Changes apply on the next page request. Clearing all modules leaves only logout available.'
                : 'Select a role to see its available modules.';
        updateCount(container);
    }

    document.querySelectorAll('.module-options').forEach(container => {
        container.addEventListener('change', () => updateCount(container));
        container.querySelectorAll('[data-select-modules]').forEach(button => {
            button.addEventListener('click', () => {
                container.querySelectorAll('fieldset:not([disabled]) input[type="checkbox"]').forEach(input => {
                    input.checked = button.dataset.selectModules === 'all';
                });
                updateCount(container);
            });
        });
    });

    const roleSelect = document.getElementById('createRole');
    const createOptions = document.querySelector('#addUserModal .module-options');
    const editOptions = document.querySelector('#permissionsModal .module-options');
    roleSelect.addEventListener('change', () => {
        showModules(createOptions, roleSelect.value.toLowerCase().replaceAll(' ', ''));
    });
    showModules(createOptions, roleSelect.value.toLowerCase().replaceAll(' ', ''));

    document.querySelectorAll('.permission-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('permissionsUsername').textContent = button.dataset.username;
            document.getElementById('permissionsUserId').value = button.dataset.id;
            document.getElementById('permissionsRoleKey').value = button.dataset.roleKey;
            const selected = button.dataset.permissions ? button.dataset.permissions.split('|') : [];
            showModules(editOptions, button.dataset.roleKey, selected);
            document.getElementById('permissionsModal').classList.remove('hidden');
        });
    });

    const previousInput = document.getElementById('previousModuleInput');
    if (previousInput) {
        const previous = JSON.parse(previousInput.textContent);
        const permissions = Array.isArray(previous.permissions) ? previous.permissions : [];
        if (previous.form === 'create') {
            showModules(createOptions, roleSelect.value.toLowerCase().replaceAll(' ', ''), permissions);
            document.getElementById('addUserModal').classList.remove('hidden');
        } else if (previous.form === 'permissions') {
            const account = Array.from(document.querySelectorAll('.permission-btn')).find(button =>
                button.dataset.roleKey === previous.role && button.dataset.id === String(previous.id));
            if (account) {
                account.click();
                showModules(editOptions, previous.role, permissions);
            }
        }
    }
});
