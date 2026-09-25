<?php

declare(strict_types=1);

$user = require_permission('admin.access');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();

    if (post('action') === 'run_migrations') {
        $results = run_pending_migrations();
        $applied = count(array_filter($results, fn ($r) => $r['status'] === 'applied'));
        $failed = count(array_filter($results, fn ($r) => $r['status'] === 'failed'));
        flash_set($failed ? 'warning' : 'success', "Migrations run complete. Applied: {$applied}, Failed: {$failed}");
    }

    if (post('action') === 'save_branding') {
        $appName = post('app_name', 'Backline');
        $madeIn = post('made_in', 'USA');
        $stmt = db()->prepare('INSERT INTO app_settings (`key_name`, `value_json`, `created_at`, `updated_at`) VALUES
            ("branding.app_name", ?, NOW(), NOW()),
            ("branding.made_in", ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = NOW()');
        $stmt->execute([json_encode($appName), json_encode($madeIn)]);

        if (!empty($_FILES['logo']['tmp_name'] ?? null)) {
            $dir = __DIR__ . '/../../uploads/branding';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/logo-light.png');
        }

        flash_set('success', 'Branding saved.');
    }

    redirect('/admin/settings');
}

$rows = migration_status_rows();
$appName = 'Backline';
$madeIn = 'USA';
$settings = db()->query('SELECT key_name, value_json FROM app_settings WHERE key_name IN ("branding.app_name", "branding.made_in")')->fetchAll();
foreach ($settings as $row) {
    if ($row['key_name'] === 'branding.app_name') $appName = (string) json_decode((string) $row['value_json'], true);
    if ($row['key_name'] === 'branding.made_in') $madeIn = (string) json_decode((string) $row['value_json'], true);
}

render_page('System Settings', function () use ($rows, $appName, $madeIn): void {
    ?>
    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Branding</h3></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="save_branding">
                        <div class="mb-3"><label class="form-label">App Name</label><input class="form-control" name="app_name" value="<?= e($appName) ?>"></div>
                        <div class="mb-3"><label class="form-label">Made In</label><input class="form-control" name="made_in" value="<?= e($madeIn) ?>"></div>
                        <div class="mb-3"><label class="form-label">Logo</label><input class="form-control" type="file" name="logo" accept="image/*"></div>
                        <button class="btn btn-primary">Save branding</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex align-items-center"><h3 class="card-title">Database Migrations</h3><div class="ms-auto"><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="run_migrations"><button class="btn btn-primary">Run pending</button></form></div></div>
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead><tr><th>Key</th><th>Status</th><th>Applied</th><th>Error</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['key']) ?></td>
                                <td><span class="badge bg-<?= $row['status'] === 'applied' ? 'green' : ($row['status'] === 'failed' ? 'red' : 'secondary') ?>"><?= e($row['status']) ?></span></td>
                                <td><?= e((string) ($row['applied_at'] ?? '-')) ?></td>
                                <td class="text-danger small"><?= e((string) ($row['error_text'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}, $user);
