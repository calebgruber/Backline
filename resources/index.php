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
$rootNodesByName = [];
foreach ($rows as $row) {
    $parentKey = $row['parent_id'] === null ? 'root' : (string) (int) $row['parent_id'];
    $byParent[$parentKey][] = $row;
    if ($row['parent_id'] === null) {
        $rootNodesByName[(string) $row['root_name']][] = $row;
    }
}

$renderTree = function (string $parentKey, int $level = 0) use (&$renderTree, $byParent): void {
    foreach ($byParent[$parentKey] ?? [] as $node) {
        echo '<div style="padding-left:' . (int) ($level * 20) . 'px" class="py-1">';
        if ($node['node_type'] === 'file') {
            echo '<i class="ti ti-file-text"></i> <a href="' . e((string) $node['path_or_url']) . '" target="_blank" rel="noopener">' . e($node['name']) . '</a>';
        } else {
            echo '<i class="ti ti-folder"></i> <strong>' . e($node['name']) . '</strong>';
        }
        echo '</div>';
        $renderTree((string) (int) $node['id'], $level + 1);
    }
};

render_page('Resources', function () use ($roots, $renderTree, $rootNodesByName): void {
    echo '<div class="card"><div class="card-header"><h3 class="card-title">Resources</h3></div><div class="card-body">';
    foreach ($roots as $root) {
        echo '<h4 class="mt-3">' . e($root) . '</h4>';
        $rendered = false;
        foreach ($rootNodesByName[(string) $root] ?? [] as $rootNode) {
            $rendered = true;
            $renderTree((string) (int) $rootNode['id'], 0);
        }
        if (!$rendered) {
            echo '<div class="text-secondary small">No resources yet.</div>';
        }
    }
    echo '</div></div>';
}, $user);
