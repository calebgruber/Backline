<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/ui.php';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
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
    echo '<label>Role<select name="role"><option value="user">User</option><option value="admin">Admin</option></select></label>';
    echo '</div><div class="grid two">';
    echo '<label><input type="checkbox" name="concentrations[]" value="lx" checked> Lighting access</label>';
    echo '<label><input type="checkbox" name="concentrations[]" value="snd"> Sound access</label>';
    echo '</div><button type="submit">Continue</button></form></section>';
});
