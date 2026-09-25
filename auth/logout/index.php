<?php

declare(strict_types=1);

if (!function_exists('app_config')) {
    $bootstrapRoot = __DIR__;
    while (!file_exists($bootstrapRoot . '/shared/bootstrap.php')) {
        $parent = dirname($bootstrapRoot);
        if ($parent === $bootstrapRoot) {
            break;
        }
        $bootstrapRoot = $parent;
    }
    require_once $bootstrapRoot . '/shared/bootstrap.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    redirect('/');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
} else {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

auth_logout();
flash_set('info', 'Signed out.');
redirect('/');
