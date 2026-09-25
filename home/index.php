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

if (auth_user()) {
    redirect('/admin/dash');
}

render_page('Backline', function (): void {
    ?>
    <div class="py-5 text-center">
        <span class="badge bg-blue-lt mb-3">Built for Theatre Production Teams</span>
        <h1 class="display-5 fw-bold">Shop Orders and Revisions, Finally Simplified.</h1>
        <p class="lead text-secondary mx-auto" style="max-width:760px;">Backline helps lighting and sound teams create, revise, and export professional shop paperwork faster, with cleaner inventory workflows and role-based access built for real production pipelines.</p>
        <div class="d-flex gap-2 justify-content-center mt-4">
            <a class="btn btn-primary btn-lg" href="/auth/login">Sign in</a>
            <a class="btn btn-outline-primary btn-lg" href="/setup">Run Setup</a>
        </div>
    </div>
    <div class="row row-cards mt-4">
        <div class="col-md-4"><div class="card"><div class="card-body"><h3 class="card-title">Dual Shop Apps</h3><p class="text-secondary">Separate LX and SND order systems with shared admin controls and permissions.</p></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h3 class="card-title">Revision Tracking</h3><p class="text-secondary">Track initial orders through every revision so teams always know current pull/return state.</p></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h3 class="card-title">Professional Exports</h3><p class="text-secondary">Generate clean paperwork aligned to your brand for handoff to shop teams.</p></div></div></div>
    </div>
    <?php
});
