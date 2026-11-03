<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    $root.'/resources/views/auth/login.blade.php',
    $root.'/app/Http/Requests/DashboardLoginRequest.php',
    $root.'/app/Http/Controllers/DashboardAuthController.php',
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "Cannot read {$file}\n");
        exit(1);
    }
    if (str_contains($content, 'device_label')) {
        fwrite(STDERR, "FAIL: device_label still present in {$file}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: login no longer contains device_label.\n");
