<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Users', function (): void {
    echo '<section class="panel"><h1>Users</h1><p class="muted">Next build step: manual user creation, role/concentration assignment, invite/reset flow, and delete controls.</p></section>';
});
