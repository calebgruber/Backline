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

$user = require_auth();

render_page('Profile', function () use ($user): void {
    ?>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body text-center">
                    <span class="avatar avatar-xl rounded-3 mb-3" style="background-image:url(https://api.dicebear.com/9.x/thumbs/svg?seed=<?= urlencode((string) $user['email']) ?>)"></span>
                    <h3 class="mb-1"><?= e($user['name']) ?></h3>
                    <p class="text-secondary mb-0"><?= e($user['email']) ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php
}, $user);
