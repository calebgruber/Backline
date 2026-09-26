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
$canLx = user_has_permission($user, 'lx.access') || user_has_permission($user, 'lx.shop');
$canSnd = user_has_permission($user, 'snd.access') || user_has_permission($user, 'snd.shop');
$nameParts = preg_split('/\s+/', trim((string) ($user['name'] ?? ''))) ?: [];
$firstName = trim((string) ($nameParts[0] ?? 'Friend'));
if ($firstName === '') {
    $firstName = 'Friend';
}

$showScopeFilter = [];
if ($canLx) {
    $showScopeFilter[] = 'lx';
}
if ($canSnd) {
    $showScopeFilter[] = 'snd';
}
$dashboardMetrics = ['users' => 0, 'shows' => 0, 'lx_only' => 0, 'snd_only' => 0, 'both' => 0];

if ($canAdmin) {
    $showRows = db()->query('SELECT id, show_name, theatre_name, show_scope, updated_at FROM shows WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC')->fetchAll();
    $dashboardMetrics = [
        'users' => (int) db()->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn(),
        'shows' => (int) db()->query('SELECT COUNT(*) FROM shows WHERE deleted_at IS NULL')->fetchColumn(),
        'lx_only' => (int) db()->query('SELECT COUNT(*) FROM shows WHERE deleted_at IS NULL AND COALESCE(show_scope, "both") = "lx"')->fetchColumn(),
        'snd_only' => (int) db()->query('SELECT COUNT(*) FROM shows WHERE deleted_at IS NULL AND COALESCE(show_scope, "both") = "snd"')->fetchColumn(),
        'both' => (int) db()->query('SELECT COUNT(*) FROM shows WHERE deleted_at IS NULL AND COALESCE(show_scope, "both") = "both"')->fetchColumn(),
    ];
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

render_page('Dashboard', function () use ($canAdmin, $canLx, $canSnd, $showRows, $firstName, $dashboardMetrics): void {
    ?>
    <div class="mb-4 dashboard-hero">
        <h2 class="dashboard-hero-title mb-1">Welcome back, <span class="dashboard-hero-name"><?= e($firstName) ?></span> 👋</h2>
        <p class="text-secondary mb-0">Here's what's happening with your account today</p>
    </div>
    <?php if ($canAdmin): ?>
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Active Users</div><div class="h1 mb-0"><?= (int) $dashboardMetrics['users'] ?></div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Total Shows</div><div class="h1 mb-0"><?= (int) $dashboardMetrics['shows'] ?></div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">LX / SND / Both</div><div class="h2 mb-0"><?= (int) $dashboardMetrics['lx_only'] ?> / <?= (int) $dashboardMetrics['snd_only'] ?> / <?= (int) $dashboardMetrics['both'] ?></div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Resources</div><div class="h1 mb-0">Ready</div></div></div></div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Shows</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead><tr><th>Show</th><th>Scope</th><th class="text-end">Open</th></tr></thead>
                    <tbody>
                    <?php if (!$showRows): ?>
                        <tr><td colspan="3" class="text-secondary">No shows available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($showRows as $show): ?>
                            <?php $scope = strtolower((string) ($show['show_scope'] ?? 'both')); ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $show['show_name']) ?></strong>
                                    <div class="small text-secondary"><?= e((string) ($show['theatre_name'] ?? '')) ?></div>
                                </td>
                                <td><?= e($scope === 'lx' ? 'LX only' : ($scope === 'snd' ? 'Sound only' : 'Both')) ?></td>
                                <td class="text-end">
                                    <?php if (in_array($scope, ['lx', 'both'], true)): ?><a class="btn btn-sm btn-primary" href="/dash/lx?show=<?= (int) $show['id'] ?>&tab=info">Open LX</a><?php endif; ?>
                                    <?php if (in_array($scope, ['snd', 'both'], true)): ?><a class="btn btn-sm btn-primary ms-1" href="/dash/sound?show=<?= (int) $show['id'] ?>&tab=info">Open Sound</a><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
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
                                <div class="mt-auto d-grid gap-2">
                                    <?php if ($canLx && in_array($scope, ['lx', 'both'], true)): ?>
                                        <a class="btn btn-primary btn-lg w-100" href="/dash/lx?show=<?= (int) $show['id'] ?>&tab=info">Open LX</a>
                                    <?php endif; ?>
                                    <?php if ($canSnd && in_array($scope, ['snd', 'both'], true)): ?>
                                        <a class="btn btn-primary btn-lg w-100" href="/dash/sound?show=<?= (int) $show['id'] ?>&tab=info">Open Sound</a>
                                    <?php endif; ?>
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
    <?php endif; ?>
    <?php
}, $user);
