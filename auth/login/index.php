<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/ui.php';

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

    $bootstrapAdminEmail = trim((string) getenv('BACKLINE_BOOTSTRAP_ADMIN_EMAIL'));
    $role = ($bootstrapAdminEmail !== '' && strcasecmp($bootstrapAdminEmail, $email) === 0) ? 'admin' : 'user';
    $defaultConcentrations = array_values(array_intersect(
        ['lx', 'snd'],
        array_map('trim', explode(',', (string) getenv('BACKLINE_DEFAULT_CONCENTRATIONS')))
    ));
    if ($defaultConcentrations === []) {
        $defaultConcentrations = ['lx'];
    }
    $concentrations = $role === 'admin' ? ['lx', 'snd'] : $defaultConcentrations;
    $_SESSION['user'] = [
        'email' => $email,
        'role' => $role,
        'concentrations' => $concentrations,
    ];
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
