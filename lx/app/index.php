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

render_page('Lighting', function (): void {
    echo '<div class="card show-context"><div class="card-body"><div class="d-flex align-items-center justify-content-between gap-2 flex-wrap"><h3 class="card-title mb-0">Backline LX</h3><a href="/dash/home" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i>Back to Dashboard</a></div><p class="text-secondary mt-3 mb-0">Orders, revisions, rules, paperwork exports, and inventory usage for LX are scaffolded.</p></div></div>';
}, $user);
