# Module permissions

Super Admin's System User permission editor and account creation form use
`ModuleAccess::catalogs()`. Navigation, permission validation and route access use
the same resolved catalog. Refresh System User after deploying a new module.
Existing saved account selections are never expanded by discovery.

`config/modules.php` remains the canonical place to register modules, custom
labels, stable permission keys, and related page/API/write routes. Its definitions
take priority. Subfeatures belong to their parent module (for example, consumable
actions belong to Inventory), so include their route names in that definition.

As a fallback for a missed catalog entry, named routes in the existing protected
Admin, Staff or Customer route groups are discovered automatically using this
convention:

```php
Route::middleware(['auth:admin', 'check.active', 'module.access:admin'])->group(function () {
    Route::get('/admin/deliveries', [DeliveryController::class, 'index'])->name('admin.deliveries.index');
    Route::post('/admin/deliveries', [DeliveryController::class, 'store'])->name('admin.deliveries.store');
    Route::get('/admin/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('admin.deliveries.show');
});
```

This adds a **Deliveries** option with the permission key `deliveries`, covering
the protected `admin.deliveries.*` routes. The role prefix is optional; a landing
name of `deliveries` also works. Use the corresponding middleware and prefix for
Staff or Customer. The landing route must accept GET without URL or domain
parameters. Public routes, authentication routes, Super Admin routes and route
families with only API/write/detail actions are not discovered as modules.
Unconventional route names need an explicit entry in `config/modules.php`.

Discovery requires both role authentication and module access middleware. A view
file alone is not a module. Keep navigation and actions gated with `@module`,
`@moduleRoute` or `ModuleAccess`. Admin and Staff navigation provide a default
icon for newly discovered modules.

Deployments using Laravel's route/config caches must rebuild those caches after
route/config changes. Discovery uses the active route collection, including
cached routes; it does not rewrite configuration files or database permissions.

Run `php artisan test tests/Feature/ModulePermissionsTest.php` after module changes.
