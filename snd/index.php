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

$user = require_permission('snd.access');

render_page('Sound', function () use ($user): void {
    echo '<div class="card show-context"><div class="card-body"><h3 class="card-title">Sound App</h3><p class="text-secondary">Shop orders, revisions, labels, cable mapping, and bundles are scaffolded for SND.</p></div></div>';
}, $user);
