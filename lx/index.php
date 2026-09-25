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
    ?>
    <section class="launch-screen launch-screen-lx">
        <div class="launch-screen-panel">
            <span class="badge bg-blue-lt mb-3">Lighting</span>
            <div class="preloader-spinner mb-3" role="status" aria-hidden="true"></div>
            <h2 class="mb-2">Opening Backline LX</h2>
            <p class="text-secondary mb-3">Preparing your show workspace, orders, and revisions…</p>
            <a class="btn btn-primary btn-sm" href="/lx/app">Open LX now</a>
        </div>
    </section>
    <script>window.setTimeout(function(){window.location.href="/lx/app";}, 1100);</script>
    <?php
}, $user);
