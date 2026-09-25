<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/migrations.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

$messages = [];
$errors = [];
$settings = app_settings();
$keepDbPasswordChecked = true;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (isset($_POST['save_settings'])) {
        $candidateSettings = $settings;
        $candidateSettings['app_name'] = trim((string) ($_POST['app_name'] ?? 'Backline'));
        $candidateSettings['branding_logo'] = trim((string) ($_POST['branding_logo'] ?? ''));
        $candidateSettings['branding_logo_dark'] = trim((string) ($_POST['branding_logo_dark'] ?? ''));
        $candidateSettings['branding_footer_made_in'] = trim((string) ($_POST['branding_footer_made_in'] ?? ''));
        $dbPassInput = (string) ($_POST['db_pass'] ?? '');
        $keepDbPassword = isset($_POST['keep_db_pass']) && $_POST['keep_db_pass'] === '1';
        $keepDbPasswordChecked = $keepDbPassword;
        $candidateSettings['db'] = [
            'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
            'port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'name' => trim((string) ($_POST['db_name'] ?? 'backline')),
            'user' => trim((string) ($_POST['db_user'] ?? 'root')),
            'pass' => $keepDbPassword ? (string) ($settings['db']['pass'] ?? '') : $dbPassInput,
            'charset' => trim((string) ($_POST['db_charset'] ?? 'utf8mb4')),
        ];

        try {
            if (!$keepDbPassword && $dbPassInput === '') {
                throw new RuntimeException('Enter a new DB password or leave "keep existing password" checked.');
            }
            $allowedCharsets = ['utf8mb4', 'utf8', 'latin1', 'ascii'];
            $safeCharset = strtolower((string) $candidateSettings['db']['charset']);
            if (!in_array($safeCharset, $allowedCharsets, true)) {
                throw new RuntimeException('Unsupported DB charset.');
            }
            $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $candidateSettings['db']['host'], $candidateSettings['db']['port'], $safeCharset);
            $validationPdo = new PDO($dsn, (string) $candidateSettings['db']['user'], (string) $candidateSettings['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $schemaCheck = $validationPdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $schemaCheck->execute([(string) $candidateSettings['db']['name']]);
            if (!$schemaCheck->fetchColumn()) {
                throw new RuntimeException('Target database does not exist.');
            }
            $dbNameSql = '`' . str_replace('`', '``', (string) $candidateSettings['db']['name']) . '`';
            $validationPdo->exec('USE ' . $dbNameSql);
            $validationPdo->query('SELECT 1');

            if (save_settings($candidateSettings)) {
                $settings = $candidateSettings;
                reset_db_connection();
                $messages[] = 'Settings saved locally.';
            } else {
                $errors[] = 'Failed to write settings file.';
            }
        } catch (RuntimeException $error) {
            $errors[] = $error->getMessage();
        } catch (Throwable) {
            $errors[] = 'Database connection test failed; settings were not saved.';
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

render_page('System Settings', function () use ($settings, $messages, $errors, $applied, $pending, $csrfToken, $keepDbPasswordChecked): void {
    ui_card_open('settings', 'System Settings');
    if ($messages !== []) {
        echo '<div role="status" aria-live="polite">';
        foreach ($messages as $message) {
            ui_alert('success', $message);
        }
        echo '</div>';
    }
    if ($errors !== []) {
        echo '<div role="alert" aria-live="assertive">';
        foreach ($errors as $error) {
            ui_alert('danger', $error);
        }
        echo '</div>';
    }

    echo '<form method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
    echo '<div class="form-row">';
    echo '<label>System name<input name="app_name" value="' . htmlspecialchars((string) $settings['app_name']) . '"></label>';
    echo '<label>Logo URL<input name="branding_logo" value="' . htmlspecialchars((string) $settings['branding_logo']) . '"></label>';
    echo '<label>Dark Logo URL<input name="branding_logo_dark" value="' . htmlspecialchars((string) $settings['branding_logo_dark']) . '"></label>';
    echo '<label>Footer “Made in” text<input name="branding_footer_made_in" value="' . htmlspecialchars((string) $settings['branding_footer_made_in']) . '"></label>';
    echo '</div><h3>MySQL</h3><div class="form-row">';
    echo '<label>Host<input name="db_host" value="' . htmlspecialchars((string) $settings['db']['host']) . '"></label>';
    echo '<label>Port<input name="db_port" value="' . htmlspecialchars((string) $settings['db']['port']) . '"></label>';
    echo '<label>Database<input name="db_name" value="' . htmlspecialchars((string) $settings['db']['name']) . '"></label>';
    echo '<label>User<input name="db_user" value="' . htmlspecialchars((string) $settings['db']['user']) . '"></label>';
    echo '<fieldset><legend>Password update</legend>';
    echo '<label>Password<input type="password" name="db_pass" value="" autocomplete="new-password"></label>';
    echo '<label><input type="checkbox" name="keep_db_pass" value="1"' . ($keepDbPasswordChecked ? ' checked' : '') . '> Keep existing password when password field is blank</label>';
    echo '</fieldset>';
    echo '<label>Charset<input name="db_charset" value="' . htmlspecialchars((string) $settings['db']['charset']) . '"></label>';
    echo '</div><button class="btn btn-primary" type="submit" name="save_settings" value="1">Save settings</button></form>';

    echo '<h3>Database migrations</h3>';
    echo '<p class="text-muted">Applied: ' . count($applied) . ' · Pending: ' . count($pending) . '</p>';
    echo '<ul>';
    foreach ($applied as $migration) {
        echo '<li>✅ ' . htmlspecialchars((string) $migration) . '</li>';
    }
    foreach ($pending as $migrationFile) {
        echo '<li>🕒 ' . htmlspecialchars(basename((string) $migrationFile)) . '</li>';
    }
    echo '</ul><form method="post"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '"><button class="btn btn-primary" type="submit" name="run_migrations" value="1">Apply pending migrations</button></form>';
    ui_card_close();
});
