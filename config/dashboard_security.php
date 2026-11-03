<?php

return [
    'connection' => env('DASHBOARD_SECURITY_CONNECTION', 'sqlite_targets'),
    'password_min_length' => max(1, (int) env('DASHBOARD_PASSWORD_MIN_LENGTH', 8)),
    'device_cookie' => env('DASHBOARD_DEVICE_COOKIE', 'service_sparepart_device_token'),
    'device_cookie_minutes' => (int) env('DASHBOARD_DEVICE_COOKIE_MINUTES', 525600),
    'device_cookie_secure' => env('DASHBOARD_DEVICE_COOKIE_SECURE', env('APP_ENV') === 'production'),
    'device_cookie_same_site' => env('DASHBOARD_DEVICE_COOKIE_SAME_SITE', 'strict'),
    'session_idle_minutes' => (int) env('DASHBOARD_SESSION_IDLE_MINUTES', 30),
    'login_max_attempts' => (int) env('DASHBOARD_LOGIN_MAX_ATTEMPTS', 5),
    'login_ip_max_attempts' => (int) env('DASHBOARD_LOGIN_IP_MAX_ATTEMPTS', 15),
    'login_decay_seconds' => (int) env('DASHBOARD_LOGIN_DECAY_SECONDS', 60),
    'session_user_id_key' => 'dashboard_user_id',
    'session_user_name_key' => 'dashboard_user_name',
    'session_user_role_key' => 'dashboard_user_role',
    'session_device_id_key' => 'dashboard_device_id',
    'session_last_activity_key' => 'dashboard_last_activity_at',
    'pending_user_id_key' => 'dashboard_pending_user_id',
    'pending_device_id_key' => 'dashboard_pending_device_id',

    'roles' => [
        'admin' => 'Administrator',
        'service' => 'Service Sparepart',
        'viewer' => 'Viewer Sparepart',
    ],

    'menus' => [
        'service_quotations' => [
            'label' => 'Penawaran Service',
            'route' => 'service-quotations.index',
            'group' => 'Service',
        ],
        'sparepart_search' => [
            'label' => 'Pencarian Sparepart',
            'route' => 'spareparts.search',
            'group' => 'Service',
        ],
        'security_users' => [
            'label' => 'Pengguna',
            'route' => 'security.users.index',
            'group' => 'Keamanan',
        ],
        'security_roles' => [
            'label' => 'Role & Permission',
            'route' => 'security.roles.index',
            'group' => 'Keamanan',
        ],
        'security_devices' => [
            'label' => 'Riwayat Perangkat',
            'route' => 'security.devices.index',
            'group' => 'Keamanan',
        ],
        'security_audits' => [
            'label' => 'Audit Aktivitas',
            'route' => 'security.audits.index',
            'group' => 'Keamanan',
        ],
    ],

    'landing_menu_order' => [
        'service_quotations',
        'sparepart_search',
        'security_users',
        'security_roles',
        'security_devices',
        'security_audits',
    ],

    'legacy_role_menus' => [
        'service' => ['service_quotations', 'sparepart_search'],
        'viewer' => ['sparepart_search'],
    ],

    'audited_routes' => [
        'spareparts.search' => 'search_spareparts',

        'service-quotations.index' => 'view_service_quotations',
        'service-quotations.export' => 'export_service_quotations',
        'service-quotations.sparepart-report' => 'view_service_sparepart_report',
        'service-quotations.sparepart-report.export' => 'export_service_sparepart_report',
        'service-quotations.approval-status' => 'update_service_quotation_approval',
        'service-quotations.create' => 'create_service_quotation_form',
        'service-quotations.store' => 'create_service_quotation',
        'service-quotations.edit' => 'edit_service_quotation_form',
        'service-quotations.update' => 'update_service_quotation',
        'service-quotations.show' => 'view_service_quotation',
        'service-quotations.print' => 'print_service_quotation',
        'service-quotations.print-receipt' => 'print_service_receipt',
        'service-quotations.send-erp' => 'send_service_quotation_erp',
        'service-quotations.service-centers' => 'view_service_centers',
        'service-quotations.service-centers.save' => 'update_service_centers',
        'service-quotations.service-customers' => 'view_service_customers',
        'service-quotations.service-customers.save' => 'update_service_customers',

        'security.users.store' => 'create_dashboard_user',
        'security.users.update' => 'update_dashboard_user',
        'security.users.password' => 'reset_dashboard_user_password',
        'security.roles.store' => 'create_dashboard_role',
        'security.roles.update' => 'update_dashboard_role',
        'account.password.update' => 'change_own_password',
        'security.devices.update' => 'rename_dashboard_device',
    ],
];
