<?php

declare(strict_types=1);

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
    <div class="row justify-content-center mt-6"><div class="col-md-5"><div class="card"><div class="card-header"><h3 class="card-title">Reset Password</h3></div><div class="card-body">
    <form method="post"><?= csrf_input() ?><div class="mb-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div><button class="btn btn-primary">Send reset link</button></form>
    </div></div></div></div>
    <?php
});
