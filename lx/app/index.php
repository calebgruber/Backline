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

$user = require_permission('lx.access');
require_once __DIR__ . '/../../shared/shop_app.php';

render_shop_app_page($user, 'lx', 'Lighting', 'Backline LX', 'Create an initial order to start LX revision tracking.', true);
