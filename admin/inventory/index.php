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
                'default_note' => (string) ($cols[6] ?? ''),
                'description' => (string) ($cols[7] ?? ''),
            ];
        } else {
            $rows[] = [
                'category' => (string) ($cols[0] ?? ''),
                'name' => (string) ($cols[1] ?? ''),
                'sku' => null,
                'shop_quantity' => (int) ($cols[2] ?? 0),
                'unit' => (string) ($cols[3] ?? 'ea'),
                'default_note' => (string) ($cols[4] ?? ''),
                'description' => (string) ($cols[5] ?? ''),
            ];
        }
    }

    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');
    $shop = post('shop_type');

    if ($action === 'create_item') {
        $stmt = db()->prepare('INSERT INTO inventory_items (shop_type, category_id, name, sku, shop_quantity, unit, default_note, description, is_spacer, sort_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            $shop,
            (int) post('category_id', '0') ?: null,
            post('name'),
            $shop === 'snd' ? (post('sku') ?: null) : null,
            (int) post('shop_quantity', '0'),
            post('unit', 'ea'),
            post('default_note'),
            post('description'),
            isset($_POST['is_spacer']) ? 1 : 0,
            (int) post('sort_order', '0'),
        ]);
        flash_set('success', strtoupper($shop) . ' item created.');
    }

    if ($action === 'update_item') {
        $stmt = db()->prepare('UPDATE inventory_items
            SET category_id = ?, name = ?, sku = ?, shop_quantity = ?, unit = ?, is_spacer = ?, sort_order = ?, updated_at = NOW()
            WHERE id = ? AND shop_type = ?');
        $stmt->execute([
            (int) post('category_id', '0') ?: null,
            post('name'),
            $shop === 'snd' ? (post('sku') ?: null) : null,
            (int) post('shop_quantity', '0'),
            post('unit', 'ea'),
            isset($_POST['is_spacer']) ? 1 : 0,
            (int) post('sort_order', '0'),
            (int) post('id'),
            $shop,
        ]);
        flash_set('success', 'Item updated.');
    }

    if ($action === 'delete_item') {
        $stmt = db()->prepare('DELETE FROM inventory_items WHERE id = ?');
        $stmt->execute([(int) post('id')]);
        flash_set('warning', 'Item deleted.');
    }

    if ($action === 'clear_shop') {
        $stmt = db()->prepare('DELETE FROM inventory_items WHERE shop_type = ?');
        $stmt->execute([$shop]);
        flash_set('warning', strtoupper($shop) . ' inventory cleared.');
    }

    if ($action === 'import') {
        $rows = parse_import_rows($shop, post('import_text'));
        db()->beginTransaction();
        foreach ($rows as $row) {
            $catStmt = db()->prepare('SELECT id FROM inventory_categories WHERE shop_type = ? AND name = ? LIMIT 1');
            $catStmt->execute([$shop, $row['category']]);
            $catId = $catStmt->fetchColumn();
            if (!$catId && $row['category'] !== '') {
                $insCat = db()->prepare('INSERT INTO inventory_categories (shop_type, name, sort_order, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW())');
                $insCat->execute([$shop, $row['category']]);
                $catId = (int) db()->lastInsertId();
            }
            $ins = db()->prepare('INSERT INTO inventory_items (shop_type, category_id, name, sku, shop_quantity, unit, default_note, description, is_spacer, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW(), NOW())');
            $ins->execute([$shop, $catId ?: null, $row['name'], $row['sku'], $row['shop_quantity'], $row['unit'], $row['default_note'], $row['description']]);
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

$cats = db()->query('SELECT id, shop_type, name FROM inventory_categories ORDER BY shop_type, sort_order, name')->fetchAll();
$items = db()->query('SELECT ii.*, ic.name AS category_name FROM inventory_items ii LEFT JOIN inventory_categories ic ON ic.id = ii.category_id ORDER BY ii.shop_type, ii.sort_order, ii.name')->fetchAll();
$catsByShop = ['lx' => [], 'snd' => []];
foreach ($cats as $cat) {
    $catsByShop[$cat['shop_type']][] = $cat;
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
foreach (['lx', 'snd'] as $shopType) {
    ksort($itemsGroupedByShopCategory[$shopType]);
}

render_page('Inventory', function () use ($catsByShop, $itemsGroupedByShopCategory): void {
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
                                        <div class="mb-2"><input class="form-control" name="name" placeholder="Name" required></div>
                                        <?php if ($shopKey === 'snd'): ?><div class="mb-2"><input class="form-control" name="sku" placeholder="SKU"></div><?php endif; ?>
                                        <div class="row g-2"><div class="col"><input class="form-control" type="number" name="shop_quantity" placeholder="Qty"></div><div class="col"><input class="form-control" name="unit" placeholder="Unit" value="ea"></div></div>
                                        <div class="mb-2 mt-2"><textarea class="form-control" name="default_note" placeholder="Default note"></textarea></div>
                                        <div class="mb-2"><textarea class="form-control" name="description" placeholder="Description"></textarea></div>
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
                        <div class="table-responsive">
                            <table class="table table-vcenter inventory-table">
                                <thead><tr><th>Category</th><th>Name</th><th>SKU</th><th>Qty</th><th>Unit</th><th>Spacer</th><th>Sort</th><th class="text-end">Actions</th></tr></thead>
                                <?php foreach ($itemsGroupedByShopCategory[$shopKey] as $categoryName => $groupItems): ?>
                                <tbody>
                                    <tr class="category-header-row" data-target="cat-<?= e($shopKey) ?>-<?= md5($categoryName) ?>">
                                        <td colspan="8">
                                            <button type="button" class="btn btn-link p-0 text-reset category-toggle-btn"><i class="ti ti-chevron-down me-2"></i><i class="ti ti-folder me-1"></i><strong><?= e($categoryName) ?></strong></button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody class="category-item-group" id="cat-<?= e($shopKey) ?>-<?= md5($categoryName) ?>" data-shop="<?= e($shopKey) ?>">
                                    <?php foreach ($groupItems as $item): ?>
                                        <tr data-item-id="<?= (int) $item['id'] ?>">
                                            <td>
                                                <select class="form-select" name="category_id" form="item-update-<?= (int) $item['id'] ?>">
                                                    <option value="">No category</option>
                                                    <?php foreach ($catsByShop[$shopKey] as $cat): ?>
                                                        <option value="<?= (int) $cat['id'] ?>" <?= ((int) ($item['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input class="form-control" name="name" value="<?= e($item['name']) ?>" form="item-update-<?= (int) $item['id'] ?>" required></td>
                                            <td><input class="form-control" name="sku" value="<?= e((string) $item['sku']) ?>" form="item-update-<?= (int) $item['id'] ?>" <?= $shopKey === 'lx' ? 'disabled' : '' ?>></td>
                                            <td><input class="form-control" type="number" name="shop_quantity" value="<?= (int) $item['shop_quantity'] ?>" form="item-update-<?= (int) $item['id'] ?>"></td>
                                            <td><input class="form-control" name="unit" value="<?= e($item['unit']) ?>" form="item-update-<?= (int) $item['id'] ?>"></td>
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

      document.querySelectorAll('.category-item-group').forEach((group) => {
        new Sortable(group, {
          animation: 120,
          handle: 'td',
          onEnd: async () => {
            const shopType = group.getAttribute('data-shop');
            const ids = Array.from(group.querySelectorAll('tr[data-item-id]')).map((tr) => Number(tr.getAttribute('data-item-id')));
            const body = new URLSearchParams();
            body.set('_csrf', '<?= e(csrf_token()) ?>');
            body.set('action', 'reorder_items');
            body.set('shop_type', shopType || '');
            body.set('ordered_ids', JSON.stringify(ids));
            await fetch('/admin/inventory', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
          }
        });
      });
    </script>
    <?php
}, $user);
