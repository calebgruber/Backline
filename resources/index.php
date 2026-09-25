<?php

declare(strict_types=1);

if (!function_exists('app_config')) {
    $bootstrapRoot = __DIR__;
    while (!file_exists($bootstrapRoot . '/shared/bootstrap.php')) {
        $parent = dirname($bootstrapRoot);
        if ($parent === $bootstrapRoot) {
            break;
        }
        $bootstrapRoot = $parent;
    }
    require_once $bootstrapRoot . '/shared/bootstrap.php';
}

$user = require_auth();

$roots = config('resources_roots', ['Lighting', 'Sound', 'Backline Manuals']);
$rows = db()->query('SELECT * FROM resource_nodes WHERE deleted_at IS NULL ORDER BY parent_id, sort_order, name')->fetchAll();

$byParent = [];
foreach ($rows as $row) {
    $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
}

$renderTree = function ($parentId = 0, $level = 0) use (&$renderTree, $byParent): void {
    foreach ($byParent[$parentId] ?? [] as $node) {
        echo '<div style="padding-left:' . (int) ($level * 20) . 'px" class="py-1">';
        if ($node['node_type'] === 'file') {
            echo '<i class="ti ti-file-text"></i> <a href="' . e((string) $node['path_or_url']) . '" target="_blank" rel="noopener">' . e($node['name']) . '</a>';
        } else {
            echo '<i class="ti ti-folder"></i> <strong>' . e($node['name']) . '</strong>';
        }
        echo '</div>';
        $renderTree((int) $node['id'], $level + 1);
    }
};

render_page('Resources', function () use ($roots, $renderTree): void {
    echo '<div class="card"><div class="card-header"><h3 class="card-title">Resources</h3></div><div class="card-body">';
    foreach ($roots as $root) {
        echo '<h4 class="mt-3">' . e($root) . '</h4>';
    }
    $renderTree(0, 0);
    echo '</div></div>';
}, $user);
