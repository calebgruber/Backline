<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/ui.php';

require_any_access(['lx']);

render_page('Lighting Shop Orders', function (): void {
    ui_card_open('lightbulb', 'Lighting (LX)');
    echo '<p>Per-show order and revision workflow starts here. Inventory and category management remain admin-only.</p>';
    echo '<p class="text-muted">Import format: category,name,shop_quantity,unit,default_note,description</p>';
    ui_card_close();
});
