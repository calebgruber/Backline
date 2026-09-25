<?php

declare(strict_types=1);

require_once __DIR__ . '/shared/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', $basePath);
}
if ($basePath !== '' && $basePath !== '/' && str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}

$routeSegments = array_values(array_filter(explode('/', trim($requestPath, '/')), static fn (string $segment): bool => $segment !== ''));
$safeSegments = [];
foreach ($routeSegments as $segment) {
    $segment = rawurldecode($segment);
    if ($segment === '.' || $segment === '..') {
        continue;
    }
    if (str_contains($segment, '/') || str_contains($segment, '\\') || str_contains($segment, chr(0))) {
        continue;
    }
    $safeSegments[] = $segment;
}
$route = implode('/', $safeSegments);
$route = $route === '' ? 'auth/login' : $route;

$isSetupRoute = $route === 'setup' || str_starts_with($route, 'setup/');
if (!is_setup_complete() && !$isSetupRoute) {
    header('Location: ' . app_url('setup'));
    exit;
}
if (is_setup_complete() && $isSetupRoute) {
    header('Location: ' . app_url('auth/login'));
    exit;
}

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
