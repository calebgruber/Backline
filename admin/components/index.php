<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Components', function (): void {
    echo '<div class="row g-3">';

    ui_card_open('palette', 'Typography');
    echo '<h2>Heading 2</h2><h3>Heading 3</h3><p>Body paragraph text.</p><p class="text-muted">Muted helper text.</p>';
    ui_card_close();

    ui_card_open('smart_button', 'Buttons + Badges');
    echo '<div class="d-flex gap-2 flex-wrap">';
    echo '<button type="button" class="btn btn-primary">Primary</button>';
    echo '<button type="button" class="btn btn-secondary">Secondary</button>';
    echo '<button type="button" class="btn btn-outline-secondary">Outline</button>';
    echo '<span class="badge bg-secondary">Neutral</span>';
    echo '<span class="badge bg-success">Success</span>';
    echo '<span class="badge bg-warning text-dark">Warning</span>';
    echo '<span class="badge bg-danger">Danger</span>';
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
    echo '<div class="row g-3">';
    echo '<div class="col-md-6"><label class="form-label">Text Input</label><input class="form-control" type="text" placeholder="Show name"></div>';
    echo '<div class="col-md-6"><label class="form-label">Email Input</label><input class="form-control" type="email" placeholder="user@example.com"></div>';
    echo '<div class="col-md-6"><label class="form-label">Password Input</label><input class="form-control" type="password" placeholder="••••••••"></div>';
    echo '<div class="col-md-6"><label class="form-label">Select</label><select class="form-select"><option>Lighting</option><option>Sound</option></select></div>';
    echo '</div>';
    echo '<div class="mt-3"><label class="form-label">Textarea</label><textarea class="form-control" rows="3" placeholder="Notes"></textarea></div>';
    echo '<label class="form-check mt-3"><input class="form-check-input" type="checkbox"><span class="form-check-label">Checkbox option</span></label>';
    echo '<button type="button" class="btn btn-primary">Submit Style</button>';
    echo '</form>';
    ui_card_close();

    ui_card_open('insights', 'Tabs + KPIs');
    echo '<ul class="nav nav-pills mb-3"><li class="nav-item"><span class="nav-link active">Overview</span></li><li class="nav-item"><span class="nav-link">Inventory</span></li><li class="nav-item"><span class="nav-link">Orders</span></li><li class="nav-item"><span class="nav-link">Revisions</span></li></ul>';
    echo '<div class="row g-3">';
    echo '<div class="col-md-4"><div class="text-muted">Shows</div><div class="h2 mb-0">12</div></div>';
    echo '<div class="col-md-4"><div class="text-muted">Orders</div><div class="h2 mb-0">38</div></div>';
    echo '<div class="col-md-4"><div class="text-muted">Revisions</div><div class="h2 mb-0">119</div></div>';
    echo '</div>';
    ui_card_close();

    ui_card_open('table_chart', 'Table + List');
    echo '<div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Name</th><th>Category</th><th>Qty</th><th>Status</th></tr></thead><tbody>';
    echo '<tr><td>QL5</td><td>CONSOLE</td><td>1</td><td><span class="badge bg-success">Ready</span></td></tr>';
    echo '<tr><td>HES Solaframe Theatre</td><td>FIXTURES</td><td>12</td><td><span class="badge bg-warning text-dark">Pending</span></td></tr>';
    echo '</tbody></table></div>';
    echo '<ul class="list-group list-group-flush mt-3"><li class="list-group-item">Lighting</li><li class="list-group-item">Sound</li><li class="list-group-item">Backline Manuals</li></ul>';
    ui_card_close();

    echo '</div>';
});
