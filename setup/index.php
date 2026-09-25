<?php

declare(strict_types=1);

if (!function_exists('app_is_installed')) {
    require_once __DIR__ . '/../shared/bootstrap.php';
}

if (app_is_installed()) {
    redirect('/auth/login');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();

    $local = [
        'installed' => false,
        'db' => [
            'host' => post('db_host'),
            'port' => (int) post('db_port', '3306'),
            'name' => post('db_name'),
            'user' => post('db_user'),
            'pass' => post('db_pass'),
            'charset' => 'utf8mb4',
        ],
        'mail' => [
            'from_name' => post('mail_from_name', 'Backline'),
            'from_email' => post('mail_from_email', 'noreply@example.com'),
        ],
    ];

    try {
        db_test_connection($local['db']);
        write_local_config($local);

        run_pending_migrations();

        $name = post('admin_name');
        $email = mb_strtolower(post('admin_email'));
        $password = post('admin_password');

        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, is_super_admin, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int) db()->lastInsertId();

        $stmt = db()->prepare('INSERT INTO app_settings (`key_name`, `value_json`, `created_at`, `updated_at`) VALUES
            ("branding.app_name", ?, NOW(), NOW()),
            ("branding.made_in", ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = NOW()');
        $stmt->execute([json_encode(post('app_name', 'Backline')), json_encode(post('made_in', 'USA'))]);

        $local['installed'] = true;
        write_local_config($local);

        audit_log($userId, 'system', 'setup_complete', null, ['email' => $email]);
        flash_set('success', 'Setup complete. Please login.');
        redirect('/auth/login');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

render_page('Setup', function () use ($error): void {
    if ($error) {
        echo '<div class="alert alert-danger">' . e($error) . '</div>';
    }
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Initial Setup</h3></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_input() ?>
                        <h4>Database</h4>
                        <div class="row g-3">
                            <div class="col-md-6"><input class="form-control" name="db_host" placeholder="Host" required value="127.0.0.1"></div>
                            <div class="col-md-6"><input class="form-control" name="db_port" placeholder="Port" required value="3306"></div>
                            <div class="col-md-6"><input class="form-control" name="db_name" placeholder="Database" required></div>
                            <div class="col-md-6"><input class="form-control" name="db_user" placeholder="User" required></div>
                            <div class="col-12"><input class="form-control" type="password" name="db_pass" placeholder="Password"></div>
                        </div>
                        <hr>
                        <h4>Branding</h4>
                        <div class="row g-3">
                            <div class="col-md-6"><input class="form-control" name="app_name" placeholder="App Name" value="Backline"></div>
                            <div class="col-md-6"><input class="form-control" name="made_in" placeholder="Made In" value="USA"></div>
                            <div class="col-md-6"><input class="form-control" name="mail_from_name" placeholder="Mail From Name" value="Backline"></div>
                            <div class="col-md-6"><input class="form-control" name="mail_from_email" placeholder="Mail From Email" value="noreply@example.com"></div>
                        </div>
                        <hr>
                        <h4>Admin User</h4>
                        <div class="row g-3">
                            <div class="col-md-6"><input class="form-control" name="admin_name" placeholder="Name" required></div>
                            <div class="col-md-6"><input type="email" class="form-control" name="admin_email" placeholder="Email" required></div>
                            <div class="col-12"><input type="password" class="form-control" name="admin_password" placeholder="Password" required minlength="12"></div>
                        </div>
                        <div class="mt-4 d-flex justify-content-end"><button class="btn btn-primary">Complete Setup</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
});
