<?php

return [

    'admin' => [

        'dashboard' => [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'routes' => [
                'dashboard',
                'notifications.*',
            ],
        ],

        'sales' => [
            'label' => 'Sales',
            'route' => 'sales',
            'routes' => [
                'sales',
                'sales.*',
            ],
        ],

        'products' => [
            'label' => 'Product Management',
            'route' => 'product.management',
            'routes' => [
                'product.management',
                'products.*',
            ],
        ],

        'reports' => [
            'label' => 'Reports',
            'route' => 'reports',
            'routes' => [
                'reports',
            ],
        ],

        'inventory' => [
            'label' => 'Inventory Management',
            'description' => 'Includes Consumables: add, edit, activate, deactivate, and delete.',
            'route' => 'inventory.management',
            'routes' => [
                'inventory',
                'inventory.*',
                'consumables.*',
            ],
        ],

        'purchase_orders' => [
            'label' => 'Purchase Orders',
            'route' => 'purchase.order',
            'routes' => [
                'purchase.order',
                'purchase.order.*',
            ],
        ],

        'suppliers' => [
            'label' => 'Supplier Management',
            'route' => 'suppliers',
            'routes' => ['suppliers', 'suppliers.*'],
        ],

        'accounts' => [
            'label' => 'Create Account',
            'route' => 'add.account',
            'routes' => [
                'add.account',
                'users.create',
                'users.store',
            ],
        ],

        'system_logs' => [
            'label' => 'System Logs',
            'route' => 'admin.system.logs',
            'routes' => [
                'admin.system.logs',
                'admin.system.logs.stream',
            ],
        ],

        'settings' => [
            'label' => 'Settings',
            'route' => 'settings',
            'routes' => [
                'settings',
                'settings.store.update',
            ],
        ],

    ],

    'staff' => [

        'dashboard' => [
            'label' => 'Dashboard',
            'route' => 'dashboardstaff',
            'routes' => [
                'dashboardstaff',
            ],
        ],

        'pos' => [
        'label' => 'Point of Sale',
        'route' => 'pos',
        'routes' => [
            'pos',
            'pos.*',
            'newsales',
            'staff.catalog',
            'staff.orders.show',
            'staff.receipts.*',
            'delivery-zone.lookup',
            ],
        ],

            'transactions' => [
        'label' => 'Transactions',
        'route' => 'transactions',
        'routes' => [
            'transactions',
            'staff.receipt',
            'staff.orders.show',
            'staff.receipts.*',
            ],
        ],
        
        'inventory' => [
            'label' => 'Inventory',
            'route' => 'staff.inventory',
            'routes' => [
                'staff.inventory',
            ],
        ],

        'orders' => [
            'label' => 'Orders',
            'route' => 'orders',
            'routes' => [
                'orders',
                'staff.orders.*',
                'staff.receipts.*',
            ],
        ],

        'customers' => [
            'label' => 'Customers',
            'route' => 'customers',
            'routes' => [
                'customers',
                'staff.customers.*',
            ],
        ],

    ],

    'customer' => [

        'dashboard' => [
            'label' => 'Dashboard',
            'route' => 'customer.dashboard',
            'routes' => ['customer.dashboard'],
        ],

        'home' => [
            'label' => 'Menu',
            'route' => 'customer.home',
            'routes' => [
                'customer.home',
            ],
        ],

        'order' => [
            'label' => 'Order',
            'route' => 'customer.order',
            'routes' => [
                'customer.order',
                'customer.order.*',
            ],
        ],

        'more' => [
            'label' => 'More',
            'route' => 'customer.more',
            'routes' => [
                'customer.more',
            ],
        ],

        'cart' => [
            'label' => 'Cart',
            'route' => 'customer.cart',
            'routes' => ['customer.cart', 'customer.cart.*'],
        ],

        'checkout' => [
            'label' => 'Checkout',
            'route' => 'customer.checkout',
            'routes' => ['customer.checkout', 'customer.checkout.*'],

    ],

    ],
];
