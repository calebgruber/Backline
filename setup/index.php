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
    if (!in_array(strtolower($dbCharset), $allowedCharsets, true)) {
        $errors[] = 'Unsupported DB charset.';
    }
    $form = [
        'app_name' => $appName,
        'db_host' => $dbHost,
        'db_port' => $dbPort,
        'db_name' => $dbName,
        'db_user' => $dbUser,
        'db_pass' => $dbPass,
        'db_charset' => $dbCharset,
        'admin_email' => $adminEmail,
    ];

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid admin email is required.';
    }
    if (strlen($adminPassword) < 10) {
        $errors[] = 'Admin password must be at least 10 characters.';
    }
    if (!hash_equals($adminPassword, $adminPasswordConfirm)) {
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
        'charset' => $dbCharset,
    ];

    if ($errors === []) {
        $setupLockHandle = null;
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

            $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $dbHost, $dbPort, $dbCharset);
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $dbNameSql = '`' . str_replace('`', '``', $dbName) . '`';
            $safeCharset = strtolower($dbCharset);
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

            try {
                foreach (apply_pending_migrations_with_pdo($pdo, '') as $result) {
                    if (($result['status'] ?? '') === 'failed') {
                        throw new RuntimeException((string) ($result['migration'] ?? 'migration') . ': ' . (string) ($result['message'] ?? 'Failed'));
                    }
                }

                $pdo->beginTransaction();
                $existingUserCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $existingUserCheck->execute([$adminEmail]);
                if ($existingUserCheck->fetchColumn()) {
                    throw new RuntimeException('Admin email already exists. Choose a new email.');
                }

                $insertAdmin = $pdo->prepare('INSERT INTO users (email, role, password_hash) VALUES (?, ?, ?)');
                $insertAdmin->execute([$adminEmail, 'admin', password_hash($adminPassword, PASSWORD_DEFAULT)]);

                $adminId = (int) $pdo->lastInsertId();
                if ($adminId <= 0) {
                    throw new RuntimeException('Could not resolve admin user id.');
                }

                $grantStmt = $pdo->prepare('INSERT IGNORE INTO user_concentrations (user_id, concentration) VALUES (?, ?)');
                $grantStmt->execute([$adminId, 'lx']);
                $grantStmt->execute([$adminId, 'snd']);
                $pdo->commit();

                if (!save_settings($candidateSettings)) {
                    $pdo->beginTransaction();
                    try {
                        $cleanupAdmin($pdo, $adminId);
                        $pdo->commit();
                    } catch (Throwable) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                    }
                    throw new RuntimeException('Could not save setup settings file.');
                }
                if (!mark_setup_complete()) {
                    if (is_string($settingsBackup)) {
                        file_put_contents(settings_file(), $settingsBackup, LOCK_EX);
                    } else {
                        @unlink(settings_file());
                    }
                    $pdo->beginTransaction();
                    try {
                        $cleanupAdmin($pdo, $adminId);
                        $pdo->commit();
                    } catch (Throwable) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                    }
                    throw new RuntimeException('Could not write setup completion marker.');
                }
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('backline_setup')");
            }

            reset_db_connection();
            header('Location: ' . app_url('auth/login'));
            exit;
        } catch (Throwable $error) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @unlink(setup_state_file());
            if (is_string($settingsBackup)) {
                file_put_contents(settings_file(), $settingsBackup, LOCK_EX);
            } else {
                @unlink(settings_file());
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
    echo '<section class="panel"><h1>One-time Setup</h1><p class="muted">Configure DB + first admin account. This page is disabled after successful setup.</p>';

    foreach ($errors as $error) {
        echo '<p style="color:#ffb8b8;">' . htmlspecialchars($error) . '</p>';
    }

    echo '<form method="post" class="grid">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';

    echo '<h3>Application</h3>';
    echo '<label>Application Name<input name="app_name" value="' . htmlspecialchars($form['app_name']) . '" required></label>';

    echo '<h3>Database</h3><div class="grid two">';
    echo '<label>Host<input name="db_host" value="' . htmlspecialchars($form['db_host']) . '" required></label>';
    echo '<label>Port<input name="db_port" value="' . htmlspecialchars($form['db_port']) . '" required></label>';
    echo '<label>Database<input name="db_name" value="' . htmlspecialchars($form['db_name']) . '" required></label>';
    echo '<label>User<input name="db_user" value="' . htmlspecialchars($form['db_user']) . '" required></label>';
    echo '<label>Password<input type="password" name="db_pass" value="' . htmlspecialchars($form['db_pass']) . '" autocomplete="new-password"></label>';
    echo '<label>Charset<input name="db_charset" value="' . htmlspecialchars($form['db_charset']) . '" required></label>';
    echo '</div>';

    echo '<h3>Initial Admin Account</h3><div class="grid two">';
    echo '<label>Admin Email<input type="email" name="admin_email" value="' . htmlspecialchars($form['admin_email']) . '" required></label>';
    echo '<label>Admin Password<input type="password" name="admin_password" required></label>';
    echo '<label>Confirm Password<input type="password" name="admin_password_confirm" required></label>';
    echo '</div>';

    echo '<button type="submit">Complete Setup</button>';
    echo '</form></section>';
});
