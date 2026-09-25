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

$base = dirname(__DIR__) . '/uploads/resources/' . $selected;
$items = [];
if (is_dir($base)) {
    foreach (scandir($base) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $fullPath = $base . '/' . $item;
        $items[] = [
            'name' => $item,
            'is_dir' => is_dir($fullPath),
            'url' => app_url('uploads/resources/' . rawurlencode($selected) . '/' . rawurlencode($item)),
        ];
    }
}

render_page('Resources', function () use ($roots, $selected, $items): void {
    echo '<section class="panel"><h1>Resources</h1><div class="grid two">';
    echo '<div><h3>Folders</h3><ul>';
    foreach ($roots as $root) {
        $href = app_url('resources?folder=' . rawurlencode($root));
        echo '<li><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($root) . '</a></li>';
    }
    echo '</ul></div><div><h3>' . htmlspecialchars($selected) . '</h3><ul>';
    foreach ($items as $item) {
        echo '<li>';
        if ($item['is_dir']) {
            echo '📁 ' . htmlspecialchars($item['name']);
        } else {
            echo '<a target="_blank" rel="noopener" href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['name']) . '</a>';
        }
        echo '</li>';
    }
    echo '</ul></div></div></section>';
});
