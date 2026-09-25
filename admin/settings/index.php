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

$user = require_permission('admin.access');

function save_branding_asset(string $inputName, string $baseName, array $allowedMimeToExt): bool
{
    if (empty($_FILES[$inputName]['tmp_name'] ?? null)) {
        return true;
    }

    $dir = __DIR__ . '/../../uploads/branding';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $tmpPath = (string) $_FILES[$inputName]['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!isset($allowedMimeToExt[$mime])) {
        flash_set('danger', 'Unsupported file type uploaded for branding asset.');
        return false;
    }
    $ext = $allowedMimeToExt[$mime];

    foreach (glob($dir . '/' . $baseName . '.*') ?: [] as $existing) {
        @unlink($existing);
    }

    if (!move_uploaded_file($tmpPath, $dir . '/' . $baseName . '.' . $ext)) {
        flash_set('danger', 'Failed to save uploaded branding asset.');
        return false;
    }
    return true;
}

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
        $loginCardColor = trim(post('login_card_color', ''));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $loginCardColor)) {
            $loginCardColor = '';
        }
        $loginCardIcon = trim(post('login_card_icon', ''));
        if (!preg_match('/^[a-z0-9_]{1,48}$/i', $loginCardIcon)) {
            $loginCardIcon = '';
        }
        $stmt = db()->prepare('INSERT INTO app_settings (`key_name`, `value_json`, `created_at`, `updated_at`) VALUES
            ("branding.app_name", ?, NOW(), NOW()),
            ("branding.made_in", ?, NOW(), NOW()),
            ("branding.login_card_color", ?, NOW(), NOW()),
            ("branding.login_card_icon", ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = NOW()');
        $stmt->execute([json_encode($appName), json_encode($madeIn), json_encode($loginCardColor), json_encode($loginCardIcon)]);

        $logoMimes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $faviconMimes = ['image/png' => 'png', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
        $uploadsOk = true;
        $uploadsOk = save_branding_asset('logo_light', 'logo-light', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('logo_dark', 'logo-dark', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('logo_lx', 'logo-lx', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('logo_lx_light', 'logo-lx-light', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('logo_lx_dark', 'logo-lx-dark', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('logo_snd', 'logo-snd', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('logo_snd_light', 'logo-snd-light', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('logo_snd_dark', 'logo-snd-dark', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('logo', 'logo', $logoMimes) && $uploadsOk; // backwards compatibility
        $uploadsOk = save_branding_asset('favicon', 'favicon', $faviconMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('login_background', 'login-bg', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('login_background_light', 'login-bg-light', $logoMimes) && $uploadsOk;
        $uploadsOk = save_branding_asset('login_background_dark', 'login-bg-dark', $logoMimes) && $uploadsOk;

        $hasLoginBackgroundUpload = !empty($_FILES['login_background']['tmp_name'] ?? null)
            || !empty($_FILES['login_background_light']['tmp_name'] ?? null)
            || !empty($_FILES['login_background_dark']['tmp_name'] ?? null);
        if (post('clear_login_background') === '1' && !$hasLoginBackgroundUpload) {
            foreach (glob(__DIR__ . '/../../uploads/branding/login-bg.*') ?: [] as $existing) {
                @unlink($existing);
            }
            foreach (glob(__DIR__ . '/../../uploads/branding/login-bg-light.*') ?: [] as $existing) {
                @unlink($existing);
            }
            foreach (glob(__DIR__ . '/../../uploads/branding/login-bg-dark.*') ?: [] as $existing) {
                @unlink($existing);
            }
        }
        app_setting_clear_cache();

        if ($uploadsOk) {
            flash_set('success', 'Branding saved.');
        }
    }

    redirect('/admin/settings');
}

$rows = migration_status_rows();
$appName = 'Backline';
$madeIn = 'USA';
$loginCardColor = '';
$loginCardIcon = '';
$settings = db()->query('SELECT key_name, value_json FROM app_settings WHERE key_name IN ("branding.app_name", "branding.made_in", "branding.login_card_color", "branding.login_card_icon")')->fetchAll();
foreach ($settings as $row) {
    if ($row['key_name'] === 'branding.app_name') $appName = (string) json_decode((string) $row['value_json'], true);
    if ($row['key_name'] === 'branding.made_in') $madeIn = (string) json_decode((string) $row['value_json'], true);
    if ($row['key_name'] === 'branding.login_card_color') $loginCardColor = (string) json_decode((string) $row['value_json'], true);
    if ($row['key_name'] === 'branding.login_card_icon') $loginCardIcon = (string) json_decode((string) $row['value_json'], true);
}

render_page('System Settings', function () use ($rows, $appName, $madeIn, $loginCardColor, $loginCardIcon): void {
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
                        <div class="mb-3"><label class="form-label">Light Logo</label><input class="form-control" type="file" name="logo_light" accept="image/*"></div>
                        <div class="mb-3"><label class="form-label">Dark Logo</label><input class="form-control" type="file" name="logo_dark" accept="image/*"></div>
                        <div class="mb-3"><label class="form-label">LX App Logo</label><input class="form-control" type="file" name="logo_lx" accept="image/png,image/jpeg,image/webp"></div>
                        <div class="mb-3"><label class="form-label">LX App Light Logo</label><input class="form-control" type="file" name="logo_lx_light" accept="image/png,image/jpeg,image/webp"></div>
                        <div class="mb-3"><label class="form-label">LX App Dark Logo</label><input class="form-control" type="file" name="logo_lx_dark" accept="image/png,image/jpeg,image/webp"></div>
                        <div class="mb-3"><label class="form-label">SND App Logo</label><input class="form-control" type="file" name="logo_snd" accept="image/png,image/jpeg,image/webp"></div>
                        <div class="mb-3"><label class="form-label">SND App Light Logo</label><input class="form-control" type="file" name="logo_snd_light" accept="image/png,image/jpeg,image/webp"></div>
                        <div class="mb-3"><label class="form-label">SND App Dark Logo</label><input class="form-control" type="file" name="logo_snd_dark" accept="image/png,image/jpeg,image/webp"></div>
                        <div class="mb-3"><label class="form-label">Login Background Image</label><input class="form-control" type="file" name="login_background" accept="image/png,image/jpeg,image/webp"></div>
                        <div class="mb-3"><label class="form-label">Login Background Light</label><input class="form-control" type="file" name="login_background_light" accept="image/png,image/jpeg,image/webp"></div>
                        <div class="mb-3"><label class="form-label">Login Background Dark</label><input class="form-control" type="file" name="login_background_dark" accept="image/png,image/jpeg,image/webp"></div>
                        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="clear_login_background" value="1"><span class="form-check-label">Remove login background</span></label>
                        <div class="mb-3"><label class="form-label">Login Card Color</label><input class="form-control" type="text" name="login_card_color" value="<?= e($loginCardColor) ?>" placeholder="#206bc4"></div>
                        <div class="mb-3"><label class="form-label">Login Card Material Icon</label><input class="form-control" type="text" name="login_card_icon" value="<?= e($loginCardIcon) ?>" placeholder="lock"></div>
                        <div class="mb-3"><label class="form-label">Favicon (.ico or .png)</label><input class="form-control" type="file" name="favicon" accept=".ico,image/png,image/x-icon"></div>
                        <div class="form-hint mb-3">Theme-specific LX/SND logos and login backgrounds will follow light/dark mode automatically.</div>
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
