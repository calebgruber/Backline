<?php

declare(strict_types=1);

if (!app_is_installed()) {
    redirect('/setup');
}

$token = (string) ($_GET['token'] ?? '');
$tokenHash = hash('sha256', $token);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $token = post('token');
    $tokenHash = hash('sha256', $token);
    $password = post('password');

    $stmt = db()->prepare('SELECT * FROM password_tokens WHERE token_hash = ? AND token_type IN ("reset", "invite") AND used_at IS NULL AND expires_at >= NOW() LIMIT 1');
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    if (!$row) {
        $error = 'Invalid or expired token.';
    } else {
        db()->beginTransaction();
        $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
        $stmt = db()->prepare('UPDATE password_tokens SET used_at = NOW() WHERE id = ?');
        $stmt->execute([(int) $row['id']]);
        db()->commit();
        flash_set('success', 'Password has been set. You can login now.');
        redirect('/auth/login');
    }
}

render_page('Set Password', function () use ($token, $error): void {
    if ($error) {
        echo '<div class="alert alert-danger">' . e($error) . '</div>';
    }
    ?>
    <div class="row justify-content-center mt-6"><div class="col-md-5"><div class="card"><div class="card-header"><h3 class="card-title">Set Password</h3></div><div class="card-body">
    <form method="post"><?= csrf_input() ?><input type="hidden" name="token" value="<?= e($token) ?>"><div class="mb-3"><input class="form-control" type="password" name="password" required minlength="12" placeholder="New password"></div><button class="btn btn-primary">Save password</button></form>
    </div></div></div></div>
    <?php
});
