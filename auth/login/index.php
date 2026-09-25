<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/ui.php';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $bootstrapAdminEmail = trim((string) getenv('BACKLINE_BOOTSTRAP_ADMIN_EMAIL'));
    $role = ($bootstrapAdminEmail !== '' && strcasecmp($bootstrapAdminEmail, $email) === 0) ? 'admin' : 'user';
    $concentrations = $_POST['concentrations'] ?? [];
    $_SESSION['user'] = [
        'email' => $email,
        'role' => $role,
        'concentrations' => array_values(array_intersect(['lx', 'snd'], (array) $concentrations)),
    ];
    $destination = 'lx';
    if ($role === 'admin') {
        $destination = 'admin/dash';
    } elseif (in_array('snd', $_SESSION['user']['concentrations'], true) && !in_array('lx', $_SESSION['user']['concentrations'], true)) {
        $destination = 'snd';
    }
    header('Location: ' . app_url($destination));
    exit;
}

render_page('Login', function (): void {
    echo '<section class="panel"><h1>Login</h1><p class="muted">Starter auth flow (invite/reset plumbing comes next).</p>';
    echo '<form method="post" class="grid"><div class="grid two">';
    echo '<label>Email<input type="email" name="email" required></label>';
    echo '<div class="muted">Admin bootstrap uses the BACKLINE_BOOTSTRAP_ADMIN_EMAIL environment variable.</div>';
    echo '</div><div class="grid two">';
    echo '<label><input type="checkbox" name="concentrations[]" value="lx" checked> Lighting access</label>';
    echo '<label><input type="checkbox" name="concentrations[]" value="snd"> Sound access</label>';
    echo '</div><button type="submit">Continue</button></form></section>';
});
