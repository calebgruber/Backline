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
    <div class="mb-4">
        <h1 class="display-4 fw-bold mb-1">Welcome back, <?= e($user['name']) ?> 👋</h1>
        <p class="text-secondary mb-0">Use your available tools below to continue your shop-order workflow.</p>
    </div>
    <div class="row row-cards">
        <?php if (user_has_permission($user, 'admin.access')): ?>
        <div class="col-md-4"><a href="/admin/settings" class="card card-link"><div class="card-body"><strong>System Settings</strong><p class="text-secondary mb-0">Migrations, branding, DB operations.</p></div></a></div>
        <?php endif; ?>
        <div class="col-md-4"><a href="/dash/shows" class="card card-link"><div class="card-body"><strong>Shows</strong><p class="text-secondary mb-0">Create, manage, and edit your shows.</p></div></a></div>
        <?php if (user_has_permission($user, 'inventory.manage')): ?>
        <div class="col-md-4"><a href="/admin/inventory" class="card card-link"><div class="card-body"><strong>Inventory</strong><p class="text-secondary mb-0">Manage LX and SND items.</p></div></a></div>
        <?php endif; ?>
        <?php if (user_has_permission($user, 'categories.manage')): ?>
        <div class="col-md-4"><a href="/admin/categories" class="card card-link"><div class="card-body"><strong>Categories</strong><p class="text-secondary mb-0">Manage and organize shop categories.</p></div></a></div>
        <?php endif; ?>
        <?php if (user_has_permission($user, 'users.manage')): ?>
        <div class="col-md-4"><a href="/admin/users" class="card card-link"><div class="card-body"><strong>Users</strong><p class="text-secondary mb-0">Manage user invites and permissions.</p></div></a></div>
        <?php endif; ?>
        <?php if (user_has_permission($user, 'resources.manage')): ?>
        <div class="col-md-4"><a href="/admin/resources" class="card card-link"><div class="card-body"><strong>Resources Admin</strong><p class="text-secondary mb-0">Manage resources folders and files.</p></div></a></div>
        <?php endif; ?>
        <div class="col-md-4"><a href="/resources" class="card card-link"><div class="card-body"><strong>Resources</strong><p class="text-secondary mb-0">Browse Lighting, Sound, Backline Manuals.</p></div></a></div>
        <?php if (user_has_permission($user, 'lx.access')): ?>
        <div class="col-md-4"><a href="/dash/lx" class="card card-link"><div class="card-body"><strong>LX App</strong><p class="text-secondary mb-0">Open lighting show workspace.</p></div></a></div>
        <?php endif; ?>
        <?php if (user_has_permission($user, 'snd.access')): ?>
        <div class="col-md-4"><a href="/dash/sound" class="card card-link"><div class="card-body"><strong>Sound App</strong><p class="text-secondary mb-0">Open sound show workspace.</p></div></a></div>
        <?php endif; ?>
    </div>
    <?php
}, $user);
