<?php

declare(strict_types=1);

$user = require_permission('resources.manage');

$roots = config('resources_roots', ['Lighting', 'Sound', 'Backline Manuals']);

render_page('Resources Admin', function () use ($roots): void {
    echo '<div class="card"><div class="card-header"><h3 class="card-title">Resources Structure</h3></div><div class="list-group list-group-flush">';
    foreach ($roots as $root) {
        echo '<div class="list-group-item d-flex justify-content-between align-items-center"><strong>' . e($root) . '</strong><span class="text-secondary">Upload and folder controls scaffolded in DB-backed structure.</span></div>';
    }
    echo '</div></div>';
});
