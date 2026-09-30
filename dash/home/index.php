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

$dashboardMetrics = ['users' => 0, 'shows' => 0];

if ($canAdmin) {
    $showRows = db()->query('SELECT id, show_name, theatre_name, updated_at FROM shows WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC')->fetchAll();
    $dashboardMetrics = [
        'users' => (int) db()->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn(),
        'shows' => (int) db()->query('SELECT COUNT(*) FROM shows WHERE deleted_at IS NULL')->fetchColumn(),
    ];
} else {
    if (!$canLx && !$canSnd) {
        $showRows = [];
    } else {
        if (show_user_access_table_exists()) {
            $stmt = db()->prepare('SELECT s.id, s.show_name, s.theatre_name, s.updated_at
                FROM shows s
                WHERE s.deleted_at IS NULL
                  AND (
                    s.owner_user_id = ?
                    OR EXISTS (
                        SELECT 1
                        FROM show_user_access sua
                        WHERE sua.show_id = s.id AND sua.user_id = ?
                    )
                  )
                ORDER BY s.updated_at DESC, s.id DESC');
            $stmt->execute([(int) $user['id'], (int) $user['id']]);
        } else {
            $stmt = db()->prepare('SELECT id, show_name, theatre_name, updated_at FROM shows WHERE deleted_at IS NULL AND owner_user_id = ? ORDER BY updated_at DESC, id DESC');
            $stmt->execute([(int) $user['id']]);
        }
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
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-stamp card-stamp-lg">
                        <div class="card-stamp-icon bg-blue-lt">
                            <i class="ti ti-users"></i>
                        </div>
                    </div>
                    <div class="card-header"><h3 class="card-title">Active Users</h3></div>
                    <div class="card-body">
                        <div class="h1 mb-0"><?= (int) $dashboardMetrics['users'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-stamp card-stamp-lg">
                        <div class="card-stamp-icon bg-indigo-lt">
                            <i class="ti ti-layout-grid"></i>
                        </div>
                    </div>
                    <div class="card-header"><h3 class="card-title">Total Shows</h3></div>
                    <div class="card-body">
                        <div class="h1 mb-0"><?= (int) $dashboardMetrics['shows'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-stamp card-stamp-lg">
                        <div class="card-stamp-icon bg-orange-lt">
                            <i class="ti ti-adjustments"></i>
                        </div>
                    </div>
                    <div class="card-header"><h3 class="card-title">Shops</h3></div>
                    <div class="card-body">
                        <div class="h2 mb-0">LX + SND</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-stamp card-stamp-lg">
                        <div class="card-stamp-icon bg-green-lt">
                            <i class="ti ti-book"></i>
                        </div>
                    </div>
                    <div class="card-header"><h3 class="card-title">Resources</h3></div>
                    <div class="card-body">
                        <div class="h1 mb-0">Ready</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Shows</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead><tr><th>Show</th><th class="text-end">Open</th></tr></thead>
                <tbody>
                <?php if (!$showRows): ?>
                    <tr><td colspan="2" class="text-secondary">No shows available.</td></tr>
                <?php else: ?>
                    <?php foreach ($showRows as $show): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $show['show_name']) ?></strong>
                                <div class="small text-secondary"><?= e((string) ($show['theatre_name'] ?? '')) ?></div>
                            </td>
                            <td class="text-end">
                                <?php if ($canLx): ?><a class="btn btn-sm btn-primary" href="/dash/lx?show=<?= (int) $show['id'] ?>&tab=info">Open LX</a><?php endif; ?>
                                <?php if ($canSnd): ?><a class="btn btn-sm btn-primary ms-1" href="/dash/sound?show=<?= (int) $show['id'] ?>&tab=info">Open Sound</a><?php endif; ?>
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
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100 dashboard-show-card card-title-enhanced">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <span class="card-title-pill">
                                        <i class="ti ti-circle-dot me-1"></i>
                                        <?= e((string) $show['show_name']) ?>
                                    </span>
                                </h3>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <p class="text-secondary mb-2"><?= e((string) ($show['theatre_name'] ?? '')) ?></p>
                                <div class="mt-auto d-grid gap-2">
                                    <?php if ($canLx): ?>
                                        <a class="btn btn-primary btn-lg w-100" href="/dash/lx?show=<?= (int) $show['id'] ?>&tab=info">Open LX</a>
                                    <?php endif; ?>
                                    <?php if ($canSnd): ?>
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
