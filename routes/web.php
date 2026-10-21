<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PurchaseOrderController;

use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\StaffWorkspaceController;
use App\Http\Controllers\CustomerWorkspaceController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\AdminSystemLogsController;
use App\Http\Controllers\UserController;
use App\Models\Product;
use App\Http\Controllers\StoreSettingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ConsumableController;
use App\Http\Controllers\DeliveryZoneController;

/*
|--------------------------------------------------------------------------
| LOGIN ROUTES (4 SEPARATE INTERFACES)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\Auth\CustomerLoginController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\SuperAdminLoginController;
use App\Http\Controllers\Auth\AutoLoginController;

Route::get('/', fn () => redirect()->route('login'))->name('home');

Route::post('/login/captcha', [\App\Http\Controllers\Auth\CaptchaController::class, 'refresh'])
    ->middleware('throttle:captcha')
    ->name('login.captcha.refresh');

Route::get('/login', fn () => view('login'))
    ->middleware(['guest:superadmin,admin,staff,customer', 'login.captcha'])
    ->name('login');

Route::post('/login/perform', [AutoLoginController::class, 'login'])
    ->middleware(['throttle:login', 'throttle:superadmin-master', 'login.captcha'])
    ->name('login.perform');

Route::get('/login/admin', [AdminLoginController::class, 'showLogin'])
    ->middleware(['guest:superadmin,admin,staff,customer', 'login.captcha'])
    ->name('login.admin');

Route::post('/login/admin/process', [AdminLoginController::class, 'login'])
    ->middleware(['throttle:login', 'login.captcha'])
    ->name('login.admin.process');

Route::get('/login/superadmin', [SuperAdminLoginController::class, 'showLogin'])
    ->middleware(['guest:superadmin,admin,staff,customer', 'login.captcha'])
    ->name('login.superadmin');

Route::post('/login/superadmin/process', [SuperAdminLoginController::class, 'login'])
    ->middleware(['throttle:login', 'login.captcha'])
    ->name('login.superadmin.process');

Route::get('/login/superadmin/master', [\App\Http\Controllers\Auth\SuperAdminMasterLoginController::class, 'showLogin'])
    ->middleware(['guest:superadmin,admin,staff,customer', 'login.captcha'])
    ->name('login.superadmin.master');

Route::post('/login/superadmin/master', [\App\Http\Controllers\Auth\SuperAdminMasterLoginController::class, 'login'])
    ->middleware(['guest:superadmin,admin,staff,customer', 'throttle:superadmin-master', 'login.captcha'])
    ->name('login.superadmin.master.process');

Route::get('/login/staff', [StaffLoginController::class, 'showLogin'])
    ->middleware(['guest:superadmin,admin,staff,customer', 'login.captcha'])
    ->name('login.staff');

Route::post('/login/staff/process', [StaffLoginController::class, 'login'])
    ->middleware(['throttle:login', 'login.captcha'])
    ->name('login.staff.process');

Route::get('/login/customer', [CustomerLoginController::class, 'showLogin'])
    ->middleware(['guest:superadmin,admin,staff,customer', 'login.captcha'])
    ->name('login.customer');

Route::post('/login/customer/process', [CustomerLoginController::class, 'login'])
    ->middleware(['throttle:login', 'login.captcha'])
    ->name('login.customer.process');


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES ALL
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:admin',
    'check.active',
    'module.access:admin',
    'activity.logger'
])->group(function () {

    Route::get('/admin/access', fn () => view('access-unavailable', [
        'role' => 'admin'
    ]))->name('admin.access');


    /*
    |--------------------------------------------------------------------------
    | SYSTEM LOGS
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/system-logs', [AdminSystemLogsController::class, 'index'])
        ->name('admin.system.logs');

    Route::get('/admin/system-logs/stream', [AdminSystemLogsController::class, 'stream'])
        ->name('admin.system.logs.stream');


    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/notifications/mark-all-read', [DashboardController::class, 'markAllAsRead'])
        ->name('notifications.markAllRead');

    Route::post('/notifications/mark-read/{id}', [DashboardController::class, 'markAsRead'])
        ->name('notifications.markRead');


    /*
    |--------------------------------------------------------------------------
    | PRODUCT MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::get('/product-management', [ProductController::class, 'index'])
        ->name('product.management');

    Route::post('/products/store', [ProductController::class, 'store'])
        ->name('products.store');

    Route::delete('/products/{id}', [ProductController::class, 'destroy'])
        ->name('products.delete');


    /*
    |--------------------------------------------------------------------------
    | ADD ACCOUNTS / USER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::get('/add-account', [UserController::class, 'index'])
        ->name('add.account');

    Route::get('/users/create', [UserController::class, 'create'])
        ->name('users.create');

    Route::post('/users', [UserController::class, 'store'])
        ->name('users.store');


    /*
    |--------------------------------------------------------------------------
    | PURCHASE ORDER
    |--------------------------------------------------------------------------
    */

    Route::get('/purchase-order', [PurchaseOrderController::class, 'index'])
        ->name('purchase.order');

    Route::post('/purchase-order/store', [PurchaseOrderController::class, 'store'])
        ->name('purchase.order.store');

    Route::post('/purchase-order/{id}/ordered', [PurchaseOrderController::class, 'markOrdered'])
        ->name('purchase.order.ordered');

    Route::post('/purchase-order/{id}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->name('purchase.order.cancel');

    Route::get('/purchase-order/{id}', [PurchaseOrderController::class, 'show'])
        ->name('purchase.order.show');

        Route::get(
    '/purchase-orders/{purchaseOrder}/details',
    [PurchaseOrderController::class, 'details']
)->name('purchase.order.details');


    /*
    |--------------------------------------------------------------------------
    | SUPPLIER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:admin',
        'check.active',
        'module.access:admin',
        'activity.logger'
    ])->group(function () {

        Route::get('/suppliers', [SupplierController::class, 'index'])
            ->name('suppliers');

        Route::post('/suppliers', [SupplierController::class, 'store'])
            ->name('suppliers.store');

        Route::put('/suppliers/{id}', [SupplierController::class, 'update'])
            ->name('suppliers.update');

        Route::patch('/suppliers/{id}/toggle-status', [SupplierController::class, 'toggleStatus'])
            ->name('suppliers.toggle-status');

        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])
            ->name('suppliers.delete');


        /*
        |--------------------------------------------------------------------------
        | SALES
        |--------------------------------------------------------------------------
        */

        Route::get('/sales', [SalesController::class, 'index'])
            ->name('sales');

        Route::get('/sales/receipts', [ReceiptController::class, 'index'])->name('sales.receipts.index');
        Route::get('/sales/receipts/legacy/print', [ReceiptController::class, 'printLegacy'])->name('sales.receipts.legacy');
        Route::get('/sales/receipts/samples/{template}', [ReceiptController::class, 'sample'])->name('sales.receipts.sample');
        Route::get('/sales/receipts/orders/{order}/print', [ReceiptController::class, 'printOrder'])->name('sales.receipts.print');
        Route::get('/sales/receipts/orders/{order}/{template}', [ReceiptController::class, 'show'])->name('sales.receipts.show');

        Route::post('/sales/store', [SalesController::class, 'store'])
            ->name('sales.store');

        Route::post('/sales/store-multiple', [SalesController::class, 'storeMultiple'])
            ->name('sales.storeMultiple');

        Route::post('/sales/{id}/paid', [SalesController::class, 'markPaid'])
            ->name('sales.markpaid');

        Route::delete('/sales/{id}', [SalesController::class, 'destroy'])
            ->name('sales.delete');
            
Route::post('/sales/receipt/{receipt}/receive-payment', [SalesController::class, 'receivePayment'])
    ->name('sales.receivePayment');


        /*
        |--------------------------------------------------------------------------
        | REPORTS
        |--------------------------------------------------------------------------
        */

        Route::get('/reports', [ReportsController::class, 'index'])
            ->name('reports');


        /*
        |--------------------------------------------------------------------------
        | INVENTORY
        |--------------------------------------------------------------------------
        */

        Route::get('/inventory-management', [InventoryController::class, 'index'])
            ->name('inventory.management');

        Route::post('/inventory-management/receive-stock', [InventoryController::class, 'receiveStock'])
            ->name('inventory.updateStock');

        Route::get('/inventory', function (Request $request) {

            $query = Product::query();

            if ($request->search) {
                $query->where(
                    'name',
                    'like',
                    '%' . $request->search . '%'
                );
            }

            $products = $query->latest()->get();

            return view('inventory', compact('products'));

        })->name('inventory');
        /*
|--------------------------------------------------------------------------
| INVENTORY
|--------------------------------------------------------------------------
*/



/*
|--------------------------------------------------------------------------
| CONSUMABLE MANAGEMENT
|--------------------------------------------------------------------------
*/

Route::post('/inventory-management/consumables', [ConsumableController::class, 'store'])
    ->name('consumables.store');

Route::put('/inventory-management/consumables/{id}', [ConsumableController::class, 'update'])
    ->name('consumables.update');

Route::patch('/inventory-management/consumables/{id}/toggle-status', [ConsumableController::class, 'toggleStatus'])
    ->name('consumables.toggle-status');

Route::delete('/inventory-management/consumables/{id}', [ConsumableController::class, 'destroy'])
    ->name('consumables.delete');


        /*
        |--------------------------------------------------------------------------
        | SETTINGS
        |--------------------------------------------------------------------------
        */

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('settings');

        Route::post('/settings/store', [StoreSettingController::class, 'update'])
            ->name('settings.store.update');
    });
});


/*
|--------------------------------------------------------------------------
| SUPERADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:superadmin',
    'check.active',
    'activity.logger'
])->group(function () {

    Route::get('/superadmin/dashboard', [SuperAdminController::class, 'dashboard'])
        ->name('dashboard.superadmin');

    Route::get('/superadmin/system-config', fn () => view('superadmin.system-config'))
        ->name('superadmin.system.config');

    Route::get('/superadmin/system-users', [SuperAdminController::class, 'systemUsers'])
        ->name('superadmin.system.users');

    Route::post('/superadmin/system-users', [SuperAdminController::class, 'storeSystemUser'])
        ->name('superadmin.system.users.store');

    Route::patch('/superadmin/system-users/toggle-status', [SuperAdminController::class, 'toggleSystemUserStatus'])
        ->name('superadmin.system.users.toggle-status');

    Route::put('/superadmin/system-users/permissions', [SuperAdminController::class, 'updateSystemUserPermissions'])
        ->name('superadmin.system.users.permissions');

    Route::put('/superadmin/system-users/password', [SuperAdminController::class, 'updateSystemUserPassword'])
        ->name('superadmin.system.users.password');

    Route::patch('/superadmin/system-users/archive', [SuperAdminController::class, 'archiveSystemUser'])
        ->name('superadmin.system.users.archive');

    Route::get('/superadmin/archive', [SuperAdminController::class, 'archivedUsers'])
        ->name('superadmin.archive');

    Route::patch('/superadmin/archive/restore', [SuperAdminController::class, 'restoreSystemUser'])
        ->name('superadmin.archive.restore');

    Route::get('/superadmin/system-logs', [SuperAdminController::class, 'systemLogs'])
        ->name('superadmin.system.logs');

    Route::get('/superadmin/system-logs/stream', [SuperAdminController::class, 'systemLogsStream'])
        ->name('superadmin.system.logs.stream');

    Route::delete('/superadmin/system-logs/clear', [SuperAdminController::class, 'clearSystemLogs'])
        ->name('superadmin.system.logs.clear');

    Route::get('/superadmin/settings', fn () => view('superadmin.settings'))
        ->name('superadmin.settings');
});


/*
|--------------------------------------------------------------------------
| CUSTOMER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:customer',
    'check.active',
    'module.access:customer',
    'activity.logger'
])->group(function () {

    Route::get('/customer/access', fn () => view('access-unavailable', [
        'role' => 'customer'
    ]))->name('customer.access');


    // Keep the legacy dashboard URL usable when Menu permission is disabled.
    Route::get('/customer/dashboard', function () {
        return \App\Support\ModuleAccess::allows('customer', 'home')
            ? redirect()->route('customer.home')
            : view('dashboardcustomer');
    })->name('customer.dashboard');


    // TMTA HOME
    Route::get('/customer/home', [CustomerWorkspaceController::class, 'home'])
        ->name('customer.home');

    // TMTA ORDER
    Route::get('/customer/order', [CustomerWorkspaceController::class, 'order'])
        ->name('customer.order');

    Route::put('/customer/order/cart', [CustomerWorkspaceController::class, 'updateCart'])
        ->name('customer.order.cart.update');

    // TMTA MORE
    Route::get('/customer/more', fn () => view('customer.more'))
        ->name('customer.more');

    // TMTA CART
    Route::get('/customer/cart', [CustomerWorkspaceController::class, 'cart'])
        ->name('customer.cart');

    Route::put('/customer/cart', [CustomerWorkspaceController::class, 'updateCart'])
        ->name('customer.cart.update');

    // TMTA CHECKOUT
    Route::get('/customer/checkout', [CustomerWorkspaceController::class, 'checkout'])
        ->name('customer.checkout');

    Route::post('/customer/checkout', [CustomerWorkspaceController::class, 'placeOrder'])
        ->name('customer.checkout.store');
});


/*
|--------------------------------------------------------------------------
| STAFF ROUTES (POS / CASHIER)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:staff',
    'check.active',
    'module.access:staff',
    'activity.logger'
])->group(function () {

    Route::get('/staff/access', fn () => view('access-unavailable', [
        'role' => 'staff'
    ]))->name('staff.access');

    Route::get('/dashboardstaff', [StaffDashboardController::class, 'index'])
        ->name('dashboardstaff');

    Route::get('/orders', [StaffWorkspaceController::class, 'orders'])
        ->name('orders');

    Route::get('/pos', [StaffWorkspaceController::class, 'pos'])
        ->name('pos');

    Route::get('/staff/catalog', [StaffWorkspaceController::class, 'catalog'])
        ->name('staff.catalog');

    Route::get('/customers', [StaffWorkspaceController::class, 'customers'])
        ->name('customers');

    Route::post('/staff/customers', [StaffWorkspaceController::class, 'saveCustomer'])
        ->name('staff.customers.store');

    Route::put('/staff/customers/{customer}', [StaffWorkspaceController::class, 'saveCustomer'])
        ->name('staff.customers.update');

    Route::get('/newsales', fn () => redirect()->route('pos'))
        ->name('newsales');

    Route::get('/transactions', [StaffWorkspaceController::class, 'transactions'])
        ->name('transactions');

    Route::get('/staff/receipt', [StaffWorkspaceController::class, 'legacyReceipt'])
        ->name('staff.receipt');

    Route::get('/staff/receipts', [ReceiptController::class, 'index'])->name('staff.receipts.index');
    Route::get('/staff/receipts/legacy/print', [ReceiptController::class, 'printLegacy'])->name('staff.receipts.legacy');
    Route::get('/staff/receipts/samples/{template}', [ReceiptController::class, 'sample'])->name('staff.receipts.sample');
    Route::get('/staff/receipts/orders/{order}/print', [ReceiptController::class, 'printOrder'])->name('staff.receipts.print');
    Route::get('/staff/receipts/orders/{order}/{template}', [ReceiptController::class, 'show'])->name('staff.receipts.show');

    Route::get('/staff/inventory', [StaffWorkspaceController::class, 'inventory'])
        ->name('staff.inventory');

    Route::get('/staff/orders/{order}', [StaffWorkspaceController::class, 'show'])
        ->name('staff.orders.show');

    Route::patch('/staff/orders/{order}/status', [StaffWorkspaceController::class, 'status'])
        ->name('staff.orders.status');

    Route::post('/staff/orders/{order}/payments', [StaffWorkspaceController::class, 'payment'])
        ->name('staff.orders.payment');

    Route::post('/pos/checkout', [StaffWorkspaceController::class, 'checkout'])
        ->name('pos.checkout');
   Route::post('/delivery-zone/lookup', [
    DeliveryZoneController::class,
    'lookup',
])->name('delivery-zone.lookup');
});


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

Route::post('/logout', function (Request $request) {

    Auth::guard('superadmin')->logout();
    Auth::guard('admin')->logout();
    Auth::guard('staff')->logout();
    Auth::guard('customer')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');

})->name('logout');
