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
if (!user_has_permission($user, 'lx.access') && !user_has_permission($user, 'lx.shop')) {
    http_response_code(403);
    render_page('Forbidden', function () use ($user): void {
        echo '<div class="alert alert-danger">You do not have permission for this action.</div>';
    }, $user);
    exit;
}
redirect('/dash/lx');
