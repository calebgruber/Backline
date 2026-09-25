<?php

declare(strict_types=1);

session_start();

$route = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$route = $route === '' ? 'auth/login' : $route;
$routeFile = __DIR__ . '/' . $route . '/index.php';

if (is_file($routeFile)) {
    require $routeFile;
    exit;
}

http_response_code(404);
require __DIR__ . '/shared/ui.php';
render_page('Not Found', function (): void {
    echo '<p>Route not found.</p>';
});
