<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/ui.php';

require_any_access(['snd']);

render_page('Sound Shop Orders', function (): void {
    ui_card_open('graphic_eq', 'Sound (SND)');
    echo '<p>Per-show order/revision and cable label workflow starts here. Inventory and category management remain admin-only.</p>';
    echo '<p class="text-muted">Import format: category,category,name,sku,shop_quantity,unit,default_note,description (first category ignored)</p>';
    ui_card_close();
});
