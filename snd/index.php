<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/ui.php';

require_any_access(['snd']);

render_page('Sound Shop Orders', function (): void {
    echo '<section class="panel"><h1>Sound (SND)</h1>';
    echo '<p>Per-show order/revision and cable label workflow starts here. Inventory and category management remain admin-only.</p>';
    echo '<p class="muted">Import format: category,category,name,sku,shop_quantity,unit,default_note,description (first category ignored)</p>';
    echo '</section>';
});
