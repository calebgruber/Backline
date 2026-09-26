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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $email = mb_strtolower(post('email'));
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $token = bin2hex(random_bytes(32));
        $stmt = db()->prepare('INSERT INTO password_tokens (user_id, token_hash, token_type, expires_at, created_at) VALUES (?, ?, "reset", DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())');
        $stmt->execute([(int) $id, hash('sha256', $token)]);
        $url = (config('base_url') ?: '') . '/auth/reset?token=' . urlencode($token);
        send_basic_mail($email, 'Backline password reset', 'Open this link within 24 hours: <a href="' . e($url) . '">' . e($url) . '</a>');
    }
    flash_set('success', 'If the account exists, a reset link was sent.');
    redirect('/auth/login');
}

render_page('Forgot Password', function (): void {
    ?>
    <form class="card card-md" method="post">
        <?= csrf_input() ?>
        <div class="card-body">
            <h2 class="card-title text-center mb-4">Forgot password</h2>
            <p class="text-secondary mb-4">Enter your email and we’ll send a reset link.</p>
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input class="form-control" type="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="form-footer">
                <button class="btn btn-primary w-100">Send reset link</button>
            </div>
        </div>
        <div class="hr-text">or</div>
        <div class="card-body">
            <a href="/auth/login" class="btn btn-outline-secondary w-100">Back to sign in</a>
        </div>
    </form>
    <?php
});
