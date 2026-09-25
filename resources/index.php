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

$rawRelativePath = trim((string) ($_GET['path'] ?? ''), '/');
$relativePath = $rawRelativePath;
$segments = $relativePath === '' ? [] : explode('/', $relativePath);
$segments = array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..' && !str_contains($segment, '\\') && !str_contains($segment, '/')));
$relativePath = implode('/', $segments);
$invalidInputPath = $rawRelativePath !== $relativePath;

$root = realpath(dirname(__DIR__) . '/uploads/resources/' . $selected);
$base = $root !== false ? ($root . ($relativePath !== '' ? '/' . $relativePath : '')) : '';
$resolvedBase = $base !== '' ? realpath($base) : false;
$invalidPath = $invalidInputPath;
$missingPath = false;
if ($root === false) {
    $missingPath = true;
    http_response_code(404);
    $resolvedBase = false;
} elseif ($relativePath !== '' && $resolvedBase === false) {
    $missingPath = true;
    http_response_code(404);
    $resolvedBase = false;
} elseif ($resolvedBase !== false && !str_starts_with($resolvedBase, $root . DIRECTORY_SEPARATOR) && $resolvedBase !== $root) {
    $invalidPath = true;
    http_response_code(400);
    $resolvedBase = false;
} elseif ($invalidPath) {
    http_response_code(400);
    $resolvedBase = false;
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
    usort($items, static function (array $a, array $b): int {
        if (($a['is_dir'] ?? false) !== ($b['is_dir'] ?? false)) {
            return ($a['is_dir'] ?? false) ? -1 : 1;
        }
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
}

render_page('Resources', function () use ($roots, $selected, $items, $relativePath, $invalidPath, $missingPath): void {
    ui_card_open('folder_open', 'Resources');
    if ($invalidPath) {
        ui_alert('danger', 'Invalid resource path.');
    } elseif ($missingPath) {
        ui_alert('warning', 'Requested resource path was not found.');
    }
    echo '<div class="row g-3">';
    echo '<div class="col-md-4"><h3>Folders</h3><ul class="list-group">';
    foreach ($roots as $root) {
        $href = app_url('resources') . '?' . http_build_query(['folder' => $root]);
        echo '<li class="list-group-item"><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($root) . '</a></li>';
    }
    echo '</ul></div><div class="col-md-8"><h3>' . htmlspecialchars($selected . ($relativePath !== '' ? ' / ' . $relativePath : '')) . '</h3><ul class="list-group">';
    if ($relativePath !== '') {
        $parts = explode('/', $relativePath);
        array_pop($parts);
        $upPath = implode('/', $parts);
        echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('resources') . '?' . http_build_query(['folder' => $selected, 'path' => $upPath])) . '">.. (Up)</a></li>';
    }
    foreach ($items as $item) {
        echo '<li class="list-group-item">';
        if ($item['is_dir']) {
            echo '<a href="' . htmlspecialchars($item['url']) . '"><span aria-hidden="true">📁 </span>' . htmlspecialchars($item['name']) . ' <span class="text-muted">(folder)</span></a>';
        } else {
            $newTabLabel = $item['name'] . ' (opens in new tab)';
            echo '<a target="_blank" rel="noopener" aria-label="' . htmlspecialchars($newTabLabel) . '" href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['name']) . ' <span class="text-muted">(opens in new tab)</span></a>';
        }
        echo '</li>';
    }
    echo '</ul></div></div>';
    ui_card_close();
});
