<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/ui.php';
require_once dirname(__DIR__, 2) . '/shared/db.php';

$errors = [];
if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['login_csrf_token'];

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $bootstrapToken = trim((string) getenv('BACKLINE_BOOTSTRAP_LOGIN_TOKEN'));
    $email = trim((string) ($_POST['email'] ?? ''));
    $allowedEmails = array_values(array_filter(array_map('trim', explode(',', (string) getenv('BACKLINE_ALLOWED_EMAILS')))));
    $isAllowedEmail = $allowedEmails === [] || in_array(strtolower($email), array_map('strtolower', $allowedEmails), true);
    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Invalid request token.';
    } elseif ($bootstrapToken === '' || !hash_equals($bootstrapToken, (string) ($_POST['bootstrap_token'] ?? ''))) {
        $errors[] = 'Invalid login token.';
    } elseif (!$isAllowedEmail) {
        $errors[] = 'Email is not authorized.';
    }

    if ($errors !== []) {
        render_page('Login', function () use ($errors, $csrfToken): void {
            echo '<section class="panel"><h1>Login</h1>';
            foreach ($errors as $error) {
                echo '<p style="color:#ffb8b8;">' . htmlspecialchars($error) . '</p>';
            }
            echo '<form method="post" class="grid">';
            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
            echo '<label>Email<input type="email" name="email" required></label>';
            echo '<label>Login token<input type="password" name="bootstrap_token" required></label>';
            echo '<button type="submit">Continue</button></form></section>';
        });
        exit;
    }

    $sessionUser = null;
    $hasExistingUsers = false;
    if (db_ready()) {
        try {
            $countStmt = db()->query('SELECT COUNT(*) FROM users');
            $hasExistingUsers = (int) $countStmt->fetchColumn() > 0;
            $stmt = db()->prepare('SELECT id, email, role FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $userRow = $stmt->fetch();
            if ($userRow) {
                $grantsStmt = db()->prepare('SELECT concentration FROM user_concentrations WHERE user_id = ?');
                $grantsStmt->execute([(int) $userRow['id']]);
                $dbConcentrations = $grantsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $sessionUser = [
                    'email' => (string) $userRow['email'],
                    'role' => (string) $userRow['role'],
                    'concentrations' => array_values(array_intersect(['lx', 'snd'], $dbConcentrations)),
                ];
            }
        } catch (Throwable) {
            $sessionUser = null;
        }
    }

    if ($sessionUser === null) {
        if ($hasExistingUsers) {
            $errors[] = 'Unknown user account.';
            render_page('Login', function () use ($errors, $csrfToken): void {
                echo '<section class="panel"><h1>Login</h1>';
                foreach ($errors as $error) {
                    echo '<p style="color:#ffb8b8;">' . htmlspecialchars($error) . '</p>';
                }
                echo '<form method="post" class="grid">';
                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
                echo '<label>Email<input type="email" name="email" required></label>';
                echo '<label>Login token<input type="password" name="bootstrap_token" required></label>';
                echo '<button type="submit">Continue</button></form></section>';
            });
            exit;
        }
        $bootstrapAdminEmail = trim((string) getenv('BACKLINE_BOOTSTRAP_ADMIN_EMAIL'));
        $role = ($bootstrapAdminEmail !== '' && strcasecmp($bootstrapAdminEmail, $email) === 0) ? 'admin' : 'user';
        $defaultConcentrations = array_values(array_intersect(
            ['lx', 'snd'],
            array_map('trim', explode(',', (string) getenv('BACKLINE_DEFAULT_CONCENTRATIONS')))
        ));
        if ($defaultConcentrations === []) {
            $defaultConcentrations = ['lx'];
        }
        $sessionUser = [
            'email' => $email,
            'role' => $role,
            'concentrations' => $role === 'admin' ? ['lx', 'snd'] : $defaultConcentrations,
        ];
    }

    $_SESSION['user'] = $sessionUser;
    header('Location: ' . app_url(user_home_route($_SESSION['user'])));
    exit;
}

render_page('Login', function () use ($csrfToken): void {
    echo '<section class="panel"><h1>Login</h1><p class="muted">Starter auth flow (invite/reset plumbing comes next).</p>';
    echo '<form method="post" class="grid"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '"><div class="grid two">';
    echo '<label>Email<input type="email" name="email" required></label>';
    echo '<div class="muted">Admin bootstrap uses BACKLINE_BOOTSTRAP_ADMIN_EMAIL.</div>';
    echo '</div><label>Login token<input type="password" name="bootstrap_token" required></label><p class="muted">Allowed users come from BACKLINE_ALLOWED_EMAILS. User concentration grants come from BACKLINE_DEFAULT_CONCENTRATIONS.</p>';
    echo '<button type="submit">Continue</button></form></section>';
});
