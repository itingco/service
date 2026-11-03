<?php

return [
    'name' => env('DASHBOARD_ADMIN_NAME', 'Dashboard Administrator'),
    'username' => env('DASHBOARD_ADMIN_USER', 'admin'),
    'password' => env('DASHBOARD_ADMIN_PASSWORD'),
    'session_key' => 'dashboard_authenticated',
    'username_session_key' => 'dashboard_username',
];
