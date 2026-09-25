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
$messages = [];
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
        if (!save_settings($candidateSettings)) {
            $errors[] = 'Could not save setup settings file.';
        } else {
            $settings = $candidateSettings;
            reset_db_connection();

            try {
                db()->query('SELECT 1');
                foreach (apply_pending_migrations() as $result) {
                    if (($result['status'] ?? '') === 'failed') {
                        throw new RuntimeException((string) ($result['migration'] ?? 'migration') . ': ' . (string) ($result['message'] ?? 'Failed'));
                    }
                }

                $pdo = db();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO users (email, role, password_hash) VALUES (?, ?, ?)');
                $stmt->execute([$adminEmail, 'admin', password_hash($adminPassword, PASSWORD_DEFAULT)]);
                $adminId = (int) $pdo->lastInsertId();

                $grantStmt = $pdo->prepare('INSERT INTO user_concentrations (user_id, concentration) VALUES (?, ?)');
                $grantStmt->execute([$adminId, 'lx']);
                $grantStmt->execute([$adminId, 'snd']);
                $pdo->commit();

                if (!mark_setup_complete()) {
                    throw new RuntimeException('Setup completed but could not write setup lock file.');
                }

                $_SESSION['user'] = [
                    'email' => $adminEmail,
                    'role' => 'admin',
                    'concentrations' => ['lx', 'snd'],
                ];

                header('Location: ' . app_url('admin/dash'));
                exit;
            } catch (Throwable $error) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Setup failed: ' . $error->getMessage());
                $errors[] = 'Setup failed. Verify DB settings and check server logs for details.';
            }
        }
    }
}

render_page('Setup', function () use ($csrfToken, $errors, $messages, $settings): void {
    echo '<section class="panel"><h1>One-time Setup</h1><p class="muted">Configure DB + first admin account. This page is disabled after successful setup.</p>';

    foreach ($messages as $message) {
        echo '<p>' . htmlspecialchars($message) . '</p>';
    }
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
