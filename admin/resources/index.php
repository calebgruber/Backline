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

$user = require_permission('resources.manage');

$roots = config('resources_roots', ['Lighting', 'Sound', 'Backline Manuals']);

render_page('Resources Admin', function () use ($roots): void {
    echo '<div class="card"><div class="card-header"><h3 class="card-title">Resources Structure</h3></div><div class="list-group list-group-flush">';
    foreach ($roots as $root) {
        echo '<div class="list-group-item d-flex justify-content-between align-items-center"><strong>' . e($root) . '</strong><span class="text-secondary">Upload and folder controls scaffolded in DB-backed structure.</span></div>';
    }
    echo '</div></div>';
});
