<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/ui.php';

require_any_access(['lx']);

render_page('Lighting Shop Orders', function (): void {
    echo '<section class="panel"><h1>Lighting (LX)</h1>';
    echo '<p>Per-show order and revision workflow starts here. Inventory and category management remain admin-only.</p>';
    echo '<p class="muted">Import format: category,name,shop_quantity,unit,default_note,description</p>';
    echo '</section>';
});
