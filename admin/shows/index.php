<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Shows', function (): void {
    ui_card_open('theater_comedy', 'Shows');
    echo '<p class="text-muted">Next build step: admin global show visibility, show deletion, and required contact field validation.</p>';
    ui_card_close();
});
