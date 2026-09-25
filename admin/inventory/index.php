<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Inventory', function (): void {
    echo '<section class="panel"><h1>Inventory</h1><p class="muted">Next build step: LX/SND category/item CRUD, import parsing, sort ordering, and spacer controls (admin-only).</p></section>';
});
