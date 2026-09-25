<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Resources', function (): void {
    echo '<section class="panel"><h1>Resources Manager</h1><p class="muted">Next build step: admin folder/file CRUD for Lighting, Sound, and Backline Manuals with subfolder controls.</p></section>';
});
