<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/ui.php';

if (!current_user()) {
    header('Location: ' . app_url('auth/login'));
    exit;
}

$roots = ['Lighting', 'Sound', 'Backline Manuals'];
$selected = $_GET['folder'] ?? $roots[0];
if (!in_array($selected, $roots, true)) {
    $selected = $roots[0];
}

$relativePath = trim((string) ($_GET['path'] ?? ''), '/');
$segments = $relativePath === '' ? [] : explode('/', $relativePath);
$segments = array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..'));
$relativePath = implode('/', $segments);

$base = dirname(__DIR__) . '/uploads/resources/' . $selected . ($relativePath !== '' ? '/' . $relativePath : '');
$items = [];
if (is_dir($base)) {
    foreach (scandir($base) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $fullPath = $base . '/' . $item;
        $nextPath = ltrim($relativePath . '/' . $item, '/');
        $items[] = [
            'name' => $item,
            'is_dir' => is_dir($fullPath),
            'url' => is_dir($fullPath)
                ? app_url('resources?folder=' . rawurlencode($selected) . '&path=' . rawurlencode($nextPath))
                : app_url('resources/file?folder=' . rawurlencode($selected) . '&path=' . rawurlencode($relativePath) . '&name=' . rawurlencode($item)),
        ];
    }
}

render_page('Resources', function () use ($roots, $selected, $items, $relativePath): void {
    echo '<section class="panel"><h1>Resources</h1><div class="grid two">';
    echo '<div><h3>Folders</h3><ul>';
    foreach ($roots as $root) {
        $href = app_url('resources?folder=' . rawurlencode($root));
        echo '<li><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($root) . '</a></li>';
    }
    echo '</ul></div><div><h3>' . htmlspecialchars($selected . ($relativePath !== '' ? ' / ' . $relativePath : '')) . '</h3><ul>';
    if ($relativePath !== '') {
        $parts = explode('/', $relativePath);
        array_pop($parts);
        $upPath = implode('/', $parts);
        echo '<li><a href="' . htmlspecialchars(app_url('resources?folder=' . rawurlencode($selected) . '&path=' . rawurlencode($upPath))) . '">.. (Up)</a></li>';
    }
    foreach ($items as $item) {
        echo '<li>';
        if ($item['is_dir']) {
            echo '<a href="' . htmlspecialchars($item['url']) . '">📁 ' . htmlspecialchars($item['name']) . '</a>';
        } else {
            echo '<a target="_blank" rel="noopener" href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['name']) . '</a>';
        }
        echo '</li>';
    }
    echo '</ul></div></div></section>';
});
