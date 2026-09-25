<?php

declare(strict_types=1);

require __DIR__ . '/shared/bootstrap.php';

$path = route_path();

if (!app_is_installed() && !path_starts_with($path, '/setup')) {
    redirect('/setup');
}

if (app_is_installed() && path_starts_with($path, '/setup')) {
    redirect('/auth/login');
}

$routes = [
    '/' => __DIR__ . '/home/index.php',
    '/auth/login' => __DIR__ . '/auth/login/index.php',
    '/auth/logout' => __DIR__ . '/auth/logout/index.php',
    '/auth/forgot' => __DIR__ . '/auth/forgot/index.php',
    '/auth/reset' => __DIR__ . '/auth/reset/index.php',
    '/auth/invite' => __DIR__ . '/auth/invite/index.php',
    '/setup' => __DIR__ . '/setup/index.php',
    '/admin/dash' => __DIR__ . '/admin/dash/index.php',
    '/admin/settings' => __DIR__ . '/admin/settings/index.php',
    '/admin/users' => __DIR__ . '/admin/users/index.php',
    '/admin/inventory' => __DIR__ . '/admin/inventory/index.php',
    '/admin/categories' => __DIR__ . '/admin/categories/index.php',
    '/admin/resources' => __DIR__ . '/admin/resources/index.php',
    '/admin/shows' => __DIR__ . '/admin/shows/index.php',
    '/profile' => __DIR__ . '/profile/index.php',
    '/lx' => __DIR__ . '/lx/index.php',
    '/snd' => __DIR__ . '/snd/index.php',
    '/resources' => __DIR__ . '/resources/index.php',
];

if (isset($routes[$path])) {
    require $routes[$path];
    exit;
}

http_response_code(404);
render_page('Not found', function (): void {
    echo '<div class="alert alert-danger">Page not found.</div>';
});
