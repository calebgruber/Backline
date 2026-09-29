<?php

declare(strict_types=1);

if (!function_exists('shop_revision_label')) {
    function shop_revision_label(int $number): string
    {
        return '1.' . max(1, $number);
    }
}

if (!function_exists('shop_has_order_line_return_date_column')) {
    function shop_has_order_line_return_date_column(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        try {
            $stmt = db()->query("SHOW COLUMNS FROM order_lines LIKE 'specific_return_date'");
            $hasColumn = (bool) $stmt->fetch();
        } catch (Throwable) {
            $hasColumn = false;
        }

        return $hasColumn;
    }
}

if (!function_exists('shop_has_inventory_subcategory_column')) {
    function shop_has_inventory_subcategory_column(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }
        try {
            $stmt = db()->query("SHOW COLUMNS FROM inventory_items LIKE 'subcategory_name'");
            $hasColumn = (bool) $stmt->fetch();
        } catch (Throwable) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('render_shop_app_page')) {
    function render_shop_app_page(array $user, string $shopType, string $pageTitle, string $heading, string $scaffoldCopy, bool $showFirstNav = false): void
    {
        $appPath = $shopType === 'lx' ? '/dash/lx' : '/dash/sound';
        $isAdmin = user_has_permission($user, 'admin.access');
        $showListStmt = $isAdmin
            ? (function () use ($shopType) {
                $stmt = db()->prepare('SELECT id, show_name, show_scope, theatre_name, shop_name, lead_designer_name, lead_designer_email, lead_designer_phone, ald_name, ald_email, ald_phone, assistant_snd_designer_name, assistant_snd_designer_email, assistant_snd_designer_phone, shop_manager_name, shop_manager_email, shop_manager_phone, assistants_json, pull_date, return_date, strike_date, opening_date, closing_date, theatre_address, shop_address FROM shows WHERE deleted_at IS NULL AND COALESCE(show_scope, "both") IN ("both", ?) ORDER BY show_name');
                $stmt->execute([$shopType]);
                return $stmt;
            })()
            : (function () use ($user, $shopType) {
                $stmt = db()->prepare('SELECT id, show_name, show_scope, theatre_name, shop_name, lead_designer_name, lead_designer_email, lead_designer_phone, ald_name, ald_email, ald_phone, assistant_snd_designer_name, assistant_snd_designer_email, assistant_snd_designer_phone, shop_manager_name, shop_manager_email, shop_manager_phone, assistants_json, pull_date, return_date, strike_date, opening_date, closing_date, theatre_address, shop_address FROM shows WHERE deleted_at IS NULL AND owner_user_id = ? AND COALESCE(show_scope, "both") IN ("both", ?) ORDER BY show_name');
                $stmt->execute([(int) $user['id'], $shopType]);
                return $stmt;
            })();
        $shows = $showListStmt->fetchAll();

        $selectedShowId = (int) ($_GET['show'] ?? ($_POST['show_id'] ?? 0));
        if ($selectedShowId <= 0 && !$showFirstNav) {
            $selectedShowId = (int) ($shows[0]['id'] ?? 0);
        }
        $selectedShow = null;
        foreach ($shows as $s) {
            if ((int) $s['id'] === $selectedShowId) {
                $selectedShow = $s;
                break;
            }
        }
        if ($selectedShow === null) {
            $selectedShowId = 0;
        }

        $showAccessCondition = $isAdmin ? '' : ' AND s.owner_user_id = ' . (int) $user['id'] . ' AND COALESCE(s.show_scope, "both") IN ("both", ' . db()->quote($shopType) . ')';
        $allowedTabs = ['info', 'initial', 'revisions', 'paperwork'];
        $currentTab = (string) ($_GET['tab'] ?? 'info');
        $hasReturnDateColumn = shop_has_order_line_return_date_column();
        $hasSubcategoryColumn = shop_has_inventory_subcategory_column();
        if (!in_array($currentTab, $allowedTabs, true)) {
            $currentTab = 'info';
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
                redirect($appPath);
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
                            if ($hasReturnDateColumn) {
                                $copyStmt = db()->prepare('INSERT INTO order_lines (revision_id, inventory_item_id, qty, spares, line_note, specific_pull_date, specific_return_date, action_code, sort_order, created_at, updated_at)
                                    SELECT ?, inventory_item_id, qty, spares, line_note, NULL, NULL, action_code, sort_order, NOW(), NOW()
                                    FROM order_lines WHERE revision_id = ?');
                            } else {
                                $copyStmt = db()->prepare('INSERT INTO order_lines (revision_id, inventory_item_id, qty, spares, line_note, specific_pull_date, action_code, sort_order, created_at, updated_at)
                                    SELECT ?, inventory_item_id, qty, spares, line_note, NULL, action_code, sort_order, NOW(), NOW()
                                    FROM order_lines WHERE revision_id = ?');
                            }
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
                    try {
                        $lineIds = $_POST['line_id'] ?? [];
                        $qty = $_POST['qty'] ?? [];
                        $spares = $_POST['spares'] ?? [];
                        $notes = $_POST['line_note'] ?? [];
                        $pullDates = $_POST['specific_pull_date'] ?? [];
                        $returnDates = $_POST['specific_return_date'] ?? [];
                        $actions = $_POST['action_code'] ?? [];
                        if ($hasReturnDateColumn) {
                            $upd = db()->prepare('UPDATE order_lines SET qty = ?, spares = ?, line_note = ?, specific_pull_date = ?, specific_return_date = ?, action_code = ?, updated_at = NOW() WHERE id = ? AND revision_id = ?');
                        } else {
                            $upd = db()->prepare('UPDATE order_lines SET qty = ?, spares = ?, line_note = ?, specific_pull_date = ?, action_code = ?, updated_at = NOW() WHERE id = ? AND revision_id = ?');
                        }
                        foreach ($lineIds as $idx => $lineId) {
                            $actionCode = (string) ($actions[$idx] ?? 'blank');
                            if (!in_array($actionCode, ['blank', 'add', 'return', 'exchange', 'notes'], true)) {
                                $actionCode = 'blank';
                            }
                            $pullDate = trim((string) ($pullDates[$idx] ?? ''));
                            $returnDate = trim((string) ($returnDates[$idx] ?? ''));
                            $args = [
                                (int) ($qty[$idx] ?? 0),
                                (int) ($spares[$idx] ?? 0),
                                trim((string) ($notes[$idx] ?? '')),
                                $pullDate === '' ? null : $pullDate,
                            ];
                            if ($hasReturnDateColumn) {
                                $args[] = $returnDate === '' ? null : $returnDate;
                            }
                            $args[] = $actionCode;
                            $args[] = (int) $lineId;
                            $args[] = $revisionId;
                            $upd->execute($args);
                        }
                        flash_set('success', 'Revision lines saved.');
                    } catch (Throwable) {
                        flash_set('danger', 'Save failed. Please retry.');
                    }
                }
            }

            if ($action === 'save_line_live') {
                $orderId = (int) post('order_id');
                $revisionId = (int) post('revision_id');
                $lineId = (int) post('line_id');
                $checkStmt = db()->prepare('SELECT r.id FROM order_revisions r JOIN orders o ON o.id = r.order_id JOIN shows s ON s.id = o.show_id WHERE r.id = ? AND r.order_id = ? AND o.show_id = ? AND o.shop_type = ? AND s.deleted_at IS NULL' . $showAccessCondition . ' LIMIT 1');
                $checkStmt->execute([$revisionId, $orderId, $selectedShowId, $shopType]);
                if ($checkStmt->fetchColumn() && $lineId > 0) {
                    $actionCode = (string) post('action_code', 'blank');
                    if (!in_array($actionCode, ['blank', 'add', 'return', 'exchange', 'notes'], true)) {
                        $actionCode = 'blank';
                    }
                    $pullDate = trim((string) post('specific_pull_date', ''));
                    $returnDate = trim((string) post('specific_return_date', ''));
                    try {
                        if ($hasReturnDateColumn) {
                            $upd = db()->prepare('UPDATE order_lines SET qty = ?, spares = ?, line_note = ?, specific_pull_date = ?, specific_return_date = ?, action_code = ?, updated_at = NOW() WHERE id = ? AND revision_id = ?');
                            $upd->execute([
                                (int) post('qty', '0'),
                                (int) post('spares', '0'),
                                trim((string) post('line_note', '')),
                                $pullDate === '' ? null : $pullDate,
                                $returnDate === '' ? null : $returnDate,
                                $actionCode,
                                $lineId,
                                $revisionId,
                            ]);
                        } else {
                            $upd = db()->prepare('UPDATE order_lines SET qty = ?, spares = ?, line_note = ?, specific_pull_date = ?, action_code = ?, updated_at = NOW() WHERE id = ? AND revision_id = ?');
                            $upd->execute([
                                (int) post('qty', '0'),
                                (int) post('spares', '0'),
                                trim((string) post('line_note', '')),
                                $pullDate === '' ? null : $pullDate,
                                $actionCode,
                                $lineId,
                                $revisionId,
                            ]);
                        }
                    } catch (Throwable) {
                        http_response_code(500);
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => false]);
                        exit;
                    }
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true]);
                    exit;
                }
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false]);
                exit;
            }

            if ($action === 'export_latest' && $selectedShowId > 0) {
                $orderStmt = db()->prepare('SELECT o.id FROM orders o JOIN shows s ON s.id = o.show_id WHERE o.show_id = ? AND o.shop_type = ? AND o.order_kind = "initial" AND s.deleted_at IS NULL' . $showAccessCondition . ' LIMIT 1');
                $orderStmt->execute([$selectedShowId, $shopType]);
                $orderId = (int) ($orderStmt->fetchColumn() ?: 0);
                if ($orderId <= 0) {
                    flash_set('warning', 'No order exists to export yet.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
                }

                $revisionStmt = db()->prepare('SELECT id, revision_number, revision_label, revised_at FROM order_revisions WHERE order_id = ? ORDER BY revision_number DESC LIMIT 1');
                $revisionStmt->execute([$orderId]);
                $latestRevision = $revisionStmt->fetch();
                if (!$latestRevision) {
                    flash_set('warning', 'No revisions exist to export yet.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
                }
                $latestRevisionId = (int) ($latestRevision['id'] ?? 0);
                redirect($appPath . '?show=' . $selectedShowId . '&tab=paperwork&preview=1&revision=' . $latestRevisionId);
            }
            if ($action === 'print_labels' && $selectedShowId > 0 && $shopType === 'snd') {
                flash_set('info', 'Label printing is not wired yet.');
            }

            redirect($appPath . '?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
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
                foreach ($revisions as &$revisionRow) {
                    $revisionRow['revision_label'] = shop_revision_label((int) ($revisionRow['revision_number'] ?? 1));
                }
                unset($revisionRow);
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
                    if ($hasReturnDateColumn) {
                        $ensureStmt = db()->prepare('INSERT INTO order_lines (revision_id, inventory_item_id, qty, spares, line_note, specific_pull_date, specific_return_date, action_code, sort_order, created_at, updated_at)
                            SELECT ?, ii.id, 0, 0, "", NULL, NULL, "blank", ii.sort_order, NOW(), NOW()
                            FROM inventory_items ii
                            WHERE ii.shop_type = ? AND ii.is_spacer = 0
                            AND NOT EXISTS (
                                SELECT 1 FROM order_lines ol WHERE ol.revision_id = ? AND ol.inventory_item_id = ii.id
                            )');
                    } else {
                        $ensureStmt = db()->prepare('INSERT INTO order_lines (revision_id, inventory_item_id, qty, spares, line_note, specific_pull_date, action_code, sort_order, created_at, updated_at)
                            SELECT ?, ii.id, 0, 0, "", NULL, "blank", ii.sort_order, NOW(), NOW()
                            FROM inventory_items ii
                            WHERE ii.shop_type = ? AND ii.is_spacer = 0
                            AND NOT EXISTS (
                                SELECT 1 FROM order_lines ol WHERE ol.revision_id = ? AND ol.inventory_item_id = ii.id
                            )');
                    }
                    $ensureStmt->execute([$selectedRevisionId, $shopType, $selectedRevisionId]);

                    if ($hasReturnDateColumn) {
                        $lineStmt = db()->prepare('SELECT ol.*, ' . ($hasSubcategoryColumn ? 'ii.subcategory_name,' : 'NULL AS subcategory_name,') . ' ii.name AS item_name, ii.sku, ii.unit, ic.name AS category_name
                            FROM order_lines ol
                            JOIN inventory_items ii ON ii.id = ol.inventory_item_id
                            LEFT JOIN inventory_categories ic ON ic.id = ii.category_id
                            WHERE ol.revision_id = ? AND ii.is_spacer = 0
                            ORDER BY COALESCE(ic.sort_order, 9999), COALESCE(ic.name, "Uncategorized"), ' . ($hasSubcategoryColumn ? 'COALESCE(ii.subcategory_name, "")' : '""') . ', ii.sort_order, ii.name');
                    } else {
                        $lineStmt = db()->prepare('SELECT ol.*, NULL AS specific_return_date, ' . ($hasSubcategoryColumn ? 'ii.subcategory_name,' : 'NULL AS subcategory_name,') . ' ii.name AS item_name, ii.sku, ii.unit, ic.name AS category_name
                            FROM order_lines ol
                            JOIN inventory_items ii ON ii.id = ol.inventory_item_id
                            LEFT JOIN inventory_categories ic ON ic.id = ii.category_id
                            WHERE ol.revision_id = ? AND ii.is_spacer = 0
                            ORDER BY COALESCE(ic.sort_order, 9999), COALESCE(ic.name, "Uncategorized"), ' . ($hasSubcategoryColumn ? 'COALESCE(ii.subcategory_name, "")' : '""') . ', ii.sort_order, ii.name');
                    }
                    $lineStmt->execute([$selectedRevisionId]);
                    $lines = $lineStmt->fetchAll();
                    foreach ($lines as $line) {
                        $cat = trim((string) ($line['category_name'] ?? ''));
                        if ($cat === '') {
                            $cat = 'Uncategorized';
                        }
                        $sub = trim((string) ($line['subcategory_name'] ?? ''));
                        if ($sub === '') {
                            $sub = 'General';
                        }
                        $linesByCategory[$cat][$sub][] = $line;
                    }
                }
            }
        }

        render_page($pageTitle, function () use ($shows, $selectedShowId, $selectedShow, $order, $revisions, $selectedRevisionId, $linesByCategory, $shopType, $heading, $scaffoldCopy, $currentTab, $showFirstNav, $appPath, $hasSubcategoryColumn): void {
            ?>
            <?php if ($showFirstNav && !$selectedShow): ?>
                <div class="card mb-3 paperwork-preview-actions">
                    <div class="card-body">
                        <h3 class="card-title mb-2"><?= e($heading) ?></h3>
                        <p class="text-secondary mb-0">Welcome. Select a show card to open its workspace.</p>
                    </div>
                </div>
                <div class="row row-cards">
                    <?php foreach ($shows as $show): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <h3 class="card-title mb-2"><?= e((string) $show['show_name']) ?></h3>
                                    <div class="mt-auto">
                                        <a class="btn btn-primary btn-sm" href="<?= e($appPath) ?>?show=<?= (int) $show['id'] ?>&tab=info">Open Show</a>
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
                <?php
                $currentRevisionLabel = '—';
                if ($order && $selectedRevisionId > 0) {
                    foreach ($revisions as $revisionCandidate) {
                        if ((int) ($revisionCandidate['id'] ?? 0) === (int) $selectedRevisionId) {
                            $currentRevisionLabel = (string) ($revisionCandidate['revision_label'] ?? '—');
                            break;
                        }
                    }
                }
                ?>
                <?php if ($selectedShow): ?>
                    <div class="shop-workspace-banner mb-3">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                            <div class="text-center flex-fill">
                                <div class="h1 mb-1"><?= e((string) $selectedShow['show_name']) ?></div>
                                <div class="h3 mb-0 text-secondary">Revision <?= e($currentRevisionLabel) ?></div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-md-end gap-2">
                                <a class="btn btn-outline-danger btn-sm" href="/dash/home">Exit Show</a>
                                <a class="btn btn-outline-primary btn-sm" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=paperwork&preview=1">Export Latest Paperwork</a>
                                <?php if ($shopType === 'snd'): ?>
                                    <form method="post" class="d-inline-block">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="print_labels">
                                        <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                        <input type="hidden" name="current_tab" value="<?= e($currentTab) ?>">
                                        <button class="btn btn-outline-primary btn-sm" type="submit">Print Labels</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <ul class="nav nav-tabs mb-3">
                        <?php foreach (['info' => 'Show Information', 'initial' => 'Initial Order', 'revisions' => 'Revisions', 'paperwork' => 'Paperwork'] as $tabKey => $tabLabel): ?>
                            <li class="nav-item"><a class="nav-link <?= $currentTab === $tabKey ? 'active' : '' ?>" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=<?= e($tabKey) ?>"><?= e($tabLabel) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif (!$showFirstNav): ?>
                    <form method="get" class="row g-2 align-items-end mb-3">
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

                <?php if ($selectedShow && $currentTab === 'info'): ?>
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <h3 class="card-title mb-0">Show Information</h3>
                            <a class="btn btn-outline-primary btn-sm" href="/dash/shows?edit=<?= (int) $selectedShowId ?>">Edit Show</a>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><div class="small text-secondary">Show</div><div><?= e((string) ($selectedShow['show_name'] ?? '')) ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Theatre</div><div><?= e((string) ($selectedShow['theatre_name'] ?? '')) ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Shop</div><div><?= e((string) ($selectedShow['shop_name'] ?? '')) ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Lead Designer</div><div><?= e((string) ($selectedShow['lead_designer_name'] ?? '')) ?></div></div>
                                <?php
                                $leadContact = implode(' · ', array_values(array_filter([
                                    trim((string) ($selectedShow['lead_designer_email'] ?? '')),
                                    trim((string) ($selectedShow['lead_designer_phone'] ?? '')),
                                ], static fn ($v) => $v !== '')));
                                $assistantName = $shopType === 'lx'
                                    ? trim((string) (($selectedShow['ald_name'] ?? '') ?: ($selectedShow['assistant_snd_designer_name'] ?? '')))
                                    : trim((string) (($selectedShow['assistant_snd_designer_name'] ?? '') ?: ($selectedShow['ald_name'] ?? '')));
                                $assistantEmail = $shopType === 'lx'
                                    ? trim((string) (($selectedShow['ald_email'] ?? '') ?: ($selectedShow['assistant_snd_designer_email'] ?? '')))
                                    : trim((string) (($selectedShow['assistant_snd_designer_email'] ?? '') ?: ($selectedShow['ald_email'] ?? '')));
                                $assistantPhone = $shopType === 'lx'
                                    ? trim((string) (($selectedShow['ald_phone'] ?? '') ?: ($selectedShow['assistant_snd_designer_phone'] ?? '')))
                                    : trim((string) (($selectedShow['assistant_snd_designer_phone'] ?? '') ?: ($selectedShow['ald_phone'] ?? '')));
                                $assistantContact = implode(' · ', array_values(array_filter([
                                    $assistantEmail,
                                    $assistantPhone,
                                ], static fn ($v) => $v !== '')));
                                $shopManagerContact = implode(' · ', array_values(array_filter([
                                    trim((string) ($selectedShow['shop_manager_email'] ?? '')),
                                    trim((string) ($selectedShow['shop_manager_phone'] ?? '')),
                                ], static fn ($v) => $v !== '')));
                                $assistantShopManagerName = '';
                                $assistantShopManagerContact = '';
                                $assistantsDecoded = json_decode((string) ($selectedShow['assistants_json'] ?? ''), true);
                                if (is_array($assistantsDecoded) && isset($assistantsDecoded['assistant_shop_manager']) && is_array($assistantsDecoded['assistant_shop_manager'])) {
                                    $assistantShopManagerName = trim((string) ($assistantsDecoded['assistant_shop_manager']['name'] ?? ''));
                                    $assistantShopManagerContact = implode(' · ', array_values(array_filter([
                                        trim((string) ($assistantsDecoded['assistant_shop_manager']['email'] ?? '')),
                                        trim((string) ($assistantsDecoded['assistant_shop_manager']['phone'] ?? '')),
                                    ], static fn ($v) => $v !== '')));
                                }
                                $displayOrDash = static function ($value): string {
                                    $text = trim((string) ($value ?? ''));
                                    return $text !== '' ? $text : '—';
                                };
                                ?>
                                <div class="col-md-6"><div class="small text-secondary">Lead Contact</div><div><?= e($leadContact !== '' ? $leadContact : '—') ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Assistant</div><div><?= e($displayOrDash($assistantName)) ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Assistant Contact</div><div><?= e($assistantContact !== '' ? $assistantContact : '—') ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Shop Manager</div><div><?= e($displayOrDash($selectedShow['shop_manager_name'] ?? null)) ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Shop Manager Contact</div><div><?= e($shopManagerContact !== '' ? $shopManagerContact : '—') ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Assistant Shop Manager</div><div><?= e($assistantShopManagerName !== '' ? $assistantShopManagerName : '—') ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Assistant Shop Manager Contact</div><div><?= e($assistantShopManagerContact !== '' ? $assistantShopManagerContact : '—') ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Pull / Return / Strike</div><div><?= e($displayOrDash($selectedShow['pull_date'] ?? null)) ?> / <?= e($displayOrDash($selectedShow['return_date'] ?? null)) ?> / <?= e($displayOrDash($selectedShow['strike_date'] ?? null)) ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary">Opening / Closing</div><div><?= e($displayOrDash($selectedShow['opening_date'] ?? null)) ?> / <?= e($displayOrDash($selectedShow['closing_date'] ?? null)) ?></div></div>
                                <div class="col-12"><div class="small text-secondary">Theatre Address</div><div><?= nl2br(e($displayOrDash($selectedShow['theatre_address'] ?? null))) ?></div></div>
                                <div class="col-12"><div class="small text-secondary">Shop Address</div><div><?= nl2br(e($displayOrDash($selectedShow['shop_address'] ?? null))) ?></div></div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($selectedShow && $currentTab === 'paperwork'): ?>
                    <?php
                    $previewMode = (string) ($_GET['preview'] ?? '') === '1';
                    $paperworkSettings = file_exists(__DIR__ . '/paperwork_export_settings_reference.php')
                        ? require __DIR__ . '/paperwork_export_settings_reference.php'
                        : ['current_export_settings' => []];
                    $layout = $paperworkSettings['current_export_settings'] ?? [];
                    $headerText = (string) ($layout['layout.header_text'] ?? 'Production Electrician Shop Order');
                    $organizationText = (string) ($layout['layout.organization_text'] ?? '');
                    $footerText = (string) ($layout['layout.footer_text'] ?? 'Prepared in Backline');
                    $defaultNotes = preg_split('/\r\n|\r|\n/', (string) ($layout['layout.export_notes'] ?? '')) ?: [];
                    $defaultNotes = array_values(array_filter(array_map(static fn ($n): string => trim((string) $n), $defaultNotes), static fn ($n): bool => $n !== ''));
                    $isInitialRevision = $currentRevisionLabel === '1.1';
                    $backTab = $isInitialRevision ? 'initial' : 'revisions';
                    ?>
                    <?php if (!$previewMode): ?>
                        <div class="card"><div class="card-body text-secondary">
                            <p class="mb-0">Use “Export Latest Paperwork” to open print preview.</p>
                        </div></div>
                    <?php else: ?>
                        <div class="card mb-3 paperwork-preview-actions">
                            <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                <a class="btn btn-outline-secondary" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=<?= e($backTab) ?><?= $selectedRevisionId > 0 ? '&revision=' . (int) $selectedRevisionId : '' ?>">Back to Show</a>
                                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
                            </div>
                        </div>
                        <?php
                        $previewRows = [];
                        foreach ($linesByCategory as $category => $subGroups) {
                            foreach ($subGroups as $subcategory => $lines) {
                                $previewRows[] = ['__type' => 'subcategory', 'category' => $category, 'subcategory' => $subcategory];
                                foreach ($lines as $line) {
                                    $previewRows[] = ['__type' => 'line', 'category' => $category, 'subcategory' => $subcategory, 'line' => $line];
                                }
                            }
                        }
                        $rowsPerPage = 28;
                        $previewPages = array_chunk($previewRows, $rowsPerPage);
                        if (!$previewPages) {
                            $previewPages = [[]];
                        }
                        ?>
                        <div class="paperwork-preview-wrap">
                            <?php foreach ($previewPages as $pageIndex => $pageRows): ?>
                                <div class="paperwork-page-break"></div>
                                <div class="paperwork-page">
                                    <div class="paperwork-top">
                                        <div>
                                            <h2 class="paperwork-title"><?= e($headerText) ?></h2>
                                            <div class="paperwork-subtitle"><?= e((string) $selectedShow['show_name']) ?> · Revision <?= e($currentRevisionLabel) ?></div>
                                        </div>
                                        <div class="paperwork-org"><?= e($organizationText) ?> · Page <?= (int) ($pageIndex + 1) ?> / <?= count($previewPages) ?></div>
                                    </div>
                                    <table class="paperwork-table">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Item</th>
                                                <?php if ($shopType === 'snd'): ?><th>SKU</th><?php endif; ?>
                                                <th>Used</th>
                                                <th>Spare</th>
                                                <th>Total</th>
                                                <th>Unit</th>
                                                <th>Action</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pageRows as $row): ?>
                                                <?php if (($row['__type'] ?? '') === 'subcategory'): ?>
                                                    <tr><td colspan="<?= $shopType === 'snd' ? '9' : '8' ?>" class="paperwork-category-row"><?= e((string) ($row['category'] ?? '')) ?><?php if ((string) ($row['subcategory'] ?? '') !== ''): ?> <span class="paperwork-subcat">› <?= e((string) ($row['subcategory'] ?? '')) ?></span><?php endif; ?></td></tr>
                                                <?php else: ?>
                                                    <?php
                                                    $line = $row['line'] ?? [];
                                                    $noteParts = [];
                                                    $lineNote = trim((string) ($line['line_note'] ?? ''));
                                                    if ($lineNote !== '') {
                                                        $noteParts[] = $lineNote;
                                                    }
                                                    $pullDate = trim((string) ($line['specific_pull_date'] ?? ''));
                                                    if ($pullDate !== '') {
                                                        $noteParts[] = 'Pull: ' . $pullDate;
                                                    }
                                                    $returnDate = trim((string) ($line['specific_return_date'] ?? ''));
                                                    if ($returnDate !== '') {
                                                        $noteParts[] = 'Return: ' . $returnDate;
                                                    }
                                                    $qty = (int) ($line['qty'] ?? 0);
                                                    $spares = (int) ($line['spares'] ?? 0);
                                                    ?>
                                                    <tr>
                                                        <td><?= e((string) ($row['category'] ?? '')) ?></td>
                                                        <td><?= e((string) ($line['item_name'] ?? '')) ?></td>
                                                        <?php if ($shopType === 'snd'): ?><td><?= e((string) ($line['sku'] ?? '')) ?></td><?php endif; ?>
                                                        <td><?= $qty ?></td>
                                                        <td><?= $spares ?></td>
                                                        <td><?= $qty + $spares ?></td>
                                                        <td><?= e((string) ($line['unit'] ?? '')) ?></td>
                                                        <td><?= e((string) ($line['action_code'] ?? 'blank')) ?></td>
                                                        <td><?= e(implode(' | ', $noteParts)) ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php if ($defaultNotes && $pageIndex === count($previewPages) - 1): ?>
                                        <div class="paperwork-notes">
                                            <h3>Important Notes</h3>
                                            <ul>
                                                <?php foreach ($defaultNotes as $note): ?>
                                                    <li><?= e($note) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <div class="paperwork-footer"><?= e($footerText) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                                <a class="btn btn-sm <?= ((int) $rev['id'] === (int) $selectedRevisionId) ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=revisions&revision=<?= (int) $rev['id'] ?>">
                                    <?= e((string) $rev['revision_label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($selectedRevisionId > 0): ?>
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Revision Lines</h3></div>
                            <div class="card-body p-0 js-shop-lines-editor">
                                <form method="post">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="save_lines">
                                    <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="revision_id" value="<?= (int) $selectedRevisionId ?>">
                                    <input type="hidden" name="current_tab" value="<?= e($currentTab) ?>">
                                    <div class="card-body border-bottom">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-5">
                                                <label class="form-label">Live Search</label>
                                                <input type="search" class="form-control js-line-search" placeholder="Search items, SKU, or notes">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Filter Category</label>
                                                <select class="form-select js-category-filter">
                                                    <option value="">All Categories</option>
                                                    <?php foreach (array_keys($linesByCategory) as $categoryName): ?>
                                                        <option value="<?= e(strtolower($categoryName)) ?>"><?= e($categoryName) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 text-md-end">
                                                <div class="small text-secondary">Total (Qty + Spares)</div>
                                                <div class="h3 mb-0"><span class="badge bg-azure-lt js-grand-total">0</span></div>
                                            </div>
                                            <div class="col-12">
                                                <div class="small text-secondary js-save-status">All changes auto-save as you type.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th style="width: 18%">Category</th><th>Item</th><th style="width: 90px">Qty</th><th style="width: 90px">Spares</th><th style="width: 90px">Total</th><th style="width: 140px">Action</th><th style="width: 220px">Dates</th><th>Note</th></tr></thead>
                                            <?php foreach ($linesByCategory as $category => $subGroups): ?>
                                                <?php $lineCount = 0; foreach ($subGroups as $subLinesForCount) { $lineCount += count($subLinesForCount); } ?>
                                                <?php $categoryKey = 'rev-cat-' . substr(md5($category), 0, 12); ?>
                                                <tbody>
                                                    <tr class="category-header-row shop-category-header" data-target="<?= e($categoryKey) ?>" data-expanded="0">
                                                        <td colspan="8">
                                                            <button type="button" class="btn btn-ghost-secondary btn-sm js-toggle-category">
                                                                <span class="shop-category-toggle-icon me-1">▶</span>
                                                                <strong><?= e($category) ?></strong>
                                                                <span class="badge bg-secondary-lt ms-2"><?= (int) $lineCount ?></span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tbody id="<?= e($categoryKey) ?>" class="shop-category-group" data-category="<?= e(strtolower($category)) ?>" style="display:none;">
                                                <?php foreach ($subGroups as $subcategory => $lines): ?>
                                                    <tr class="shop-subcategory-row"><td colspan="8"><span class="badge bg-blue-lt"><?= e($subcategory) ?></span></td></tr>
                                                    <?php foreach ($lines as $line): ?>
                                                        <?php
                                                        $searchBlob = strtolower(trim(implode(' ', [
                                                            (string) $category,
                                                            (string) $subcategory,
                                                            (string) ($line['item_name'] ?? ''),
                                                            (string) ($line['sku'] ?? ''),
                                                            (string) ($line['line_note'] ?? ''),
                                                        ])));
                                                        ?>
                                                        <tr class="shop-line-row" data-line-id="<?= (int) $line['id'] ?>" data-search="<?= e($searchBlob) ?>">
                                                            <td><?= e($category) ?></td>
                                                            <td><?= e((string) $line['item_name']) ?><?php if ($shopType === 'snd' && (string) $line['sku'] !== ''): ?> <span class="text-secondary small">(<?= e((string) $line['sku']) ?>)</span><?php endif; ?></td>
                                                            <td>
                                                                <input type="hidden" name="line_id[]" value="<?= (int) $line['id'] ?>">
                                                                <input class="form-control js-live-field js-qty" type="number" min="0" name="qty[]" value="<?= (int) $line['qty'] ?>">
                                                            </td>
                                                            <td><input class="form-control js-live-field js-spares" type="number" min="0" name="spares[]" value="<?= (int) $line['spares'] ?>"></td>
                                                            <td><span class="badge bg-azure-lt js-row-total">0</span></td>
                                                            <td>
                                                                <select class="form-select js-live-field" name="action_code[]">
                                                                    <?php foreach (['blank' => '—', 'add' => 'Add', 'return' => 'Return', 'exchange' => 'Exchange', 'notes' => 'Notes'] as $value => $label): ?>
                                                                        <option value="<?= e($value) ?>" <?= ((string) $line['action_code'] === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <details>
                                                                    <summary class="text-primary">Pull/Return</summary>
                                                                    <div class="mt-2 d-grid gap-2">
                                                                        <input class="form-control js-live-field" type="date" name="specific_pull_date[]" value="<?= e((string) ($line['specific_pull_date'] ?? '')) ?>" aria-label="Specific pull date">
                                                                        <input class="form-control js-live-field" type="date" name="specific_return_date[]" value="<?= e((string) ($line['specific_return_date'] ?? '')) ?>" aria-label="Specific return date">
                                                                    </div>
                                                                </details>
                                                            </td>
                                                            <td><input class="form-control js-live-field" name="line_note[]" value="<?= e((string) $line['line_note']) ?>"></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                                </tbody>
                                            <?php endforeach; ?>
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
                            <div class="card-body p-0 js-shop-lines-editor">
                                <form method="post">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="save_lines">
                                    <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="revision_id" value="<?= (int) $selectedRevisionId ?>">
                                    <input type="hidden" name="current_tab" value="<?= e($currentTab) ?>">
                                    <div class="card-body border-bottom">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-5">
                                                <label class="form-label">Live Search</label>
                                                <input type="search" class="form-control js-line-search" placeholder="Search items, SKU, or notes">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Filter Category</label>
                                                <select class="form-select js-category-filter">
                                                    <option value="">All Categories</option>
                                                    <?php foreach (array_keys($linesByCategory) as $categoryName): ?>
                                                        <option value="<?= e(strtolower($categoryName)) ?>"><?= e($categoryName) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 text-md-end">
                                                <div class="small text-secondary">Total (Qty + Spares)</div>
                                                <div class="h3 mb-0"><span class="badge bg-azure-lt js-grand-total">0</span></div>
                                            </div>
                                            <div class="col-12">
                                                <div class="small text-secondary js-save-status">All changes auto-save as you type.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-vcenter">
                                            <thead><tr><th style="width: 18%">Category</th><th>Item</th><th style="width: 90px">Qty</th><th style="width: 90px">Spares</th><th style="width: 90px">Total</th><th style="width: 140px">Action</th><th style="width: 220px">Dates</th><th>Note</th></tr></thead>
                                            <?php foreach ($linesByCategory as $category => $subGroups): ?>
                                                <?php $lineCount = 0; foreach ($subGroups as $subLinesForCount) { $lineCount += count($subLinesForCount); } ?>
                                                <?php $categoryKey = 'init-cat-' . substr(md5($category), 0, 12); ?>
                                                <tbody>
                                                    <tr class="category-header-row shop-category-header" data-target="<?= e($categoryKey) ?>" data-expanded="0">
                                                        <td colspan="8">
                                                            <button type="button" class="btn btn-ghost-secondary btn-sm js-toggle-category">
                                                                <span class="shop-category-toggle-icon me-1">▶</span>
                                                                <strong><?= e($category) ?></strong>
                                                                <span class="badge bg-secondary-lt ms-2"><?= (int) $lineCount ?></span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tbody id="<?= e($categoryKey) ?>" class="shop-category-group" data-category="<?= e(strtolower($category)) ?>" style="display:none;">
                                                <?php foreach ($subGroups as $subcategory => $lines): ?>
                                                    <tr class="shop-subcategory-row"><td colspan="8"><span class="badge bg-blue-lt"><?= e($subcategory) ?></span></td></tr>
                                                    <?php foreach ($lines as $line): ?>
                                                        <?php
                                                        $searchBlob = strtolower(trim(implode(' ', [
                                                            (string) $category,
                                                            (string) $subcategory,
                                                            (string) ($line['item_name'] ?? ''),
                                                            (string) ($line['sku'] ?? ''),
                                                            (string) ($line['line_note'] ?? ''),
                                                        ])));
                                                        ?>
                                                        <tr class="shop-line-row" data-line-id="<?= (int) $line['id'] ?>" data-search="<?= e($searchBlob) ?>">
                                                            <td><?= e($category) ?></td>
                                                            <td><?= e((string) $line['item_name']) ?><?php if ($shopType === 'snd' && (string) $line['sku'] !== ''): ?> <span class="text-secondary small">(<?= e((string) $line['sku']) ?>)</span><?php endif; ?></td>
                                                            <td>
                                                                <input type="hidden" name="line_id[]" value="<?= (int) $line['id'] ?>">
                                                                <input class="form-control js-live-field js-qty" type="number" min="0" name="qty[]" value="<?= (int) $line['qty'] ?>">
                                                            </td>
                                                            <td><input class="form-control js-live-field js-spares" type="number" min="0" name="spares[]" value="<?= (int) $line['spares'] ?>"></td>
                                                            <td><span class="badge bg-azure-lt js-row-total">0</span></td>
                                                            <td>
                                                                <select class="form-select js-live-field" name="action_code[]">
                                                                    <?php foreach (['blank' => '—', 'add' => 'Add', 'return' => 'Return', 'exchange' => 'Exchange', 'notes' => 'Notes'] as $value => $label): ?>
                                                                        <option value="<?= e($value) ?>" <?= ((string) $line['action_code'] === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <details>
                                                                    <summary class="text-primary">Pull/Return</summary>
                                                                    <div class="mt-2 d-grid gap-2">
                                                                        <input class="form-control js-live-field" type="date" name="specific_pull_date[]" value="<?= e((string) ($line['specific_pull_date'] ?? '')) ?>" aria-label="Specific pull date">
                                                                        <input class="form-control js-live-field" type="date" name="specific_return_date[]" value="<?= e((string) ($line['specific_return_date'] ?? '')) ?>" aria-label="Specific return date">
                                                                    </div>
                                                                </details>
                                                            </td>
                                                            <td><input class="form-control js-live-field" name="line_note[]" value="<?= e((string) $line['line_note']) ?>"></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                                </tbody>
                                            <?php endforeach; ?>
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
                <script>
                    (() => {
                        const editors = document.querySelectorAll('.js-shop-lines-editor');
                        if (!editors.length) {
                            return;
                        }

                        const saveTimers = new Map();
                        const updateEditorTotals = (editor) => {
                            let grandTotal = 0;
                            editor.querySelectorAll('.shop-line-row').forEach((row) => {
                                const qty = Number.parseInt(row.querySelector('.js-qty')?.value || '0', 10) || 0;
                                const spares = Number.parseInt(row.querySelector('.js-spares')?.value || '0', 10) || 0;
                                const total = qty + spares;
                                const totalEl = row.querySelector('.js-row-total');
                                if (totalEl) {
                                    totalEl.textContent = String(total);
                                }
                                grandTotal += total;
                            });
                            const grandEl = editor.querySelector('.js-grand-total');
                            if (grandEl) {
                                grandEl.textContent = String(grandTotal);
                            }
                        };

                        const applyFilters = (editor) => {
                            const search = (editor.querySelector('.js-line-search')?.value || '').trim().toLowerCase();
                            const categoryFilter = (editor.querySelector('.js-category-filter')?.value || '').trim();
                            const hasFilter = search !== '' || categoryFilter !== '';

                            editor.querySelectorAll('.shop-category-group').forEach((group) => {
                                const groupCategory = (group.getAttribute('data-category') || '').toLowerCase();
                                const header = editor.querySelector(`.shop-category-header[data-target="${group.id}"]`);
                                let visibleRows = 0;
                                group.querySelectorAll('.shop-line-row').forEach((row) => {
                                    const haystack = (row.getAttribute('data-search') || '').toLowerCase();
                                    const matchesSearch = search === '' || haystack.includes(search);
                                    const matchesCategory = categoryFilter === '' || groupCategory === categoryFilter;
                                    const shouldShow = matchesSearch && matchesCategory;
                                    row.style.display = shouldShow ? '' : 'none';
                                    if (shouldShow) {
                                        visibleRows++;
                                    }
                                });

                                if (!header) {
                                    return;
                                }

                                if (visibleRows === 0) {
                                    header.style.display = 'none';
                                    group.style.display = 'none';
                                    return;
                                }

                                header.style.display = '';
                                if (hasFilter) {
                                    group.style.display = '';
                                } else {
                                    group.style.display = header.getAttribute('data-expanded') === '1' ? '' : 'none';
                                }
                            });
                        };

                        const saveRow = (editor, row) => {
                            const form = editor.querySelector('form');
                            if (!form) {
                                return;
                            }
                            const saveStatus = editor.querySelector('.js-save-status');
                            if (saveStatus) {
                                saveStatus.textContent = 'Saving…';
                            }
                            const params = new URLSearchParams();
                            params.set('_csrf', form.querySelector('input[name="_csrf"]')?.value || '');
                            params.set('action', 'save_line_live');
                            params.set('show_id', form.querySelector('input[name="show_id"]')?.value || '');
                            params.set('order_id', form.querySelector('input[name="order_id"]')?.value || '');
                            params.set('revision_id', form.querySelector('input[name="revision_id"]')?.value || '');
                            params.set('current_tab', form.querySelector('input[name="current_tab"]')?.value || '');
                            params.set('line_id', row.querySelector('input[name="line_id[]"]')?.value || '');
                            params.set('qty', row.querySelector('input[name="qty[]"]')?.value || '0');
                            params.set('spares', row.querySelector('input[name="spares[]"]')?.value || '0');
                            params.set('action_code', row.querySelector('select[name="action_code[]"]')?.value || 'blank');
                            params.set('specific_pull_date', row.querySelector('input[name="specific_pull_date[]"]')?.value || '');
                            params.set('specific_return_date', row.querySelector('input[name="specific_return_date[]"]')?.value || '');
                            params.set('line_note', row.querySelector('input[name="line_note[]"]')?.value || '');

                            fetch(window.location.pathname + window.location.search, {
                                method: 'POST',
                                credentials: 'same-origin',
                                redirect: 'error',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: params.toString(),
                            }).then(async (response) => {
                                if (!response.ok) {
                                    throw new Error('save failed');
                                }
                                const payload = await response.json();
                                if (!payload || payload.ok !== true) {
                                    throw new Error('save rejected');
                                }
                                if (saveStatus) {
                                    saveStatus.textContent = 'Saved';
                                }
                            }).catch(() => {
                                if (saveStatus) {
                                    saveStatus.textContent = 'Autosave failed. Use Save button.';
                                }
                            });
                        };

                        editors.forEach((editor) => {
                            updateEditorTotals(editor);
                            applyFilters(editor);
                            editor.querySelectorAll('.shop-category-header .js-toggle-category').forEach((btn) => {
                                btn.addEventListener('click', () => {
                                    const header = btn.closest('.shop-category-header');
                                    if (!header) {
                                        return;
                                    }
                                    const target = header.getAttribute('data-target');
                                    if (!target) {
                                        return;
                                    }
                                    const group = editor.querySelector('#' + target);
                                    if (!group) {
                                        return;
                                    }
                                    const nextExpanded = header.getAttribute('data-expanded') === '1' ? '0' : '1';
                                    header.setAttribute('data-expanded', nextExpanded);
                                    const icon = header.querySelector('.shop-category-toggle-icon');
                                    if (icon) {
                                        icon.textContent = nextExpanded === '1' ? '▼' : '▶';
                                    }
                                    if ((editor.querySelector('.js-line-search')?.value || '').trim() !== '' || (editor.querySelector('.js-category-filter')?.value || '').trim() !== '') {
                                        return;
                                    }
                                    group.style.display = nextExpanded === '1' ? '' : 'none';
                                });
                            });

                            const searchInput = editor.querySelector('.js-line-search');
                            const categoryInput = editor.querySelector('.js-category-filter');
                            searchInput?.addEventListener('input', () => applyFilters(editor));
                            categoryInput?.addEventListener('change', () => applyFilters(editor));

                            editor.querySelectorAll('.shop-line-row').forEach((row) => {
                                row.querySelectorAll('.js-live-field').forEach((field) => {
                                    const handler = () => {
                                        updateEditorTotals(editor);
                                        const lineId = row.getAttribute('data-line-id') || '';
                                        if (saveTimers.has(lineId)) {
                                            window.clearTimeout(saveTimers.get(lineId));
                                        }
                                        const timeoutId = window.setTimeout(() => saveRow(editor, row), 350);
                                        saveTimers.set(lineId, timeoutId);
                                    };
                                    field.addEventListener('input', handler);
                                    field.addEventListener('change', handler);
                                });
                            });
                        });
                    })();
                </script>
            <?php endif; ?>
            <?php
        }, $user);
    }
}
