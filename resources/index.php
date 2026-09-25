<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/ui.php';

if (!current_user()) {
    header('Location: ' . app_url('auth/login'));
    exit;
}

$user = current_user();
$roots = $user ? allowed_resource_roots($user) : ['Backline Manuals'];
$selected = $_GET['folder'] ?? $roots[0];
if (!in_array($selected, $roots, true)) {
    $selected = $roots[0];
}

$relativePath = trim((string) ($_GET['path'] ?? ''), '/');
$segments = $relativePath === '' ? [] : explode('/', $relativePath);
$segments = array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..' && !str_contains($segment, '\\') && !str_contains($segment, '/')));
$relativePath = implode('/', $segments);

$root = realpath(dirname(__DIR__) . '/uploads/resources/' . $selected);
$base = $root !== false ? ($root . ($relativePath !== '' ? '/' . $relativePath : '')) : '';
$resolvedBase = $base !== '' ? realpath($base) : false;
if ($root === false || $resolvedBase === false || (!str_starts_with($resolvedBase, $root . DIRECTORY_SEPARATOR) && $resolvedBase !== $root)) {
    $resolvedBase = $root;
    $relativePath = '';
}
$items = [];
if ($resolvedBase !== false && is_dir($resolvedBase)) {
    foreach (scandir($resolvedBase) ?: [] as $item) {
        if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
            continue;
        }
        $fullPath = $resolvedBase . '/' . $item;
        $isDirectory = is_dir($fullPath);
        $nextPath = ltrim($relativePath . '/' . $item, '/');
        $items[] = [
            'name' => $item,
            'is_dir' => $isDirectory,
            'url' => $isDirectory
                ? app_url('resources') . '?' . http_build_query(['folder' => $selected, 'path' => $nextPath])
                : app_url('resources/file') . '?' . http_build_query(['folder' => $selected, 'path' => $relativePath, 'name' => $item]),
        ];
    }
}

render_page('Resources', function () use ($roots, $selected, $items, $relativePath): void {
    echo '<section class="panel"><h1>Resources</h1><div class="grid two">';
    echo '<div><h3>Folders</h3><ul>';
    foreach ($roots as $root) {
        $href = app_url('resources') . '?' . http_build_query(['folder' => $root]);
        echo '<li><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($root) . '</a></li>';
    }
    echo '</ul></div><div><h3>' . htmlspecialchars($selected . ($relativePath !== '' ? ' / ' . $relativePath : '')) . '</h3><ul>';
    if ($relativePath !== '') {
        $parts = explode('/', $relativePath);
        array_pop($parts);
        $upPath = implode('/', $parts);
        echo '<li><a href="' . htmlspecialchars(app_url('resources') . '?' . http_build_query(['folder' => $selected, 'path' => $upPath])) . '">.. (Up)</a></li>';
    }
    foreach ($items as $item) {
        echo '<li>';
        if ($item['is_dir']) {
            echo '<a href="' . htmlspecialchars($item['url']) . '"><span aria-hidden="true">📁 </span>' . htmlspecialchars($item['name']) . ' <span class="muted">(folder)</span></a>';
        } else {
            $newTabLabel = $item['name'] . ' (opens in new tab)';
            echo '<a target="_blank" rel="noopener" aria-label="' . htmlspecialchars($newTabLabel) . '" href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['name']) . ' <span class="muted">(opens in new tab)</span></a>';
        }
        echo '</li>';
    }
    echo '</ul></div></div></section>';
});
