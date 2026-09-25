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
    $items = [];
    foreach (nav_items() as $item) {
        if (!isset($item['section'])) {
            $items[] = $item;
        }
    }

    return $items;
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
    if (in_array(strtolower((string) $scheme), ['https'], true)) {
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
    if ($target === '') {
        return false;
    }

    return $path === $target || str_starts_with($path, $target . '/');
}

function card_accent_color(string $icon): string
{
    static $map = [
        'dashboard' => '#6366f1',
        'settings' => '#6366f1',
        'group' => '#10b981',
        'theater_comedy' => '#8b5cf6',
        'inventory_2' => '#3b82f6',
        'folder' => '#f59e0b',
        'folder_open' => '#f59e0b',
        'lightbulb' => '#f59e0b',
        'graphic_eq' => '#ef4444',
        'lock' => '#3b82f6',
        'widgets' => '#3b82f6',
    ];
    return $map[$icon] ?? '#3b82f6';
}

function hex_to_rgb_and_text(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return [$r . ',' . $g . ',' . $b, $lum > 0.55 ? '#000000' : '#ffffff'];
}

function ui_alert(string $type, string $message): void
{
    $type = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
    $icons = [
        'info' => 'info',
        'success' => 'check_circle',
        'warning' => 'warning',
        'danger' => 'error',
    ];
    $accents = [
        'info' => ['#3b82f6', '59,130,246', '#ffffff'],
        'success' => ['#10b981', '16,185,129', '#ffffff'],
        'warning' => ['#f59e0b', '245,158,11', '#000000'],
        'danger' => ['#ef4444', '239,68,68', '#ffffff'],
    ];
    [$color, $rgb, $textOn] = $accents[$type] ?? $accents['info'];

    echo '<div class="alert alert-' . htmlspecialchars($type) . '" style="--alert-accent:' . htmlspecialchars($color) . ';--alert-accent-rgb:' . htmlspecialchars($rgb) . ';--alert-text-on-solid:' . htmlspecialchars($textOn) . '">';
    echo '<span class="material-symbols-outlined">' . htmlspecialchars($icons[$type]) . '</span>';
    echo '<span class="alert-text">' . htmlspecialchars($message) . '</span>';
    echo '</div>';
}

function ui_card_open(string $icon, string $title): void
{
    $accent = card_accent_color($icon);
    [$rgb, $textOn] = hex_to_rgb_and_text($accent);
    $style = 'border-left:3px solid ' . $accent
        . ';--card-accent:' . $accent
        . ';--card-accent-rgb:' . $rgb
        . ';--card-text-on-solid:' . $textOn;

    echo '<section class="card" style="' . htmlspecialchars($style) . '">';
    echo '<div class="card-top"><div class="card-tab">';
    echo '<span class="material-symbols-outlined">' . htmlspecialchars($icon) . '</span>';
    echo '<h3>' . htmlspecialchars($title) . '</h3>';
    echo '</div></div>';
    echo '<div class="card-body">';
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

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>' . htmlspecialchars($title) . ' | ' . htmlspecialchars($appName) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(app_url('shared/assets/style.css')) . '">';
    echo '<script>(function(){var t=localStorage.getItem("cg-theme")||(window.matchMedia("(prefers-color-scheme:dark)").matches?"dark":"light");document.documentElement.setAttribute("data-theme",t);})();document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("page-loader");if(l)l.classList.add("pg-done");});</script>';
    echo '</head><body><div id="page-loader"></div><div class="app">';

    echo '<div class="topbar">';
    echo '<a href="' . htmlspecialchars(app_url($homeRoute)) . '" class="topbar-brand">';
    if ($logo !== '') {
        echo '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($appName) . '" class="brand-logo brand-logo-light topbar-logo-image">';
        if ($logoDark !== '') {
            echo '<img src="' . htmlspecialchars($logoDark) . '" alt="' . htmlspecialchars($appName) . '" class="brand-logo brand-logo-dark topbar-logo-image">';
        }
    } else {
        echo '<span class="material-symbols-outlined topbar-logo-icon">dashboard</span>';
    }
    echo '<span class="topbar-brand-text">' . htmlspecialchars($appName) . '</span></a>';

    if ($user) {
        echo '<nav class="topbar-nav" aria-label="Primary">';
        foreach (top_nav_items() as $item) {
            $href = (string) ($item['href'] ?? '#');
            $active = is_active_nav($href) ? ' active' : '';
            echo '<a href="' . htmlspecialchars($href) . '" class="topbar-link' . $active . '">';
            echo '<span class="material-symbols-outlined">' . htmlspecialchars((string) ($item['icon'] ?? 'circle')) . '</span>';
            echo '<span>' . htmlspecialchars((string) ($item['label'] ?? '')) . '</span></a>';
        }
        echo '</nav>';
    }

    echo '<div class="topbar-right">';
    echo '<button id="theme-toggle" class="topbar-btn" title="Toggle theme" aria-label="Toggle theme"><span class="material-symbols-outlined" id="theme-icon">dark_mode</span></button>';
    if ($user) {
        $displayName = (string) ($user['email'] ?? 'Account');
        echo '<span class="topbar-user"><span class="topbar-username">' . htmlspecialchars($displayName) . '</span><div class="topbar-avatar">' . htmlspecialchars(user_avatar_text($displayName)) . '</div></span>';
        echo '<a href="' . htmlspecialchars($logoutRoute) . '" class="topbar-btn topbar-logout" title="Logout" aria-label="Logout"><span class="material-symbols-outlined">logout</span></a>';
    }
    echo '</div></div>';

    echo '<main class="content"><div class="page-header"><div><h1>' . htmlspecialchars($title) . '</h1></div></div><div class="page-body">';
    $content();
    echo '</div></main></div><script src="' . htmlspecialchars(app_url('shared/assets/app.js')) . '"></script></body></html>';
}
