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

$user = require_auth();

render_page('Dashboard', function () use ($user): void {
    ?>
    <div class="row row-cards">
        <div class="col-12">
            <div class="card show-context"><div class="card-body">
                <h3 class="card-title">Welcome to Backline</h3>
                <p class="text-secondary mb-0">Use the admin area to configure inventory, shows, settings, and migrations.</p>
            </div></div>
        </div>
        <div class="col-md-4"><a href="/admin/settings" class="card card-link"><div class="card-body"><strong>System Settings</strong><p class="text-secondary mb-0">Migrations, branding, DB operations.</p></div></a></div>
        <div class="col-md-4"><a href="/admin/inventory" class="card card-link"><div class="card-body"><strong>Inventory</strong><p class="text-secondary mb-0">Manage LX and SND items.</p></div></a></div>
        <div class="col-md-4"><a href="/resources" class="card card-link"><div class="card-body"><strong>Resources</strong><p class="text-secondary mb-0">Lighting, Sound, Backline Manuals.</p></div></a></div>
    </div>
    <?php
}, $user);
