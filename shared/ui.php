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

    return '';
}

function current_path(): string
{
    $pathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $basePath = defined('APP_BASE_PATH') ? (string) APP_BASE_PATH : '';
    if ($basePath !== '' && $basePath !== '/' && str_starts_with($pathRaw, $basePath)) {
        $pathRaw = substr($pathRaw, strlen($basePath)) ?: '/';
    }

    $path = trim($pathRaw, '/');
    return $path === '' ? 'auth/login' : $path;
}

function user_initials(string $value): string
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
    if ($basePath !== '' && $basePath !== '/' && str_starts_with($targetPath, $basePath)) {
        $targetPath = substr($targetPath, strlen($basePath)) ?: '/';
    }
    $target = trim($targetPath, '/');
    if ($target === '') {
        return false;
    }
    return $path === $target || str_starts_with($path, $target . '/');
}

function render_page(string $title, callable $content): void
{
    $settings = app_settings();
    $user = $_SESSION['user'] ?? null;
    $logo = safe_logo_src(trim((string) ($settings['branding_logo'] ?? '')));
    $logoDark = safe_logo_src(trim((string) ($settings['branding_logo_dark'] ?? '')));
    $appName = (string) ($settings['app_name'] ?? 'Backline');
    $homeRoute = $user ? user_home_route($user) : 'auth/login';

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>' . htmlspecialchars($title) . ' | ' . htmlspecialchars($appName) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(app_url('shared/assets/style.css')) . '">';
    echo '<script>(function(){var t=localStorage.getItem("cg-theme")||(window.matchMedia("(prefers-color-scheme:dark)").matches?"dark":"light");document.documentElement.setAttribute("data-theme",t);})();document.addEventListener("DOMContentLoaded",function(){var l=document.getElementById("page-loader");if(l)l.classList.add("pg-done");});</script>';
    echo '</head><body><div id="page-loader"></div><div class="app">';

    echo '<div class="topbar">';
    echo '<button id="mobile-menu-btn" class="topbar-btn mobile-menu-btn" title="Menu" aria-label="Open navigation menu"><span class="material-symbols-outlined">menu</span></button>';
    echo '<a href="' . htmlspecialchars(app_url($homeRoute)) . '" class="topbar-launcher"><span class="material-symbols-outlined">home</span>Launcher</a>';
    echo '<span class="topbar-sep">›</span>';
    echo '<span class="topbar-app"><span class="material-symbols-outlined">dashboard</span>' . htmlspecialchars($title) . '</span>';
    echo '<div class="topbar-right">';
    echo '<button id="theme-toggle" class="topbar-btn" title="Toggle theme" aria-label="Toggle light and dark theme"><span class="material-symbols-outlined" id="theme-icon">dark_mode</span></button>';
    if ($user) {
        $displayName = (string) ($user['email'] ?? 'Account');
        $initials = user_initials($displayName);
        echo '<span class="topbar-user"><div class="topbar-avatar">' . htmlspecialchars($initials) . '</div></span>';
    }
    echo '</div></div>';

    echo '<div id="sidebar-overlay" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:49;top:2.625rem;"></div>';
    echo '<aside class="sidebar">';
    echo '<div class="sidebar-header"><h2>';
    if ($logo !== '') {
        echo '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($appName) . '" class="brand-logo brand-logo-light">';
        if ($logoDark !== '') {
            echo '<img src="' . htmlspecialchars($logoDark) . '" alt="' . htmlspecialchars($appName) . '" class="brand-logo brand-logo-dark">';
        }
    } else {
        echo '<span class="material-symbols-outlined app-logo">dashboard</span>' . htmlspecialchars($appName);
    }
    echo '</h2></div><nav>';

    foreach (nav_items() as $item) {
        if (isset($item['section'])) {
            echo '<div class="sidebar-section">' . htmlspecialchars((string) $item['section']) . '</div>';
            continue;
        }
        $href = (string) ($item['href'] ?? '#');
        $active = is_active_nav($href) ? ' active' : '';
        echo '<a href="' . htmlspecialchars($href) . '" class="nav-item' . $active . '"><span class="material-symbols-outlined">' . htmlspecialchars((string) ($item['icon'] ?? 'circle')) . '</span>' . htmlspecialchars((string) ($item['label'] ?? '')) . '</a>';
    }
    echo '</nav>';

    if ($user) {
        $displayName = (string) ($user['email'] ?? 'Account');
        $initials = user_initials($displayName);
        echo '<div class="sidebar-footer"><div class="user-info"><div class="user-avatar">' . htmlspecialchars($initials) . '</div><div class="user-details"><div class="user-name truncate">' . htmlspecialchars($displayName) . '</div><div class="user-role">' . htmlspecialchars(ucfirst((string) ($user['role'] ?? 'user'))) . '</div></div></div></div>';
    }

    echo '</aside><main class="content">';
    echo '<div class="page-header"><div><h2>' . htmlspecialchars($title) . '</h2></div></div>';
    echo '<div class="page-body">';
    $content();
    echo '</div></main></div><script src="' . htmlspecialchars(app_url('shared/assets/app.js')) . '"></script></body></html>';
}
