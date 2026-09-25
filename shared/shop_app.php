<?php

declare(strict_types=1);

if (!function_exists('shop_revision_label')) {
    function shop_revision_label(int $number): string
    {
        $n = max(1, $number);
        $label = '';
        while ($n > 0) {
            $n--;
            $label = chr(65 + ($n % 26)) . $label;
            $n = intdiv($n, 26);
        }
        return 'Rev ' . $label;
    }
}

if (!function_exists('render_shop_app_page')) {
    function render_shop_app_page(array $user, string $shopType, string $pageTitle, string $heading, string $scaffoldCopy, bool $showFirstNav = false): void
    {
        $isAdmin = user_has_permission($user, 'admin.access');
        $showListStmt = $isAdmin
            ? db()->query('SELECT id, show_name FROM shows WHERE deleted_at IS NULL ORDER BY show_name')
            : (function () use ($user) {
                $stmt = db()->prepare('SELECT id, show_name FROM shows WHERE deleted_at IS NULL AND owner_user_id = ? ORDER BY show_name');
                $stmt->execute([(int) $user['id']]);
                return $stmt;
            })();
        $shows = $showListStmt->fetchAll();

        $selectedShowId = (int) ($_GET['show'] ?? ($_POST['show_id'] ?? ($shows[0]['id'] ?? 0)));
        $selectedShow = null;
        foreach ($shows as $s) {
            if ((int) $s['id'] === $selectedShowId) {
                $selectedShow = $s;
                break;
            }
        }

        $showAccessCondition = $isAdmin ? '' : ' AND s.owner_user_id = ' . (int) $user['id'];
        $allowedTabs = ['info', 'initial', 'revisions', 'paperwork'];
        $currentTab = (string) ($_GET['tab'] ?? 'info');
        if (!in_array($currentTab, $allowedTabs, true)) {
            $currentTab = 'info';
        }

        if (isset($_GET['export']) && $_GET['export'] === 'latest' && $selectedShowId > 0) {
            flash_set('info', 'Paperwork export is not wired yet.');
            redirect('/' . $shopType . '/app?show=' . $selectedShowId . '&tab=paperwork');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify_or_fail();
            $action = post('action');
            $postedTab = (string) post('current_tab', $currentTab);
            if (!in_array($postedTab, $allowedTabs, true)) {
                $postedTab = 'info';
            }

            if ($selectedShowId <= 0) {
                flash_set('warning', 'Select a show first.');
                redirect('/' . $shopType . '/app');
            }

            if ($action === 'create_initial') {
                db()->beginTransaction();
                try {
                    $orderStmt = db()->prepare('SELECT o.id FROM orders o JOIN shows s ON s.id = o.show_id WHERE o.show_id = ? AND o.shop_type = ? AND o.order_kind = "initial" AND s.deleted_at IS NULL' . $showAccessCondition . ' LIMIT 1 FOR UPDATE');
                    $orderStmt->execute([$selectedShowId, $shopType]);
                    $orderId = (int) ($orderStmt->fetchColumn() ?: 0);
                    if ($orderId === 0) {
                        try {
                            $createOrder = db()->prepare('INSERT INTO orders (show_id, shop_type, order_kind, created_by, created_at, updated_at) VALUES (?, ?, "initial", ?, NOW(), NOW())');
                            $createOrder->execute([$selectedShowId, $shopType, (int) $user['id']]);
                            $orderId = (int) db()->lastInsertId();
                        } catch (Throwable) {
                            $orderStmt->execute([$selectedShowId, $shopType]);
                            $orderId = (int) ($orderStmt->fetchColumn() ?: 0);
                        }
                    }

                    $revCountStmt = db()->prepare('SELECT COUNT(*) FROM order_revisions WHERE order_id = ? FOR UPDATE');
                    $revCountStmt->execute([$orderId]);
                    $revCount = (int) $revCountStmt->fetchColumn();
                    if ($revCount === 0) {
                        try {
                            $createRevision = db()->prepare('INSERT INTO order_revisions (order_id, revision_number, revision_label, revised_at, created_by, created_at, updated_at) VALUES (?, 1, ?, NOW(), ?, NOW(), NOW())');
                            $createRevision->execute([$orderId, shop_revision_label(1), (int) $user['id']]);
                        } catch (Throwable) {
                            // Another request created initial revision first.
                        }
                    }
                    db()->commit();
                    flash_set('success', 'Initial order ready.');
                } catch (Throwable) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    flash_set('danger', 'Could not create initial order. Please retry.');
                }
            }

            if ($action === 'create_revision') {
                $orderId = (int) post('order_id');
                $checkStmt = db()->prepare('SELECT o.id FROM orders o JOIN shows s ON s.id = o.show_id WHERE o.id = ? AND o.show_id = ? AND o.shop_type = ? AND s.deleted_at IS NULL' . $showAccessCondition . ' LIMIT 1');
                $checkStmt->execute([$orderId, $selectedShowId, $shopType]);
                if ($checkStmt->fetchColumn()) {
                    db()->beginTransaction();
                    try {
                        $lockOrderStmt = db()->prepare('SELECT id FROM orders WHERE id = ? FOR UPDATE');
                        $lockOrderStmt->execute([$orderId]);

                        $latestStmt = db()->prepare('SELECT id, revision_number FROM order_revisions WHERE order_id = ? ORDER BY revision_number DESC LIMIT 1 FOR UPDATE');
                        $latestStmt->execute([$orderId]);
                        $latest = $latestStmt->fetch();
                        $nextNumber = $latest ? ((int) $latest['revision_number'] + 1) : 1;
                        $label = shop_revision_label($nextNumber);
                        $insRev = db()->prepare('INSERT INTO order_revisions (order_id, revision_number, revision_label, revised_at, created_by, created_at, updated_at) VALUES (?, ?, ?, NOW(), ?, NOW(), NOW())');
                        $insRev->execute([$orderId, $nextNumber, $label, (int) $user['id']]);
                        $newRevisionId = (int) db()->lastInsertId();

                        if ($latest) {
                            $copyStmt = db()->prepare('INSERT INTO order_lines (revision_id, inventory_item_id, qty, spares, line_note, specific_pull_date, action_code, sort_order, created_at, updated_at)
                                SELECT ?, inventory_item_id, qty, spares, line_note, specific_pull_date, action_code, sort_order, NOW(), NOW()
                                FROM order_lines WHERE revision_id = ?');
                            $copyStmt->execute([$newRevisionId, (int) $latest['id']]);
                        }
                        db()->commit();
                        flash_set('success', 'Revision created.');
                    } catch (Throwable) {
                        if (db()->inTransaction()) {
                            db()->rollBack();
                        }
                        flash_set('danger', 'Could not create revision. Please retry.');
                    }
                }
            }

            if ($action === 'save_lines') {
                $orderId = (int) post('order_id');
                $revisionId = (int) post('revision_id');
                $checkStmt = db()->prepare('SELECT r.id FROM order_revisions r JOIN orders o ON o.id = r.order_id JOIN shows s ON s.id = o.show_id WHERE r.id = ? AND r.order_id = ? AND o.show_id = ? AND o.shop_type = ? AND s.deleted_at IS NULL' . $showAccessCondition . ' LIMIT 1');
                $checkStmt->execute([$revisionId, $orderId, $selectedShowId, $shopType]);
                if ($checkStmt->fetchColumn()) {
                    $lineIds = $_POST['line_id'] ?? [];
                    $qty = $_POST['qty'] ?? [];
                    $spares = $_POST['spares'] ?? [];
                    $notes = $_POST['line_note'] ?? [];
                    $pullDates = $_POST['specific_pull_date'] ?? [];
                    $actions = $_POST['action_code'] ?? [];
                    $upd = db()->prepare('UPDATE order_lines SET qty = ?, spares = ?, line_note = ?, specific_pull_date = ?, action_code = ?, updated_at = NOW() WHERE id = ? AND revision_id = ?');
                    foreach ($lineIds as $idx => $lineId) {
                        $actionCode = (string) ($actions[$idx] ?? 'blank');
                        if (!in_array($actionCode, ['blank', 'add', 'return', 'exchange', 'notes'], true)) {
                            $actionCode = 'blank';
                        }
                        $pullDate = trim((string) ($pullDates[$idx] ?? ''));
                        $upd->execute([
                            (int) ($qty[$idx] ?? 0),
                            (int) ($spares[$idx] ?? 0),
                            trim((string) ($notes[$idx] ?? '')),
                            $pullDate === '' ? null : $pullDate,
                            $actionCode,
                            (int) $lineId,
                            $revisionId,
                        ]);
                    }
                    flash_set('success', 'Revision lines saved.');
                }
            }

            redirect('/' . $shopType . '/app?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
        }

        $order = null;
        $revisions = [];
        $selectedRevisionId = (int) ($_GET['revision'] ?? 0);
        $linesByCategory = [];

        if ($selectedShowId > 0) {
            $orderStmt = db()->prepare('SELECT o.* FROM orders o JOIN shows s ON s.id = o.show_id WHERE o.show_id = ? AND o.shop_type = ? AND o.order_kind = "initial" AND s.deleted_at IS NULL' . $showAccessCondition . ' LIMIT 1');
            $orderStmt->execute([$selectedShowId, $shopType]);
            $order = $orderStmt->fetch() ?: null;

            if ($order) {
                $revStmt = db()->prepare('SELECT * FROM order_revisions WHERE order_id = ? ORDER BY revision_number DESC');
                $revStmt->execute([(int) $order['id']]);
                $revisions = $revStmt->fetchAll();
                if ($selectedRevisionId <= 0 && !empty($revisions)) {
                    $selectedRevisionId = (int) $revisions[0]['id'];
                }
                if ($currentTab === 'initial' && !empty($revisions)) {
                    $initialRevision = null;
                    foreach ($revisions as $candidateRevision) {
                        if ((int) ($candidateRevision['revision_number'] ?? 0) === 1) {
                            $initialRevision = $candidateRevision;
                            break;
                        }
                    }
                    if ($initialRevision !== null) {
                        $selectedRevisionId = (int) $initialRevision['id'];
                    }
                }

                if ($selectedRevisionId > 0) {
                    $ensureStmt = db()->prepare('INSERT INTO order_lines (revision_id, inventory_item_id, qty, spares, line_note, specific_pull_date, action_code, sort_order, created_at, updated_at)
                        SELECT ?, ii.id, 0, 0, "", NULL, "blank", ii.sort_order, NOW(), NOW()
                        FROM inventory_items ii
                        WHERE ii.shop_type = ? AND ii.is_spacer = 0
                        AND NOT EXISTS (
                            SELECT 1 FROM order_lines ol WHERE ol.revision_id = ? AND ol.inventory_item_id = ii.id
                        )');
                    $ensureStmt->execute([$selectedRevisionId, $shopType, $selectedRevisionId]);

                    $lineStmt = db()->prepare('SELECT ol.*, ii.name AS item_name, ii.sku, ii.unit, ic.name AS category_name
                        FROM order_lines ol
                        JOIN inventory_items ii ON ii.id = ol.inventory_item_id
                        LEFT JOIN inventory_categories ic ON ic.id = ii.category_id
                        WHERE ol.revision_id = ?
                        ORDER BY COALESCE(ic.sort_order, 9999), COALESCE(ic.name, "Uncategorized"), ol.sort_order, ii.name');
                    $lineStmt->execute([$selectedRevisionId]);
                    $lines = $lineStmt->fetchAll();
                    foreach ($lines as $line) {
                        $cat = trim((string) ($line['category_name'] ?? ''));
                        if ($cat === '') {
                            $cat = 'Uncategorized';
                        }
                        $linesByCategory[$cat][] = $line;
                    }
                }
            }
        }

        render_page($pageTitle, function () use ($shows, $selectedShowId, $selectedShow, $order, $revisions, $selectedRevisionId, $linesByCategory, $shopType, $heading, $scaffoldCopy, $currentTab, $showFirstNav): void {
            ?>
            <div class="card show-context mb-3">
                <div class="card-body d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <h3 class="card-title mb-0"><?= e($heading) ?></h3>
                    <a href="/dash/home" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i>Back to Dashboard</a>
                </div>
            </div>

            <?php if ($showFirstNav && !$selectedShow): ?>
                <div class="row row-cards">
                    <?php foreach ($shows as $show): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <h3 class="card-title mb-2"><?= e((string) $show['show_name']) ?></h3>
                                    <div class="mt-auto">
                                        <a class="btn btn-primary btn-sm" href="/<?= e($shopType) ?>/app?show=<?= (int) $show['id'] ?>&tab=info">Open Show</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$shows): ?>
                        <div class="col-12"><div class="card"><div class="card-body text-secondary">No shows available.</div></div></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <h3 class="card-title mb-0"><?= $selectedShow ? e((string) $selectedShow['show_name']) : 'Select Show' ?></h3>
                        <?php if ($showFirstNav): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="/<?= e($shopType) ?>/app">All Shows</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$showFirstNav): ?>
                            <form method="get" class="row g-2 align-items-end">
                                <div class="col-md-12">
                                    <label class="form-label">Show</label>
                                    <select class="form-select" name="show" onchange="this.form.submit()">
                                        <option value="">Select show</option>
                                        <?php foreach ($shows as $show): ?>
                                            <option value="<?= (int) $show['id'] ?>" <?= ((int) $show['id'] === (int) $selectedShowId) ? 'selected' : '' ?>><?= e((string) $show['show_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        <?php endif; ?>
                        <?php if ($selectedShow): ?>
                            <ul class="nav nav-pills mt-2">
                                <?php foreach (['info' => 'Show Information', 'initial' => 'Initial Order', 'revisions' => 'Revisions', 'paperwork' => 'Paperwork'] as $tabKey => $tabLabel): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?= $currentTab === $tabKey ? 'active' : '' ?>" href="/<?= e($shopType) ?>/app?show=<?= (int) $selectedShowId ?>&tab=<?= e($tabKey) ?>"><?= e($tabLabel) ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-3">
                                <a class="btn btn-outline-primary btn-sm" href="/<?= e($shopType) ?>/app?show=<?= (int) $selectedShowId ?>&tab=paperwork&export=latest">Export Latest Paperwork</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selectedShow && $currentTab === 'info'): ?>
                    <div class="card"><div class="card-body text-secondary">Show information is managed in <a href="/dash/shows">Shows</a>.</div></div>
                <?php elseif ($selectedShow && $currentTab === 'paperwork'): ?>
                    <div class="card"><div class="card-body text-secondary">Paperwork views are coming next. Use “Export Latest Paperwork” to start export flow when enabled.</div></div>
                <?php elseif ($selectedShow && $currentTab === 'revisions' && $order): ?>
                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title">Revisions</h3></div>
                        <div class="card-body d-flex flex-wrap gap-2">
                            <form method="post" class="d-inline-block me-2">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="create_revision">
                                <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="current_tab" value="<?= e($currentTab) ?>">
                                <button class="btn btn-outline-primary btn-sm">Add Revision</button>
                            </form>
                            <?php foreach ($revisions as $rev): ?>
                                <a class="btn btn-sm <?= ((int) $rev['id'] === (int) $selectedRevisionId) ? 'btn-primary' : 'btn-outline-primary' ?>" href="/<?= e($shopType) ?>/app?show=<?= (int) $selectedShowId ?>&tab=revisions&revision=<?= (int) $rev['id'] ?>">
                                    <?= e((string) $rev['revision_label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($selectedRevisionId > 0): ?>
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Revision Lines</h3></div>
                            <div class="card-body p-0">
                                <form method="post">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="save_lines">
                                    <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="revision_id" value="<?= (int) $selectedRevisionId ?>">
                                    <input type="hidden" name="current_tab" value="<?= e($currentTab) ?>">
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Category</th><th>Item</th><th>Qty</th><th>Spares</th><th>Action</th><th>Pull Date</th><th>Note</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($linesByCategory as $category => $lines): ?>
                                                <tr class="category-header-row"><td colspan="7"><strong><?= e($category) ?></strong></td></tr>
                                                <?php foreach ($lines as $line): ?>
                                                    <tr>
                                                        <td><?= e($category) ?></td>
                                                        <td><?= e((string) $line['item_name']) ?><?php if ($shopType === 'snd' && (string) $line['sku'] !== ''): ?> <span class="text-secondary small">(<?= e((string) $line['sku']) ?>)</span><?php endif; ?></td>
                                                        <td>
                                                            <input type="hidden" name="line_id[]" value="<?= (int) $line['id'] ?>">
                                                            <input class="form-control" type="number" min="0" name="qty[]" value="<?= (int) $line['qty'] ?>">
                                                        </td>
                                                        <td><input class="form-control" type="number" min="0" name="spares[]" value="<?= (int) $line['spares'] ?>"></td>
                                                        <td>
                                                            <select class="form-select" name="action_code[]">
                                                                <?php foreach (['blank' => '—', 'add' => 'Add', 'return' => 'Return', 'exchange' => 'Exchange', 'notes' => 'Notes'] as $value => $label): ?>
                                                                    <option value="<?= e($value) ?>" <?= ((string) $line['action_code'] === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td><input class="form-control" type="date" name="specific_pull_date[]" value="<?= e((string) ($line['specific_pull_date'] ?? '')) ?>"></td>
                                                        <td><input class="form-control" name="line_note[]" value="<?= e((string) $line['line_note']) ?>"></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-body border-top">
                                        <button class="btn btn-primary">Save Revision</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card"><div class="card-body text-secondary">No revisions yet.</div></div>
                    <?php endif; ?>
                <?php elseif ($selectedShow && $currentTab === 'initial'): ?>
                    <?php if (!$order): ?>
                        <div class="card"><div class="card-body">
                            <p class="text-secondary mb-3"><?= e($scaffoldCopy) ?></p>
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="create_initial">
                                <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                <input type="hidden" name="current_tab" value="<?= e($currentTab) ?>">
                                <button class="btn btn-primary">Create Initial Order</button>
                            </form>
                        </div></div>
                    <?php elseif ($selectedRevisionId > 0): ?>
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Initial Order</h3></div>
                            <div class="card-body p-0">
                                <form method="post">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="save_lines">
                                    <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="revision_id" value="<?= (int) $selectedRevisionId ?>">
                                    <input type="hidden" name="current_tab" value="<?= e($currentTab) ?>">
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th>Category</th><th>Item</th><th>Qty</th><th>Spares</th><th>Action</th><th>Pull Date</th><th>Note</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($linesByCategory as $category => $lines): ?>
                                                <tr class="category-header-row"><td colspan="7"><strong><?= e($category) ?></strong></td></tr>
                                                <?php foreach ($lines as $line): ?>
                                                    <tr>
                                                        <td><?= e($category) ?></td>
                                                        <td><?= e((string) $line['item_name']) ?><?php if ($shopType === 'snd' && (string) $line['sku'] !== ''): ?> <span class="text-secondary small">(<?= e((string) $line['sku']) ?>)</span><?php endif; ?></td>
                                                        <td>
                                                            <input type="hidden" name="line_id[]" value="<?= (int) $line['id'] ?>">
                                                            <input class="form-control" type="number" min="0" name="qty[]" value="<?= (int) $line['qty'] ?>">
                                                        </td>
                                                        <td><input class="form-control" type="number" min="0" name="spares[]" value="<?= (int) $line['spares'] ?>"></td>
                                                        <td>
                                                            <select class="form-select" name="action_code[]">
                                                                <?php foreach (['blank' => '—', 'add' => 'Add', 'return' => 'Return', 'exchange' => 'Exchange', 'notes' => 'Notes'] as $value => $label): ?>
                                                                    <option value="<?= e($value) ?>" <?= ((string) $line['action_code'] === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td><input class="form-control" type="date" name="specific_pull_date[]" value="<?= e((string) ($line['specific_pull_date'] ?? '')) ?>"></td>
                                                        <td><input class="form-control" name="line_note[]" value="<?= e((string) $line['line_note']) ?>"></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-body border-top">
                                        <button class="btn btn-primary">Save Initial Order</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card"><div class="card-body text-secondary">Initial order is not available yet.</div></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="card"><div class="card-body text-secondary"><?= e($scaffoldCopy) ?></div></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php
        }, $user);
    }
}
