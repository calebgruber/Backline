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
    $appName = (string) app_setting('branding.app_name', config('app_name', 'Backline'));
    $brandingDir = __DIR__ . '/../../uploads/branding';
    $logoLightFile = function_exists('first_existing_brand_asset') ? first_existing_brand_asset($brandingDir, ['logo-light.*', 'logo.*']) : null;
    $logoDarkFile = function_exists('first_existing_brand_asset') ? first_existing_brand_asset($brandingDir, ['logo-dark.*']) : null;
    $logoLightPath = $logoLightFile ? '/uploads/branding/' . basename($logoLightFile) : null;
    $logoDarkPath = $logoDarkFile ? '/uploads/branding/' . basename($logoDarkFile) : null;
    $loginCardColor = (string) app_setting('branding.login_card_color', '');
    $loginCardIcon = (string) app_setting('branding.login_card_icon', '');
    ?>
    <div class="text-center mb-4">
        <a href="/" class="navbar-brand navbar-brand-autodark">
            <?php if ($logoLightPath): ?><img src="<?= e($logoLightPath) ?>" alt="<?= e($appName) ?> logo" class="auth-page-logo logo-light"><?php endif; ?>
            <?php if ($logoDarkPath): ?><img src="<?= e($logoDarkPath) ?>" alt="<?= e($appName) ?> logo" class="auth-page-logo logo-dark"><?php endif; ?>
            <?php if (!$logoLightPath && !$logoDarkPath): ?><span class="h2"><?= e($appName) ?></span><?php endif; ?>
        </a>
    </div>
    <form class="card card-md" method="post" autocomplete="on" data-card-color="<?= e($loginCardColor) ?>" data-card-icon="<?= e($loginCardIcon) ?>">
        <?= csrf_input() ?>
        <div class="card-body">
            <h2 class="h2 text-center mb-4">Sign in to your account</h2>
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input class="form-control" type="email" name="email" required autocomplete="email">
            </div>
            <div class="mb-2">
                <label class="form-label">
                    Password
                    <span class="form-label-description"><a href="/auth/forgot">Forgot password?</a></span>
                </label>
                <input class="form-control" type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="form-footer">
                <button class="btn btn-primary w-100">Sign in</button>
            </div>
        </div>
    </form>
    <?php
});
