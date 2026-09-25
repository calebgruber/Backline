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

render_page('Launch SND', function (): void {
    echo '<div class="launch-overlay" id="snd-launch-overlay"><div class="launch-overlay-card"><div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div><h2 class="mb-2">Launching Backline SND</h2><p class="text-secondary mb-3">Preparing your sound workspace…</p><p class="small text-secondary mb-0">If redirect does not start, <a href="/snd/app">open SND manually</a>.</p></div></div>';
    echo '<script>window.setTimeout(function(){window.location.href="/snd/app";}, 900);</script>';
}, $user);
