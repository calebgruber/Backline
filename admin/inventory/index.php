<?php

declare(strict_types=1);

if (!function_exists('app_config')) {
    $bootstrapRoot = __DIR__;
    while (!file_exists($bootstrapRoot . '/shared/bootstrap.php')) {
        $parent = dirname($bootstrapRoot);
        if ($parent === $bootstrapRoot) {
            break;
        }
        $bootstrapRoot = $parent;
    }
    require_once $bootstrapRoot . '/shared/bootstrap.php';
}

$user = require_permission('inventory.manage');

function inventory_has_subcategory_column(): bool
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

function inventory_has_subcategory_catalog_table(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'inventory_subcategories'");
        $exists = (bool) $stmt?->fetchColumn();
    } catch (Throwable) {
        $exists = false;
    }
    return $exists;
}

function parse_import_rows(string $shop, string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    if (!$lines || count($lines) < 2) {
        return [];
    }

    array_shift($lines);
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $cols = str_getcsv($line);
        if ($shop === 'snd') {
            $rows[] = [
                'category' => (string) ($cols[1] ?? ''),
                'name' => (string) ($cols[2] ?? ''),
                'sku' => (string) ($cols[3] ?? ''),
                'shop_quantity' => (int) ($cols[4] ?? 0),
                'unit' => (string) ($cols[5] ?? 'ea'),
                'description' => trim((string) ($cols[7] ?? '')) !== '' ? (string) ($cols[7] ?? '') : (string) ($cols[6] ?? ''),
            ];
        } else {
            $rows[] = [
                'category' => (string) ($cols[0] ?? ''),
                'name' => (string) ($cols[1] ?? ''),
                'sku' => null,
                'shop_quantity' => (int) ($cols[2] ?? 0),
                'unit' => (string) ($cols[3] ?? 'ea'),
                'description' => trim((string) ($cols[5] ?? '')) !== '' ? (string) ($cols[5] ?? '') : (string) ($cols[4] ?? ''),
            ];
        }
    }

    return $rows;
}

function inventory_update_item_record(string $shop, bool $hasSubcategoryColumn): void
{
    if ($shop === 'snd') {
        $sql = $hasSubcategoryColumn
            ? 'UPDATE inventory_items
            SET category_id = ?, subcategory_name = ?, name = ?, sku = ?, shop_quantity = ?, unit = ?, description = ?, is_spacer = ?, sort_order = ?, updated_at = NOW()
            WHERE id = ? AND shop_type = ?'
            : 'UPDATE inventory_items
            SET category_id = ?, name = ?, sku = ?, shop_quantity = ?, unit = ?, description = ?, is_spacer = ?, sort_order = ?, updated_at = NOW()
            WHERE id = ? AND shop_type = ?';
        $stmt = db()->prepare($sql);
        $params = [
            (int) post('category_id', '0') ?: null,
        ];
        if ($hasSubcategoryColumn) {
            $params[] = ($subcat = trim(post('subcategory_name', ''))) !== '' ? $subcat : null;
        }
        $params = array_merge($params, [
            post('name'),
            post('sku') ?: null,
            (int) post('shop_quantity', '0'),
            post('unit', 'ea'),
            post('description'),
            isset($_POST['is_spacer']) ? 1 : 0,
            (int) post('sort_order', '0'),
            (int) post('id'),
            $shop,
        ]);
        $stmt->execute($params);
        return;
    }

    $sql = $hasSubcategoryColumn
        ? 'UPDATE inventory_items
        SET category_id = ?, subcategory_name = ?, name = ?, shop_quantity = ?, unit = ?, description = ?, is_spacer = ?, sort_order = ?, updated_at = NOW()
        WHERE id = ? AND shop_type = ?'
        : 'UPDATE inventory_items
        SET category_id = ?, name = ?, shop_quantity = ?, unit = ?, description = ?, is_spacer = ?, sort_order = ?, updated_at = NOW()
        WHERE id = ? AND shop_type = ?';
    $stmt = db()->prepare($sql);
    $params = [
        (int) post('category_id', '0') ?: null,
    ];
    if ($hasSubcategoryColumn) {
        $params[] = ($subcat = trim(post('subcategory_name', ''))) !== '' ? $subcat : null;
    }
    $params = array_merge($params, [
        post('name'),
        (int) post('shop_quantity', '0'),
        post('unit', 'ea'),
        post('description'),
        isset($_POST['is_spacer']) ? 1 : 0,
        (int) post('sort_order', '0'),
        (int) post('id'),
        $shop,
    ]);
    $stmt->execute($params);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');
    $shop = post('shop_type');
    $hasSubcategoryColumn = inventory_has_subcategory_column();

    if ($action === 'create_item') {
        $requestedSortOrder = (int) post('sort_order', '0');
        if ($requestedSortOrder <= 0) {
            $nextSortStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM inventory_items WHERE shop_type = ?');
            $nextSortStmt->execute([$shop]);
            $requestedSortOrder = (int) $nextSortStmt->fetchColumn();
        }
        if ($hasSubcategoryColumn) {
            $stmt = db()->prepare('INSERT INTO inventory_items (shop_type, category_id, subcategory_name, name, sku, shop_quantity, unit, default_note, description, is_spacer, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([
                $shop,
                (int) post('category_id', '0') ?: null,
                ($subcat = trim(post('subcategory_name', ''))) !== '' ? $subcat : null,
                post('name'),
                $shop === 'snd' ? (post('sku') ?: null) : null,
                (int) post('shop_quantity', '0'),
                post('unit', 'ea'),
                '',
                post('description'),
                isset($_POST['is_spacer']) ? 1 : 0,
                $requestedSortOrder,
            ]);
        } else {
            $stmt = db()->prepare('INSERT INTO inventory_items (shop_type, category_id, name, sku, shop_quantity, unit, default_note, description, is_spacer, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([
                $shop,
                (int) post('category_id', '0') ?: null,
                post('name'),
                $shop === 'snd' ? (post('sku') ?: null) : null,
                (int) post('shop_quantity', '0'),
                post('unit', 'ea'),
                '',
                post('description'),
                isset($_POST['is_spacer']) ? 1 : 0,
                $requestedSortOrder,
            ]);
        }
        flash_set('success', strtoupper($shop) . ' item created.');
    }

    if ($action === 'update_item') {
        inventory_update_item_record($shop, $hasSubcategoryColumn);
        flash_set('success', 'Item updated.');
    }

    if ($action === 'save_item_live') {
        inventory_update_item_record($shop, $hasSubcategoryColumn);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete_item') {
        $stmt = db()->prepare('DELETE FROM inventory_items WHERE id = ? AND shop_type = ?');
        $stmt->execute([(int) post('id'), $shop]);
        flash_set('warning', 'Item deleted.');
    }

    if ($action === 'clear_shop') {
        $stmt = db()->prepare('DELETE FROM inventory_items WHERE shop_type = ?');
        $stmt->execute([$shop]);
        flash_set('warning', strtoupper($shop) . ' inventory cleared.');
    }

    if ($action === 'import') {
        $rows = parse_import_rows($shop, post('import_text'));
        $nextCategorySortStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM inventory_categories WHERE shop_type = ?');
        $nextCategorySortStmt->execute([$shop]);
        $nextCategorySortOrder = (int) $nextCategorySortStmt->fetchColumn();
        $nextItemSortStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM inventory_items WHERE shop_type = ?');
        $nextItemSortStmt->execute([$shop]);
        $nextItemSortOrder = (int) $nextItemSortStmt->fetchColumn();
        db()->beginTransaction();
        foreach ($rows as $row) {
            $catStmt = db()->prepare('SELECT id FROM inventory_categories WHERE shop_type = ? AND name = ? LIMIT 1');
            $catStmt->execute([$shop, $row['category']]);
            $catId = $catStmt->fetchColumn();
            if (!$catId && $row['category'] !== '') {
                $insCat = db()->prepare('INSERT INTO inventory_categories (shop_type, name, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
                $insCat->execute([$shop, $row['category'], $nextCategorySortOrder]);
                $catId = (int) db()->lastInsertId();
                $nextCategorySortOrder++;
            }
            if ($hasSubcategoryColumn) {
                $ins = db()->prepare('INSERT INTO inventory_items (shop_type, category_id, subcategory_name, name, sku, shop_quantity, unit, default_note, description, is_spacer, sort_order, created_at, updated_at)
                    VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())');
            } else {
                $ins = db()->prepare('INSERT INTO inventory_items (shop_type, category_id, name, sku, shop_quantity, unit, default_note, description, is_spacer, sort_order, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())');
            }
            $ins->execute([$shop, $catId ?: null, $row['name'], $row['sku'], $row['shop_quantity'], $row['unit'], '', $row['description'], $nextItemSortOrder]);
            $nextItemSortOrder++;
        }
        db()->commit();
        flash_set('success', strtoupper($shop) . ' import complete: ' . count($rows) . ' rows.');
    }

    if ($action === 'reorder_items') {
        $orderedIds = json_decode((string) ($_POST['ordered_ids'] ?? '[]'), true);
        if (is_array($orderedIds)) {
            db()->beginTransaction();
            $stmt = db()->prepare('UPDATE inventory_items SET sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
            foreach (array_values($orderedIds) as $idx => $id) {
                $stmt->execute([$idx + 1, (int) $id, $shop]);
            }
            db()->commit();
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    redirect('/admin/inventory');
}

$hasSubcategoryColumn = inventory_has_subcategory_column();
$hasSubcategoryCatalogTable = inventory_has_subcategory_catalog_table();
$cats = db()->query('SELECT id, shop_type, name FROM inventory_categories ORDER BY shop_type, sort_order, name')->fetchAll();
$items = db()->query('SELECT ii.*, ' . ($hasSubcategoryColumn ? 'ii.subcategory_name,' : 'NULL AS subcategory_name,') . ' ic.name AS category_name, ic.sort_order AS category_sort_order
    FROM inventory_items ii
    LEFT JOIN inventory_categories ic ON ic.id = ii.category_id
    ORDER BY ii.shop_type, COALESCE(ic.sort_order, 2147483647), COALESCE(ic.name, ""), ii.sort_order, ii.id')->fetchAll();
$catsByShop = ['lx' => [], 'snd' => []];
$subcategoryOptionsByShop = ['lx' => [], 'snd' => []];
foreach ($cats as $cat) {
    $catsByShop[$cat['shop_type']][] = $cat;
}
if ($hasSubcategoryCatalogTable) {
    $subcatRows = db()->query('SELECT shop_type, name FROM inventory_subcategories ORDER BY shop_type, sort_order, name')->fetchAll();
    foreach ($subcatRows as $subcat) {
        $shop = (string) ($subcat['shop_type'] ?? '');
        $name = trim((string) ($subcat['name'] ?? ''));
        if (($shop === 'lx' || $shop === 'snd') && $name !== '') {
            $subcategoryOptionsByShop[$shop][] = $name;
        }
    }
    foreach ($subcategoryOptionsByShop as $shop => $values) {
        $subcategoryOptionsByShop[$shop] = array_values(array_unique($values));
    }
}
$itemsGroupedByShopCategory = ['lx' => [], 'snd' => []];
foreach ($items as $item) {
    $shop = (string) $item['shop_type'];
    $group = trim((string) ($item['category_name'] ?? ''));
    if ($group === '') {
        $group = 'Uncategorized';
    }
    $itemsGroupedByShopCategory[$shop][$group][] = $item;
}
render_page('Inventory', function () use ($catsByShop, $itemsGroupedByShopCategory, $hasSubcategoryColumn, $subcategoryOptionsByShop): void {
    $shops = [
        'lx' => 'Lighting Inventory',
        'snd' => 'Sound Inventory',
    ];
    ?>
    <ul class="nav nav-tabs mb-3" data-bs-toggle="tabs" role="tablist">
        <li class="nav-item" role="presentation"><a href="#inv-lx" class="nav-link active" data-bs-toggle="tab" aria-selected="true" role="tab">LX</a></li>
        <li class="nav-item" role="presentation"><a href="#inv-snd" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">Sound</a></li>
    </ul>

    <div class="tab-content">
    <?php foreach ($shops as $shopKey => $shopLabel): ?>
        <div class="tab-pane <?= $shopKey === 'lx' ? 'active show' : '' ?>" id="inv-<?= e($shopKey) ?>">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title"><?= e($shopLabel) ?></h3>
                            <div class="ms-auto">
                                <form method="post" onsubmit="return confirm('Clear all <?= e(strtoupper($shopKey)) ?> items?')">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="clear_shop">
                                    <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                    <button class="btn btn-outline-danger btn-sm">Clear <?= e(strtoupper($shopKey)) ?> Inventory</button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <h4 class="mb-2">Add <?= e(strtoupper($shopKey)) ?> Item</h4>
                                    <form method="post">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="create_item">
                                        <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                        <div class="mb-2"><select class="form-select" name="category_id"><option value="">No category</option><?php foreach($catsByShop[$shopKey] as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
                                        <?php if ($hasSubcategoryColumn): ?>
                                            <div class="mb-2">
                                                <input class="form-control" name="subcategory_name" placeholder="Subcategory (optional)" list="subcategory-options-<?= e($shopKey) ?>">
                                            </div>
                                        <?php endif; ?>
                                        <div class="mb-2"><input class="form-control" name="name" placeholder="Name" required></div>
                                        <?php if ($shopKey === 'snd'): ?><div class="mb-2"><input class="form-control" name="sku" placeholder="SKU"></div><?php endif; ?>
                                        <div class="row g-2"><div class="col"><input class="form-control" type="number" name="shop_quantity" placeholder="Qty"></div><div class="col"><input class="form-control" name="unit" placeholder="Unit" value="ea"></div></div>
                                        <div class="mb-2 mt-2"><textarea class="form-control" name="description" placeholder="Description"></textarea></div>
                                        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_spacer"><span class="form-check-label">Spacer item</span></label>
                                        <button class="btn btn-primary">Create <?= e(strtoupper($shopKey)) ?> Item</button>
                                    </form>
                                </div>
                                <div class="col-lg-6">
                                    <h4 class="mb-2">Import <?= e(strtoupper($shopKey)) ?> CSV Text</h4>
                                    <form method="post">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="import">
                                        <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                        <textarea class="form-control mb-2" name="import_text" rows="10" placeholder="Paste CSV text with header"></textarea>
                                        <button class="btn btn-outline-primary">Import <?= e(strtoupper($shopKey)) ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><h3 class="card-title"><?= e($shopLabel) ?> Items</h3></div>
                        <div class="card-body border-bottom">
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <input type="text" class="form-control inventory-search" data-shop="<?= e($shopKey) ?>" placeholder="Search items in <?= e($shopLabel) ?>">
                                </div>
                                <div class="col-md-5">
                                    <select class="form-select inventory-category-filter" data-shop="<?= e($shopKey) ?>">
                                        <option value="">All Categories</option>
                                        <?php foreach (array_keys($itemsGroupedByShopCategory[$shopKey]) as $catName): ?>
                                            <option value="<?= e($catName) ?>"><?= e($catName) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="small text-secondary mt-2 js-inventory-save-status" data-shop="<?= e($shopKey) ?>">All changes auto-save as you edit.</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-vcenter inventory-table">
                                <thead><tr><th class="w-1"></th><th>Category</th><?php if ($hasSubcategoryColumn): ?><th>Subcategory</th><?php endif; ?><th>Name</th><?php if ($shopKey === 'snd'): ?><th>SKU</th><?php endif; ?><th>Qty</th><th>Unit</th><th>Description</th><th>Spacer</th><th>Sort</th><th class="text-end">Actions</th></tr></thead>
                                <?php foreach ($itemsGroupedByShopCategory[$shopKey] as $categoryName => $groupItems): ?>
                                <tbody>
                                    <tr class="category-header-row" data-target="cat-<?= e($shopKey) ?>-<?= md5($categoryName) ?>" data-category-name="<?= e($categoryName) ?>" data-shop="<?= e($shopKey) ?>">
                                        <td colspan="<?= $shopKey === 'snd' ? ($hasSubcategoryColumn ? '11' : '10') : ($hasSubcategoryColumn ? '10' : '9') ?>">
                                            <button type="button" class="btn btn-link p-0 text-reset category-toggle-btn"><i class="ti ti-chevron-right me-2"></i><i class="ti ti-folder me-1"></i><strong><?= e($categoryName) ?></strong></button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody class="category-item-group" id="cat-<?= e($shopKey) ?>-<?= md5($categoryName) ?>" data-shop="<?= e($shopKey) ?>" data-sortable='{"animation":150,"handle":".sortable-handle"}' style="display:none;">
                                    <?php foreach ($groupItems as $item): ?>
                                        <tr data-item-id="<?= (int) $item['id'] ?>">
                                            <td>
                                                <span class="sortable-handle cursor-move text-secondary d-inline-flex align-items-center" title="Drag to reorder" aria-hidden="true">
                                                    <i class="ti ti-grip-vertical"></i>
                                                </span>
                                            </td>
                                            <td>
                                                <select class="form-select" name="category_id" form="item-update-<?= (int) $item['id'] ?>">
                                                    <option value="">No category</option>
                                                    <?php foreach ($catsByShop[$shopKey] as $cat): ?>
                                                        <option value="<?= (int) $cat['id'] ?>" <?= ((int) ($item['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <?php if ($hasSubcategoryColumn): ?><td><input class="form-control" name="subcategory_name" value="<?= e((string) ($item['subcategory_name'] ?? '')) ?>" form="item-update-<?= (int) $item['id'] ?>" list="subcategory-options-<?= e($shopKey) ?>"></td><?php endif; ?>
                                            <td><input class="form-control" name="name" value="<?= e($item['name']) ?>" form="item-update-<?= (int) $item['id'] ?>" required></td>
                                            <?php if ($shopKey === 'snd'): ?><td><input class="form-control" name="sku" value="<?= e((string) $item['sku']) ?>" form="item-update-<?= (int) $item['id'] ?>"></td><?php endif; ?>
                                            <td><input class="form-control" type="number" name="shop_quantity" value="<?= (int) $item['shop_quantity'] ?>" form="item-update-<?= (int) $item['id'] ?>"></td>
                                            <td><input class="form-control" name="unit" value="<?= e($item['unit']) ?>" form="item-update-<?= (int) $item['id'] ?>"></td>
                                            <td><input class="form-control" name="description" value="<?= e((string) $item['description']) ?>" form="item-update-<?= (int) $item['id'] ?>"></td>
                                            <td class="text-center"><input class="form-check-input" type="checkbox" name="is_spacer" value="1" form="item-update-<?= (int) $item['id'] ?>" <?= (int) $item['is_spacer'] ? 'checked' : '' ?>></td>
                                            <td><input class="form-control" type="number" name="sort_order" value="<?= (int) $item['sort_order'] ?>" form="item-update-<?= (int) $item['id'] ?>"></td>
                                            <td class="text-end">
                                                <form id="item-update-<?= (int) $item['id'] ?>" method="post" class="d-inline-block">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="update_item">
                                                    <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                    <button class="btn btn-icon btn-sm btn-primary" title="Update"><i class="ti ti-check"></i></button>
                                                </form>
                                                <form method="post" class="d-inline-block ms-1">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                    <button class="btn btn-icon btn-sm btn-outline-danger" title="Delete"><i class="ti ti-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <datalist id="subcategory-options-<?= e($shopKey) ?>">
                <?php foreach ($subcategoryOptionsByShop[$shopKey] as $subcatOption): ?>
                    <option value="<?= e((string) $subcatOption) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
    <?php endforeach; ?>
    </div>

    <script>
      document.querySelectorAll('.category-header-row').forEach((row) => {
        row.addEventListener('click', () => {
          const targetId = row.getAttribute('data-target');
          const target = document.getElementById(targetId);
          const icon = row.querySelector('.ti');
          if (!target) return;
          const hidden = target.style.display === 'none';
          target.style.display = hidden ? '' : 'none';
          if (icon) {
            icon.classList.toggle('ti-chevron-down', hidden);
            icon.classList.toggle('ti-chevron-right', !hidden);
          }
        });
      });

      const applyInventoryFilter = (shop) => {
        const search = (document.querySelector('.inventory-search[data-shop=\"' + shop + '\"]')?.value || '').toLowerCase();
        const selectedCategory = (document.querySelector('.inventory-category-filter[data-shop=\"' + shop + '\"]')?.value || '').toLowerCase();
        const rowSearchText = (row) => {
          const directText = row.textContent || '';
          const fieldText = Array.from(row.querySelectorAll('input, select, textarea'))
            .map((el) => {
              if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) return el.value || '';
              if (el instanceof HTMLSelectElement) return el.options[el.selectedIndex]?.text || '';
              return '';
            })
            .join(' ');
          return (directText + ' ' + fieldText).toLowerCase();
        };
        document.querySelectorAll('.category-header-row[data-shop=\"' + shop + '\"]').forEach((header) => {
          const categoryName = (header.getAttribute('data-category-name') || '').toLowerCase();
          const group = document.getElementById(header.getAttribute('data-target') || '');
          if (!group) return;
          let visibleRows = 0;
          group.querySelectorAll('tr[data-item-id]').forEach((row) => {
            const text = rowSearchText(row);
            const matchSearch = search === '' || text.includes(search);
            const matchCategory = selectedCategory === '' || categoryName === selectedCategory;
            const show = matchSearch && matchCategory;
            row.style.display = show ? '' : 'none';
            if (show) visibleRows++;
          });
          const showGroup = visibleRows > 0;
          header.style.display = showGroup ? '' : 'none';
          group.style.display = showGroup ? '' : 'none';
          const icon = header.querySelector('.ti');
          if (icon) {
            icon.classList.add('ti-chevron-down');
            icon.classList.remove('ti-chevron-right');
          }
        });
      };

      document.querySelectorAll('.inventory-search, .inventory-category-filter').forEach((el) => {
        el.addEventListener('input', () => applyInventoryFilter(el.getAttribute('data-shop') || ''));
        el.addEventListener('change', () => applyInventoryFilter(el.getAttribute('data-shop') || ''));
      });

      document.querySelectorAll('.category-item-group').forEach((group) => {
        const SortableLib = window.Sortable;
        if (!SortableLib) return;
        let config = { animation: 150, handle: '.sortable-handle' };
        const rawConfig = group.getAttribute('data-sortable') || '';
        if (rawConfig) {
          try {
            config = { ...config, ...JSON.parse(rawConfig) };
          } catch {}
        }
        new SortableLib(group, {
          ...config,
          ghostClass: 'sortable-ghost',
          chosenClass: 'sortable-chosen',
          onEnd: async () => {
            const shopType = group.getAttribute('data-shop');
            const shopRows = Array.from(document.querySelectorAll('.category-item-group[data-shop="' + shopType + '"] tr[data-item-id]'));
            const ids = shopRows.map((tr) => Number(tr.getAttribute('data-item-id')));
            shopRows.forEach((tr, idx) => {
              const sortInput = tr.querySelector('input[name="sort_order"]');
              if (sortInput instanceof HTMLInputElement) sortInput.value = String(idx + 1);
            });
            const body = new URLSearchParams();
            body.set('_csrf', '<?= e(csrf_token()) ?>');
            body.set('action', 'reorder_items');
            body.set('shop_type', shopType || '');
            body.set('ordered_ids', JSON.stringify(ids));
            const response = await fetch('/admin/inventory', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: body.toString(),
            });
            if (!response.ok) {
              alert('Could not save new item order. Please try again.');
            }
          }
        });
      });

      (() => {
        const saveTimers = new Map();
        const setStatus = (shop, text) => {
          const status = document.querySelector('.js-inventory-save-status[data-shop="' + shop + '"]');
          if (status) status.textContent = text;
        };
        document.querySelectorAll('.category-item-group tr[data-item-id]').forEach((row) => {
          const form = row.querySelector('form[id^="item-update-"]');
          if (!form) return;
          const shop = (form.querySelector('input[name="shop_type"]')?.value || '').trim();
          const itemId = (form.querySelector('input[name="id"]')?.value || '').trim();
          const fields = row.querySelectorAll('input[name], select[name], textarea[name]');
          const queueSave = () => {
            const timerKey = shop + ':' + itemId;
            if (saveTimers.has(timerKey)) {
              window.clearTimeout(saveTimers.get(timerKey));
            }
            saveTimers.set(timerKey, window.setTimeout(async () => {
              setStatus(shop, 'Saving…');
              const body = new URLSearchParams();
              body.set('_csrf', form.querySelector('input[name="_csrf"]')?.value || '');
              body.set('action', 'save_item_live');
              body.set('shop_type', shop);
              body.set('id', itemId);
              body.set('category_id', (row.querySelector('select[name="category_id"]')?.value || '0'));
              body.set('subcategory_name', (row.querySelector('input[name="subcategory_name"]')?.value || ''));
              body.set('name', (row.querySelector('input[name="name"]')?.value || ''));
              body.set('sku', (row.querySelector('input[name="sku"]')?.value || ''));
              body.set('shop_quantity', (row.querySelector('input[name="shop_quantity"]')?.value || '0'));
              body.set('unit', (row.querySelector('input[name="unit"]')?.value || 'ea'));
              body.set('description', (row.querySelector('input[name="description"]')?.value || ''));
              body.set('sort_order', (row.querySelector('input[name="sort_order"]')?.value || '0'));
              if (row.querySelector('input[name="is_spacer"]')?.checked) {
                body.set('is_spacer', '1');
              }
              try {
                const response = await fetch('/admin/inventory', {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                  },
                  body: body.toString()
                });
                if (!response.ok) throw new Error('save failed');
                const payload = await response.json();
                if (!payload || payload.ok !== true) throw new Error('save rejected');
                setStatus(shop, 'Saved');
              } catch {
                setStatus(shop, 'Autosave failed. Use update button.');
              }
            }, 300));
          };
          fields.forEach((field) => {
            field.addEventListener('input', queueSave);
            field.addEventListener('change', queueSave);
          });
        });
      })();
    </script>
    <?php
}, $user);
