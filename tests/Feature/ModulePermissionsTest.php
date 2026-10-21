<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Consumable;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Support\ModuleAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthenticationTestCase;

class ModulePermissionsTest extends AuthenticationTestCase
{
    public function test_receipt_templates_use_existing_permissions_for_library_samples_and_documents(): void
    {
        $order = Order::create(['number' => 'RECEIPT-PERM-1', 'request_key' => (string) Str::uuid(),
            'staff_name' => 'Cashier', 'type' => 'Walk-in', 'status' => 'Pending', 'subtotal' => 100, 'total_amount' => 100]);
        $sale = Sale::create(['receipt_no' => 'LEGACY-PERM', 'staff_name' => 'Cashier', 'item_name' => 'Item', 'qty' => 1,
            'total_amount' => 100, 'paid_amount' => 100, 'status' => 'Completed']);
        foreach (['staff.receipts', 'sales.receipts'] as $prefix) {
            $this->getJson(route($prefix.'.index'))->assertUnauthorized();
            $this->getJson(route($prefix.'.print', $order))->assertUnauthorized();
            $this->getJson(route($prefix.'.legacy', ['receipt' => $sale->receipt_no]))->assertUnauthorized();
            $this->getJson(route($prefix.'.sample', 'cash-sale'))->assertUnauthorized();
            $this->getJson(route($prefix.'.show', ['order' => $order, 'template' => 'invoice-preview']))->assertUnauthorized();
        }
        foreach ([['staff', ['pos']], ['staff', ['orders']], ['staff', ['transactions']], ['admin', ['sales']]] as [$role, $permissions]) {
            $account = $this->account($role, $permissions);
            $prefix = $role === 'admin' ? 'sales.receipts' : 'staff.receipts';
            $this->actingAs($account, $role)->get(route($prefix.'.index'))->assertOk()->assertSee('Receipt Templates');
            $this->get(route($prefix.'.sample', 'cash-sale'))->assertOk();
            $this->get(route($prefix.'.print', $order))->assertRedirect(route($prefix.'.show', ['order' => $order, 'template' => 'charge-sale', 'print' => 1]));
            $this->get(route($prefix.'.legacy', ['receipt' => $sale->receipt_no]))->assertOk();
            $this->get(route($prefix.'.show', ['order' => $order, 'template' => 'invoice-preview']))->assertOk();
            if ($role === 'staff') {
                $this->get(route('staff.orders.show', $order))->assertOk()->assertSee('href="'.route('staff.receipts.print', $order).'"', false);
                if ($permissions !== ['orders']) {
                    $this->post(route('staff.orders.payment', $order), [])->assertForbidden();
                }
            }
            $this->assertSame($permissions, $account->fresh()->permissions);
        }
        foreach (['staff', 'admin'] as $role) {
            $account = $this->account($role, ['dashboard']);
            $prefix = $role === 'admin' ? 'sales.receipts' : 'staff.receipts';
            $this->actingAs($account, $role)->get(route($prefix.'.index'))->assertForbidden();
            $this->get(route($prefix.'.sample', 'cash-sale'))->assertForbidden();
            $this->get(route($prefix.'.print', $order))->assertForbidden();
            $this->get(route($prefix.'.legacy', ['receipt' => $sale->receipt_no]))->assertForbidden();
            $this->get(route($prefix.'.show', ['order' => $order, 'template' => 'invoice-preview']))->assertForbidden();
            $this->assertSame(['dashboard'], $account->fresh()->permissions);
        }
    }

    public function test_delivery_zone_lookup_uses_the_existing_pos_permission(): void
    {
        $url = route('delivery-zone.lookup');
        $this->postJson($url, ['address' => 'Silang, Cavite'])->assertUnauthorized();

        foreach ([['pos'], ['sales'], null] as $permissions) {
            $staff = $this->account('staff', $permissions);
            $this->actingAs($staff, 'staff')->get(route('pos'))->assertOk()
                ->assertSee('id="customerId"', false)
                ->assertSee('data-zone-url="'.$url.'"', false);
            $this->postJson($url, ['address' => 'Silang, Cavite'])->assertOk()
                ->assertJsonPath('zone.zone_id', 'silang')
                ->assertJsonPath('zone.fee', 500);
            $this->assertSame($permissions, $staff->fresh()->permissions);
        }

        foreach ([['dashboard'], ['orders'], ['transactions'], []] as $permissions) {
            $staff = $this->account('staff', $permissions);
            $this->actingAs($staff, 'staff')->get(route('pos'))->assertForbidden();
            $this->postJson($url, ['address' => 'Silang, Cavite'])->assertForbidden();
            $this->assertSame($permissions, $staff->fresh()->permissions);
        }
        $this->assertSame(config('modules.staff'), ModuleAccess::catalog('staff'));
        $patterns = array_merge(...array_column(config('modules.staff'), 'routes'));
        foreach (Route::getRoutes() as $route) {
            if (in_array('auth:staff', $route->gatherMiddleware(), true) && $route->getName() !== 'staff.access') {
                $this->assertContains('module.access:staff', $route->gatherMiddleware());
                $this->assertTrue(Str::is($patterns, $route->getName()), $route->getName());
            }
        }
    }

    public function test_product_image_upload_uses_the_existing_products_permission(): void
    {
        Storage::fake('public');
        $image = fn () => UploadedFile::fake()->createWithContent('product.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $product = Product::create(['name' => 'Hammer', 'category' => 'Tools', 'stock' => 10,
            'stock_unit' => 'piece', 'price' => 100]);
        $data = ['id' => $product->id, 'name' => $product->name, 'category' => $product->category,
            'stock' => 10, 'stock_unit' => 'piece', 'price' => 100];
        $denied = $this->account('admin', ['dashboard']);
        $this->actingAs($denied, 'admin')->get(route('product.management'))->assertForbidden();
        $this->post(route('products.store'), $data + ['image' => $image()])->assertForbidden();
        $this->assertNull($product->fresh()->image_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(['dashboard'], $denied->fresh()->permissions);

        $allowed = $this->account('admin', ['products']);
        $this->actingAs($allowed, 'admin')->get(route('product.management'))->assertOk()->assertSee('Insert Image');
        $this->post(route('products.store'), $data + ['image' => $image()])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($product->fresh()->image_path);
        $this->assertSame(['products'], $allowed->fresh()->permissions);
    }

    private function account(string $role, ?array $permissions = null)
    {
        $class = match ($role) {
            'admin' => Admin::class,
            'staff' => Staff::class,
            'customer' => Customer::class,
            'superadmin' => SuperAdmin::class,
        };
        $data = ['name' => 'Permission Test', 'username' => $role.Str::random(8),
            'password' => 'password123', 'is_active' => true, 'permissions' => $permissions];
        $data[$role === 'staff' ? 'role' : 'email'] = $role === 'staff' ? 'Staff' : Str::random(8).'@example.test';

        return $class::create($data);
    }

    public static function roles(): array
    {
        return [['admin'], ['staff'], ['customer']];
    }

    private function registerDiscoveredModule(string $role): void
    {
        Route::middleware(['web', 'auth:'.$role, 'module.access:'.$role])->group(function () use ($role) {
            // Register actions first to ensure discovery does not depend on order.
            Route::post('/'.$role.'/delivery-plans', fn () => 'Saved')->name($role.'.delivery_plans.store');
            Route::get('/'.$role.'/delivery-plans/{plan}', fn () => 'Detail')->name($role.'.delivery_plans.show');
            Route::get('/'.$role.'/delivery-plans', fn () => 'Delivery Plans')->name($role.'.delivery_plans.index');
        });
        Route::getRoutes()->refreshNameLookups();
    }

    #[DataProvider('roles')]
    public function test_new_route_modules_appear_in_both_superadmin_forms_and_can_be_assigned(string $role): void
    {
        $user = $this->account($role, ['dashboard']);
        $this->registerDiscoveredModule($role);
        $response = $this->actingAs($this->account('superadmin'), 'superadmin')
            ->get('/superadmin/system-users')->assertOk()->assertSee('Delivery Plans');

        $catalog = $response->viewData('moduleCatalog');
        $this->assertSame($role.'.delivery_plans.index', $catalog[$role]['delivery_plans']['route']);
        $this->assertSame(2, substr_count($response->getContent(), 'value="delivery_plans"'));
        foreach (array_diff(['admin', 'staff', 'customer'], [$role]) as $otherRole) {
            $this->assertArrayNotHasKey('delivery_plans', $catalog[$otherRole]);
        }
        $this->assertSame(['dashboard'], $user->fresh()->permissions);
        $this->assertSame(['dashboard'], ModuleAccess::permissions($user, $role));
        $this->assertSame([], ModuleAccess::permissions($this->account($role, []), $role));
        $this->assertContains('delivery_plans', ModuleAccess::permissions($this->account($role), $role));

        $this->put('/superadmin/system-users/permissions', [
            'role_key' => $role, 'id' => $user->id, 'permissions' => ['dashboard', 'delivery_plans'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['dashboard', 'delivery_plans'], $user->fresh()->permissions);

        $this->post('/superadmin/system-users', [
            'role' => ucfirst($role), 'name' => 'New Module Account', 'username' => 'discovery.'.$role,
            'email' => 'discovery@example.test', 'password' => 'password123', 'password_confirmation' => 'password123',
            'permissions' => ['delivery_plans'],
        ])->assertSessionHasNoErrors();
        $created = Auth::guard($role)->getProvider()->retrieveByCredentials(['username' => 'discovery.'.$role]);
        $this->assertSame(['delivery_plans'], $created->permissions);
    }

    #[DataProvider('roles')]
    public function test_discovered_modules_gate_navigation_pages_and_writes(string $role): void
    {
        $this->registerDiscoveredModule($role);
        $navigation = match ($role) {
            'admin' => 'partials.admin-module-links',
            'staff' => 'staff.partials.sidebar',
            'customer' => 'customer.partials.navigation',
        };
        $this->actingAs($this->account($role, ['delivery_plans']), $role);
        $this->get('/'.$role.'/delivery-plans')->assertOk();
        $this->get('/'.$role.'/delivery-plans/1')->assertOk();
        $this->postJson('/'.$role.'/delivery-plans')->assertOk();
        $this->view($navigation)->assertSee('Delivery Plans')
            ->assertSee('href="'.route($role.'.delivery_plans.index').'"', false);
        if ($role !== 'customer') {
            $this->view($navigation)->assertSee('fa-solid fa-layer-group', false);
        }

        $this->actingAs($this->account($role, ['dashboard']), $role);
        $this->get('/'.$role.'/delivery-plans')->assertForbidden();
        $this->get('/'.$role.'/delivery-plans/1')->assertForbidden();
        $this->postJson('/'.$role.'/delivery-plans')->assertForbidden();
        $this->view($navigation)->assertDontSee('Delivery Plans');
    }

    public function test_discovery_preserves_explicit_catalog_definitions_and_subfeatures(): void
    {
        $this->registerDiscoveredModule('admin');
        config(['modules.admin.dispatch' => [
            'label' => 'Dispatch Center', 'route' => 'admin.delivery_plans.index',
            'routes' => ['admin.delivery_plans.*'],
        ]]);
        Route::get('/pos/delivery-map', fn () => 'Delivery map')
            ->middleware(['auth:staff', 'module.access:staff'])->name('pos.delivery_map');
        Route::getRoutes()->refreshNameLookups();

        $this->assertSame(config('modules.admin'), ModuleAccess::catalog('admin'));
        $this->assertSame(config('modules.staff'), ModuleAccess::catalog('staff'));
        $this->actingAs($this->account('staff', ['pos']), 'staff');
        $this->get('/pos/delivery-map')->assertOk();
        $this->actingAs($this->account('staff', ['inventory']), 'staff');
        $this->get('/pos/delivery-map')->assertForbidden();
    }

    public function test_discovery_works_with_laravels_compiled_route_collection(): void
    {
        foreach (['admin', 'staff', 'customer'] as $role) {
            $this->registerDiscoveredModule($role);
        }
        $expected = ModuleAccess::catalogs();
        Route::setCompiledRoutes(Route::getRoutes()->compile());

        $this->assertSame($expected, ModuleAccess::catalogs());
        $this->actingAs($this->account('superadmin'), 'superadmin')
            ->get('/superadmin/system-users')->assertOk()->assertSee('Delivery Plans');
    }

    public function test_discovery_ignores_unprotected_routes_and_families_without_landing_pages(): void
    {
        Route::get('/public-tools', fn () => 'Public')->name('admin.public_tools');
        Route::get('/auth-only', fn () => 'Auth')->middleware('auth:admin')->name('admin.auth_only');
        Route::get('/module-only', fn () => 'Module')->middleware('module.access:admin')->name('admin.module_only');
        Route::middleware(['auth:admin', 'module.access:admin'])->group(function () {
            Route::post('/write-only', fn () => 'Write')->name('admin.write_only.store');
            Route::get('/details/{id}', fn () => 'Detail')->name('admin.details.index');
            Route::get('/unnamed', fn () => 'Unnamed');
            Route::get('/tools', fn () => 'Tools')->name('tools');
            Route::post('/tools', fn () => 'Saved')->name('tools.store');
        });
        Route::get('/public-tools-export', fn () => 'Public export')->name('tools.export');
        Route::getRoutes()->refreshNameLookups();

        $catalog = ModuleAccess::catalog('admin');
        $this->assertSame(['tools'], array_values(array_diff(array_keys($catalog), array_keys(config('modules.admin')))));
        $this->assertSame(['tools', 'tools.store'], $catalog['tools']['routes']);
        $this->assertSame('Tools', $catalog['tools']['label']);
        $this->assertSame([], ModuleAccess::catalog('superadmin'));
    }

    public function test_consumable_actions_belong_to_inventory_permission(): void
    {
        $this->actingAs($this->account('admin', ['inventory']), 'admin');
        foreach (['store', 'update', 'toggle-status', 'delete'] as $action) {
            $this->assertTrue(ModuleAccess::allowsRoute('admin', 'consumables.'.$action));
        }
        $this->postJson('/inventory-management/consumables', [])->assertUnprocessable();
        $this->actingAs($this->account('admin', ['reports']), 'admin');
        $this->postJson('/inventory-management/consumables', [])->assertForbidden();
        $this->putJson('/inventory-management/consumables/1', [])->assertForbidden();
        $this->patchJson('/inventory-management/consumables/1/toggle-status')->assertForbidden();
        $this->deleteJson('/inventory-management/consumables/1')->assertForbidden();
    }

    public function test_editor_explains_consumables_access_under_inventory(): void
    {
        $admin = $this->account('admin', ['inventory']);
        $response = $this->actingAs($this->account('superadmin'), 'superadmin')
            ->get('/superadmin/system-users')->assertOk()
            ->assertSee('Includes Consumables: add, edit, activate, deactivate, and delete.');
        $catalog = $response->viewData('moduleCatalog')['admin'];
        $this->assertContains('consumables.*', $catalog['inventory']['routes']);
        $this->assertArrayNotHasKey('consumables', $catalog);
        $this->assertSame(['inventory'], $admin->fresh()->permissions);
    }

    public function test_inventory_permission_allows_the_complete_consumables_workflow(): void
    {
        $this->actingAs($this->account('admin', ['inventory']), 'admin');
        $this->get(route('inventory.management'))->assertOk()->assertSee('Consumables Management');
        $data = ['name' => 'Permission Test Consumable', 'category' => 'Supplies', 'unit' => 'pcs',
            'stock' => 20, 'minimum_stock' => 5, 'price' => 10];
        $this->post(route('consumables.store'), $data)
            ->assertSessionHasNoErrors()->assertRedirect(route('inventory.management'));
        $consumable = Consumable::where('name', $data['name'])->firstOrFail();

        $this->put(route('consumables.update', $consumable), array_merge($data, ['stock' => 30]))
            ->assertSessionHasNoErrors()->assertRedirect(route('inventory.management'));
        $this->assertEquals(30, $consumable->fresh()->stock);
        $this->patch(route('consumables.toggle-status', $consumable))->assertRedirect(route('inventory.management'));
        $this->assertFalse($consumable->fresh()->is_active);
        $this->patch(route('consumables.toggle-status', $consumable))->assertRedirect(route('inventory.management'));
        $this->assertTrue($consumable->fresh()->is_active);

        $this->actingAs($this->account('admin', ['reports']), 'admin');
        $this->putJson(route('consumables.update', $consumable), array_merge($data, ['stock' => 99]))->assertForbidden();
        $this->patchJson(route('consumables.toggle-status', $consumable))->assertForbidden();
        $this->deleteJson(route('consumables.delete', $consumable))->assertForbidden();
        $this->assertEquals(30, $consumable->fresh()->stock);
        $this->assertTrue($consumable->fresh()->is_active);

        $this->actingAs($this->account('admin', ['inventory']), 'admin');
        $this->delete(route('consumables.delete', $consumable))->assertRedirect(route('inventory.management'));
        $this->assertDatabaseMissing('consumables', ['id' => $consumable->id]);
    }

    public function test_staff_sidebar_renders_every_enabled_module(): void
    {
        $this->actingAs($this->account('staff'), 'staff');
        $sidebar = $this->view('staff.partials.sidebar')
            ->assertSee('class="admin-sidebar"', false)->assertSee('Staff navigation');
        foreach (ModuleAccess::catalog('staff') as $definition) {
            $sidebar->assertSee('href="'.route($definition['route']).'"', false);
            $this->get(route($definition['route']))->assertOk()
                ->assertSee('css/admin-sidebar.css')->assertSee('Staff navigation');
        }
    }

    public function test_editor_presents_only_real_modules_for_each_role(): void
    {
        $this->account('staff');
        $response = $this->actingAs($this->account('superadmin'), 'superadmin')
            ->get('/superadmin/system-users')->assertOk();
        $response->assertSee('Point of Sale')->assertSee('Purchase Orders')->assertSee('Full system access');
        $response->assertSee('css/admin-sidebar.css')->assertSee('class="admin-sidebar"', false)
            ->assertSee('Super Admin navigation');
        $catalog = $response->viewData('moduleCatalog');
        $this->assertSame(['dashboard', 'pos', 'transactions', 'inventory', 'orders', 'customers'], array_keys($catalog['staff']));
        $this->assertSame(['dashboard', 'home', 'order', 'more', 'cart', 'checkout'], array_keys($catalog['customer']));
        $this->assertArrayNotHasKey('system_users', $catalog['admin']);
        $response->assertSee('data-module-role="staff"', false)->assertSee('module-permissions.js');
    }

    public function test_admin_sidebar_renders_every_enabled_module_including_suppliers(): void
    {
        $this->actingAs($this->account('admin'), 'admin');
        $sidebar = $this->view('partials.admin-module-links');

        foreach (ModuleAccess::catalog('admin') as $definition) {
            $sidebar->assertSee('href="'.route($definition['route']).'"', false)
                ->assertSee($definition['label']);
        }
        $sidebar->assertSee('fa-solid fa-truck', false);
        $this->get('/dashboard')->assertOk()->assertSee('Supplier Management');
        $this->get('/suppliers')->assertOk()->assertViewIs('supplier')->assertSee('Supplier Management');
    }

    public function test_new_admin_modules_without_an_icon_render_safely_and_respect_permissions(): void
    {
        config(['modules.admin.new_module' => [
            'label' => 'New Module', 'route' => 'admin.new-module', 'routes' => ['admin.new-module'],
        ]]);
        Route::get('/admin/new-module', fn () => 'New module')
            ->middleware(['auth:admin', 'module.access:admin'])->name('admin.new-module');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($this->account('admin', ['new_module']), 'admin');
        $this->view('partials.admin-module-links')
            ->assertSee('New Module')->assertSee('fa-solid fa-layer-group', false)
            ->assertDontSee('Supplier Management');

        $this->actingAs($this->account('admin', ['dashboard']), 'admin');
        $this->view('partials.admin-module-links')->assertDontSee('New Module');
        $this->get('/admin/new-module')->assertForbidden();
    }

    #[DataProvider('roles')]
    public function test_superadmin_can_save_each_roles_modules_and_clear_all(string $role): void
    {
        $user = $this->account($role);
        $this->actingAs($this->account('superadmin'), 'superadmin');
        $this->put('/superadmin/system-users/permissions', [
            'role_key' => $role, 'id' => $user->id, 'permissions' => ['dashboard'],
        ])->assertSessionHasNoErrors()->assertRedirect('/superadmin/system-users');
        $this->assertSame(['dashboard'], $user->fresh()->permissions);

        $this->put('/superadmin/system-users/permissions', [
            'role_key' => $role, 'id' => $user->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $user->fresh()->permissions);
    }

    #[DataProvider('roles')]
    public function test_accounts_cannot_grant_themselves_permissions(string $role): void
    {
        $user = $this->account($role, ['dashboard']);
        $this->actingAs($user, $role)->put('/superadmin/system-users/permissions', [
            'role_key' => $role, 'id' => $user->id, 'permissions' => ['settings'],
        ])->assertRedirect('/login');
        $this->assertSame(['dashboard'], $user->fresh()->permissions);
    }

    #[DataProvider('roles')]
    public function test_out_of_role_permissions_are_rejected_without_changing_access(string $role): void
    {
        $user = $this->account($role, ['dashboard']);
        $this->actingAs($this->account('superadmin'), 'superadmin')
            ->put('/superadmin/system-users/permissions', [
                'role_key' => $role, 'id' => $user->id, 'permissions' => ['dashboard', 'system_users'],
            ])->assertSessionHasErrors('permissions.1');
        $this->assertSame(['dashboard'], $user->fresh()->permissions);
    }

    #[DataProvider('roles')]
    public function test_disabling_all_modules_has_a_logout_page_and_blocks_dashboard_data(string $role): void
    {
        $user = $this->account($role, []);
        $this->actingAs($user, $role);
        $dashboard = ModuleAccess::catalog($role)['dashboard']['route'];
        $this->get(route($dashboard))->assertRedirect(route($role.'.access'));
        $this->get(route($role.'.access'))->assertOk()->assertSee('No modules assigned')->assertSee('Logout');
        $this->getJson(route($dashboard))->assertForbidden();
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_staff_navigation_and_direct_requests_follow_permissions(): void
    {
        $this->actingAs($this->account('staff', ['inventory']), 'staff');
        $this->get('/dashboardstaff')->assertRedirect('/staff/inventory');
        $this->get('/staff/inventory')->assertOk()
            ->assertSee('href="'.route('staff.inventory').'"', false)
            ->assertDontSee('href="'.route('pos').'"', false)
            ->assertDontSee('href="'.route('orders').'"', false)
            ->assertDontSee('href="'.route('customers').'"', false);
        foreach (['/pos', '/newsales', '/transactions', '/orders', '/customers', '/staff/catalog'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->postJson('/pos/checkout', [])->assertForbidden();
        $this->post('/staff/customers', ['name' => 'Unauthorized Customer'])->assertForbidden();
        $this->assertDatabaseMissing('customers', ['name' => 'Unauthorized Customer']);
    }

    public function test_admin_navigation_and_write_routes_follow_permissions(): void
    {
        $this->actingAs($this->account('admin', ['reports']), 'admin');
        $this->get('/dashboard')->assertRedirect('/reports');
        $this->get('/reports')->assertOk()->assertSee('href="'.route('reports').'"', false)
            ->assertDontSee('href="'.route('sales').'"', false)
            ->assertDontSee('href="'.route('add.account').'"', false)
            ->assertDontSee('href="'.route('settings').'"', false);
        foreach (['/sales', '/product-management', '/inventory', '/inventory-management', '/purchase-order', '/add-account', '/users/create', '/settings'] as $path) {
            $this->get($path)->assertForbidden();
        }
        foreach (['/products/store', '/sales/store', '/sales/store-multiple', '/inventory-management/receive-stock', '/purchase-order/store', '/users'] as $path) {
            $this->postJson($path, [])->assertForbidden();
        }
    }

    public function test_permission_changes_apply_to_an_already_logged_in_account(): void
    {
        $staff = $this->account('staff', ['dashboard', 'pos']);
        $this->postLogin('/login/staff/process', ['username' => $staff->username, 'password' => 'password123']);
        $this->get('/pos')->assertOk();
        $this->actingAs($this->account('superadmin'), 'superadmin')
            ->put('/superadmin/system-users/permissions', ['role_key' => 'staff', 'id' => $staff->id, 'permissions' => ['inventory']])
            ->assertSessionHasNoErrors();
        Auth::forgetGuards();
        $this->get('/pos')->assertForbidden();
        $this->get('/staff/inventory')->assertOk();
    }

    public function test_transactions_permission_allows_receipts_without_order_mutations(): void
    {
        $staff = $this->account('staff', ['transactions']);
        $order = Order::create(['number' => 'PERM-1', 'request_key' => (string) Str::uuid(),
            'staff_name' => $staff->name, 'type' => 'Walk-in', 'status' => 'Pending', 'subtotal' => 100, 'total_amount' => 100]);
        $this->actingAs($staff, 'staff')->get(route('staff.orders.show', $order))->assertOk()
            ->assertDontSee('Record Payment')->assertDontSee('Mark Processing')->assertDontSee('Cancel Order');
        $this->patch(route('staff.orders.status', $order), ['status' => 'Cancelled', 'version' => 1])->assertForbidden();
        $this->post(route('staff.orders.payment', $order), [])->assertForbidden();
        $this->assertSame('Pending', $order->fresh()->status);
    }

    public function test_every_protected_route_is_mapped_and_other_role_routes_remain_inaccessible(): void
    {
        foreach (['admin', 'staff', 'customer'] as $role) {
            $this->actingAs($this->account($role), $role);
            foreach (Route::getRoutes() as $route) {
                if (in_array('auth:'.$role, $route->gatherMiddleware(), true) && $route->getName() !== $role.'.access') {
                    $this->assertContains('module.access:'.$role, $route->gatherMiddleware(), $route->uri());
                    $this->assertNotNull($route->getName(), 'Module routes must be named: '.$route->uri());
                    $this->assertTrue(ModuleAccess::allowsRoute($role, $route->getName()), $route->getName());
                }
            }
        }
        $this->assertFalse(ModuleAccess::allowsRoute('staff', 'superadmin.system.users'));
        $this->assertFalse(ModuleAccess::allowsRoute('customer', 'pos'));
        $this->assertFalse(ModuleAccess::allowsRoute('admin', 'unknown.route'));
    }

    public function test_catalog_entries_have_real_protected_landing_routes(): void
    {
        foreach (['admin', 'staff', 'customer'] as $role) {
            foreach (ModuleAccess::catalog($role) as $module => $definition) {
                $route = Route::getRoutes()->getByName($definition['route']);
                $this->assertNotNull($route, $role.'.'.$module.' has no landing route.');
                $this->assertContains('GET', $route->methods());
                $this->assertContains('auth:'.$role, $route->gatherMiddleware());
                $this->assertContains('module.access:'.$role, $route->gatherMiddleware());
                $this->assertTrue(Str::is($definition['routes'], $definition['route']));
            }
        }
    }

    public function test_staff_legacy_sales_permission_maps_to_pos_and_transactions(): void
    {
        $staff = $this->account('staff', ['sales']);
        $this->assertSame(['pos', 'transactions'], ModuleAccess::permissions($staff, 'staff'));
    }

    public static function customerStoreModules(): array
    {
        return array_map(fn ($module) => [$module], ['home', 'order', 'more', 'cart', 'checkout']);
    }

    #[DataProvider('customerStoreModules')]
    public function test_customer_store_permissions_can_be_saved_and_enforced(string $module): void
    {
        $customer = $this->account('customer', ['dashboard']);
        $this->actingAs($this->account('superadmin'), 'superadmin')
            ->put('/superadmin/system-users/permissions', [
                'role_key' => 'customer', 'id' => $customer->id, 'permissions' => [$module],
            ])->assertSessionHasNoErrors();
        $this->assertSame([$module], $customer->fresh()->permissions);

        $this->actingAs($customer->fresh(), 'customer');
        $this->get('/customer/dashboard')->assertRedirect(route('customer.'.$module));
        $response = $this->get(route('customer.'.$module))->assertOk()
            ->assertViewIs('customer.'.$module);
        // Cart and checkout use the existing action buttons, not sidebar links.
        if (! in_array($module, ['cart', 'checkout'])) {
            $response->assertSee('href="'.route('customer.'.$module).'"', false);
        }

        foreach (['home', 'order', 'more', 'cart', 'checkout'] as $other) {
            if ($other !== $module) {
                $response->assertDontSee('href="'.route('customer.'.$other).'"', false);
                $this->get(route('customer.'.$other))->assertForbidden();
            }
        }
    }

    public function test_new_modules_do_not_expand_explicit_account_permissions(): void
    {
        $customer = $this->account('customer', ['dashboard']);
        $this->assertSame(['dashboard'], ModuleAccess::permissions($customer, 'customer'));
        $this->actingAs($customer, 'customer');
        $this->get('/customer/dashboard')->assertOk()->assertViewIs('dashboardcustomer');
        foreach (['home', 'order', 'more', 'cart', 'checkout'] as $module) {
            $this->get(route('customer.'.$module))->assertForbidden();
        }
    }

    public function test_customer_backend_routes_follow_their_existing_modules(): void
    {
        foreach (['order', 'cart', 'checkout'] as $module) {
            $customer = $this->account('customer', [$module]);
            $this->actingAs($customer, 'customer');
            foreach (['order' => '/customer/order/cart', 'cart' => '/customer/cart'] as $owner => $path) {
                $response = $this->putJson($path, ['version' => 1, 'items' => []]);
                $owner === $module ? $response->assertOk() : $response->assertForbidden();
            }
            $response = $this->postJson('/customer/checkout', []);
            $module === 'checkout' ? $response->assertUnprocessable() : $response->assertForbidden();
            $this->assertSame([$module], $customer->fresh()->permissions);
        }
    }

    public function test_customer_sidebar_renders_existing_menu_with_all_modules_enabled(): void
    {
        $this->actingAs($this->account('customer'), 'customer');
        foreach (['home', 'order', 'more', 'cart', 'checkout'] as $module) {
            $response = $this->get(route('customer.'.$module))->assertOk();
            foreach (['home', 'order', 'more'] as $navigation) {
                $response->assertSee('href="'.route('customer.'.$navigation).'"', false);
            }
        }
    }

    public function test_customer_dashboard_redirects_to_home_when_menu_is_allowed(): void
    {
        $this->actingAs($this->account('customer', ['dashboard', 'home']), 'customer');
        $this->get('/customer/dashboard')->assertRedirect(route('customer.home'));
        $this->get('/customer/home')->assertOk()->assertViewIs('customer.home');
    }

    #[DataProvider('roles')]
    public function test_new_accounts_save_the_selected_role_modules(string $role): void
    {
        $permissions = array_keys(ModuleAccess::catalog($role));
        $this->actingAs($this->account('superadmin'), 'superadmin')->post('/superadmin/system-users', [
            'role' => ucfirst($role), 'name' => 'New Account', 'username' => 'new.'.$role,
            'email' => 'new@example.test', 'password' => 'password123', 'password_confirmation' => 'password123',
            'permissions' => $permissions,
        ])->assertSessionHasNoErrors();
        $user = Auth::guard($role)->getProvider()->retrieveByCredentials(['username' => 'new.'.$role]);
        $this->assertSame($permissions, $user->permissions);
    }

    public function test_creation_cannot_assign_another_roles_modules(): void
    {
        $this->actingAs($this->account('superadmin'), 'superadmin')->post('/superadmin/system-users', [
            'role' => 'Customer', 'name' => 'Invalid Account', 'username' => 'invalid.customer',
            'email' => 'invalid@example.test', 'password' => 'password123', 'password_confirmation' => 'password123',
            'permissions' => ['dashboard', 'pos'],
        ])->assertSessionHasErrors('permissions.1');
        $this->assertDatabaseMissing('customers', ['username' => 'invalid.customer']);
    }
}
