<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Components', function (): void {
    echo '<div class="card-grid">';

    ui_card_open('palette', 'Typography');
    echo '<h2>Heading 2</h2><h3>Heading 3</h3><p>Body paragraph text.</p><p class="text-muted">Muted helper text.</p>';
    ui_card_close();

    ui_card_open('smart_button', 'Buttons + Badges');
    echo '<div class="flex gap-2" style="flex-wrap:wrap">';
    echo '<button type="button" class="btn btn-primary">Primary</button>';
    echo '<button type="button" class="btn">Default</button>';
    echo '<button type="button" class="btn btn-ghost">Ghost</button>';
    echo '<span class="badge badge-neutral">Neutral</span>';
    echo '<span class="badge badge-success">Success</span>';
    echo '<span class="badge badge-warning">Warning</span>';
    echo '<span class="badge badge-danger">Danger</span>';
    echo '</div>';
    ui_card_close();

    ui_card_open('notification_important', 'Alerts');
    ui_alert('info', 'Info alert block');
    ui_alert('success', 'Success alert block');
    ui_alert('warning', 'Warning alert block');
    ui_alert('danger', 'Error alert block');
    ui_card_close();

    ui_card_open('edit_square', 'Form Inputs');
    echo '<form>';
    echo '<div class="form-row">';
    echo '<label>Text Input<input type="text" placeholder="Show name"></label>';
    echo '<label>Email Input<input type="email" placeholder="user@example.com"></label>';
    echo '<label>Password Input<input type="password" placeholder="••••••••"></label>';
    echo '<label>Select<select><option>Lighting</option><option>Sound</option></select></label>';
    echo '</div>';
    echo '<label>Textarea<textarea rows="3" placeholder="Notes"></textarea></label>';
    echo '<label><input type="checkbox"> Checkbox option</label>';
    echo '<button type="button" class="btn btn-primary">Submit Style</button>';
    echo '</form>';
    ui_card_close();

    ui_card_open('insights', 'Tabs + KPIs');
    echo '<div class="tabs"><span class="tab active">Overview</span><span class="tab">Inventory</span><span class="tab">Orders</span><span class="tab">Revisions</span></div>';
    echo '<hr class="divider"><div class="card-grid card-grid-3">';
    echo '<div><div class="text-muted">Shows</div><div style="font-size:1.375rem;font-weight:700;line-height:1.2;">12</div></div>';
    echo '<div><div class="text-muted">Orders</div><div style="font-size:1.375rem;font-weight:700;line-height:1.2;">38</div></div>';
    echo '<div><div class="text-muted">Revisions</div><div style="font-size:1.375rem;font-weight:700;line-height:1.2;">119</div></div>';
    echo '</div>';
    ui_card_close();

    ui_card_open('table_chart', 'Table + List');
    echo '<table><thead><tr><th>Name</th><th>Category</th><th>Qty</th><th>Status</th></tr></thead><tbody>';
    echo '<tr><td>QL5</td><td>CONSOLE</td><td>1</td><td><span class="badge badge-success">Ready</span></td></tr>';
    echo '<tr><td>HES Solaframe Theatre</td><td>FIXTURES</td><td>12</td><td><span class="badge badge-warning">Pending</span></td></tr>';
    echo '</tbody></table>';
    echo '<hr class="divider"><ul><li>Lighting</li><li>Sound</li><li>Backline Manuals</li></ul>';
    ui_card_close();

    echo '</div>';
});
