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

if (auth_user()) {
    redirect('/admin/dash');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $email = post('email');
    $password = post('password');
    if (auth_login($email, $password)) {
        flash_set('success', 'Welcome back.');
        redirect('/admin/dash');
    }
    flash_set('danger', 'Invalid credentials.');
}

render_page('Login', function (): void {
    ?>
    <div class="row justify-content-center mt-6">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Login</h3></div>
                <div class="card-body">
                    <form method="post" autocomplete="on">
                        <?= csrf_input() ?>
                        <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                        <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="/auth/forgot">Forgot password?</a>
                            <button class="btn btn-primary">Sign in</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
});
