<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function nav_items(): array
{
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        return [];
    }

    $items = [];
    if (($user['role'] ?? '') === 'admin') {
        $items[] = ['section' => 'Admin'];
        $items[] = ['icon' => 'dashboard', 'label' => 'Dashboard', 'href' => app_url('admin/dash')];
        $items[] = ['icon' => 'widgets', 'label' => 'Components', 'href' => app_url('admin/components')];
        $items[] = ['icon' => 'settings', 'label' => 'Settings', 'href' => app_url('admin/settings')];
        $items[] = ['icon' => 'group', 'label' => 'Users', 'href' => app_url('admin/users')];
        $items[] = ['icon' => 'theater_comedy', 'label' => 'Shows', 'href' => app_url('admin/shows')];
        $items[] = ['icon' => 'inventory_2', 'label' => 'Inventory', 'href' => app_url('admin/inventory')];
        $items[] = ['icon' => 'folder', 'label' => 'Resources Manager', 'href' => app_url('admin/resources')];
    }

    $items[] = ['section' => 'Apps'];
    if (in_array('lx', $user['concentrations'] ?? [], true) || ($user['role'] ?? '') === 'admin') {
        $items[] = ['icon' => 'lightbulb', 'label' => 'Lighting', 'href' => app_url('lx')];
    }
    if (in_array('snd', $user['concentrations'] ?? [], true) || ($user['role'] ?? '') === 'admin') {
        $items[] = ['icon' => 'graphic_eq', 'label' => 'Sound', 'href' => app_url('snd')];
    }
    $items[] = ['icon' => 'folder_open', 'label' => 'Resources', 'href' => app_url('resources')];

    return $items;
}

function top_nav_items(): array
{
    return array_values(array_filter(nav_items(), static fn (array $item): bool => !isset($item['section'])));
}

function safe_logo_src(string $logo): string
{
    if ($logo === '') {
        return '';
    }
    if (str_starts_with($logo, '/')) {
        return $logo;
    }
    $scheme = parse_url($logo, PHP_URL_SCHEME);
    if (in_array(strtolower((string) $scheme), ['https', 'http'], true)) {
        return $logo;
    }
    if ($scheme === null && !str_contains($logo, '..') && !str_contains($logo, '\\') && !str_starts_with($logo, '//')) {
        return app_url(ltrim($logo, '/'));
    }

    return '';
}

function current_path(): string
{
    $pathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $basePath = defined('APP_BASE_PATH') ? (string) APP_BASE_PATH : '';
    $basePrefix = $basePath === '' || $basePath === '/' ? '' : rtrim($basePath, '/') . '/';
    if ($basePath !== '' && $basePath !== '/' && ($pathRaw === $basePath || str_starts_with($pathRaw, $basePrefix))) {
        $pathRaw = substr($pathRaw, strlen($basePath)) ?: '/';
    }

    $path = trim($pathRaw, '/');
    return $path === '' ? 'auth/login' : $path;
}

function is_active_nav(string $target): bool
{
    $path = current_path();
    if ($target === '' || $target === '#' || str_starts_with($target, '#')) {
        return false;
    }

    $targetPath = parse_url($target, PHP_URL_PATH) ?: $target;
    $basePath = defined('APP_BASE_PATH') ? (string) APP_BASE_PATH : '';
    $basePrefix = $basePath === '' || $basePath === '/' ? '' : rtrim($basePath, '/') . '/';
    if ($basePath !== '' && $basePath !== '/' && ($targetPath === $basePath || str_starts_with($targetPath, $basePrefix))) {
        $targetPath = substr($targetPath, strlen($basePath)) ?: '/';
    }

    $target = trim($targetPath, '/');
    return $target !== '' && ($path === $target || str_starts_with($path, $target . '/'));
}

function user_avatar_text(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return 'AC';
    }
    if (function_exists('mb_substr')) {
        return strtoupper((string) mb_substr($trimmed, 0, 2, 'UTF-8'));
    }

    return strtoupper(substr($trimmed, 0, 2));
}

function ui_alert(string $type, string $message): void
{
    $map = [
        'info' => 'info',
        'success' => 'success',
        'warning' => 'warning',
        'danger' => 'danger',
    ];
    $class = $map[$type] ?? 'info';

    echo '<div class="alert alert-' . htmlspecialchars($class) . '" role="alert">' . htmlspecialchars($message) . '</div>';
}

function ui_card_open(string $icon, string $title): void
{
    $iconLabel = strtoupper(str_replace('_', ' ', trim($icon)));
    echo '<section class="card mb-3 backline-card">';
    echo '<div class="card-header">';
    echo '<h3 class="card-title mb-0"><span class="badge bg-primary-lt text-primary me-2">' . htmlspecialchars($iconLabel) . '</span>' . htmlspecialchars($title) . '</h3>';
    echo '</div><div class="card-body">';
}

function ui_card_close(): void
{
    echo '</div></section>';
}

function render_page(string $title, callable $content): void
{
    $settings = app_settings();
    $user = $_SESSION['user'] ?? null;
    $logo = safe_logo_src(trim((string) ($settings['branding_logo'] ?? '')));
    $logoDark = safe_logo_src(trim((string) ($settings['branding_logo_dark'] ?? '')));
    $appName = (string) ($settings['app_name'] ?? 'Backline');
    $homeRoute = $user ? user_home_route($user) : 'auth/login';
    $logoutRoute = app_url('auth/logout');
    if ($user && empty($_SESSION['logout_csrf_token'])) {
        $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
    }
    $logoutCsrf = (string) ($_SESSION['logout_csrf_token'] ?? '');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>' . htmlspecialchars($title) . ' | ' . htmlspecialchars($appName) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css">';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(app_url('shared/assets/style.css')) . '">';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(app_url('shared/assets/custom.css')) . '">';
    echo '<script>(function(){var t;try{t=localStorage.getItem("cg-theme");}catch(e){t=null;}if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}document.documentElement.setAttribute("data-bs-theme",t);})();</script>';
    echo '</head><body>';
    echo '<div id="page-loader" aria-hidden="true"></div>';

    echo '<header class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top"><div class="container-xl">';
    echo '<a class="navbar-brand d-flex align-items-center gap-2" href="' . htmlspecialchars(app_url($homeRoute)) . '">';
    if ($logo !== '') {
        echo '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($appName) . '" class="brand-logo brand-logo-light">';
        if ($logoDark !== '') {
            echo '<img src="' . htmlspecialchars($logoDark) . '" alt="' . htmlspecialchars($appName) . '" class="brand-logo brand-logo-dark">';
        }
    }
    echo '<span class="navbar-brand-text">' . htmlspecialchars($appName) . '</span></a>';

    echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#backline-top-nav" aria-controls="backline-top-nav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>';
    echo '<div class="collapse navbar-collapse" id="backline-top-nav">';

    if ($user) {
        echo '<ul class="navbar-nav me-auto">';
        foreach (top_nav_items() as $item) {
            $href = (string) ($item['href'] ?? '#');
            $active = is_active_nav($href) ? ' active' : '';
            echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . htmlspecialchars($href) . '">' . htmlspecialchars((string) ($item['label'] ?? '')) . '</a></li>';
        }
        echo '</ul>';

        $displayName = (string) ($user['email'] ?? 'Account');
        echo '<div class="navbar-nav ms-auto align-items-center gap-2">';
        echo '<button type="button" id="theme-toggle" class="btn btn-ghost-secondary btn-icon" aria-label="Toggle theme" aria-pressed="false">🌓</button>';
        echo '<span class="nav-link disabled text-secondary">' . htmlspecialchars($displayName) . '</span>';
        echo '<span class="avatar avatar-sm">' . htmlspecialchars(user_avatar_text($displayName)) . '</span>';
        echo '<form method="post" action="' . htmlspecialchars($logoutRoute) . '" class="d-inline-block m-0">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($logoutCsrf) . '">';
        echo '<button class="btn btn-outline-light btn-sm" type="submit">Logout</button>';
        echo '</form>';
        echo '</div>';
    }

    echo '</div></div></header>';

    echo '<main class="page-wrapper"><div class="page-body"><div class="container-xl py-4">';
    echo '<div class="d-flex align-items-center justify-content-between mb-3"><h1 class="page-title mb-0">' . htmlspecialchars($title) . '</h1></div>';
    $content();
    echo '</div></div></main>';

    echo '<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>';
    echo '<script src="' . htmlspecialchars(app_url('shared/assets/app.js')) . '"></script>';
    echo '</body></html>';
}
