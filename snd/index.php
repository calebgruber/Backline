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
    ?>
    <section class="launch-screen launch-screen-snd">
        <div class="launch-screen-panel">
            <span class="badge bg-purple-lt mb-3">Sound</span>
            <div class="preloader-spinner mb-3" role="status" aria-hidden="true"></div>
            <h2 class="mb-2">Opening Backline SND</h2>
            <p class="text-secondary mb-3">Preparing your show workspace, orders, and revisions…</p>
            <a class="btn btn-primary btn-sm" href="/snd/app">Open SND now</a>
        </div>
    </section>
    <script>window.setTimeout(function(){window.location.href="/snd/app";}, 1100);</script>
    <?php
}, $user);
