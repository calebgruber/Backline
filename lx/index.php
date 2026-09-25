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

render_page('Lighting', function () use ($user): void {
    echo '<div class="card show-context"><div class="card-body"><h3 class="card-title">Lighting App</h3><p class="text-secondary">Orders, revisions, rules, paperwork exports, and inventory usage for LX are scaffolded.</p></div></div>';
}, $user);
