<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
if ($basePath !== '' && $basePath !== '/' && str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

$route = trim($requestPath, '/');
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
