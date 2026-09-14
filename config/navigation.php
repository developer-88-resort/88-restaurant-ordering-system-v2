<?php

/**
 * THE single source of truth for the admin sidebar.
 *
 * Two completely different render stacks show this menu — the legacy
 * Blade+Alpine layout (layouts/app.blade.php) and the Inertia+React one
 * (AuthenticatedLayout.jsx) — and before M4 each hardcoded its own item
 * list. They drifted: Weigh & Order and Daily Market Prices existed only
 * in the React sidebar, Quotations only in the Blade one. A user on
 * /orders and a user on /weigh saw two different apps.
 *
 * Both layouts now read this array through App\Support\Navigation, which
 * resolves visibility (permission), translation (label), and the active
 * route highlight identically for either stack. Add a page here once and
 * it appears everywhere, correctly, immediately.
 *
 * 'permission' is one of:
 *   null            — visible to any operational user (superadmin/admin/staff)
 *   'superadmin'     — superadmin only
 *   'reports'        — superadmin or admin
 *   any other string — a Gate ability, checked via $user->can($ability)
 *
 * 'icon' is a key, not markup — each render stack keeps its own small SVG
 * lookup by that key (Blade: resources/views/components/sidebar-icon.blade.php,
 * React: the `icons` object in AuthenticatedLayout.jsx). The two SVG sets
 * only need to agree on which KEYS exist, not on markup, since Blade and
 * JSX can't share a literal template — see the nav parity test, which
 * fails loudly if a route's rendered sidebar is missing an item the config
 * says it should have.
 */
return [
    'groups' => [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'permission' => null,
            'items' => [
                [
                    'key' => 'overview',
                    'label' => 'Overview',
                    'icon' => 'overview',
                    'route' => 'superadmin.dashboard',
                    'active' => ['superadmin.dashboard'],
                    'inertia' => true,
                    'permission' => null,
                ],
                [
                    'key' => 'chat',
                    'label' => 'Chat',
                    'icon' => 'chat',
                    'route' => 'chat.index',
                    'active' => ['chat.*'],
                    'inertia' => true,
                    'permission' => null,
                    'badge' => 'unread_chat',
                ],
            ],
        ],
        [
            'key' => 'operations',
            'label' => 'Operations',
            'permission' => null,
            'items' => [
                [
                    'key' => 'orders',
                    'label' => 'Order Management',
                    'icon' => 'orders',
                    'route' => 'orders.index',
                    'active' => ['orders.*'],
                    'inertia' => false,
                    'permission' => null,
                    'badge' => 'pending_orders',
                ],
                [
                    'key' => 'weigh',
                    'label' => 'Weigh & Order',
                    'icon' => 'scale',
                    'route' => 'weigh.station',
                    'active' => ['weigh.station'],
                    'inertia' => true,
                    'permission' => 'weigh.record',
                ],
                [
                    // Was 'weigh's sole remaining child once Weigh Log moved
                    // into Reports (see 'reports' below) — a submenu with
                    // exactly one child is awkward UX, so it's flattened to
                    // a top-level sibling instead of staying nested.
                    'key' => 'weigh-prices',
                    'label' => 'Daily Market Prices',
                    'icon' => 'marketPrices',
                    'route' => 'weigh.prices.index',
                    'active' => ['weigh.prices.*'],
                    'inertia' => true,
                    'permission' => 'weigh.set_daily_price',
                ],
                [
                    'key' => 'weigh-items',
                    'label' => 'Weighted Items',
                    'icon' => 'scale',
                    'route' => 'weigh.items.index',
                    'active' => ['weigh.items.*'],
                    'inertia' => true,
                    'permission' => 'weigh.set_daily_price',
                ],
                [
                    'key' => 'weigh-cooking-styles',
                    'label' => 'Cooking Styles',
                    'icon' => 'cookingStyles',
                    'route' => 'weigh.cooking-styles.index',
                    'active' => ['weigh.cooking-styles.*'],
                    'inertia' => true,
                    'permission' => 'weigh.set_daily_price',
                ],
                [
                    'key' => 'kitchen',
                    'label' => 'Kitchen',
                    'icon' => 'kitchen',
                    'route' => 'kitchen.index',
                    'active' => ['kitchen.*'],
                    'inertia' => false,
                    'permission' => null,
                    'badge' => 'pending_orders',
                ],
                [
                    'key' => 'quotations',
                    'label' => 'Quotations',
                    'icon' => 'quotations',
                    'route' => 'quotations.index',
                    'active' => ['quotations.*'],
                    'inertia' => true,
                    'permission' => null,
                ],
                [
                    'key' => 'spaces',
                    'label' => 'Spaces',
                    'icon' => 'spaces',
                    'route' => 'spaces.index',
                    'active' => ['spaces.*', 'areas.*', 'space-categories.*'],
                    'inertia' => true,
                    'permission' => null,
                ],
            ],
        ],
        [
            'key' => 'management',
            'label' => 'Management',
            'permission' => null,
            'items' => [
                [
                    'key' => 'menu-items',
                    'label' => 'Menu Management',
                    'icon' => 'menu',
                    'route' => 'menu-items.index',
                    'active' => ['menu-items.*', 'menu-categories.*'],
                    'inertia' => true,
                    'permission' => null,
                ],
                [
                    // Weighed Lines (formerly the standalone /weigh/log
                    // page) is a tab on this same page now — active still
                    // matches so the sidebar highlights Reports on either
                    // tab, even though the tab itself is Inertia while the
                    // Overview tab is Blade.
                    'key' => 'reports',
                    'label' => 'Reports',
                    'icon' => 'reports',
                    'route' => 'superadmin.reports.index',
                    'active' => ['superadmin.reports.*'],
                    'inertia' => false,
                    'permission' => 'reports',
                ],
                [
                    'key' => 'promotions',
                    'label' => 'Promotions & Banners',
                    'icon' => 'promotions',
                    'route' => 'superadmin.promotions.index',
                    'active' => ['superadmin.promotions.*'],
                    'inertia' => true,
                    'permission' => 'promotions',
                ],
            ],
        ],
        [
            'key' => 'administration',
            'label' => 'Administration',
            'permission' => 'superadmin',
            'items' => [
                [
                    'key' => 'users',
                    'label' => 'User Management',
                    'icon' => 'users',
                    'route' => 'superadmin.users.index',
                    'active' => ['superadmin.users.*'],
                    'inertia' => false,
                    'permission' => 'superadmin',
                ],
                [
                    'key' => 'audit-logs',
                    'label' => 'Audit Logs',
                    'icon' => 'auditLogs',
                    'route' => 'superadmin.audit-logs.index',
                    'active' => ['superadmin.audit-logs.*'],
                    'inertia' => false,
                    'permission' => 'superadmin',
                ],
            ],
        ],
        [
            'key' => 'system',
            'label' => 'System',
            'permission' => 'superadmin',
            'items' => [
                [
                    'key' => 'settings',
                    'label' => 'Settings',
                    'icon' => 'settings',
                    'route' => 'superadmin.settings.edit',
                    'active' => ['superadmin.settings.*'],
                    'inertia' => false,
                    'permission' => 'superadmin',
                ],
            ],
        ],
    ],
];
