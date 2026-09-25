<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Inventory', function (): void {
    ui_card_open('inventory_2', 'Inventory');
    echo '<p class="text-muted">Next build step: LX/SND category/item CRUD, import parsing, sort ordering, and spacer controls (admin-only).</p>';
    ui_card_close();
});
