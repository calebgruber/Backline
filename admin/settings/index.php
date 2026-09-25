<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/migrations.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

$messages = [];
$errors = [];
$settings = app_settings();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (isset($_POST['save_settings'])) {
        $settings['app_name'] = trim((string) ($_POST['app_name'] ?? 'Backline'));
        $settings['branding_logo'] = trim((string) ($_POST['branding_logo'] ?? ''));
        $settings['branding_logo_dark'] = trim((string) ($_POST['branding_logo_dark'] ?? ''));
        $settings['branding_footer_made_in'] = trim((string) ($_POST['branding_footer_made_in'] ?? ''));
        $dbPassInput = (string) ($_POST['db_pass'] ?? '');
        $settings['db'] = [
            'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
            'port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'name' => trim((string) ($_POST['db_name'] ?? 'backline')),
            'user' => trim((string) ($_POST['db_user'] ?? 'root')),
            'pass' => $dbPassInput !== '' ? $dbPassInput : (string) ($settings['db']['pass'] ?? ''),
            'charset' => trim((string) ($_POST['db_charset'] ?? 'utf8mb4')),
        ];

        if (save_settings($settings)) {
            reset_db_connection();
            $messages[] = 'Settings saved locally.';
        } else {
            $errors[] = 'Failed to write settings file.';
        }
    } elseif (isset($_POST['run_migrations'])) {
        try {
            foreach (apply_pending_migrations() as $result) {
                if (($result['status'] ?? '') === 'applied') {
                    $messages[] = $result['migration'] . ': ' . $result['message'];
                } else {
                    $errors[] = $result['migration'] . ': ' . $result['message'];
                }
            }
        } catch (Throwable $error) {
            error_log('Migration apply failure: ' . $error->getMessage());
            $errors[] = 'Migration apply failed. Check server logs for details.';
        }
    }
}

$applied = [];
$pending = [];
try {
    $applied = applied_migrations();
    $pending = pending_migrations();
} catch (Throwable $error) {
    $errors[] = 'Migration metadata unavailable: ' . $error->getMessage();
}

render_page('System Settings', function () use ($settings, $messages, $errors, $applied, $pending, $csrfToken): void {
    echo '<section class="panel"><h1>System Settings</h1>';
    foreach ($messages as $message) {
        echo '<p>' . htmlspecialchars($message) . '</p>';
    }
    foreach ($errors as $error) {
        echo '<p style="color:#ffb8b8;">' . htmlspecialchars($error) . '</p>';
    }

    echo '<form method="post" class="grid">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
    echo '<div class="grid two">';
    echo '<label>System name<input name="app_name" value="' . htmlspecialchars((string) $settings['app_name']) . '"></label>';
    echo '<label>Logo URL<input name="branding_logo" value="' . htmlspecialchars((string) $settings['branding_logo']) . '"></label>';
    echo '<label>Dark Logo URL<input name="branding_logo_dark" value="' . htmlspecialchars((string) $settings['branding_logo_dark']) . '"></label>';
    echo '<label>Footer “Made in” text<input name="branding_footer_made_in" value="' . htmlspecialchars((string) $settings['branding_footer_made_in']) . '"></label>';
    echo '</div><h3>MySQL</h3><div class="grid two">';
    echo '<label>Host<input name="db_host" value="' . htmlspecialchars((string) $settings['db']['host']) . '"></label>';
    echo '<label>Port<input name="db_port" value="' . htmlspecialchars((string) $settings['db']['port']) . '"></label>';
    echo '<label>Database<input name="db_name" value="' . htmlspecialchars((string) $settings['db']['name']) . '"></label>';
    echo '<label>User<input name="db_user" value="' . htmlspecialchars((string) $settings['db']['user']) . '"></label>';
    echo '<label>Password<input type="password" name="db_pass" value="" autocomplete="new-password"></label>';
    echo '<label>Charset<input name="db_charset" value="' . htmlspecialchars((string) $settings['db']['charset']) . '"></label>';
    echo '</div><button type="submit" name="save_settings" value="1">Save settings</button></form>';

    echo '<h3>Database migrations</h3>';
    echo '<p class="muted">Applied: ' . count($applied) . ' · Pending: ' . count($pending) . '</p>';
    echo '<ul>';
    foreach ($applied as $migration) {
        echo '<li>✅ ' . htmlspecialchars((string) $migration) . '</li>';
    }
    foreach ($pending as $migrationFile) {
        echo '<li>🕒 ' . htmlspecialchars(basename((string) $migrationFile)) . '</li>';
    }
    echo '</ul><form method="post"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '"><button type="submit" name="run_migrations" value="1">Apply pending migrations</button></form>';
    echo '</section>';
});
