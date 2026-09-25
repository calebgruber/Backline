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
    redirect('/dash/home');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $email = post('email');
    $password = post('password');
    if (auth_login($email, $password)) {
        flash_set('success', 'Welcome back.');
        redirect('/dash/home');
    }
    flash_set('danger', 'Invalid credentials.');
}

render_page('Login', function (): void {
    $brandingDir = __DIR__ . '/../../uploads/branding';
    $logoLightFile = function_exists('first_existing_brand_asset') ? first_existing_brand_asset($brandingDir, ['logo-light.*', 'logo.*']) : null;
    $logoDarkFile = function_exists('first_existing_brand_asset') ? first_existing_brand_asset($brandingDir, ['logo-dark.*']) : null;
    $logoLightPath = $logoLightFile ? '/uploads/branding/' . basename($logoLightFile) : null;
    $logoDarkPath = $logoDarkFile ? '/uploads/branding/' . basename($logoDarkFile) : null;
    $loginCardColor = (string) app_setting('branding.login_card_color', '');
    $loginCardIcon = (string) app_setting('branding.login_card_icon', '');
    ?>
    <div class="row justify-content-center mt-6">
        <div class="col-md-5">
            <div class="card" data-card-color="<?= e($loginCardColor) ?>" data-card-icon="<?= e($loginCardIcon) ?>">
                <div class="card-header"><h3 class="card-title">Login</h3></div>
                <div class="card-body">
                    <?php if ($logoLightPath || $logoDarkPath): ?>
                        <div class="text-center mb-4">
                            <?php if ($logoLightPath): ?><img src="<?= e($logoLightPath) ?>" alt="logo" class="auth-page-logo logo-light"><?php endif; ?>
                            <?php if ($logoDarkPath): ?><img src="<?= e($logoDarkPath) ?>" alt="logo" class="auth-page-logo logo-dark"><?php endif; ?>
                        </div>
                    <?php endif; ?>
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
