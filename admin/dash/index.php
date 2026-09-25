<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Dashboard', function (): void {
    ui_card_open('dashboard', 'Admin Dashboard');
    echo '<p>Inventory, users, categories, shows, and system settings are admin-managed.</p>';
    echo '<ul class="list-group">';
    echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('admin/components')) . '">UI components showcase</a></li>';
    echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('admin/settings')) . '">System settings + migrations</a></li>';
    echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('admin/users')) . '">Users</a></li>';
    echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('admin/shows')) . '">Shows</a></li>';
    echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('admin/inventory')) . '">Inventory</a></li>';
    echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('admin/resources')) . '">Resources manager</a></li>';
    echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('lx')) . '">Lighting app</a></li>';
    echo '<li class="list-group-item"><a href="' . htmlspecialchars(app_url('snd')) . '">Sound app</a></li>';
    echo '</ul>';
    ui_card_close();
});
