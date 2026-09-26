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
$canAdmin = user_has_permission($user, 'admin.access');
$canLx = user_has_permission($user, 'lx.access');
$canSnd = user_has_permission($user, 'snd.access');

$showScopeFilter = [];
if ($canLx) {
    $showScopeFilter[] = 'lx';
}
if ($canSnd) {
    $showScopeFilter[] = 'snd';
}

if ($canAdmin) {
    if (empty($showScopeFilter)) {
        $showRows = db()->query('SELECT id, show_name, theatre_name, show_scope, updated_at FROM shows WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC')->fetchAll();
    } else {
        $placeholders = implode(',', array_fill(0, count($showScopeFilter), '?'));
        $stmt = db()->prepare('SELECT id, show_name, theatre_name, show_scope, updated_at FROM shows WHERE deleted_at IS NULL AND COALESCE(show_scope, "both") IN ("both",' . $placeholders . ') ORDER BY updated_at DESC, id DESC');
        $stmt->execute($showScopeFilter);
        $showRows = $stmt->fetchAll();
    }
} else {
    if (empty($showScopeFilter)) {
        $showRows = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($showScopeFilter), '?'));
        $stmt = db()->prepare('SELECT id, show_name, theatre_name, show_scope, updated_at FROM shows WHERE deleted_at IS NULL AND owner_user_id = ? AND COALESCE(show_scope, "both") IN ("both",' . $placeholders . ') ORDER BY updated_at DESC, id DESC');
        $stmt->execute([(int) $user['id'], ...$showScopeFilter]);
        $showRows = $stmt->fetchAll();
    }
}

render_page('Dashboard', function () use ($user, $canAdmin, $canLx, $canSnd, $showRows): void {
    ?>
    <div class="mb-4">
        <h1 class="display-4 fw-bold mb-1">Welcome back, <?= e($user['name']) ?> 👋</h1>
        <p class="text-secondary mb-0">Open a show card to jump directly into your workspace.</p>
    </div>
    <div class="row row-cards">
        <?php if ($showRows): ?>
            <?php foreach ($showRows as $show): ?>
                <?php $scope = strtolower((string) ($show['show_scope'] ?? 'both')); ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h3 class="card-title mb-1"><?= e((string) $show['show_name']) ?></h3>
                            <p class="text-secondary mb-2"><?= e((string) ($show['theatre_name'] ?? '')) ?></p>
                            <div class="small mb-3 text-secondary">
                                Scope:
                                <?= e($scope === 'lx' ? 'LX only' : ($scope === 'snd' ? 'Sound only' : 'Both')) ?>
                            </div>
                            <div class="mt-auto d-flex gap-2 flex-wrap">
                                <?php if ($canLx && in_array($scope, ['lx', 'both'], true)): ?>
                                    <a class="btn btn-primary btn-sm" href="/dash/lx?show=<?= (int) $show['id'] ?>&tab=info">Open LX</a>
                                <?php endif; ?>
                                <?php if ($canSnd && in_array($scope, ['snd', 'both'], true)): ?>
                                    <a class="btn btn-primary btn-sm" href="/dash/sound?show=<?= (int) $show['id'] ?>&tab=info">Open Sound</a>
                                <?php endif; ?>
                                <a class="btn btn-outline-secondary btn-sm" href="/dash/shows?edit=<?= (int) $show['id'] ?>">Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-secondary">No shows available yet for your current roles. Create one in Shows.</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="row row-cards mt-2">
        <div class="col-md-4"><a href="/dash/shows" class="card card-link"><div class="card-body"><strong>Shows</strong><p class="text-secondary mb-0">Create, manage, and edit your shows.</p></div></a></div>
        <?php if ($canAdmin): ?>
        <div class="col-md-4"><a href="/admin/settings" class="card card-link"><div class="card-body"><strong>System Settings</strong><p class="text-secondary mb-0">Migrations, branding, DB operations.</p></div></a></div>
        <div class="col-md-4"><a href="/admin/users" class="card card-link"><div class="card-body"><strong>Users</strong><p class="text-secondary mb-0">Manage user invites and role assignments.</p></div></a></div>
        <div class="col-md-4"><a href="/admin/inventory" class="card card-link"><div class="card-body"><strong>Inventory</strong><p class="text-secondary mb-0">Manage LX and SND items.</p></div></a></div>
        <div class="col-md-4"><a href="/admin/categories" class="card card-link"><div class="card-body"><strong>Categories</strong><p class="text-secondary mb-0">Manage and organize shop categories.</p></div></a></div>
        <div class="col-md-4"><a href="/admin/resources" class="card card-link"><div class="card-body"><strong>Resources Admin</strong><p class="text-secondary mb-0">Manage resources folders and files.</p></div></a></div>
        <?php endif; ?>
        <div class="col-md-4"><a href="/resources" class="card card-link"><div class="card-body"><strong>Resources</strong><p class="text-secondary mb-0">Browse Lighting, Sound, Backline Manuals.</p></div></a></div>
    </div>
    <?php
}, $user);
