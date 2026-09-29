<?php

declare(strict_types=1);

if (!function_exists('shop_revision_label')) {
    function shop_revision_label(int $number): string
    {
        return '1.' . max(0, $number - 1);
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
            if (!$hasColumn) {
                db()->exec('ALTER TABLE inventory_items ADD COLUMN subcategory_name VARCHAR(190) NULL AFTER category_id');
                $stmt = db()->query("SHOW COLUMNS FROM inventory_items LIKE 'subcategory_name'");
                $hasColumn = (bool) $stmt->fetch();
            }
        } catch (Throwable) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('shop_store_show_upload')) {
    function shop_store_show_upload(array $file, int $showId, string $baseName): ?string
    {
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return null;
        }

        $dir = __DIR__ . '/../uploads/shows/' . $showId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        foreach (glob($dir . '/' . $baseName . '.*') ?: [] as $oldFile) {
            @unlink($oldFile);
        }

        $target = $dir . '/' . $baseName . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmpPath, $target)) {
            return null;
        }
        return '/uploads/shows/' . $showId . '/' . $baseName . '.' . $allowed[$mime];
    }
}

if (!function_exists('shop_sync_initial_line_to_revisions')) {
    function shop_sync_initial_line_to_revisions(int $orderId, int $inventoryItemId, int $qty, int $spares, string $lineNote, ?string $pullDate, ?string $returnDate, int $sortOrder, bool $hasReturnDateColumn): void
    {
        $revisionStmt = db()->prepare('SELECT id FROM order_revisions WHERE order_id = ? AND revision_number > 1');
        $revisionStmt->execute([$orderId]);
        $revisionIds = array_map(static fn ($row): int => (int) ($row['id'] ?? 0), $revisionStmt->fetchAll());
        $revisionIds = array_values(array_filter($revisionIds, static fn ($id): bool => $id > 0));
        if (empty($revisionIds)) {
            return;
        }

        if ($hasReturnDateColumn) {
            $insertStmt = db()->prepare('INSERT INTO order_lines (revision_id, inventory_item_id, qty, spares, line_note, specific_pull_date, specific_return_date, action_code, sort_order, created_at, updated_at)
                SELECT ?, ?, ?, ?, ?, ?, ?, "blank", ?, NOW(), NOW()
                FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM order_lines WHERE revision_id = ? AND inventory_item_id = ?)');
            $updateStmt = db()->prepare('UPDATE order_lines ol
                JOIN order_revisions r ON r.id = ol.revision_id
                SET ol.qty = ?, ol.spares = ?, ol.line_note = ?, ol.specific_pull_date = ?, ol.specific_return_date = ?, ol.sort_order = ?, ol.updated_at = NOW()
                WHERE r.order_id = ? AND r.revision_number > 1 AND ol.inventory_item_id = ?');
        } else {
            $insertStmt = db()->prepare('INSERT INTO order_lines (revision_id, inventory_item_id, qty, spares, line_note, specific_pull_date, action_code, sort_order, created_at, updated_at)
                SELECT ?, ?, ?, ?, ?, ?, "blank", ?, NOW(), NOW()
                FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM order_lines WHERE revision_id = ? AND inventory_item_id = ?)');
            $updateStmt = db()->prepare('UPDATE order_lines ol
                JOIN order_revisions r ON r.id = ol.revision_id
                SET ol.qty = ?, ol.spares = ?, ol.line_note = ?, ol.specific_pull_date = ?, ol.sort_order = ?, ol.updated_at = NOW()
                WHERE r.order_id = ? AND r.revision_number > 1 AND ol.inventory_item_id = ?');
        }

        foreach ($revisionIds as $revisionId) {
            if ($hasReturnDateColumn) {
                $insertStmt->execute([$revisionId, $inventoryItemId, $qty, $spares, $lineNote, $pullDate, $returnDate, $sortOrder, $revisionId, $inventoryItemId]);
            } else {
                $insertStmt->execute([$revisionId, $inventoryItemId, $qty, $spares, $lineNote, $pullDate, $sortOrder, $revisionId, $inventoryItemId]);
            }
        }

        if ($hasReturnDateColumn) {
            $updateStmt->execute([$qty, $spares, $lineNote, $pullDate, $returnDate, $sortOrder, $orderId, $inventoryItemId]);
        } else {
            $updateStmt->execute([$qty, $spares, $lineNote, $pullDate, $sortOrder, $orderId, $inventoryItemId]);
        }
    }
}

if (!function_exists('render_shop_app_page')) {
    function render_shop_app_page(array $user, string $shopType, string $pageTitle, string $heading, string $scaffoldCopy, bool $showFirstNav = false): void
    {
        $appPath = $shopType === 'lx' ? '/dash/lx' : '/dash/sound';
        $isAdmin = user_has_permission($user, 'admin.access');
        $showListStmt = $isAdmin
            ? (function () use ($shopType) {
                $stmt = db()->prepare('SELECT id, show_name, show_scope, theatre_name, shop_name, lead_designer_name, lead_designer_email, lead_designer_phone, ald_name, ald_email, ald_phone, assistant_snd_designer_name, assistant_snd_designer_email, assistant_snd_designer_phone, shop_manager_name, shop_manager_email, shop_manager_phone, assistants_json, pull_date, return_date, strike_date, opening_date, closing_date, theatre_address, shop_address, show_image_path FROM shows WHERE deleted_at IS NULL AND COALESCE(show_scope, "both") IN ("both", ?) ORDER BY show_name');
                $stmt->execute([$shopType]);
                return $stmt;
            })()
            : (function () use ($user, $shopType) {
                $stmt = db()->prepare('SELECT id, show_name, show_scope, theatre_name, shop_name, lead_designer_name, lead_designer_email, lead_designer_phone, ald_name, ald_email, ald_phone, assistant_snd_designer_name, assistant_snd_designer_email, assistant_snd_designer_phone, shop_manager_name, shop_manager_email, shop_manager_phone, assistants_json, pull_date, return_date, strike_date, opening_date, closing_date, theatre_address, shop_address, show_image_path FROM shows WHERE deleted_at IS NULL AND owner_user_id = ? AND COALESCE(show_scope, "both") IN ("both", ?) ORDER BY show_name');
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
        $allowedTabs = ['info', 'initial', 'revisions', 'paperwork', 'exports'];
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
                $checkStmt = db()->prepare('SELECT r.id, r.revision_number FROM order_revisions r JOIN orders o ON o.id = r.order_id JOIN shows s ON s.id = o.show_id WHERE r.id = ? AND r.order_id = ? AND o.show_id = ? AND o.shop_type = ? AND s.deleted_at IS NULL' . $showAccessCondition . ' LIMIT 1');
                $checkStmt->execute([$revisionId, $orderId, $selectedShowId, $shopType]);
                $revisionRow = $checkStmt->fetch();
                if ($revisionRow) {
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
                        $lineMeta = [];
                        $lineMetaStmt = db()->prepare('SELECT id, inventory_item_id, sort_order FROM order_lines WHERE revision_id = ?');
                        $lineMetaStmt->execute([$revisionId]);
                        foreach ($lineMetaStmt->fetchAll() as $lineMetaRow) {
                            $lineMeta[(int) ($lineMetaRow['id'] ?? 0)] = [
                                'inventory_item_id' => (int) ($lineMetaRow['inventory_item_id'] ?? 0),
                                'sort_order' => (int) ($lineMetaRow['sort_order'] ?? 0),
                            ];
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
                            if ((int) ($revisionRow['revision_number'] ?? 0) === 1) {
                                $meta = $lineMeta[(int) $lineId] ?? null;
                                if ($meta && (int) ($meta['inventory_item_id'] ?? 0) > 0) {
                                    shop_sync_initial_line_to_revisions(
                                        $orderId,
                                        (int) $meta['inventory_item_id'],
                                        (int) ($qty[$idx] ?? 0),
                                        (int) ($spares[$idx] ?? 0),
                                        trim((string) ($notes[$idx] ?? '')),
                                        $pullDate === '' ? null : $pullDate,
                                        $returnDate === '' ? null : $returnDate,
                                        (int) ($meta['sort_order'] ?? 0),
                                        $hasReturnDateColumn
                                    );
                                }
                            }
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
                $checkStmt = db()->prepare('SELECT r.id, r.revision_number FROM order_revisions r JOIN orders o ON o.id = r.order_id JOIN shows s ON s.id = o.show_id WHERE r.id = ? AND r.order_id = ? AND o.show_id = ? AND o.shop_type = ? AND s.deleted_at IS NULL' . $showAccessCondition . ' LIMIT 1');
                $checkStmt->execute([$revisionId, $orderId, $selectedShowId, $shopType]);
                $revisionRow = $checkStmt->fetch();
                if ($revisionRow && $lineId > 0) {
                    $actionCode = (string) post('action_code', 'blank');
                    if (!in_array($actionCode, ['blank', 'add', 'return', 'exchange', 'notes'], true)) {
                        $actionCode = 'blank';
                    }
                    $pullDate = trim((string) post('specific_pull_date', ''));
                    $returnDate = trim((string) post('specific_return_date', ''));
                    try {
                        $qtyValue = (int) post('qty', '0');
                        $sparesValue = (int) post('spares', '0');
                        $lineNoteValue = trim((string) post('line_note', ''));
                        if ($hasReturnDateColumn) {
                            $upd = db()->prepare('UPDATE order_lines SET qty = ?, spares = ?, line_note = ?, specific_pull_date = ?, specific_return_date = ?, action_code = ?, updated_at = NOW() WHERE id = ? AND revision_id = ?');
                            $upd->execute([
                                $qtyValue,
                                $sparesValue,
                                $lineNoteValue,
                                $pullDate === '' ? null : $pullDate,
                                $returnDate === '' ? null : $returnDate,
                                $actionCode,
                                $lineId,
                                $revisionId,
                            ]);
                        } else {
                            $upd = db()->prepare('UPDATE order_lines SET qty = ?, spares = ?, line_note = ?, specific_pull_date = ?, action_code = ?, updated_at = NOW() WHERE id = ? AND revision_id = ?');
                            $upd->execute([
                                $qtyValue,
                                $sparesValue,
                                $lineNoteValue,
                                $pullDate === '' ? null : $pullDate,
                                $actionCode,
                                $lineId,
                                $revisionId,
                            ]);
                        }
                        if ((int) ($revisionRow['revision_number'] ?? 0) === 1) {
                            $lineMetaStmt = db()->prepare('SELECT inventory_item_id, sort_order FROM order_lines WHERE id = ? AND revision_id = ? LIMIT 1');
                            $lineMetaStmt->execute([$lineId, $revisionId]);
                            $lineMeta = $lineMetaStmt->fetch();
                            if ($lineMeta && (int) ($lineMeta['inventory_item_id'] ?? 0) > 0) {
                                shop_sync_initial_line_to_revisions(
                                    $orderId,
                                    (int) $lineMeta['inventory_item_id'],
                                    $qtyValue,
                                    $sparesValue,
                                    $lineNoteValue,
                                    $pullDate === '' ? null : $pullDate,
                                    $returnDate === '' ? null : $returnDate,
                                    (int) ($lineMeta['sort_order'] ?? 0),
                                    $hasReturnDateColumn
                                );
                            }
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

            if ($action === 'save_paperwork_options' && $selectedShowId > 0) {
                $paperworkSettings = file_exists(__DIR__ . '/paperwork_export_settings_reference.php')
                    ? require __DIR__ . '/paperwork_export_settings_reference.php'
                    : ['show_override_keys' => [], 'show_checkbox_inputs' => []];
                $allowedKeys = array_flip($paperworkSettings['show_override_keys'] ?? []);
                $checkboxInputs = $paperworkSettings['show_checkbox_inputs'] ?? [];
                $updates = [];
                foreach (($paperworkSettings['configurable_options'] ?? []) as $option) {
                    if (empty($option['show_override']) || empty($option['setting_key']) || empty($option['input_name'])) {
                        continue;
                    }
                    $settingKey = (string) $option['setting_key'];
                    if (!isset($allowedKeys[$settingKey])) {
                        continue;
                    }
                    $inputName = (string) $option['input_name'];
                    if (in_array($inputName, $checkboxInputs, true)) {
                        $updates[$settingKey] = isset($_POST[$inputName]) ? '1' : '0';
                    } else {
                        $updates[$settingKey] = trim((string) ($_POST[$inputName] ?? ''));
                    }
                }
                $uploadedFooterLogo = shop_store_show_upload($_FILES['cover_footer_logo_file'] ?? [], $selectedShowId, 'paperwork-footer-logo');
                if ($uploadedFooterLogo !== null && isset($allowedKeys['layout.cover_footer_logo_url'])) {
                    $updates['layout.cover_footer_logo_url'] = $uploadedFooterLogo;
                }
                $uploadedShowImage = shop_store_show_upload($_FILES['show_photo_file'] ?? [], $selectedShowId, 'show-photo');
                if ($uploadedShowImage !== null) {
                    $showImageStmt = db()->prepare('UPDATE shows SET show_image_path = ?, updated_at = NOW() WHERE id = ?');
                    $showImageStmt->execute([$uploadedShowImage, $selectedShowId]);
                }
                $upsert = db()->prepare('INSERT INTO show_settings (show_id, key_name, value_json, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = NOW()');
                $upsert->execute([$selectedShowId, 'paperwork.layout_overrides', json_encode($updates)]);
                flash_set('success', 'Paperwork options saved.');
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
                redirect($appPath . '?show=' . $selectedShowId . '&tab=exports&export_revision=' . $latestRevisionId);
            }
            if ($action === 'print_labels' && $selectedShowId > 0 && $shopType === 'snd') {
                flash_set('info', 'Label printing is not wired yet.');
            }

            redirect($appPath . '?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
        }

        $order = null;
        $revisions = [];
        $initialRevision = null;
        $revisionEntries = [];
        $exportableRevisions = [];
        $selectedRevisionId = (int) ($_GET['revision'] ?? 0);
        $selectedExportRevisionId = (int) ($_GET['export_revision'] ?? 0);
        $linesByCategory = [];
        $paperworkSettings = file_exists(__DIR__ . '/paperwork_export_settings_reference.php')
            ? require __DIR__ . '/paperwork_export_settings_reference.php'
            : ['current_export_settings' => [], 'show_override_keys' => [], 'show_checkbox_inputs' => [], 'configurable_options' => []];
        $paperworkLayout = $paperworkSettings['current_export_settings'] ?? [];
        $globalPaperworkRaw = app_setting('paperwork.global_layout_overrides', []);
        $globalPaperworkOverrides = is_array($globalPaperworkRaw) ? $globalPaperworkRaw : (json_decode((string) $globalPaperworkRaw, true) ?: []);
        if (is_array($globalPaperworkOverrides)) {
            $paperworkLayout = array_replace($paperworkLayout, $globalPaperworkOverrides);
        }
        $paperworkOverrides = [];

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

                $revisionLineCounts = [];
                if (!empty($revisions)) {
                    $revisionIds = array_values(array_filter(array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $revisions), static fn ($id): bool => $id > 0));
                    if (!empty($revisionIds)) {
                        $inPlaceholders = implode(',', array_fill(0, count($revisionIds), '?'));
                        $countSql = 'SELECT revision_id, SUM(CASE WHEN qty > 0 OR spares > 0 OR TRIM(COALESCE(line_note, "")) <> "" OR action_code IN ("add","return","exchange","notes") OR COALESCE(specific_pull_date, "") <> ""' . ($hasReturnDateColumn ? ' OR COALESCE(specific_return_date, "") <> ""' : '') . ' THEN 1 ELSE 0 END) AS exportable_count FROM order_lines WHERE revision_id IN (' . $inPlaceholders . ') GROUP BY revision_id';
                        $countStmt = db()->prepare($countSql);
                        $countStmt->execute($revisionIds);
                        foreach ($countStmt->fetchAll() as $row) {
                            $revisionLineCounts[(int) ($row['revision_id'] ?? 0)] = (int) ($row['exportable_count'] ?? 0);
                        }
                    }
                }

                foreach ($revisions as $candidateRevision) {
                    $candidateId = (int) ($candidateRevision['id'] ?? 0);
                    $candidateNumber = (int) ($candidateRevision['revision_number'] ?? 0);
                    $candidateRevision['has_exportable_lines'] = (($revisionLineCounts[$candidateId] ?? 0) > 0) ? 1 : 0;
                    if ($candidateNumber === 1) {
                        $initialRevision = $candidateRevision;
                    } elseif ($candidateNumber > 1) {
                        $revisionEntries[] = $candidateRevision;
                    }
                    if (!empty($candidateRevision['has_exportable_lines'])) {
                        $exportableRevisions[] = $candidateRevision;
                    }
                }

                if ($currentTab === 'initial') {
                    $selectedRevisionId = (int) ($initialRevision['id'] ?? 0);
                } elseif ($currentTab === 'revisions') {
                    if ($selectedRevisionId <= 0 || !in_array($selectedRevisionId, array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $revisionEntries), true)) {
                        $selectedRevisionId = (int) ($revisionEntries[0]['id'] ?? 0);
                    }
                } elseif ($selectedRevisionId <= 0 && $initialRevision !== null) {
                    $selectedRevisionId = (int) $initialRevision['id'];
                }

                if ($selectedExportRevisionId <= 0 && !empty($exportableRevisions)) {
                    $selectedExportRevisionId = (int) ($exportableRevisions[0]['id'] ?? 0);
                }
                if ($selectedExportRevisionId > 0 && !in_array($selectedExportRevisionId, array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $exportableRevisions), true)) {
                    $selectedExportRevisionId = (int) ($exportableRevisions[0]['id'] ?? 0);
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
                        $lineStmt = db()->prepare('SELECT ol.*, ' . ($hasSubcategoryColumn ? 'ii.subcategory_name,' : 'NULL AS subcategory_name,') . ' ii.name AS item_name, ii.sku, ii.unit, ii.shop_quantity, ic.name AS category_name
                            FROM order_lines ol
                            JOIN inventory_items ii ON ii.id = ol.inventory_item_id
                            LEFT JOIN inventory_categories ic ON ic.id = ii.category_id
                            WHERE ol.revision_id = ? AND ii.is_spacer = 0
                            ORDER BY COALESCE(ic.sort_order, 9999), COALESCE(ic.name, "Uncategorized"), ' . ($hasSubcategoryColumn ? 'COALESCE(ii.subcategory_name, "")' : '""') . ', ii.sort_order, ii.name');
                    } else {
                        $lineStmt = db()->prepare('SELECT ol.*, NULL AS specific_return_date, ' . ($hasSubcategoryColumn ? 'ii.subcategory_name,' : 'NULL AS subcategory_name,') . ' ii.name AS item_name, ii.sku, ii.unit, ii.shop_quantity, ic.name AS category_name
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
        if ($selectedShowId > 0) {
            $showSettingStmt = db()->prepare('SELECT value_json FROM show_settings WHERE show_id = ? AND key_name = ? LIMIT 1');
            $showSettingStmt->execute([$selectedShowId, 'paperwork.layout_overrides']);
            $showSettingRaw = $showSettingStmt->fetchColumn();
            if (is_string($showSettingRaw) && $showSettingRaw !== '') {
                $decoded = json_decode($showSettingRaw, true);
                if (is_array($decoded)) {
                    $allowedKeys = array_flip($paperworkSettings['show_override_keys'] ?? []);
                    foreach ($decoded as $key => $value) {
                        if (isset($allowedKeys[(string) $key])) {
                            $paperworkOverrides[(string) $key] = is_scalar($value) ? (string) $value : '';
                        }
                    }
                }
            }
            $paperworkLayout = array_replace($paperworkLayout, $paperworkOverrides);
        }

        render_page($pageTitle, function () use ($shows, $selectedShowId, $selectedShow, $order, $revisions, $initialRevision, $revisionEntries, $exportableRevisions, $selectedRevisionId, $selectedExportRevisionId, $linesByCategory, $shopType, $heading, $scaffoldCopy, $currentTab, $showFirstNav, $appPath, $hasSubcategoryColumn, $paperworkSettings, $paperworkLayout): void {
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
                                <div class="h1 mb-1"><?= e((string) $selectedShow['show_name']) ?> - <?= e(strtoupper($shopType)) ?></div>
                                <div class="h3 mb-0 text-secondary">Revision <?= e($currentRevisionLabel) ?></div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-md-end gap-2">
                                <a class="btn btn-outline-danger btn-sm" href="/dash/home">Exit Show</a>
                                <a class="btn btn-outline-primary btn-sm" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=exports">Exports</a>
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
                        <?php foreach (['info' => 'Show Information', 'initial' => 'Initial Order', 'revisions' => 'Revisions', 'paperwork' => 'Paperwork Settings', 'exports' => 'Exports'] as $tabKey => $tabLabel): ?>
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
                    $layout = $paperworkLayout;
                    $checkboxInputs = $paperworkSettings['show_checkbox_inputs'] ?? [];
                    ?>
                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title mb-0">Paperwork Settings (Per Show)</h3></div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" class="row g-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="save_paperwork_options">
                                <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                <input type="hidden" name="current_tab" value="paperwork">
                                <?php foreach (($paperworkSettings['configurable_options'] ?? []) as $option): ?>
                                    <?php if (empty($option['show_override'])) continue; ?>
                                    <?php
                                    $settingKey = (string) ($option['setting_key'] ?? '');
                                    $inputName = (string) ($option['input_name'] ?? '');
                                    $label = (string) ($option['label'] ?? $inputName);
                                    $type = (string) ($option['type'] ?? 'text');
                                    $value = (string) ($layout[$settingKey] ?? ($option['default_value'] ?? ''));
                                    ?>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= e($label) ?></label>
                                        <?php if ($type === 'textarea'): ?>
                                            <textarea class="form-control" name="<?= e($inputName) ?>" rows="4"><?= e($value) ?></textarea>
                                        <?php elseif (in_array($inputName, $checkboxInputs, true) || $type === 'checkbox'): ?>
                                            <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="<?= e($inputName) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>><span class="form-check-label">Enabled</span></label>
                                        <?php elseif ($type === 'color'): ?>
                                            <input class="form-control form-control-color" type="color" name="<?= e($inputName) ?>" value="<?= e($value !== '' ? $value : '#000000') ?>">
                                        <?php elseif ($type === 'integer'): ?>
                                            <input class="form-control" type="number" step="1" name="<?= e($inputName) ?>" value="<?= e($value) ?>">
                                        <?php elseif ($type === 'decimal'): ?>
                                            <input class="form-control" type="number" step="0.01" name="<?= e($inputName) ?>" value="<?= e($value) ?>">
                                        <?php else: ?>
                                            <input class="form-control" type="text" name="<?= e($inputName) ?>" value="<?= e($value) ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <div class="col-md-6">
                                    <label class="form-label">Personal Cover Footer Logo (Upload)</label>
                                    <input class="form-control" type="file" name="cover_footer_logo_file" accept="image/png,image/jpeg,image/webp,image/gif">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Show Photo (Upload)</label>
                                    <input class="form-control" type="file" name="show_photo_file" accept="image/png,image/jpeg,image/webp,image/gif">
                                </div>
                                <div class="col-12 d-flex justify-content-between">
                                    <button class="btn btn-primary" type="submit">Save Paperwork Options</button>
                                    <a class="btn btn-outline-primary" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=exports&export_revision=<?= (int) $selectedExportRevisionId ?>">Go to Exports</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($selectedShow && $currentTab === 'exports'): ?>
                    <?php
                    $selectedExport = null;
                    foreach ($exportableRevisions as $rev) {
                        if ((int) $rev['id'] === (int) $selectedExportRevisionId) {
                            $selectedExport = $rev;
                            break;
                        }
                    }
                    if ($selectedExport === null && !empty($exportableRevisions)) {
                        $selectedExport = $exportableRevisions[0];
                    }
                    $exportRevisionId = (int) ($selectedExport['id'] ?? 0);
                    $paperworkUrl = '/shared/paperwork_export_template_reference.php?show_id=' . (int) $selectedShowId
                        . '&revision_id=' . $exportRevisionId
                        . '&type=order&shop=' . rawurlencode($shopType)
                        . '&embed=1';
                    ?>
                    <?php if (empty($exportableRevisions)): ?>
                        <div class="card"><div class="card-body text-secondary">No exportable paperwork yet. Add line quantities/notes/actions, let autosave run, then export.</div></div>
                    <?php else: ?>
                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title mb-0">Exports</h3></div>
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end">
                                <input type="hidden" name="show" value="<?= (int) $selectedShowId ?>">
                                <input type="hidden" name="tab" value="exports">
                                <div class="col-md-8">
                                    <label class="form-label">Export Revision</label>
                                    <select class="form-select" name="export_revision" onchange="this.form.submit()">
                                        <?php foreach ($exportableRevisions as $rev): ?>
                                            <?php
                                            $label = (int) ($rev['revision_number'] ?? 0) === 1
                                                ? 'Initial Order (' . (string) ($rev['revision_label'] ?? '1.0') . ')'
                                                : 'Revision ' . (string) ($rev['revision_label'] ?? '');
                                            ?>
                                            <option value="<?= (int) $rev['id'] ?>" <?= (int) $rev['id'] === $exportRevisionId ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button type="button" class="btn btn-primary w-100" id="print-export-button">Print Export</button>
                                    <a class="btn btn-outline-primary w-100" target="_blank" rel="noopener" href="<?= e(str_replace('&embed=1', '', $paperworkUrl)) ?>">Open Full Page</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body p-0">
                            <iframe id="export-preview-frame" src="<?= e($paperworkUrl) ?>" title="Paperwork export preview" style="display:block;width:100%;min-height:900px;border:0;"></iframe>
                        </div>
                    </div>
                    <script>
                        (() => {
                            const printButton = document.getElementById('print-export-button');
                            const frame = document.getElementById('export-preview-frame');
                            if (!printButton || !frame) return;
                            printButton.addEventListener('click', () => {
                                frame.contentWindow?.focus();
                                frame.contentWindow?.print();
                            });
                        })();
                    </script>
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
                            <?php foreach ($revisionEntries as $rev): ?>
                                <a class="btn btn-sm <?= ((int) $rev['id'] === (int) $selectedRevisionId) ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=revisions&revision=<?= (int) $rev['id'] ?>">
                                    <?= e((string) $rev['revision_label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (!empty($revisionEntries) && $selectedRevisionId > 0): ?>
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
                                            <thead><tr><th style="width: 18%">Category</th><th>Item</th><th style="width: 90px">Qty</th><th style="width: 90px">Spares</th><th style="width: 90px">Total</th><th style="width: 90px">Shop Has</th><th style="width: 140px">Action</th><th style="width: 220px">Dates</th><th>Note</th></tr></thead>
                                            <?php foreach ($linesByCategory as $category => $subGroups): ?>
                                                <?php $lineCount = 0; foreach ($subGroups as $subLinesForCount) { $lineCount += count($subLinesForCount); } ?>
                                                <?php $categoryKey = 'rev-cat-' . substr(md5($category), 0, 12); ?>
                                                <tbody>
                                                    <tr class="category-header-row shop-category-header" data-target="<?= e($categoryKey) ?>" data-expanded="0">
                                                        <td colspan="9">
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
                                                    <tr class="shop-subcategory-row" data-subcategory="<?= e(strtolower($subcategory)) ?>"><td colspan="9"><span class="badge bg-blue-lt"><?= e($subcategory) ?></span></td></tr>
                                                    <?php foreach ($lines as $line): ?>
                                                        <?php
                                                        $searchBlob = strtolower(trim(implode(' ', [
                                                            (string) $category,
                                                            (string) $subcategory,
                                                            (string) ($line['item_name'] ?? ''),
                                                            (string) ($line['sku'] ?? ''),
                                                            (string) ($line['line_note'] ?? ''),
                                                        ])));
                                                        $lineActionCode = (string) ($line['action_code'] ?? 'blank');
                                                        if ($lineActionCode === 'notes') {
                                                            $lineActionCode = 'note';
                                                        }
                                                        ?>
                                                        <tr class="shop-line-row shop-action-<?= e($lineActionCode) ?>" data-line-id="<?= (int) $line['id'] ?>" data-action="<?= e($lineActionCode) ?>" data-shop-qty="<?= (int) ($line['shop_quantity'] ?? 0) ?>" data-subcategory="<?= e(strtolower($subcategory)) ?>" data-search="<?= e($searchBlob) ?>">
                                                            <td><?= e($category) ?></td>
                                                            <td><?= e((string) $line['item_name']) ?><?php if ($shopType === 'snd' && (string) $line['sku'] !== ''): ?> <span class="text-secondary small">(<?= e((string) $line['sku']) ?>)</span><?php endif; ?></td>
                                                            <td>
                                                                <input type="hidden" name="line_id[]" value="<?= (int) $line['id'] ?>">
                                                                <input class="form-control js-live-field js-qty" type="number" min="0" name="qty[]" value="<?= (int) $line['qty'] ?>">
                                                            </td>
                                                            <td><input class="form-control js-live-field js-spares" type="number" min="0" name="spares[]" value="<?= (int) $line['spares'] ?>"></td>
                                                            <td><span class="badge bg-azure-lt js-row-total">0</span><div class="small text-danger js-overpull-warning d-none"></div></td>
                                                            <td><span class="badge bg-secondary-lt js-shop-qty"><?= (int) ($line['shop_quantity'] ?? 0) ?></span></td>
                                                            <td>
                                                                <select class="form-select js-live-field js-action-field" name="action_code[]">
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
                                            <thead><tr><th style="width: 18%">Category</th><th>Item</th><th style="width: 90px">Qty</th><th style="width: 90px">Spares</th><th style="width: 90px">Total</th><th style="width: 90px">Shop Has</th><th style="width: 220px">Dates</th><th>Note</th></tr></thead>
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
                                                    <tr class="shop-subcategory-row" data-subcategory="<?= e(strtolower($subcategory)) ?>"><td colspan="8"><span class="badge bg-blue-lt"><?= e($subcategory) ?></span></td></tr>
                                                    <?php foreach ($lines as $line): ?>
                                                        <?php
                                                        $searchBlob = strtolower(trim(implode(' ', [
                                                            (string) $category,
                                                            (string) $subcategory,
                                                            (string) ($line['item_name'] ?? ''),
                                                            (string) ($line['sku'] ?? ''),
                                                            (string) ($line['line_note'] ?? ''),
                                                        ])));
                                                        $lineActionCode = (string) ($line['action_code'] ?? 'blank');
                                                        if ($lineActionCode === 'notes') {
                                                            $lineActionCode = 'note';
                                                        }
                                                        ?>
                                                        <tr class="shop-line-row shop-action-<?= e($lineActionCode) ?>" data-line-id="<?= (int) $line['id'] ?>" data-action="<?= e($lineActionCode) ?>" data-shop-qty="<?= (int) ($line['shop_quantity'] ?? 0) ?>" data-subcategory="<?= e(strtolower($subcategory)) ?>" data-search="<?= e($searchBlob) ?>">
                                                            <td><?= e($category) ?></td>
                                                            <td><?= e((string) $line['item_name']) ?><?php if ($shopType === 'snd' && (string) $line['sku'] !== ''): ?> <span class="text-secondary small">(<?= e((string) $line['sku']) ?>)</span><?php endif; ?></td>
                                                            <td>
                                                                <input type="hidden" name="line_id[]" value="<?= (int) $line['id'] ?>">
                                                                <input class="form-control js-live-field js-qty" type="number" min="0" name="qty[]" value="<?= (int) $line['qty'] ?>">
                                                            </td>
                                                            <td><input class="form-control js-live-field js-spares" type="number" min="0" name="spares[]" value="<?= (int) $line['spares'] ?>"></td>
                                                            <td><span class="badge bg-azure-lt js-row-total">0</span><div class="small text-danger js-overpull-warning d-none"></div></td>
                                                            <td><span class="badge bg-secondary-lt js-shop-qty"><?= (int) ($line['shop_quantity'] ?? 0) ?></span></td>
                                                            <input type="hidden" name="action_code[]" value="blank">
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
                        const normalizeSearchText = (value) => String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
                        const matchesRelativeSearch = (haystackRaw, queryRaw) => {
                            const haystack = normalizeSearchText(haystackRaw);
                            const query = normalizeSearchText(queryRaw);
                            if (query === '') return true;
                            if (haystack.includes(query)) return true;
                            const terms = query.split(/\s+/).filter(Boolean);
                            if (!terms.length) return true;
                            return terms.every((term) => haystack.includes(term));
                        };
                        const updateEditorTotals = (editor) => {
                            let grandTotal = 0;
                            editor.querySelectorAll('.shop-line-row').forEach((row) => {
                                const qty = Number.parseInt(row.querySelector('.js-qty')?.value || '0', 10) || 0;
                                const spares = Number.parseInt(row.querySelector('.js-spares')?.value || '0', 10) || 0;
                                const total = qty + spares;
                                const shopQty = Number.parseInt(row.getAttribute('data-shop-qty') || '0', 10) || 0;
                                const totalEl = row.querySelector('.js-row-total');
                                if (totalEl) {
                                    totalEl.textContent = String(total);
                                }
                                const warningEl = row.querySelector('.js-overpull-warning');
                                if (warningEl instanceof HTMLElement) {
                                    if (shopQty >= 0 && total > shopQty) {
                                        warningEl.textContent = `Over by ${total - shopQty}`;
                                        warningEl.classList.remove('d-none');
                                        row.classList.add('shop-overpull');
                                    } else {
                                        warningEl.textContent = '';
                                        warningEl.classList.add('d-none');
                                        row.classList.remove('shop-overpull');
                                    }
                                }
                                grandTotal += total;
                            });
                            const grandEl = editor.querySelector('.js-grand-total');
                            if (grandEl) {
                                grandEl.textContent = String(grandTotal);
                            }
                        };

                        const applyActionRowClass = (row) => {
                            const select = row.querySelector('select[name="action_code[]"]');
                            const raw = String(select?.value || 'blank').toLowerCase();
                            const normalized = raw === 'notes' ? 'note' : raw;
                            row.classList.remove('shop-action-blank', 'shop-action-add', 'shop-action-return', 'shop-action-exchange', 'shop-action-note');
                            row.classList.add(`shop-action-${normalized}`);
                            row.setAttribute('data-action', normalized);
                        };

                        const applyFilters = (editor) => {
                            const search = (editor.querySelector('.js-line-search')?.value || '').trim();
                            const categoryFilter = (editor.querySelector('.js-category-filter')?.value || '').trim();
                            const hasFilter = search !== '' || categoryFilter !== '';

                            editor.querySelectorAll('.shop-category-group').forEach((group) => {
                                const groupCategory = (group.getAttribute('data-category') || '').toLowerCase();
                                const header = editor.querySelector(`.shop-category-header[data-target="${group.id}"]`);
                                let visibleRows = 0;
                                group.querySelectorAll('.shop-line-row').forEach((row) => {
                                    const haystack = (row.getAttribute('data-search') || '');
                                    const matchesSearch = matchesRelativeSearch(haystack, search);
                                    const matchesCategory = categoryFilter === '' || groupCategory === categoryFilter;
                                    const shouldShow = matchesSearch && matchesCategory;
                                    row.style.display = shouldShow ? '' : 'none';
                                    if (shouldShow) {
                                        visibleRows++;
                                    }
                                });
                                group.querySelectorAll('.shop-subcategory-row').forEach((subRow) => {
                                    const subKey = (subRow.getAttribute('data-subcategory') || '').toLowerCase();
                                    let hasVisibleLine = false;
                                    group.querySelectorAll('.shop-line-row[data-subcategory]').forEach((lineRow) => {
                                        if ((lineRow.getAttribute('data-subcategory') || '').toLowerCase() === subKey && lineRow.style.display !== 'none') {
                                            hasVisibleLine = true;
                                        }
                                    });
                                    subRow.style.display = hasVisibleLine ? '' : 'none';
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
                            const searchBar = editor.querySelector('.js-line-search');
                            if (searchBar instanceof HTMLInputElement && searchBar.offsetParent !== null && document.activeElement === document.body) {
                                searchBar.focus();
                                searchBar.select();
                            }
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
                                applyActionRowClass(row);
                                row.querySelectorAll('.js-live-field').forEach((field) => {
                                    const handler = () => {
                                        updateEditorTotals(editor);
                                        applyActionRowClass(row);
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
