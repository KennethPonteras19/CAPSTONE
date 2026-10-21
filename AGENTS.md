# Module permissions

Whenever adding, renaming, or removing an Admin, Staff, or Customer module:

- Update `config/modules.php` for the correct role in the same change. Include the module label, named landing route, and all related page/API/write routes.
- The Super Admin permission editor and account creation forms read this catalog. Keep it as the single source of available permissions.
- Apply both `auth:<role>` and `module.access:<role>` middleware to module routes. Gate navigation and related actions with `ModuleAccess`, `@module`, or `@moduleRoute`.
- Preserve existing saved account selections. Adding a module to the catalog does not authorize granting it to accounts with explicit permissions.
- Update and run `tests/Feature/ModulePermissionsTest.php`. Check that every role-protected route has a catalog entry, allowed accounts can open the module, and denied accounts cannot open it through a direct URL.
- Subfeatures within an existing module (such as the staff POS delivery map) use that module's permission unless separate access control is requested.
- Keep sidebar icons in sync with new modules and retain a default icon for missing mappings. Verify the sidebar renders with every module enabled.
