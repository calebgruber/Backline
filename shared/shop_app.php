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

if (!function_exists('shop_sound_cable_is_candidate')) {
    function shop_sound_cable_is_candidate(string $itemName, string $categoryName): bool
    {
        $haystack = strtolower(trim($itemName . ' ' . $categoryName));
        return str_contains($haystack, 'cable');
    }
}

if (!function_exists('shop_parse_delimited_line')) {
    function shop_parse_delimited_line(string $line): array
    {
        if (str_contains($line, "\t")) {
            return str_getcsv($line, "\t");
        }
        if (str_contains($line, ';') && !str_contains($line, ',')) {
            return str_getcsv($line, ';');
        }
        return str_getcsv($line);
    }
}

if (!function_exists('shop_detect_delimiter')) {
    function shop_detect_delimiter(string $content): string
    {
        $firstLine = '';
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed !== '') {
                $firstLine = $trimmed;
                break;
            }
        }
        if ($firstLine === '') {
            return ',';
        }
        $tabCount = substr_count($firstLine, "\t");
        $commaCount = substr_count($firstLine, ',');
        $semiCount = substr_count($firstLine, ';');
        if ($tabCount >= $commaCount && $tabCount >= $semiCount && $tabCount > 0) {
            return "\t";
        }
        if ($semiCount > $commaCount && $semiCount > 0) {
            return ';';
        }
        return ',';
    }
}

if (!function_exists('shop_parse_delimited_rows')) {
    function shop_parse_delimited_rows(string $content): array
    {
        $rows = [];
        $delimiter = shop_detect_delimiter($content);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return $rows;
        }
        fwrite($stream, $content);
        rewind($stream);
        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = array_map(static fn ($v): string => (string) $v, $row);
        }
        fclose($stream);
        return $rows;
    }
}

if (!function_exists('shop_resolve_cable_import_text')) {
    function shop_resolve_cable_import_text(array $postData, array $fileData, ?string &$error = null): string
    {
        $error = null;
        $text = trim((string) ($postData['cable_import_text'] ?? ''));
        $uploadError = (int) ($fileData['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return $text;
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            $error = 'Could not read uploaded file. Try again.';
            return '';
        }
        $tmpPath = (string) ($fileData['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $error = 'Uploaded file is invalid.';
            return '';
        }
        $size = (int) ($fileData['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            $error = 'Upload must be between 1 byte and 5 MB.';
            return '';
        }
        $extension = strtolower(pathinfo((string) ($fileData['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== '' && !in_array($extension, ['csv', 'tsv', 'txt'], true)) {
            $error = 'Upload must be a .csv, .tsv, or .txt file.';
            return '';
        }
        $raw = @file_get_contents($tmpPath);
        if ($raw === false) {
            $error = 'Could not read uploaded file.';
            return '';
        }
        $rawString = (string) $raw;
        if (function_exists('mb_convert_encoding') && function_exists('mb_check_encoding')) {
            if (str_contains($rawString, "\0")) {
                $converted = @mb_convert_encoding($rawString, 'UTF-8', 'UTF-16,UTF-16LE,UTF-16BE');
                if (is_string($converted) && $converted !== '') {
                    $rawString = $converted;
                }
            } elseif (!mb_check_encoding($rawString, 'UTF-8')) {
                $converted = @mb_convert_encoding($rawString, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
                if (is_string($converted) && $converted !== '') {
                    $rawString = $converted;
                }
            }
        }
        $rawString = str_replace("\0", '', $rawString);
        $rawString = preg_replace('/^\xEF\xBB\xBF/', '', $rawString) ?? $rawString;
        return trim($rawString);
    }
}

if (!function_exists('shop_sound_cable_colors_have_hex_column')) {
    function shop_sound_cable_colors_have_hex_column(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }
        try {
            $stmt = db()->query("SHOW COLUMNS FROM sound_cable_colors LIKE 'hex_code'");
            $hasColumn = (bool) $stmt->fetch();
        } catch (Throwable) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('shop_sound_cable_colors_table_exists')) {
    function shop_sound_cable_colors_table_exists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            $stmt = db()->query("SHOW TABLES LIKE 'sound_cable_colors'");
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }
        return $exists;
    }
}

if (!function_exists('shop_sound_cables_columns')) {
    function shop_sound_cables_columns(): array
    {
        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }
        $columns = [];
        try {
            $rows = db()->query('SHOW COLUMNS FROM sound_cables')->fetchAll();
            foreach ($rows as $row) {
                $name = strtolower(trim((string) ($row['Field'] ?? '')));
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        } catch (Throwable) {
            $columns = [];
        }
        return $columns;
    }
}

if (!function_exists('shop_sound_cables_has_column')) {
    function shop_sound_cables_has_column(string $columnName): bool
    {
        $columns = shop_sound_cables_columns();
        return isset($columns[strtolower($columnName)]);
    }

    if (!function_exists('shop_hex_to_rgba')) {
        function shop_hex_to_rgba(string $hex, float $alpha = 0.14): ?string
        {
            $value = trim($hex);
            if (!preg_match('/^#([0-9a-fA-F]{6})$/', $value, $matches)) {
                return null;
            }
            $rgb = $matches[1];
            $r = hexdec(substr($rgb, 0, 2));
            $g = hexdec(substr($rgb, 2, 2));
            $b = hexdec(substr($rgb, 4, 2));
            $a = max(0.0, min(1.0, $alpha));
            return sprintf('rgba(%d, %d, %d, %.3f)', $r, $g, $b, $a);
        }
    }
}

if (!function_exists('shop_sync_sound_cables_from_revision')) {
    function shop_sync_sound_cables_from_revision(int $showId, int $orderId, int $revisionId): int
    {
        $hasSourceRevision = shop_sound_cables_has_column('source_revision_id');
        $hasAutoCreated = shop_sound_cables_has_column('auto_created');
        if ($hasSourceRevision && $hasAutoCreated) {
            $deleteStmt = db()->prepare('DELETE FROM sound_cables WHERE show_id = ? AND source_revision_id = ? AND auto_created = 1');
            $deleteStmt->execute([$showId, $revisionId]);
        }

        $lineStmt = db()->prepare('SELECT ol.id, ol.inventory_item_id, ol.qty, ol.spares, ol.line_note, ii.name AS item_name, ic.name AS category_name
            FROM order_lines ol
            JOIN inventory_items ii ON ii.id = ol.inventory_item_id
            LEFT JOIN inventory_categories ic ON ic.id = ii.category_id
            WHERE ol.revision_id = ?');
        $lineStmt->execute([$revisionId]);
        $rows = $lineStmt->fetchAll();

        $insertColumns = ['show_id', 'order_id', 'inventory_item_id', 'cable_type', 'cable_length'];
        if (shop_sound_cables_has_column('overall_label')) {
            $insertColumns[] = 'overall_label';
        }
        $insertColumns = [...$insertColumns, 'display_name', 'color', 'source_text', 'destination_text'];
        if (shop_sound_cables_has_column('adapter_text')) {
            $insertColumns[] = 'adapter_text';
        }
        if (shop_sound_cables_has_column('notes_text')) {
            $insertColumns[] = 'notes_text';
        } elseif (shop_sound_cables_has_column('description_text')) {
            $insertColumns[] = 'description_text';
        }
        if (shop_sound_cables_has_column('loom_name')) {
            $insertColumns[] = 'loom_name';
        }
        if (shop_sound_cables_has_column('loom_id_text')) {
            $insertColumns[] = 'loom_id_text';
        }
        if (shop_sound_cables_has_column('category_name')) {
            $insertColumns[] = 'category_name';
        }
        if (shop_sound_cables_has_column('source_revision_id')) {
            $insertColumns[] = 'source_revision_id';
        }
        if (shop_sound_cables_has_column('source_line_id')) {
            $insertColumns[] = 'source_line_id';
        }
        if (shop_sound_cables_has_column('source_line_index')) {
            $insertColumns[] = 'source_line_index';
        }
        if (shop_sound_cables_has_column('auto_created')) {
            $insertColumns[] = 'auto_created';
        }
        $insertColumns = [...$insertColumns, 'sort_order', 'created_at', 'updated_at'];
        $valueSql = implode(', ', array_fill(0, count($insertColumns) - 2, '?')) . ', NOW(), NOW()';
        $insertStmt = db()->prepare('INSERT INTO sound_cables (' . implode(', ', $insertColumns) . ') VALUES (' . $valueSql . ')');

        $created = 0;
        $sortSeed = 0;
        foreach ($rows as $row) {
            $itemName = trim((string) ($row['item_name'] ?? ''));
            $categoryName = trim((string) ($row['category_name'] ?? ''));
            if (!shop_sound_cable_is_candidate($itemName, $categoryName)) {
                continue;
            }

            $qty = max(0, (int) ($row['qty'] ?? 0));
            $spares = max(0, (int) ($row['spares'] ?? 0));
            $count = $qty + $spares;
            if ($count <= 0) {
                continue;
            }
            for ($i = 1; $i <= $count; $i++) {
                $overallLabel = $count > 1 ? ($itemName . ' #' . $i) : $itemName;
                $params = [
                    $showId,
                    $orderId,
                    (int) ($row['inventory_item_id'] ?? 0),
                    $itemName,
                    '',
                ];
                if (shop_sound_cables_has_column('overall_label')) {
                    $params[] = $overallLabel;
                }
                $params[] = $overallLabel;
                $params[] = null;
                $params[] = '';
                $params[] = '';
                if (shop_sound_cables_has_column('adapter_text')) {
                    $params[] = '';
                }
                if (shop_sound_cables_has_column('notes_text') || shop_sound_cables_has_column('description_text')) {
                    $params[] = trim((string) ($row['line_note'] ?? ''));
                }
                if (shop_sound_cables_has_column('loom_name')) {
                    $params[] = '';
                }
                if (shop_sound_cables_has_column('loom_id_text')) {
                    $params[] = '';
                }
                if (shop_sound_cables_has_column('category_name')) {
                    $params[] = $categoryName;
                }
                if (shop_sound_cables_has_column('source_revision_id')) {
                    $params[] = $revisionId;
                }
                if (shop_sound_cables_has_column('source_line_id')) {
                    $params[] = (int) ($row['id'] ?? 0);
                }
                if (shop_sound_cables_has_column('source_line_index')) {
                    $params[] = $i;
                }
                if (shop_sound_cables_has_column('auto_created')) {
                    $params[] = 1;
                }
                $params[] = $sortSeed++;
                $insertStmt->execute($params);
                $created++;
            }
        }

        return $created;
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
        $hasPaidExport = user_has_paid_export_access($user);
        $canManageSoundCables = $shopType === 'snd' ? user_can_manage_sound_cables($user) : false;
        $hasShowAccessTable = show_user_access_table_exists();
        $showListStmt = $isAdmin
            ? (function () use ($shopType) {
                $stmt = db()->prepare('SELECT id, show_name, show_scope, theatre_name, shop_name, lead_designer_name, lead_designer_email, lead_designer_phone, ald_name, ald_email, ald_phone, assistant_snd_designer_name, assistant_snd_designer_email, assistant_snd_designer_phone, production_contact_name, production_contact_email, production_contact_phone, shop_manager_name, shop_manager_email, shop_manager_phone, assistants_json, pull_date, return_date, strike_date, opening_date, closing_date, theatre_address, shop_address, show_image_path, owner_user_id FROM shows WHERE deleted_at IS NULL ORDER BY show_name');
                $stmt->execute();
                return $stmt;
            })()
            : (function () use ($user, $hasShowAccessTable) {
                if ($hasShowAccessTable) {
                    $stmt = db()->prepare('SELECT s.id, s.show_name, s.show_scope, s.theatre_name, s.shop_name, s.lead_designer_name, s.lead_designer_email, s.lead_designer_phone, s.ald_name, s.ald_email, s.ald_phone, s.assistant_snd_designer_name, s.assistant_snd_designer_email, s.assistant_snd_designer_phone, s.production_contact_name, s.production_contact_email, s.production_contact_phone, s.shop_manager_name, s.shop_manager_email, s.shop_manager_phone, s.assistants_json, s.pull_date, s.return_date, s.strike_date, s.opening_date, s.closing_date, s.theatre_address, s.shop_address, s.show_image_path, s.owner_user_id
                        FROM shows s
                        WHERE s.deleted_at IS NULL
                          AND (
                            s.owner_user_id = ?
                            OR EXISTS (
                                SELECT 1
                                FROM show_user_access sua
                                WHERE sua.show_id = s.id AND sua.user_id = ?
                            )
                          )
                        ORDER BY s.show_name');
                    $stmt->execute([(int) $user['id'], (int) $user['id']]);
                } else {
                    $stmt = db()->prepare('SELECT s.id, s.show_name, s.show_scope, s.theatre_name, s.shop_name, s.lead_designer_name, s.lead_designer_email, s.lead_designer_phone, s.ald_name, s.ald_email, s.ald_phone, s.assistant_snd_designer_name, s.assistant_snd_designer_email, s.assistant_snd_designer_phone, s.production_contact_name, s.production_contact_email, s.production_contact_phone, s.shop_manager_name, s.shop_manager_email, s.shop_manager_phone, s.assistants_json, s.pull_date, s.return_date, s.strike_date, s.opening_date, s.closing_date, s.theatre_address, s.shop_address, s.show_image_path, s.owner_user_id
                        FROM shows s
                        WHERE s.deleted_at IS NULL AND s.owner_user_id = ?
                        ORDER BY s.show_name');
                    $stmt->execute([(int) $user['id']]);
                }
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

        $showAccessCondition = $isAdmin
            ? ''
            : ($hasShowAccessTable
                ? ' AND (s.owner_user_id = ' . (int) $user['id'] . ' OR EXISTS (SELECT 1 FROM show_user_access sua WHERE sua.show_id = s.id AND sua.user_id = ' . (int) $user['id'] . '))'
                : ' AND s.owner_user_id = ' . (int) $user['id']);
        $allowedTabs = ['info', 'initial', 'revisions', 'paperwork'];
        if ($hasPaidExport) {
            $allowedTabs[] = 'exports';
        }
        if ($shopType === 'snd') {
            $allowedTabs[] = 'cables';
        }
        $currentTab = (string) ($_GET['tab'] ?? 'info');
        $hasReturnDateColumn = shop_has_order_line_return_date_column();
        $hasSubcategoryColumn = shop_has_inventory_subcategory_column();
        $selectedRevisionId = (int) ($_GET['revision'] ?? ($_POST['print_revision_id'] ?? 0));
        $selectedExportRevisionId = (int) ($_GET['export_revision'] ?? 0);
        if (!$hasPaidExport && $currentTab === 'exports') {
            flash_set('warning', 'You need an active subscription to export paperwork.');
            $currentTab = 'info';
        }
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

            $cableSpreadsheetRestrictedActions = ['import_cables', 'add_cable_row', 'delete_selected_cables'];
            if ($shopType === 'snd' && in_array($action, $cableSpreadsheetRestrictedActions, true) && !$canManageSoundCables) {
                flash_set('danger', 'You do not have permission to add/import/delete cable rows from the spreadsheet.');
                redirect($appPath . '?show=' . $selectedShowId . '&tab=cables');
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

            if ($shopType === 'snd' && $action === 'sync_cables_from_revision' && $selectedShowId > 0) {
                $revisionId = (int) post('sync_revision_id', '0');
                $orderId = (int) post('order_id', '0');
                if ($revisionId > 0 && $orderId > 0) {
                    try {
                        $created = shop_sync_sound_cables_from_revision($selectedShowId, $orderId, $revisionId);
                        flash_set('success', 'Cables synced from revision. Added: ' . $created);
                    } catch (Throwable) {
                        flash_set('danger', 'Could not sync cables from revision.');
                    }
                } else {
                    flash_set('warning', 'Select a revision to sync.');
                }
            }

            if ($shopType === 'snd' && $action === 'add_cable_row' && $selectedShowId > 0) {
                $orderId = (int) post('order_id', '0');
                $nextSort = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM sound_cables WHERE show_id = ' . (int) $selectedShowId)->fetchColumn();
                $insertColumns = ['show_id', 'order_id', 'inventory_item_id', 'cable_type', 'cable_length'];
                if (shop_sound_cables_has_column('overall_label')) {
                    $insertColumns[] = 'overall_label';
                }
                $insertColumns = [...$insertColumns, 'display_name', 'color', 'source_text', 'destination_text'];
                if (shop_sound_cables_has_column('adapter_text')) {
                    $insertColumns[] = 'adapter_text';
                }
                if (shop_sound_cables_has_column('notes_text')) {
                    $insertColumns[] = 'notes_text';
                } elseif (shop_sound_cables_has_column('description_text')) {
                    $insertColumns[] = 'description_text';
                }
                if (shop_sound_cables_has_column('loom_name')) {
                    $insertColumns[] = 'loom_name';
                }
                if (shop_sound_cables_has_column('loom_id_text')) {
                    $insertColumns[] = 'loom_id_text';
                }
                if (shop_sound_cables_has_column('category_name')) {
                    $insertColumns[] = 'category_name';
                }
                if (shop_sound_cables_has_column('source_revision_id')) {
                    $insertColumns[] = 'source_revision_id';
                }
                if (shop_sound_cables_has_column('source_line_id')) {
                    $insertColumns[] = 'source_line_id';
                }
                if (shop_sound_cables_has_column('source_line_index')) {
                    $insertColumns[] = 'source_line_index';
                }
                if (shop_sound_cables_has_column('auto_created')) {
                    $insertColumns[] = 'auto_created';
                }
                $insertColumns = [...$insertColumns, 'sort_order', 'created_at', 'updated_at'];
                $valueSql = implode(', ', array_fill(0, count($insertColumns) - 2, '?')) . ', NOW(), NOW()';
                $insertStmt = db()->prepare('INSERT INTO sound_cables (' . implode(', ', $insertColumns) . ') VALUES (' . $valueSql . ')');
                $params = [
                    $selectedShowId,
                    $orderId > 0 ? $orderId : null,
                    null,
                    '',
                    '',
                ];
                if (shop_sound_cables_has_column('overall_label')) {
                    $params[] = '';
                }
                $params[] = '';
                $params[] = '';
                $params[] = '';
                $params[] = '';
                if (shop_sound_cables_has_column('adapter_text')) {
                    $params[] = '';
                }
                if (shop_sound_cables_has_column('notes_text') || shop_sound_cables_has_column('description_text')) {
                    $params[] = '';
                }
                if (shop_sound_cables_has_column('loom_name')) {
                    $params[] = '';
                }
                if (shop_sound_cables_has_column('loom_id_text')) {
                    $params[] = '';
                }
                if (shop_sound_cables_has_column('category_name')) {
                    $params[] = '';
                }
                if (shop_sound_cables_has_column('source_revision_id')) {
                    $params[] = null;
                }
                if (shop_sound_cables_has_column('source_line_id')) {
                    $params[] = null;
                }
                if (shop_sound_cables_has_column('source_line_index')) {
                    $params[] = null;
                }
                if (shop_sound_cables_has_column('auto_created')) {
                    $params[] = 0;
                }
                $params[] = $nextSort;
                $insertStmt->execute($params);
                flash_set('success', 'Blank cable row added.');
            }

            if ($shopType === 'snd' && $action === 'delete_selected_cables' && $selectedShowId > 0) {
                $selectedCableIds = $_POST['selected_cable_ids'] ?? [];
                if (!is_array($selectedCableIds)) {
                    $selectedCableIds = [];
                }
                $selectedCableIds = array_values(array_filter(array_map('intval', $selectedCableIds), static fn ($v): bool => $v > 0));
                if (empty($selectedCableIds)) {
                    flash_set('warning', 'Select cable rows first.');
                } else {
                    $in = implode(',', array_fill(0, count($selectedCableIds), '?'));
                    $deleteStmt = db()->prepare('DELETE FROM sound_cables WHERE id IN (' . $in . ') AND show_id = ?');
                    $deleteStmt->execute([...$selectedCableIds, $selectedShowId]);
                    flash_set('success', 'Selected cable rows deleted.');
                }
            }

            if ($shopType === 'snd' && $action === 'import_cables' && $selectedShowId > 0) {
                $importError = null;
                $importRaw = shop_resolve_cable_import_text($_POST, $_FILES['cable_import_file'] ?? [], $importError);
                $orderId = (int) post('order_id', '0');
                $required = ['cable_type', 'length', 'overall_label', 'from', 'to', 'adapter', 'notes', 'loom', 'loom_id', 'color', 'category'];
                $inserted = 0;
                if ($importError !== null) {
                    flash_set('danger', $importError);
                } elseif ($importRaw !== '') {
                    $rows = shop_parse_delimited_rows($importRaw);
                    if (count($rows) > 1) {
                        $header = array_map(static fn ($v): string => strtolower(trim((string) $v)), $rows[0]);
                        if ($header === $required) {
                            $insertColumns = ['show_id', 'order_id', 'inventory_item_id', 'cable_type', 'cable_length'];
                            if (shop_sound_cables_has_column('overall_label')) {
                                $insertColumns[] = 'overall_label';
                            }
                            $insertColumns = [...$insertColumns, 'display_name', 'color', 'source_text', 'destination_text'];
                            if (shop_sound_cables_has_column('adapter_text')) {
                                $insertColumns[] = 'adapter_text';
                            }
                            if (shop_sound_cables_has_column('notes_text')) {
                                $insertColumns[] = 'notes_text';
                            } elseif (shop_sound_cables_has_column('description_text')) {
                                $insertColumns[] = 'description_text';
                            }
                            if (shop_sound_cables_has_column('loom_name')) {
                                $insertColumns[] = 'loom_name';
                            }
                            if (shop_sound_cables_has_column('loom_id_text')) {
                                $insertColumns[] = 'loom_id_text';
                            }
                            if (shop_sound_cables_has_column('category_name')) {
                                $insertColumns[] = 'category_name';
                            }
                            if (shop_sound_cables_has_column('source_revision_id')) {
                                $insertColumns[] = 'source_revision_id';
                            }
                            if (shop_sound_cables_has_column('source_line_id')) {
                                $insertColumns[] = 'source_line_id';
                            }
                            if (shop_sound_cables_has_column('source_line_index')) {
                                $insertColumns[] = 'source_line_index';
                            }
                            if (shop_sound_cables_has_column('auto_created')) {
                                $insertColumns[] = 'auto_created';
                            }
                            $insertColumns = [...$insertColumns, 'sort_order', 'created_at', 'updated_at'];
                            $valueSql = implode(', ', array_fill(0, count($insertColumns) - 2, '?')) . ', NOW(), NOW()';
                            $insertStmt = db()->prepare('INSERT INTO sound_cables (' . implode(', ', $insertColumns) . ') VALUES (' . $valueSql . ')');
                            $sortSeed = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM sound_cables WHERE show_id = ' . (int) $selectedShowId)->fetchColumn();
                            foreach (array_slice($rows, 1) as $cols) {
                                if (count($cols) < 11) {
                                    continue;
                                }
                                $cols = array_map(static fn ($v): string => trim((string) $v), $cols);
                                $params = [
                                    $selectedShowId,
                                    $orderId > 0 ? $orderId : null,
                                    null,
                                    $cols[0],
                                    $cols[1],
                                ];
                                if (shop_sound_cables_has_column('overall_label')) {
                                    $params[] = $cols[2];
                                }
                                $params[] = $cols[2] !== '' ? $cols[2] : $cols[0];
                                $params[] = $cols[9];
                                $params[] = $cols[3];
                                $params[] = $cols[4];
                                if (shop_sound_cables_has_column('adapter_text')) {
                                    $params[] = $cols[5];
                                }
                                if (shop_sound_cables_has_column('notes_text') || shop_sound_cables_has_column('description_text')) {
                                    $params[] = $cols[6];
                                }
                                if (shop_sound_cables_has_column('loom_name')) {
                                    $params[] = $cols[7];
                                }
                                if (shop_sound_cables_has_column('loom_id_text')) {
                                    $params[] = $cols[8];
                                }
                                if (shop_sound_cables_has_column('category_name')) {
                                    $params[] = $cols[10];
                                }
                                if (shop_sound_cables_has_column('source_revision_id')) {
                                    $params[] = null;
                                }
                                if (shop_sound_cables_has_column('source_line_id')) {
                                    $params[] = null;
                                }
                                if (shop_sound_cables_has_column('source_line_index')) {
                                    $params[] = null;
                                }
                                if (shop_sound_cables_has_column('auto_created')) {
                                    $params[] = 0;
                                }
                                $params[] = ++$sortSeed;
                                $insertStmt->execute($params);
                                $inserted++;
                            }
                            flash_set('success', 'Cable import complete. Added: ' . $inserted);
                        } else {
                            flash_set('danger', 'Import headers must exactly be: cable_type, Length, overall_label, From, To, Adapter, Notes, Loom, loom_id, Color, category');
                        }
                    } else {
                        flash_set('warning', 'Include header plus at least one cable row.');
                    }
                } else {
                    flash_set('warning', 'Paste cable rows or upload a CSV/TSV file.');
                }
            }

            if ($shopType === 'snd' && $action === 'save_cables' && $selectedShowId > 0) {
                $isAjaxSave = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
                $cableIds = $_POST['cable_id'] ?? [];
                $types = $_POST['cable_type'] ?? [];
                $lengths = $_POST['cable_length'] ?? [];
                $overallLabels = $_POST['overall_label'] ?? [];
                $froms = $_POST['source_text'] ?? [];
                $tos = $_POST['destination_text'] ?? [];
                $adapters = $_POST['adapter_text'] ?? [];
                $notes = $_POST['notes_text'] ?? [];
                $looms = $_POST['loom_name'] ?? [];
                $loomIds = $_POST['loom_id_text'] ?? [];
                $colors = $_POST['color'] ?? [];
                $categories = $_POST['category_name'] ?? [];
                $cableRowsJson = trim((string) post('cable_rows_json', ''));
                if ($cableRowsJson !== '') {
                    $decodedRows = json_decode($cableRowsJson, true);
                    if (is_array($decodedRows)) {
                        $cableIds = [];
                        $types = [];
                        $lengths = [];
                        $overallLabels = [];
                        $froms = [];
                        $tos = [];
                        $adapters = [];
                        $notes = [];
                        $looms = [];
                        $loomIds = [];
                        $colors = [];
                        $categories = [];
                        foreach ($decodedRows as $row) {
                            if (!is_array($row)) {
                                continue;
                            }
                            $cableIds[] = (int) ($row['id'] ?? 0);
                            $types[] = (string) ($row['cable_type'] ?? '');
                            $lengths[] = (string) ($row['cable_length'] ?? '');
                            $overallLabels[] = (string) ($row['overall_label'] ?? '');
                            $froms[] = (string) ($row['source_text'] ?? '');
                            $tos[] = (string) ($row['destination_text'] ?? '');
                            $adapters[] = (string) ($row['adapter_text'] ?? '');
                            $notes[] = (string) ($row['notes_text'] ?? '');
                            $looms[] = (string) ($row['loom_name'] ?? '');
                            $loomIds[] = (string) ($row['loom_id_text'] ?? '');
                            $colors[] = (string) ($row['color'] ?? '');
                            $categories[] = (string) ($row['category_name'] ?? '');
                        }
                    }
                }
                $setSql = ['cable_type = ?', 'cable_length = ?'];
                if (shop_sound_cables_has_column('overall_label')) {
                    $setSql[] = 'overall_label = ?';
                }
                $setSql[] = 'display_name = ?';
                $setSql[] = 'source_text = ?';
                $setSql[] = 'destination_text = ?';
                if (shop_sound_cables_has_column('adapter_text')) {
                    $setSql[] = 'adapter_text = ?';
                }
                if (shop_sound_cables_has_column('notes_text')) {
                    $setSql[] = 'notes_text = ?';
                } elseif (shop_sound_cables_has_column('description_text')) {
                    $setSql[] = 'description_text = ?';
                }
                if (shop_sound_cables_has_column('loom_name')) {
                    $setSql[] = 'loom_name = ?';
                }
                if (shop_sound_cables_has_column('loom_id_text')) {
                    $setSql[] = 'loom_id_text = ?';
                }
                $setSql[] = 'color = ?';
                if (shop_sound_cables_has_column('category_name')) {
                    $setSql[] = 'category_name = ?';
                }
                if (shop_sound_cables_has_column('bundle_key')) {
                    $setSql[] = 'bundle_key = ?';
                }
                $setSql[] = 'updated_at = NOW()';
                $updateStmt = db()->prepare('UPDATE sound_cables SET ' . implode(', ', $setSql) . ' WHERE id = ? AND show_id = ?');
                foreach ($cableIds as $idx => $cableId) {
                    $cableId = (int) $cableId;
                    if ($cableId <= 0) {
                        continue;
                    }
                    $loomName = trim((string) ($looms[$idx] ?? ''));
                    $params = [
                        trim((string) ($types[$idx] ?? '')),
                        trim((string) ($lengths[$idx] ?? '')),
                    ];
                    if (shop_sound_cables_has_column('overall_label')) {
                        $params[] = trim((string) ($overallLabels[$idx] ?? ''));
                    }
                    $params[] = trim((string) ($overallLabels[$idx] ?? ''));
                    $params[] = trim((string) ($froms[$idx] ?? ''));
                    $params[] = trim((string) ($tos[$idx] ?? ''));
                    if (shop_sound_cables_has_column('adapter_text')) {
                        $params[] = trim((string) ($adapters[$idx] ?? ''));
                    }
                    if (shop_sound_cables_has_column('notes_text') || shop_sound_cables_has_column('description_text')) {
                        $params[] = trim((string) ($notes[$idx] ?? ''));
                    }
                    if (shop_sound_cables_has_column('loom_name')) {
                        $params[] = $loomName;
                    }
                    if (shop_sound_cables_has_column('loom_id_text')) {
                        $params[] = trim((string) ($loomIds[$idx] ?? ''));
                    }
                    $params[] = trim((string) ($colors[$idx] ?? ''));
                    if (shop_sound_cables_has_column('category_name')) {
                        $params[] = trim((string) ($categories[$idx] ?? ''));
                    }
                    if (shop_sound_cables_has_column('bundle_key')) {
                        $params[] = $loomName !== '' ? $loomName : null;
                    }
                    $params[] = $cableId;
                    $params[] = $selectedShowId;
                    $updateStmt->execute($params);
                }
                if ($isAjaxSave) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true]);
                    exit;
                }
                flash_set('success', 'Cable rows saved.');
            }

            if ($shopType === 'snd' && $action === 'create_loom' && $selectedShowId > 0) {
                $selectedCableIds = $_POST['selected_cable_ids'] ?? [];
                $loomName = trim((string) post('new_loom_name', ''));
                $loomIdText = trim((string) post('new_loom_id_text', ''));
                if (!shop_sound_cables_has_column('loom_name')) {
                    flash_set('warning', 'Loom fields are unavailable until sound cable migrations are applied.');
                } elseif ($loomName === '' || empty($selectedCableIds) || !is_array($selectedCableIds)) {
                    flash_set('warning', 'Enter loom name and select cable rows first.');
                } else {
                    $selectedCableIds = array_values(array_filter(array_map('intval', $selectedCableIds), static fn ($v): bool => $v > 0));
                    if (!empty($selectedCableIds)) {
                        $in = implode(',', array_fill(0, count($selectedCableIds), '?'));
                        $params = [...$selectedCableIds, $selectedShowId];
                        $setSql = ['loom_name = ?'];
                        $execParams = [$loomName];
                        if (shop_sound_cables_has_column('loom_id_text')) {
                            $setSql[] = 'loom_id_text = ?';
                            $execParams[] = $loomIdText;
                        }
                        if (shop_sound_cables_has_column('bundle_key')) {
                            $setSql[] = 'bundle_key = ?';
                            $execParams[] = $loomName;
                        }
                        $setSql[] = 'updated_at = NOW()';
                        $loomStmt = db()->prepare('UPDATE sound_cables SET ' . implode(', ', $setSql) . ' WHERE id IN (' . $in . ') AND show_id = ?');
                        $loomStmt->execute([...$execParams, ...$params]);
                        flash_set('success', 'Loom created/assigned.');
                    } else {
                        flash_set('warning', 'No cable rows selected.');
                    }
                }
            }

            if ($shopType === 'snd' && $action === 'rename_loom' && $selectedShowId > 0) {
                $oldName = trim((string) post('old_loom_name', ''));
                $newName = trim((string) post('new_loom_name', ''));
                if ($oldName !== '' && $newName !== '' && shop_sound_cables_has_column('loom_name')) {
                    $setSql = ['loom_name = ?'];
                    $params = [$newName];
                    if (shop_sound_cables_has_column('bundle_key')) {
                        $setSql[] = 'bundle_key = ?';
                        $params[] = $newName;
                    }
                    $setSql[] = 'updated_at = NOW()';
                    $renameStmt = db()->prepare('UPDATE sound_cables SET ' . implode(', ', $setSql) . ' WHERE show_id = ? AND loom_name = ?');
                    $renameStmt->execute([...$params, $selectedShowId, $oldName]);
                    flash_set('success', 'Loom renamed.');
                }
            }

            if ($shopType === 'snd' && $action === 'delete_loom' && $selectedShowId > 0) {
                $loomName = trim((string) post('loom_name', ''));
                if ($loomName !== '' && shop_sound_cables_has_column('loom_name')) {
                    $setSql = ['loom_name = ""'];
                    if (shop_sound_cables_has_column('loom_id_text')) {
                        $setSql[] = 'loom_id_text = ""';
                    }
                    if (shop_sound_cables_has_column('bundle_key')) {
                        $setSql[] = 'bundle_key = NULL';
                    }
                    $setSql[] = 'updated_at = NOW()';
                    $deleteStmt = db()->prepare('UPDATE sound_cables SET ' . implode(', ', $setSql) . ' WHERE show_id = ? AND loom_name = ?');
                    $deleteStmt->execute([$selectedShowId, $loomName]);
                    flash_set('success', 'Loom removed from all rows.');
                }
            }

            if ($shopType === 'snd' && $action === 'add_cable_color') {
                if (!shop_sound_cable_colors_table_exists()) {
                    flash_set('warning', 'Color library is unavailable until sound cable migrations are applied.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
                }
                $colorName = trim((string) post('color_name', ''));
                $colorHex = trim((string) post('color_hex', '#1f9ad1'));
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorHex)) {
                    $colorHex = '#1f9ad1';
                }
                if ($colorName !== '') {
                    $sortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM sound_cable_colors')->fetchColumn();
                    if (shop_sound_cable_colors_have_hex_column()) {
                        $insColor = db()->prepare('INSERT IGNORE INTO sound_cable_colors (name, hex_code, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
                        $insColor->execute([$colorName, $colorHex, $sortOrder]);
                    } else {
                        $insColor = db()->prepare('INSERT IGNORE INTO sound_cable_colors (name, sort_order, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
                        $insColor->execute([$colorName, $sortOrder]);
                    }
                    flash_set('success', 'Color added.');
                }
            }

            if ($shopType === 'snd' && $action === 'rename_cable_color') {
                if (!shop_sound_cable_colors_table_exists()) {
                    flash_set('warning', 'Color library is unavailable until sound cable migrations are applied.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
                }
                $colorId = (int) post('color_id', '0');
                $colorName = trim((string) post('color_name', ''));
                $colorHex = trim((string) post('color_hex', '#1f9ad1'));
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorHex)) {
                    $colorHex = '#1f9ad1';
                }
                if ($colorId > 0 && $colorName !== '') {
                    if (shop_sound_cable_colors_have_hex_column()) {
                        $updColor = db()->prepare('UPDATE sound_cable_colors SET name = ?, hex_code = ?, updated_at = NOW() WHERE id = ?');
                        $updColor->execute([$colorName, $colorHex, $colorId]);
                    } else {
                        $updColor = db()->prepare('UPDATE sound_cable_colors SET name = ?, updated_at = NOW() WHERE id = ?');
                        $updColor->execute([$colorName, $colorId]);
                    }
                    flash_set('success', 'Color updated.');
                }
            }

            if ($shopType === 'snd' && $action === 'delete_cable_color') {
                if (!shop_sound_cable_colors_table_exists()) {
                    flash_set('warning', 'Color library is unavailable until sound cable migrations are applied.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
                }
                $colorId = (int) post('color_id', '0');
                if ($colorId > 0) {
                    $findColor = db()->prepare('SELECT name FROM sound_cable_colors WHERE id = ?');
                    $findColor->execute([$colorId]);
                    $colorName = (string) ($findColor->fetchColumn() ?: '');
                    if ($colorName !== '') {
                        $delColor = db()->prepare('DELETE FROM sound_cable_colors WHERE id = ?');
                        $delColor->execute([$colorId]);
                        $clearColor = db()->prepare('UPDATE sound_cables SET color = NULL WHERE show_id = ? AND color = ?');
                        $clearColor->execute([$selectedShowId, $colorName]);
                    }
                    flash_set('success', 'Color deleted.');
                }
            }

            if ($action === 'invite_user' && $selectedShowId > 0 && $selectedShow) {
                if (!$hasShowAccessTable) {
                    flash_set('warning', 'Run pending migrations before inviting users to shows.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=info');
                }
                $isShowOwner = (int) ($selectedShow['owner_user_id'] ?? 0) === (int) $user['id'];
                if (!$isAdmin && !$isShowOwner) {
                    flash_set('danger', 'Only the show owner or admins can invite users to this show.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=info');
                }
                $inviteUserId = (int) post('invite_user_id', '0');
                if ($inviteUserId <= 0) {
                    flash_set('warning', 'Select a user to invite.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=info');
                }
                $userCheck = db()->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
                $userCheck->execute([$inviteUserId]);
                if (!$userCheck->fetchColumn()) {
                    flash_set('warning', 'That user no longer exists.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=info');
                }
                if ($inviteUserId === (int) ($selectedShow['owner_user_id'] ?? 0)) {
                    flash_set('info', 'Show owners already have access.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=info');
                }
                $insertInvite = db()->prepare('INSERT IGNORE INTO show_user_access (show_id, user_id, invited_by_user_id, created_at) VALUES (?, ?, ?, NOW())');
                $insertInvite->execute([$selectedShowId, $inviteUserId, (int) $user['id']]);
                if ($insertInvite->rowCount() > 0) {
                    flash_set('success', 'User invited to this show.');
                } else {
                    flash_set('info', 'User already has access to this show.');
                }
            }

            if ($action === 'export_latest' && $selectedShowId > 0) {
                if (!$hasPaidExport) {
                    flash_set('warning', 'You need an active subscription to export paperwork.');
                    redirect($appPath . '?show=' . $selectedShowId . '&tab=info');
                }
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
            if (($action === 'print_labels' || $action === 'print_cable_labels') && $selectedShowId > 0 && $shopType === 'snd') {
                $printRevisionId = (int) post('print_revision_id', (string) $selectedRevisionId);
                redirect('/shared/sound_cable_labels.php?show_id=' . (int) $selectedShowId . '&revision_id=' . $printRevisionId);
            }

            redirect($appPath . '?show=' . $selectedShowId . '&tab=' . urlencode($postedTab));
        }

        $order = null;
        $revisions = [];
        $initialRevision = null;
        $revisionEntries = [];
        $exportableRevisions = [];
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
        $inviteSearchTerm = trim((string) ($_GET['invite_q'] ?? ''));
        $inviteCandidates = [];
        $showAccessUsers = [];
        $canManageShowAccess = false;
        $soundCables = [];
        $soundCableColors = [];
        $soundCableLoomOptions = [];

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
        if ($selectedShowId > 0 && $shopType === 'snd') {
            if ($currentTab === 'cables' && $order && $selectedRevisionId > 0) {
                try {
                    shop_sync_sound_cables_from_revision($selectedShowId, (int) ($order['id'] ?? 0), $selectedRevisionId);
                } catch (Throwable) {
                    // Best-effort auto-sync.
                }
            }
            $colorRows = [];
            if (shop_sound_cable_colors_table_exists()) {
                if (shop_sound_cable_colors_have_hex_column()) {
                    $colorRows = db()->query("SELECT id, name, COALESCE(hex_code, '#1f9ad1') AS hex_code FROM sound_cable_colors ORDER BY sort_order, name")->fetchAll();
                } else {
                    $colorRows = db()->query("SELECT id, name, '#1f9ad1' AS hex_code FROM sound_cable_colors ORDER BY sort_order, name")->fetchAll();
                }
                if (!$colorRows) {
                    if (shop_sound_cable_colors_have_hex_column()) {
                        $seedColorStmt = db()->prepare('INSERT IGNORE INTO sound_cable_colors (name, hex_code, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
                        $seedColors = [
                            ['Black', '#111111'],
                            ['Red', '#e53935'],
                            ['Blue', '#1e88e5'],
                            ['Green', '#43a047'],
                            ['Yellow', '#fdd835'],
                            ['White', '#ffffff'],
                            ['Orange', '#fb8c00'],
                            ['Purple', '#8e24aa'],
                            ['Gray', '#9e9e9e'],
                        ];
                    } else {
                        $seedColorStmt = db()->prepare('INSERT IGNORE INTO sound_cable_colors (name, sort_order, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
                        $seedColors = [
                            ['Black', '#1f9ad1'],
                            ['Red', '#1f9ad1'],
                            ['Blue', '#1f9ad1'],
                            ['Green', '#1f9ad1'],
                            ['Yellow', '#1f9ad1'],
                            ['White', '#1f9ad1'],
                            ['Orange', '#1f9ad1'],
                            ['Purple', '#1f9ad1'],
                            ['Gray', '#1f9ad1'],
                        ];
                    }
                    $seedSort = 10;
                    foreach ($seedColors as $seedColor) {
                        if (shop_sound_cable_colors_have_hex_column()) {
                            $seedColorStmt->execute([$seedColor[0], $seedColor[1], $seedSort]);
                        } else {
                            $seedColorStmt->execute([$seedColor[0], $seedSort]);
                        }
                        $seedSort += 10;
                    }
                    if (shop_sound_cable_colors_have_hex_column()) {
                        $colorRows = db()->query("SELECT id, name, COALESCE(hex_code, '#1f9ad1') AS hex_code FROM sound_cable_colors ORDER BY sort_order, name")->fetchAll();
                    } else {
                        $colorRows = db()->query("SELECT id, name, '#1f9ad1' AS hex_code FROM sound_cable_colors ORDER BY sort_order, name")->fetchAll();
                    }
                }
            }
            $soundCableColors = $colorRows;
            $cableStmt = db()->prepare('SELECT * FROM sound_cables WHERE show_id = ? ORDER BY sort_order, id');
            $cableStmt->execute([$selectedShowId]);
            $soundCables = $cableStmt->fetchAll();
            foreach ($soundCables as $cableRow) {
                $loomName = trim((string) ($cableRow['loom_name'] ?? ''));
                if ($loomName !== '') {
                    $soundCableLoomOptions[$loomName] = $loomName;
                }
            }
            ksort($soundCableLoomOptions);
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
            if ($selectedShow) {
                $canManageShowAccess = $isAdmin || ((int) ($selectedShow['owner_user_id'] ?? 0) === (int) $user['id']);
                if ($hasShowAccessTable) {
                    $accessStmt = db()->prepare('SELECT u.id, u.name, u.email, CASE WHEN u.id = s.owner_user_id THEN 1 ELSE 0 END AS is_owner
                        FROM users u
                        JOIN shows s ON s.id = ?
                        LEFT JOIN show_user_access sua ON sua.show_id = s.id AND sua.user_id = u.id
                        WHERE u.deleted_at IS NULL
                          AND (u.id = s.owner_user_id OR sua.user_id IS NOT NULL)
                        ORDER BY is_owner DESC, u.name ASC, u.email ASC');
                    $accessStmt->execute([$selectedShowId]);
                    $showAccessUsers = $accessStmt->fetchAll();

                    if ($canManageShowAccess && $currentTab === 'info' && $inviteSearchTerm !== '') {
                        $like = '%' . $inviteSearchTerm . '%';
                        $candidateStmt = db()->prepare('SELECT u.id, u.name, u.email
                            FROM users u
                            LEFT JOIN show_user_access sua ON sua.show_id = ? AND sua.user_id = u.id
                            WHERE u.deleted_at IS NULL
                              AND (u.name LIKE ? OR u.email LIKE ?)
                              AND u.id <> ?
                              AND sua.user_id IS NULL
                            ORDER BY u.name ASC, u.email ASC
                            LIMIT 20');
                        $candidateStmt->execute([$selectedShowId, $like, $like, (int) ($selectedShow['owner_user_id'] ?? 0)]);
                        $inviteCandidates = $candidateStmt->fetchAll();
                    }
                } else {
                    $ownerRowStmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
                    $ownerRowStmt->execute([(int) ($selectedShow['owner_user_id'] ?? 0)]);
                    $ownerRow = $ownerRowStmt->fetch();
                    if ($ownerRow) {
                        $showAccessUsers[] = [
                            'id' => (int) ($ownerRow['id'] ?? 0),
                            'name' => (string) ($ownerRow['name'] ?? ''),
                            'email' => (string) ($ownerRow['email'] ?? ''),
                            'is_owner' => 1,
                        ];
                    }
                }
            }
        }

        render_page($pageTitle, function () use ($shows, $selectedShowId, $selectedShow, $order, $revisions, $initialRevision, $revisionEntries, $exportableRevisions, $selectedRevisionId, $selectedExportRevisionId, $linesByCategory, $shopType, $heading, $scaffoldCopy, $currentTab, $showFirstNav, $appPath, $hasSubcategoryColumn, $paperworkSettings, $paperworkLayout, $canManageShowAccess, $showAccessUsers, $inviteSearchTerm, $inviteCandidates, $hasShowAccessTable, $soundCables, $soundCableColors, $soundCableLoomOptions): void {
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
                                <?php if ($hasPaidExport): ?>
                                    <a class="btn btn-outline-primary btn-sm" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=exports">Exports</a>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Exports (Paid role required)</button>
                                <?php endif; ?>
                                <?php if ($shopType === 'snd'): ?>
                                    <form method="post" class="d-inline-block">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="print_cable_labels">
                                        <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                        <input type="hidden" name="print_revision_id" value="<?= (int) $selectedRevisionId ?>">
                                        <input type="hidden" name="current_tab" value="<?= e($currentTab) ?>">
                                        <button class="btn btn-outline-primary btn-sm" type="submit">Print Labels</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <ul class="nav nav-tabs mb-3">
                        <?php
                        $tabMap = ['info' => 'Show Information', 'initial' => 'Initial Order', 'revisions' => 'Revisions', 'paperwork' => 'Paperwork Settings'];
                        if ($hasPaidExport) {
                            $tabMap['exports'] = 'Exports';
                        }
                        if ($shopType === 'snd') {
                            $tabMap['cables'] = 'Cables';
                        }
                        ?>
                        <?php foreach ($tabMap as $tabKey => $tabLabel): ?>
                            <li class="nav-item"><a class="nav-link <?= $currentTab === $tabKey ? 'active' : '' ?>" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=<?= e($tabKey) ?>"><?= e($tabLabel) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!$hasPaidExport): ?>
                        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                            <span class="icon text-warning" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-lock" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <rect x="5" y="11" width="14" height="10" rx="2"></rect>
                                    <circle cx="12" cy="16" r="1"></circle>
                                    <path d="M8 11v-3a4 4 0 0 1 8 0v3"></path>
                                </svg>
                            </span>
                            <div>
                                <div class="fw-semibold">Exports are disabled.</div>
                                <div class="text-secondary">You need an active subscription to export paperwork.</div>
                            </div>
                        </div>
                    <?php endif; ?>
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
                                $productionName = trim((string) (($selectedShow['production_contact_name'] ?? '') ?: ($selectedShow['shop_manager_name'] ?? '')));
                                $productionContact = implode(' · ', array_values(array_filter([
                                    trim((string) (($selectedShow['production_contact_email'] ?? '') ?: ($selectedShow['shop_manager_email'] ?? ''))),
                                    trim((string) (($selectedShow['production_contact_phone'] ?? '') ?: ($selectedShow['shop_manager_phone'] ?? ''))),
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
                                <div class="col-md-6"><div class="small text-secondary"><?= e($shopType === 'snd' ? 'Production Audio' : 'Production Electrician') ?></div><div><?= e($displayOrDash($productionName)) ?></div></div>
                                <div class="col-md-6"><div class="small text-secondary"><?= e(($shopType === 'snd' ? 'Production Audio' : 'Production Electrician') . ' Contact') ?></div><div><?= e($productionContact !== '' ? $productionContact : '—') ?></div></div>
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
                    <div class="card mt-3">
                        <div class="card-header"><h3 class="card-title mb-0">Show Access</h3></div>
                        <div class="card-body">
                            <?php if ($canManageShowAccess): ?>
                                <?php if ($hasShowAccessTable): ?>
                                    <form method="get" class="row g-2 align-items-end mb-3">
                                        <input type="hidden" name="show" value="<?= (int) $selectedShowId ?>">
                                        <input type="hidden" name="tab" value="info">
                                        <div class="col-md-8">
                                            <label class="form-label">Search user by name or email</label>
                                            <input class="form-control" type="search" name="invite_q" value="<?= e($inviteSearchTerm) ?>" placeholder="Start typing a name...">
                                        </div>
                                        <div class="col-md-4 d-grid">
                                            <button class="btn btn-outline-primary" type="submit">Search</button>
                                        </div>
                                    </form>
                                    <?php if ($inviteSearchTerm !== ''): ?>
                                        <div class="list-group mb-3">
                                            <?php if (!$inviteCandidates): ?>
                                                <div class="list-group-item text-secondary">No users found.</div>
                                            <?php else: ?>
                                                <?php foreach ($inviteCandidates as $candidate): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                                        <div>
                                                            <div class="fw-semibold"><?= e((string) ($candidate['name'] ?? '')) ?></div>
                                                            <div class="small text-secondary"><?= e((string) ($candidate['email'] ?? '')) ?></div>
                                                        </div>
                                                        <form method="post" class="m-0">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="action" value="invite_user">
                                                            <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                                            <input type="hidden" name="current_tab" value="info">
                                                            <input type="hidden" name="invite_user_id" value="<?= (int) $candidate['id'] ?>">
                                                            <button class="btn btn-primary btn-sm" type="submit">Invite</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-3">Run pending migrations to enable show invites.</div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-vcenter">
                                    <thead><tr><th>User</th><th>Email</th><th>Access</th></tr></thead>
                                    <tbody>
                                    <?php if (!$showAccessUsers): ?>
                                        <tr><td colspan="3" class="text-secondary">No users assigned yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($showAccessUsers as $accessUser): ?>
                                            <tr>
                                                <td><?= e((string) ($accessUser['name'] ?? '')) ?></td>
                                                <td><?= e((string) ($accessUser['email'] ?? '')) ?></td>
                                                <td><?= (int) ($accessUser['is_owner'] ?? 0) === 1 ? 'Owner' : 'Invited' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
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
                                    <?php if ($hasPaidExport): ?>
                                        <a class="btn btn-outline-primary" href="<?= e($appPath) ?>?show=<?= (int) $selectedShowId ?>&tab=exports&export_revision=<?= (int) $selectedExportRevisionId ?>">Go to Exports</a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary" type="button" disabled>Go to Exports (Paid role required)</button>
                                    <?php endif; ?>
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
                <?php elseif ($selectedShow && $currentTab === 'cables' && $shopType === 'snd'): ?>
                    <div class="sound-cables-workspace">
                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title mb-0">Sound Cables Spreadsheet</h3></div>
                        <div class="card-body">
                            <div class="alert alert-info mb-3">
                                <strong>How this works:</strong> select rows to bulk edit, then autosave keeps changes. Import/Add/Delete are available only to users with Spreadsheet Editor role.
                            </div>
                            <?php
                            $soundCableColorMap = [];
                            $soundCableLoomMembers = [];
                            foreach ($soundCableColors as $colorRow) {
                                $colorNameKey = strtolower(trim((string) ($colorRow['name'] ?? '')));
                                if ($colorNameKey === '') {
                                    continue;
                                }
                                $soundCableColorMap[$colorNameKey] = [
                                    'name' => (string) ($colorRow['name'] ?? ''),
                                    'hex_code' => (string) ($colorRow['hex_code'] ?? '#1f9ad1'),
                                ];
                            }
                            foreach ($soundCables as $cableRow) {
                                $loomNameValue = trim((string) ($cableRow['loom_name'] ?? ''));
                                if ($loomNameValue === '') {
                                    continue;
                                }
                                $soundCableLoomMembers[$loomNameValue][] = trim((string) (($cableRow['overall_label'] ?? '') ?: ($cableRow['display_name'] ?? '') ?: ($cableRow['cable_type'] ?? '')));
                            }
                            ?>
                            <div class="row g-2 align-items-end mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Sort Rows By</label>
                                    <select class="form-select" id="cable-sort-select">
                                        <option value="category_name">category</option>
                                        <option value="cable_type">cable_type</option>
                                        <option value="cable_length">Length</option>
                                        <option value="overall_label">overall_label</option>
                                        <option value="source_text">From</option>
                                        <option value="destination_text">To</option>
                                        <option value="color">Color</option>
                                        <option value="loom_name">Loom</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="search" class="form-control" id="cable-search-input" placeholder="Search cables...">
                                </div>
                                <div class="col-md-4 d-flex justify-content-md-end gap-2">
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cable-looms-modal">Edit Looms</button>
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cable-colors-modal">Edit Colors</button>
                                    <form method="post">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="print_cable_labels">
                                        <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                        <input type="hidden" name="print_revision_id" value="<?= (int) $selectedRevisionId ?>">
                                        <input type="hidden" name="current_tab" value="cables">
                                        <button class="btn btn-primary" type="submit">Print Labels (Avery 5167)</button>
                                    </form>
                                </div>
                            </div>
                            <form method="post" class="mb-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="sync_cables_from_revision">
                                <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                <input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                                <input type="hidden" name="current_tab" value="cables">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label">Sync from revision</label>
                                        <select class="form-select" name="sync_revision_id">
                                            <?php foreach ($revisions as $rev): ?>
                                                <option value="<?= (int) ($rev['id'] ?? 0) ?>" <?= (int) ($rev['id'] ?? 0) === (int) $selectedRevisionId ? 'selected' : '' ?>>
                                                    <?= e('Revision ' . (string) ($rev['revision_label'] ?? '')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-outline-primary w-100" type="submit">Auto-Add Cables from Revision</button>
                                    </div>
                                </div>
                            </form>
                            <form method="post" class="mb-3" id="sound-cables-form">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="save_cables" id="sound-cables-action">
                                <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                <input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                                <input type="hidden" name="current_tab" value="cables">
                                <input type="hidden" name="cable_rows_json" id="sound-cables-json" value="">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-8">
                                        <label class="form-label">New Loom Name</label>
                                        <input class="form-control" name="new_loom_name" placeholder="e.g. DOWNSTAGE LOOM">
                                    </div>
                                    <div class="col-md-4 d-grid gap-2">
                                        <button class="btn btn-outline-primary w-100" type="button" id="create-loom-button">Create/Assign Loom from Selected Rows</button>
                                        <?php if ($canManageSoundCables): ?>
                                            <button class="btn btn-outline-primary w-100" type="button" id="add-cable-row-button">Add Cable Row</button>
                                            <button class="btn btn-outline-danger w-100" type="button" id="delete-selected-cables-button">Delete Selected Rows</button>
                                        <?php endif; ?>
                                        <div class="small text-secondary text-end" id="cable-autosave-status">Autosave ready</div>
                                    </div>
                                </div>
                                <div class="row g-2 align-items-end mt-1">
                                    <div class="col-md-4">
                                        <label class="form-label">Bulk Color (selected rows)</label>
                                        <select class="form-select form-select-sm" id="bulk-color-select">
                                            <option value="">Select Color</option>
                                            <?php foreach ($soundCableColors as $colorRow): ?>
                                                <?php $bulkColorName = (string) ($colorRow['name'] ?? ''); ?>
                                                <option value="<?= e($bulkColorName) ?>"><?= e($bulkColorName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Bulk Category (selected rows)</label>
                                        <input class="form-control form-control-sm" id="bulk-category-input" placeholder="Category">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Bulk Loom (selected rows)</label>
                                        <select class="form-select form-select-sm" id="bulk-loom-select">
                                            <option value="">Not Loomed</option>
                                            <?php foreach ($soundCableLoomOptions as $loomOption): ?>
                                                <option value="<?= e($loomOption) ?>"><?= e($loomOption) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 d-flex justify-content-end gap-2">
                                        <button class="btn btn-outline-primary btn-sm" type="button" id="bulk-apply-color-button">Apply Color to Selected</button>
                                        <button class="btn btn-outline-primary btn-sm" type="button" id="bulk-apply-category-button">Apply Category to Selected</button>
                                        <button class="btn btn-outline-primary btn-sm" type="button" id="bulk-apply-loom-button">Apply Loom to Selected</button>
                                    </div>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-vcenter" id="sound-cables-table">
                                        <thead>
                                            <tr>
                                                <th style="width:44px">#</th>
                                                <th>cable_type</th>
                                                <th>Length</th>
                                                <th>overall_label</th>
                                                <th>From</th>
                                                <th>To</th>
                                                <th>Adapter</th>
                                                <th>Notes</th>
                                                <th>Loom</th>
                                                <th>Color</th>
                                                <th>category</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($soundCables as $idx => $cable): ?>
                                                <?php
                                                $loomCurrent = trim((string) ($cable['loom_name'] ?? ''));
                                                $colorCurrent = trim((string) ($cable['color'] ?? ''));
                                                $colorHex = '#1f9ad1';
                                                $colorLookup = $soundCableColorMap[strtolower($colorCurrent)] ?? null;
                                                if (is_array($colorLookup)) {
                                                    $candidateHex = trim((string) ($colorLookup['hex_code'] ?? ''));
                                                    if (preg_match('/^#[0-9a-fA-F]{6}$/', $candidateHex)) {
                                                        $colorHex = $candidateHex;
                                                    }
                                                } elseif (preg_match('/^#[0-9a-fA-F]{6}$/', $colorCurrent)) {
                                                    $colorHex = $colorCurrent;
                                                }
                                                $colorRowBg = shop_hex_to_rgba($colorHex, 0.16) ?? 'transparent';
                                                ?>
                                                <tr class="cable-row" style="background-color: <?= e($colorRowBg) ?>;">
                                                    <td>
                                                        <input class="form-check-input" type="checkbox" name="selected_cable_ids[]" value="<?= (int) ($cable['id'] ?? 0) ?>">
                                                    </td>
                                                    <td>
                                                        <input type="hidden" name="cable_id[]" value="<?= (int) ($cable['id'] ?? 0) ?>">
                                                        <input class="form-control form-control-sm cable-edit" name="cable_type[]" value="<?= e((string) ($cable['cable_type'] ?? '')) ?>">
                                                    </td>
                                                    <td><input class="form-control form-control-sm cable-edit" name="cable_length[]" value="<?= e((string) ($cable['cable_length'] ?? '')) ?>"></td>
                                                    <td><input class="form-control form-control-sm cable-edit" name="overall_label[]" value="<?= e((string) (($cable['overall_label'] ?? '') ?: ($cable['display_name'] ?? ''))) ?>"></td>
                                                    <td><input class="form-control form-control-sm cable-edit" name="source_text[]" value="<?= e((string) ($cable['source_text'] ?? '')) ?>"></td>
                                                    <td><input class="form-control form-control-sm cable-edit" name="destination_text[]" value="<?= e((string) ($cable['destination_text'] ?? '')) ?>"></td>
                                                    <td><input class="form-control form-control-sm cable-edit" name="adapter_text[]" value="<?= e((string) ($cable['adapter_text'] ?? '')) ?>"></td>
                                                    <td><input class="form-control form-control-sm cable-edit" name="notes_text[]" value="<?= e((string) (($cable['notes_text'] ?? '') ?: ($cable['description_text'] ?? ''))) ?>"></td>
                                                    <td>
                                                        <select class="form-select form-select-sm cable-edit" name="loom_name[]">
                                                            <option value="">Not Loomed</option>
                                                            <?php foreach ($soundCableLoomOptions as $loomOption): ?>
                                                                <option value="<?= e($loomOption) ?>" <?= $loomCurrent === $loomOption ? 'selected' : '' ?>><?= e($loomOption) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select class="form-select form-select-sm cable-edit" name="color[]">
                                                            <option value="">Select Color</option>
                                                            <?php foreach ($soundCableColors as $colorRow): ?>
                                                                <?php $colorName = (string) ($colorRow['name'] ?? ''); ?>
                                                                <option value="<?= e($colorName) ?>" <?= $colorCurrent === $colorName ? 'selected' : '' ?>><?= e($colorName) ?></option>
                                                            <?php endforeach; ?>
                                                            <?php if ($colorCurrent !== '' && !isset($soundCableColorMap[strtolower($colorCurrent)])): ?>
                                                                <option value="<?= e($colorCurrent) ?>" selected><?= e($colorCurrent) ?> (custom)</option>
                                                            <?php endif; ?>
                                                        </select>
                                                    </td>
                                                    <td><input class="form-control form-control-sm cable-edit" name="category_name[]" value="<?= e((string) ($cable['category_name'] ?? '')) ?>"></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (!$soundCables): ?>
                                                <tr><td colspan="11" class="text-secondary">No cable rows yet. Sync from a revision or import using the exact headers.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($canManageSoundCables): ?>
                        <div class="card mb-3">
                            <div class="card-header"><h3 class="card-title mb-0">Import Cables</h3></div>
                            <div class="card-body">
                                <form method="post" enctype="multipart/form-data">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="import_cables">
                                    <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                                    <input type="hidden" name="current_tab" value="cables">
                                    <label class="form-label">Import cables (paste or upload with exact headers)</label>
                                    <div class="form-hint mb-2">cable_type	Length	overall_label	From	To	Adapter	Notes	Loom	loom_id	Color	category</div>
                                    <textarea class="form-control" name="cable_import_text" rows="5" placeholder="Paste header + rows here"></textarea>
                                    <div class="mt-2">
                                        <input class="form-control" type="file" name="cable_import_file" accept=".csv,.tsv,.txt,text/csv,text/plain">
                                    </div>
                                    <div class="mt-2">
                                        <button class="btn btn-outline-primary" type="submit">Import</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>

                    <div class="modal fade" id="cable-looms-modal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Cable Looms</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-vcenter">
                                            <thead><tr><th>Loom Name</th><th>Assigned Cables</th><th class="text-end">Actions</th></tr></thead>
                                            <tbody>
                                            <?php if (!$soundCableLoomMembers): ?>
                                                <tr><td colspan="3" class="text-secondary">No looms yet.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($soundCableLoomMembers as $loomName => $loomMembers): ?>
                                                <tr>
                                                    <td>
                                                        <form method="post" class="d-flex gap-2">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="action" value="rename_loom">
                                                            <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                                            <input type="hidden" name="current_tab" value="cables">
                                                            <input type="hidden" name="old_loom_name" value="<?= e($loomName) ?>">
                                                            <input class="form-control form-control-sm" name="new_loom_name" value="<?= e($loomName) ?>">
                                                            <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <div class="small text-secondary">
                                                            <?= e(implode(' • ', array_slice($loomMembers, 0, 6))) ?>
                                                            <?php if (count($loomMembers) > 6): ?><?= e(' • +' . (count($loomMembers) - 6) . ' more') ?><?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">
                                                        <form method="post" class="d-inline">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="action" value="delete_loom">
                                                            <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                                            <input type="hidden" name="current_tab" value="cables">
                                                            <input type="hidden" name="loom_name" value="<?= e($loomName) ?>">
                                                            <button class="btn btn-outline-danger btn-sm" type="submit" onclick="return confirm('Remove this loom from all rows?')">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="cable-colors-modal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Cable Colors Library</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="post" class="row g-2 mb-3">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="add_cable_color">
                                        <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                        <input type="hidden" name="current_tab" value="cables">
                                        <div class="col-md-5"><input class="form-control" name="color_name" placeholder="New color name"></div>
                                        <div class="col-md-3"><input class="form-control form-control-color w-100" type="color" name="color_hex" value="#1f9ad1" title="Pick color"></div>
                                        <div class="col-md-4"><button class="btn btn-primary w-100" type="submit">Add Color</button></div>
                                    </form>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-vcenter">
                                            <thead><tr><th style="width:90px">Swatch</th><th>Color + Hex</th><th class="text-end">Actions</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($soundCableColors as $colorRow): ?>
                                                <?php
                                                $rowHex = trim((string) ($colorRow['hex_code'] ?? '#1f9ad1'));
                                                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $rowHex)) {
                                                    $rowHex = '#1f9ad1';
                                                }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <span class="d-inline-block" style="width:28px;height:20px;border:1px solid #c0c0c0;border-radius:3px;background:<?= e($rowHex) ?>;"></span>
                                                    </td>
                                                    <td>
                                                        <form method="post" class="d-flex gap-2">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="action" value="rename_cable_color">
                                                            <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                                            <input type="hidden" name="current_tab" value="cables">
                                                            <input type="hidden" name="color_id" value="<?= (int) ($colorRow['id'] ?? 0) ?>">
                                                            <input class="form-control form-control-sm" name="color_name" value="<?= e((string) ($colorRow['name'] ?? '')) ?>">
                                                            <input class="form-control form-control-sm form-control-color" type="color" name="color_hex" value="<?= e($rowHex) ?>">
                                                            <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
                                                        </form>
                                                    </td>
                                                    <td class="text-end">
                                                        <form method="post" class="d-inline ms-1">
                                                            <?= csrf_input() ?>
                                                            <input type="hidden" name="action" value="delete_cable_color">
                                                            <input type="hidden" name="show_id" value="<?= (int) $selectedShowId ?>">
                                                            <input type="hidden" name="current_tab" value="cables">
                                                            <input type="hidden" name="color_id" value="<?= (int) ($colorRow['id'] ?? 0) ?>">
                                                            <button class="btn btn-outline-danger btn-sm" type="submit" onclick="return confirm('Delete this color?')">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        (() => {
                            const sortSelect = document.getElementById('cable-sort-select');
                            const table = document.getElementById('sound-cables-table');
                            const cablesForm = document.getElementById('sound-cables-form');
                            const actionInput = document.getElementById('sound-cables-action');
                            const jsonInput = document.getElementById('sound-cables-json');
                            const loomButton = document.getElementById('create-loom-button');
                            const addCableRowButton = document.getElementById('add-cable-row-button');
                            const deleteSelectedCablesButton = document.getElementById('delete-selected-cables-button');
                            const autosaveStatus = document.getElementById('cable-autosave-status');
                            const searchInput = document.getElementById('cable-search-input');
                            const bulkColorSelect = document.getElementById('bulk-color-select');
                            const bulkCategoryInput = document.getElementById('bulk-category-input');
                            const bulkLoomSelect = document.getElementById('bulk-loom-select');
                            const bulkApplyColorButton = document.getElementById('bulk-apply-color-button');
                            const bulkApplyCategoryButton = document.getElementById('bulk-apply-category-button');
                            const bulkApplyLoomButton = document.getElementById('bulk-apply-loom-button');
                            const colorHexMap = <?= json_encode(array_map(static fn ($row): string => (string) ($row['hex_code'] ?? '#1f9ad1'), array_column($soundCableColors, null, 'name')), JSON_UNESCAPED_SLASHES) ?>;
                            if (!sortSelect || !table || !cablesForm || !actionInput || !jsonInput) return;

                            const hexToRgba = (hex, alpha = 0.16) => {
                                if (!/^#[0-9a-fA-F]{6}$/.test(hex || '')) return 'transparent';
                                const r = Number.parseInt(hex.slice(1, 3), 16);
                                const g = Number.parseInt(hex.slice(3, 5), 16);
                                const b = Number.parseInt(hex.slice(5, 7), 16);
                                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                            };

                            const applyRowColor = (row) => {
                                const colorSelect = row.querySelector('select[name="color[]"]');
                                if (!(colorSelect instanceof HTMLSelectElement)) return;
                                const selected = (colorSelect.value || '').trim();
                                const hex = colorHexMap[selected] || (/^#[0-9a-fA-F]{6}$/.test(selected) ? selected : '');
                                row.style.backgroundColor = hex ? hexToRgba(hex) : 'transparent';
                            };

                            const applySearch = () => {
                                const query = (searchInput?.value || '').trim().toLowerCase();
                                const rows = [...table.querySelectorAll('tr.cable-row')];
                                rows.forEach((row) => {
                                    if (query === '') {
                                        row.style.display = '';
                                        return;
                                    }
                                    const values = [...row.querySelectorAll('input,select')].map((el) => {
                                        if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement) {
                                            return el.value || '';
                                        }
                                        return '';
                                    }).join(' ').toLowerCase();
                                    row.style.display = values.includes(query) ? '' : 'none';
                                });
                            };

                            const collectCableRows = () => {
                                const rows = [];
                                table.querySelectorAll('tr.cable-row').forEach((row) => {
                                    const read = (selector) => {
                                        const input = row.querySelector(selector);
                                        if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement) {
                                            return input.value || '';
                                        }
                                        return '';
                                    };
                                    const id = Number.parseInt(read('input[name="cable_id[]"]') || '0', 10);
                                    if (!Number.isFinite(id) || id <= 0) return;
                                    rows.push({
                                        id,
                                        cable_type: read('input[name="cable_type[]"]'),
                                        cable_length: read('input[name="cable_length[]"]'),
                                        overall_label: read('input[name="overall_label[]"]'),
                                        source_text: read('input[name="source_text[]"]'),
                                        destination_text: read('input[name="destination_text[]"]'),
                                        adapter_text: read('input[name="adapter_text[]"]'),
                                        notes_text: read('input[name="notes_text[]"]'),
                                        loom_name: read('select[name="loom_name[]"]'),
                                        loom_id_text: read('input[name="loom_id_text[]"]'),
                                        color: read('select[name="color[]"]'),
                                        category_name: read('input[name="category_name[]"]'),
                                    });
                                });
                                return rows;
                            };

                            const selectedRows = () => [...table.querySelectorAll('tr.cable-row')]
                                .filter((row) => {
                                    const box = row.querySelector('input[name="selected_cable_ids[]"]');
                                    return box instanceof HTMLInputElement && box.checked;
                                });

                            const saveCables = () => {
                                actionInput.value = 'save_cables';
                                if (autosaveStatus) {
                                    autosaveStatus.textContent = 'Saving…';
                                }
                                const body = new URLSearchParams();
                                body.set('_csrf', (cablesForm.querySelector('input[name="_csrf"]')?.value || ''));
                                body.set('action', 'save_cables');
                                body.set('show_id', (cablesForm.querySelector('input[name="show_id"]')?.value || '0'));
                                body.set('current_tab', (cablesForm.querySelector('input[name="current_tab"]')?.value || 'cables'));
                                const rowsJson = JSON.stringify(collectCableRows());
                                jsonInput.value = rowsJson;
                                body.set('cable_rows_json', rowsJson);
                                fetch(window.location.pathname + window.location.search, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: body.toString(),
                                }).then((response) => {
                                    if (!response.ok) {
                                        throw new Error('save failed');
                                    }
                                    if (autosaveStatus) {
                                        autosaveStatus.textContent = 'All changes saved';
                                    }
                                }).catch(() => {
                                    if (autosaveStatus) {
                                        autosaveStatus.textContent = 'Autosave failed';
                                    }
                                });
                            };

                            let saveTimer = null;
                            const queueSave = () => {
                                if (saveTimer) {
                                    window.clearTimeout(saveTimer);
                                }
                                saveTimer = window.setTimeout(saveCables, 350);
                            };

                            sortSelect.addEventListener('change', () => {
                                const field = sortSelect.value;
                                const body = table.querySelector('tbody');
                                if (!body) return;
                                const rows = [...body.querySelectorAll('tr.cable-row')];
                                const keyIndexMap = {
                                    cable_type: 1,
                                    cable_length: 2,
                                    overall_label: 3,
                                    source_text: 4,
                                    destination_text: 5,
                                    loom_name: 8,
                                    color: 9,
                                    category_name: 10,
                                };
                                const cellIndex = keyIndexMap[field] ?? 10;
                                rows.sort((a, b) => {
                                    const aCell = a.children[cellIndex];
                                    const bCell = b.children[cellIndex];
                                    const aInput = aCell?.querySelector('input,select');
                                    const bInput = bCell?.querySelector('input,select');
                                    const aText = ((aInput instanceof HTMLInputElement || aInput instanceof HTMLSelectElement) ? aInput.value : (aCell?.textContent || '')).trim().toLowerCase();
                                    const bText = ((bInput instanceof HTMLInputElement || bInput instanceof HTMLSelectElement) ? bInput.value : (bCell?.textContent || '')).trim().toLowerCase();
                                    return aText.localeCompare(bText, undefined, { numeric: true });
                                });
                                rows.forEach((row) => body.appendChild(row));
                                queueSave();
                                applySearch();
                            });

                            table.querySelectorAll('.cable-edit').forEach((field) => {
                                field.addEventListener('input', queueSave);
                                field.addEventListener('change', queueSave);
                                field.addEventListener('change', () => {
                                    const row = field.closest('tr.cable-row');
                                    if (row) applyRowColor(row);
                                });
                            });

                            table.querySelectorAll('tr.cable-row').forEach((row) => applyRowColor(row));
                            searchInput?.addEventListener('input', applySearch);

                            const applyBulkUpdate = (applyFn) => {
                                const rows = selectedRows();
                                if (rows.length === 0) {
                                    if (autosaveStatus) autosaveStatus.textContent = 'Select rows first';
                                    return;
                                }
                                rows.forEach((row) => applyFn(row));
                                queueSave();
                            };

                            const submitLightweightAction = (nextAction) => {
                                actionInput.value = nextAction;
                                table.querySelectorAll('input[name="cable_id[]"], .cable-edit').forEach((field) => {
                                    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) {
                                        field.dataset.prevName = field.name;
                                        field.removeAttribute('name');
                                    }
                                });
                                cablesForm.submit();
                            };

                            bulkApplyColorButton?.addEventListener('click', () => {
                                const nextColor = (bulkColorSelect?.value || '').trim();
                                if (nextColor === '') return;
                                applyBulkUpdate((row) => {
                                    const select = row.querySelector('select[name="color[]"]');
                                    if (select instanceof HTMLSelectElement) {
                                        select.value = nextColor;
                                        applyRowColor(row);
                                    }
                                });
                            });

                            bulkApplyCategoryButton?.addEventListener('click', () => {
                                const nextCategory = (bulkCategoryInput?.value || '').trim();
                                applyBulkUpdate((row) => {
                                    const input = row.querySelector('input[name="category_name[]"]');
                                    if (input instanceof HTMLInputElement) {
                                        input.value = nextCategory;
                                    }
                                });
                            });

                            bulkApplyLoomButton?.addEventListener('click', () => {
                                const nextLoom = (bulkLoomSelect?.value || '').trim();
                                applyBulkUpdate((row) => {
                                    const select = row.querySelector('select[name="loom_name[]"]');
                                    if (select instanceof HTMLSelectElement) {
                                        select.value = nextLoom;
                                    }
                                });
                            });

                            if (loomButton) {
                                loomButton.addEventListener('click', () => {
                                    submitLightweightAction('create_loom');
                                });
                            }

                            addCableRowButton?.addEventListener('click', () => {
                                submitLightweightAction('add_cable_row');
                            });

                            deleteSelectedCablesButton?.addEventListener('click', () => {
                                if (!window.confirm('Delete selected cable rows?')) return;
                                submitLightweightAction('delete_selected_cables');
                            });
                        })();
                    </script>
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
