<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/ui.php';
require_once dirname(__DIR__) . '/shared/db.php';
require_once dirname(__DIR__) . '/shared/migrations.php';

if (is_setup_complete()) {
    header('Location: ' . app_url('auth/login'));
    exit;
}

$errors = [];
$settings = app_settings();
$form = [
    'app_name' => (string) ($settings['app_name'] ?? 'Backline'),
    'db_host' => (string) ($settings['db']['host'] ?? '127.0.0.1'),
    'db_port' => (string) ($settings['db']['port'] ?? '3306'),
    'db_name' => (string) ($settings['db']['name'] ?? 'backline'),
    'db_user' => (string) ($settings['db']['user'] ?? 'root'),
    'db_pass' => '',
    'db_charset' => (string) ($settings['db']['charset'] ?? 'utf8mb4'),
    'admin_email' => '',
];

if (empty($_SESSION['setup_csrf_token'])) {
    $_SESSION['setup_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['setup_csrf_token'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $settingsBackup = is_file(settings_file()) ? file_get_contents(settings_file()) : null;
    $settingsSaved = false;
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Invalid request token.';
    }

    $appName = trim((string) ($_POST['app_name'] ?? 'Backline'));
    $adminEmail = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $adminPasswordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');

    $dbHost = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
    $dbPort = trim((string) ($_POST['db_port'] ?? '3306'));
    $dbName = trim((string) ($_POST['db_name'] ?? 'backline'));
    $dbUser = trim((string) ($_POST['db_user'] ?? 'root'));
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $dbCharset = trim((string) ($_POST['db_charset'] ?? 'utf8mb4'));
    $allowedCharsets = ['utf8mb4', 'utf8', 'latin1', 'ascii'];
    $safeCharset = strtolower($dbCharset);
    if (!in_array($safeCharset, $allowedCharsets, true)) {
        $errors[] = 'Unsupported DB charset.';
    }
    $form = [
        'app_name' => $appName,
        'db_host' => $dbHost,
        'db_port' => $dbPort,
        'db_name' => $dbName,
        'db_user' => $dbUser,
        'db_pass' => '',
        'db_charset' => $dbCharset,
        'admin_email' => $adminEmail,
    ];

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid admin email is required.';
    }
    if (strlen($adminPassword) < 10) {
        $errors[] = 'Admin password must be at least 10 characters.';
    }
    if ($adminPassword !== $adminPasswordConfirm) {
        $errors[] = 'Admin passwords do not match.';
    }

    $candidateSettings = $settings;
    $candidateSettings['app_name'] = $appName;
    $candidateSettings['db'] = [
        'host' => $dbHost,
        'port' => $dbPort,
        'name' => $dbName,
        'user' => $dbUser,
        'pass' => $dbPass,
        'charset' => $safeCharset,
    ];

    if ($errors === []) {
        $setupLockHandle = null;
        $restoreSettings = static function (?string $settingsBackupContent): void {
            if (is_string($settingsBackupContent)) {
                if (file_put_contents(settings_file(), $settingsBackupContent, LOCK_EX) === false) {
                    error_log('Setup rollback warning: failed restoring settings backup file.');
                }
            } else {
                if (is_file(settings_file()) && !@unlink(settings_file())) {
                    error_log('Setup rollback warning: failed removing settings file.');
                }
            }
            @unlink(setup_state_file());
        };
        try {
            if (!is_dir(storage_path()) && !mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
                throw new RuntimeException('Could not prepare setup storage directory.');
            }
            $setupLockHandle = fopen(storage_path('setup.lock'), 'c+');
            if ($setupLockHandle === false || !flock($setupLockHandle, LOCK_EX)) {
                throw new RuntimeException('Could not acquire setup filesystem lock.');
            }
            if (is_setup_complete()) {
                throw new RuntimeException('Setup has already been completed.');
            }

            $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $dbHost, $dbPort, $safeCharset);
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ]);
            $dbNameSql = '`' . str_replace('`', '``', $dbName) . '`';
            $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $dbNameSql . ' CHARACTER SET ' . $safeCharset);
            $pdo->exec('USE ' . $dbNameSql);

            $lockStmt = $pdo->query("SELECT GET_LOCK('backline_setup', 10)");
            $lockAcquired = $lockStmt ? (bool) $lockStmt->fetchColumn() : false;
            if (!$lockAcquired) {
                throw new RuntimeException('Could not acquire setup lock.');
            }
            $cleanupAdmin = static function (PDO $pdoConnection, int $userId): void {
                $cleanupConcentrations = $pdoConnection->prepare('DELETE FROM user_concentrations WHERE user_id = ?');
                $cleanupConcentrations->execute([$userId]);
                $cleanup = $pdoConnection->prepare('DELETE FROM users WHERE id = ?');
                $cleanup->execute([$userId]);
            };
            $revertSetupChanges = static function (PDO $pdoConnection, int $userId, ?string $settingsBackupContent, callable $cleanupAdminFn, callable $restoreSettingsFn): void {
                $restoreSettingsFn($settingsBackupContent);
                try {
                    $pdoConnection->beginTransaction();
                    $cleanupAdminFn($pdoConnection, $userId);
                    $pdoConnection->commit();
                } catch (Throwable $cleanupError) {
                    error_log('Setup rollback warning: failed cleaning up admin user. ' . $cleanupError->getMessage());
                    if ($pdoConnection->inTransaction()) {
                        $pdoConnection->rollBack();
                    }
                }
            };

            try {
                foreach (apply_pending_migrations_with_pdo($pdo, '') as $result) {
                    if (($result['status'] ?? '') === 'failed') {
                        throw new RuntimeException((string) ($result['migration'] ?? 'migration') . ': ' . (string) ($result['message'] ?? 'Failed'));
                    }
                }

                $pdo->beginTransaction();
                $insertAdmin = $pdo->prepare('INSERT INTO users (email, role, password_hash) VALUES (?, ?, ?)');
                try {
                    $insertAdmin->execute([$adminEmail, 'admin', password_hash($adminPassword, PASSWORD_DEFAULT)]);
                } catch (PDOException $pdoError) {
                    if (($pdoError->errorInfo[0] ?? '') === '23000') {
                        throw new RuntimeException('Admin email already exists. Choose a new email.');
                    }
                    throw $pdoError;
                }

                $adminId = (int) $pdo->lastInsertId();
                if ($adminId <= 0) {
                    throw new RuntimeException('Could not resolve admin user id.');
                }

                $grantStmt = $pdo->prepare('INSERT IGNORE INTO user_concentrations (user_id, concentration) VALUES (?, ?)');
                $grantStmt->execute([$adminId, 'lx']);
                $grantStmt->execute([$adminId, 'snd']);
                $pdo->commit();
                $rollbackSetupWithAdmin = static function (string $message) use ($pdo, $adminId, $settingsBackup, $cleanupAdmin, $restoreSettings, $revertSetupChanges): void {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $revertSetupChanges($pdo, $adminId, $settingsBackup, $cleanupAdmin, $restoreSettings);
                    throw new RuntimeException($message);
                };

                if (!save_settings($candidateSettings)) {
                    $rollbackSetupWithAdmin('Could not save setup settings file.');
                }
                $settingsSaved = true;
                if (!mark_setup_complete()) {
                    $settingsSaved = false;
                    $rollbackSetupWithAdmin('Could not write setup completion marker.');
                }
            } finally {
                if ($lockAcquired) {
                    $pdo->query("SELECT RELEASE_LOCK('backline_setup')");
                }
            }

            reset_db_connection();
            header('Location: ' . app_url('auth/login'));
            exit;
        } catch (Throwable $error) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($settingsSaved) {
                $restoreSettings($settingsBackup);
            } else {
                @unlink(setup_state_file());
            }
            error_log('Setup failed: ' . $error->getMessage());
            $errors[] = 'Setup failed. Verify DB settings and check server logs for details.';
        } finally {
            if (is_resource($setupLockHandle)) {
                flock($setupLockHandle, LOCK_UN);
                fclose($setupLockHandle);
            }
        }
    }
}

render_page('Setup', function () use ($csrfToken, $errors, $form): void {
    ui_card_open('settings', 'One-time Setup');
    echo '<p class="text-muted">Configure DB + first admin account. This page is disabled after successful setup.</p>';

    if ($errors !== []) {
        echo '<div role="alert" aria-live="assertive">';
        foreach ($errors as $error) {
            ui_alert('danger', $error);
        }
        echo '</div>';
    }

    echo '<form method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';

    echo '<h3>Application</h3>';
    echo '<div class="mb-3"><label class="form-label">Application Name</label><input class="form-control" name="app_name" value="' . htmlspecialchars($form['app_name']) . '" required></div>';

    echo '<h3>Database</h3><div class="row g-3">';
    echo '<div class="col-md-6"><label class="form-label">Host</label><input class="form-control" name="db_host" value="' . htmlspecialchars($form['db_host']) . '" required></div>';
    echo '<div class="col-md-6"><label class="form-label">Port</label><input class="form-control" name="db_port" value="' . htmlspecialchars($form['db_port']) . '" required></div>';
    echo '<div class="col-md-6"><label class="form-label">Database</label><input class="form-control" name="db_name" value="' . htmlspecialchars($form['db_name']) . '" required></div>';
    echo '<div class="col-md-6"><label class="form-label">User</label><input class="form-control" name="db_user" value="' . htmlspecialchars($form['db_user']) . '" required></div>';
    echo '<div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="db_pass" value="" autocomplete="new-password"></div>';
    echo '<div class="col-md-6"><label class="form-label">Charset</label><input class="form-control" name="db_charset" value="' . htmlspecialchars($form['db_charset']) . '" required></div>';
    echo '</div>';

    echo '<h3 class="mt-4">Initial Admin Account</h3><div class="row g-3">';
    echo '<div class="col-md-6"><label class="form-label">Admin Email</label><input class="form-control" type="email" name="admin_email" value="' . htmlspecialchars($form['admin_email']) . '" required></div>';
    echo '<div class="col-md-6"><label class="form-label">Admin Password</label><input class="form-control" type="password" name="admin_password" required></div>';
    echo '<div class="col-md-6"><label class="form-label">Confirm Password</label><input class="form-control" type="password" name="admin_password_confirm" required></div>';
    echo '</div>';

    echo '<button class="btn btn-primary" type="submit">Complete Setup</button>';
    echo '</form>';
    ui_card_close();
});
