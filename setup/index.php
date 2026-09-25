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

if (empty($_SESSION['setup_csrf_token'])) {
    $_SESSION['setup_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['setup_csrf_token'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
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
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCharset);
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $lockAcquired = (bool) $pdo->query("SELECT GET_LOCK('backline_setup', 10)")->fetchColumn();
            if (!$lockAcquired) {
                throw new RuntimeException('Could not acquire setup lock.');
            }

            try {
                $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

                $applied = $pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $appliedMap = array_flip($applied);

                foreach (migration_files() as $file) {
                    $migrationName = basename($file);
                    if (isset($appliedMap[$migrationName])) {
                        continue;
                    }

                    $sql = trim((string) file_get_contents($file));
                    if ($sql === '') {
                        continue;
                    }

                    $pdo->exec($sql);
                    $insertMigration = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
                    $insertMigration->execute([$migrationName]);
                }

                $upsertAdmin = $pdo->prepare('INSERT INTO users (email, role, password_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role), password_hash = VALUES(password_hash)');
                $upsertAdmin->execute([$adminEmail, 'admin', password_hash($adminPassword, PASSWORD_DEFAULT)]);

                $adminLookup = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $adminLookup->execute([$adminEmail]);
                $adminId = (int) $adminLookup->fetchColumn();
                if ($adminId <= 0) {
                    throw new RuntimeException('Could not resolve admin user id.');
                }

                $grantStmt = $pdo->prepare('INSERT IGNORE INTO user_concentrations (user_id, concentration) VALUES (?, ?)');
                $grantStmt->execute([$adminId, 'lx']);
                $grantStmt->execute([$adminId, 'snd']);

                if (!save_settings($candidateSettings)) {
                    throw new RuntimeException('Could not save setup settings file.');
                }

                if (!mark_setup_complete()) {
                    throw new RuntimeException('Could not write setup completion marker.');
                }
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('backline_setup')");
            }

            reset_db_connection();
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'email' => $adminEmail,
                'role' => 'admin',
                'concentrations' => ['lx', 'snd'],
            ];

            header('Location: ' . app_url('admin/dash'));
            exit;
        } catch (Throwable $error) {
            error_log('Setup failed: ' . $error->getMessage());
            $errors[] = 'Setup failed. Verify DB settings and check server logs for details.';
        }
    }
}

render_page('Setup', function () use ($csrfToken, $errors, $settings): void {
    echo '<section class="panel"><h1>One-time Setup</h1><p class="muted">Configure DB + first admin account. This page is disabled after successful setup.</p>';

    foreach ($errors as $error) {
        echo '<p style="color:#ffb8b8;">' . htmlspecialchars($error) . '</p>';
    }

    echo '<form method="post" class="grid">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';

    echo '<h3>Application</h3>';
    echo '<label>Application Name<input name="app_name" value="' . htmlspecialchars((string) ($settings['app_name'] ?? 'Backline')) . '" required></label>';

    echo '<h3>Database</h3><div class="grid two">';
    echo '<label>Host<input name="db_host" value="' . htmlspecialchars((string) ($settings['db']['host'] ?? '127.0.0.1')) . '" required></label>';
    echo '<label>Port<input name="db_port" value="' . htmlspecialchars((string) ($settings['db']['port'] ?? '3306')) . '" required></label>';
    echo '<label>Database<input name="db_name" value="' . htmlspecialchars((string) ($settings['db']['name'] ?? 'backline')) . '" required></label>';
    echo '<label>User<input name="db_user" value="' . htmlspecialchars((string) ($settings['db']['user'] ?? 'root')) . '" required></label>';
    echo '<label>Password<input type="password" name="db_pass" value="" autocomplete="new-password"></label>';
    echo '<label>Charset<input name="db_charset" value="' . htmlspecialchars((string) ($settings['db']['charset'] ?? 'utf8mb4')) . '" required></label>';
    echo '</div>';

    echo '<h3>Initial Admin Account</h3><div class="grid two">';
    echo '<label>Admin Email<input type="email" name="admin_email" required></label>';
    echo '<label>Admin Password<input type="password" name="admin_password" required></label>';
    echo '<label>Confirm Password<input type="password" name="admin_password_confirm" required></label>';
    echo '</div>';

    echo '<button type="submit">Complete Setup</button>';
    echo '</form></section>';
});
