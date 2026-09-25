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

render_page('Launch LX', function (): void {
    echo '<div class="card show-context"><div class="card-body text-center py-5"><h2 class="mb-2">Launching Backline LX</h2><p class="text-secondary mb-4">Prepare your lighting workflow and open the application.</p><a href="/lx/app" class="btn btn-primary btn-lg">Open Backline LX</a></div></div>';
}, $user);
