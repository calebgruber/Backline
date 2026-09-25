<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';
require_once dirname(__DIR__, 2) . '/shared/ui.php';

require_role('admin');

render_page('Admin Components', function (): void {
    echo '<section class="panel"><h1>Component Showcase</h1><p class="muted">Reference page with one of each current Backline UI component pattern.</p></section>';

    echo '<section class="panel"><h2>Typography</h2>';
    echo '<h1>Heading 1</h1><h2>Heading 2</h2><h3>Heading 3</h3><p>Body paragraph text in Montserrat.</p><p class="muted">Muted helper text.</p></section>';

    echo '<section class="panel"><h2>Buttons + Badges</h2><div class="stack">';
    echo '<button type="button">Primary Button</button>';
    echo '<span class="btn secondary">Secondary Button Style</span>';
    echo '<span class="btn ghost">Ghost Button Style</span>';
    echo '<span class="badge">Default</span><span class="badge success">Success</span><span class="badge warning">Warning</span><span class="badge danger">Danger</span>';
    echo '</div></section>';

    echo '<section class="panel"><h2>Alerts</h2><div class="grid">';
    echo '<div class="alert">Info alert block</div>';
    echo '<div class="alert success">Success alert block</div>';
    echo '<div class="alert warning">Warning alert block</div>';
    echo '<div class="alert error">Error alert block</div>';
    echo '</div></section>';

    echo '<section class="panel"><h2>Form Inputs</h2><form class="grid">';
    echo '<div class="grid two">';
    echo '<label>Text Input<input type="text" placeholder="Show name"></label>';
    echo '<label>Email Input<input type="email" placeholder="user@example.com"></label>';
    echo '<label>Password Input<input type="password" placeholder="••••••••"></label>';
    echo '<label>Select<select><option>Lighting</option><option>Sound</option></select></label>';
    echo '</div>';
    echo '<label>Textarea<textarea rows="3" placeholder="Notes"></textarea></label>';
    echo '<label><input type="checkbox"> Checkbox option</label>';
    echo '<button type="button">Submit Style</button>';
    echo '</form></section>';

    echo '<section class="panel"><h2>Tabs + KPIs</h2>';
    echo '<div class="tabs"><a class="active" href="#">Overview</a><a href="#">Inventory</a><a href="#">Orders</a><a href="#">Revisions</a></div>';
    echo '<div class="divider"></div><div class="grid three">';
    echo '<div class="kpi"><div class="muted">Shows</div><div class="value">12</div></div>';
    echo '<div class="kpi"><div class="muted">Orders</div><div class="value">38</div></div>';
    echo '<div class="kpi"><div class="muted">Revisions</div><div class="value">119</div></div>';
    echo '</div></section>';

    echo '<section class="panel"><h2>Table + List</h2>';
    echo '<table><thead><tr><th>Name</th><th>Category</th><th>Qty</th><th>Status</th></tr></thead><tbody>';
    echo '<tr><td>QL5</td><td>CONSOLE</td><td>1</td><td><span class="badge success">Ready</span></td></tr>';
    echo '<tr><td>HES Solaframe Theatre</td><td>FIXTURES</td><td>12</td><td><span class="badge warning">Pending</span></td></tr>';
    echo '</tbody></table>';
    echo '<div class="divider"></div><ul class="list-reset"><li>• Lighting</li><li>• Sound</li><li>• Backline Manuals</li></ul></section>';
});
