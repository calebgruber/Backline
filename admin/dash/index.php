<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Dashboard', function (): void {
    echo '<section class="panel"><h1>Admin Dashboard</h1>';
    echo '<p>Inventory, users, categories, shows, and system settings are admin-managed.</p>';
    echo '<ul><li><a href="' . htmlspecialchars(app_url('admin/settings')) . '">System settings + migrations</a></li>';
    echo '<li><a href="' . htmlspecialchars(app_url('lx')) . '">Lighting app</a></li>';
    echo '<li><a href="' . htmlspecialchars(app_url('snd')) . '">Sound app</a></li></ul></section>';
});
