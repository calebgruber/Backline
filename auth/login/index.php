<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/ui.php';
require_once dirname(__DIR__, 2) . '/shared/db.php';

if (!is_setup_complete()) {
    header('Location: ' . app_url('setup'));
    exit;
}

if (current_user()) {
    header('Location: ' . app_url(user_home_route((array) current_user())));
    exit;
}

$errors = [];
if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['login_csrf_token'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
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
            $stmt = db()->prepare('SELECT id, email, role, password_hash FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $userRow = $stmt->fetch();

            $dummyHash = '$2y$10$7f88sJHCa9BnB3V0mzx4YO9HByB3H0QdVfW0IpW8I8wlQwLO9vf7W';
            $hashToVerify = $userRow && !empty($userRow['password_hash']) ? (string) $userRow['password_hash'] : $dummyHash;
            $passwordOk = password_verify($password, $hashToVerify);
            $valid = $userRow && $passwordOk;
            if (!$valid) {
                $errors[] = 'Invalid email or password.';
            } else {
                $grantsStmt = db()->prepare('SELECT concentration FROM user_concentrations WHERE user_id = ?');
                $grantsStmt->execute([(int) $userRow['id']]);
                $dbConcentrations = $grantsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $userRow['id'],
                    'email' => (string) $userRow['email'],
                    'role' => (string) $userRow['role'],
                    'concentrations' => array_values(array_intersect(['lx', 'snd'], $dbConcentrations)),
                ];

                header('Location: ' . app_url(user_home_route($_SESSION['user'])));
                exit;
            }
        } catch (Throwable $error) {
            error_log('Login failed: ' . $error->getMessage());
            $errors[] = 'Login failed. Try again.';
        }
    }
}

render_page('Login', function () use ($csrfToken, $errors): void {
    echo '<section class="panel"><h1>Login</h1><p class="muted">Sign in with your account.</p>';
    foreach ($errors as $error) {
        echo '<p style="color:#ffb8b8;">' . htmlspecialchars($error) . '</p>';
    }

    echo '<form method="post" class="grid">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
    echo '<label>Email<input type="email" name="email" required></label>';
    echo '<label>Password<input type="password" name="password" required></label>';
    echo '<button type="submit">Sign In</button>';
    echo '</form></section>';
});
