<?php

declare(strict_types=1);

if (!function_exists('app_config')) {
    $bootstrapRoot = __DIR__;
    while (!file_exists($bootstrapRoot . '/shared/bootstrap.php')) {
        $parent = dirname($bootstrapRoot);
        if ($parent === $bootstrapRoot) {
            break;
        }
        $bootstrapRoot = $parent;
    }
    require_once $bootstrapRoot . '/shared/bootstrap.php';
}

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

    if (mb_strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } else {
        db()->beginTransaction();
        try {
            $stmt = db()->prepare('SELECT * FROM password_tokens WHERE token_hash = ? AND token_type IN ("reset", "invite") AND used_at IS NULL AND expires_at >= NOW() LIMIT 1 FOR UPDATE');
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch();
            if (!$row) {
                db()->rollBack();
                $error = 'Invalid or expired token.';
            } else {
                $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
                $stmt = db()->prepare('UPDATE password_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
                $stmt->execute([(int) $row['id']]);
                db()->commit();
                flash_set('success', 'Password has been set. You can login now.');
                redirect('/auth/login');
            }
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $error = 'Unable to reset password right now.';
        }
    }
}

render_page('Set Password', function () use ($token, $error): void {
    if ($error) {
        echo '<div class="alert alert-danger">' . e($error) . '</div>';
    }
    ?>
    <form class="card card-md" method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="card-body">
            <h2 class="card-title text-center mb-4">Set your password</h2>
            <div class="mb-3">
                <label class="form-label">New password</label>
                <input class="form-control js-password-strength-input" type="password" name="password" required minlength="12" placeholder="New password">
                <div class="progress progress-sm mt-2"><div class="progress-bar js-password-strength-bar" style="width:0%"></div></div>
                <div class="small text-secondary mt-1 js-password-strength-text">Strength: Too weak</div>
            </div>
            <div class="form-footer">
                <button class="btn btn-primary w-100">Save password</button>
            </div>
        </div>
    </form>
    <?php
});
