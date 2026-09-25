<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/ui.php';
require_once dirname(__DIR__, 2) . '/shared/db.php';
require_once dirname(__DIR__, 2) . '/shared/login_security.php';

if (!is_setup_complete()) {
    header('Location: ' . app_url('setup'));
    exit;
}

if (current_user()) {
    header('Location: ' . app_url(user_home_route((array) current_user())));
    exit;
}

$errors = [];
$submittedEmail = '';
if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['login_csrf_token'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $submittedEmail = $email;
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Invalid request token.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (!db_ready()) {
        $errors[] = 'Database connection is unavailable.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, email, role, password_hash FROM users WHERE email = ? AND password_hash IS NOT NULL AND password_hash <> ? LIMIT 1');
            $stmt->execute([$email, '']);
            $userRow = $stmt->fetch();

            $valid = verify_login_credentials($userRow ?: null, $password);
            if (!$valid) {
                $errors[] = 'Invalid email or password.';
            } else {
                $grantsStmt = db()->prepare('SELECT concentration FROM user_concentrations WHERE user_id = ?');
                $grantsStmt->execute([(int) $userRow['id']]);
                $dbConcentrations = $grantsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                session_regenerate_id(true);
                $_SESSION = [];
                $_SESSION['user'] = [
                    'id' => (int) $userRow['id'],
                    'email' => (string) $userRow['email'],
                    'role' => (string) $userRow['role'],
                    'concentrations' => normalized_session_concentrations($dbConcentrations),
                ];
                $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));

                header('Location: ' . app_url(user_home_route($_SESSION['user'])));
                exit;
            }
        } catch (Throwable $error) {
            error_log('Login failed: ' . $error->getMessage());
            $errors[] = 'Login failed. Try again.';
        }
    }
}

render_page('Login', function () use ($csrfToken, $errors, $submittedEmail): void {
    ui_card_open('lock', 'Login');
    echo '<p class="text-muted">Sign in with your account.</p>';
    if ($errors !== []) {
        echo '<div role="alert" aria-live="assertive">';
        foreach ($errors as $error) {
            ui_alert('danger', $error);
        }
        echo '</div>';
    }

    echo '<form method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
    echo '<div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="' . htmlspecialchars($submittedEmail) . '" required></div>';
    echo '<div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>';
    echo '<button class="btn btn-primary" type="submit">Sign In</button>';
    echo '</form>';
    ui_card_close();
});
