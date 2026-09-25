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
    $lines = preg_split('/
|
|
/', trim($text));
    if (!$lines || count($lines) < 2) return [];

    $rows = [];
    $header = str_getcsv((string) array_shift($lines));
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $cols = str_getcsv($line);
        if ($shop === 'snd') {
            // category,category,name,sku,shop_quantity,unit,default_note,description
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
            // category,name,shop_quantity,unit,default_note,description
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

    if ($action === 'create_item') {
        $stmt = db()->prepare('INSERT INTO inventory_items (shop_type, category_id, name, sku, shop_quantity, unit, default_note, description, is_spacer, sort_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            post('shop_type'),
            (int) post('category_id', '0') ?: null,
            post('name'),
            post('sku') ?: null,
            (int) post('shop_quantity', '0'),
            post('unit', 'ea'),
            post('default_note'),
            post('description'),
            (int) (isset($_POST['is_spacer']) ? 1 : 0),
            (int) post('sort_order', '0'),
        ]);
        flash_set('success', 'Item created.');
    }

    if ($action === 'delete_item') {
        $stmt = db()->prepare('DELETE FROM inventory_items WHERE id = ?');
        $stmt->execute([(int) post('id')]);
        flash_set('warning', 'Item deleted.');
    }

    if ($action === 'import') {
        $shop = post('shop_type');
        $rows = parse_import_rows($shop, post('import_text'));
        db()->beginTransaction();
        foreach ($rows as $row) {
            $catStmt = db()->prepare('SELECT id FROM inventory_categories WHERE shop_type = ? AND name = ? LIMIT 1');
            $catStmt->execute([$shop, $row['category']]);
            $catId = $catStmt->fetchColumn();
            if (!$catId) {
                $insCat = db()->prepare('INSERT INTO inventory_categories (shop_type, name, sort_order, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW())');
                $insCat->execute([$shop, $row['category']]);
                $catId = db()->lastInsertId();
            }
            $ins = db()->prepare('INSERT INTO inventory_items (shop_type, category_id, name, sku, shop_quantity, unit, default_note, description, is_spacer, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW(), NOW())');
            $ins->execute([$shop, (int) $catId, $row['name'], $row['sku'], $row['shop_quantity'], $row['unit'], $row['default_note'], $row['description']]);
        }
        db()->commit();
        flash_set('success', 'Import complete: ' . count($rows) . ' rows.');
    }

    redirect('/admin/inventory');
}

$items = db()->query('SELECT ii.*, ic.name AS category_name FROM inventory_items ii LEFT JOIN inventory_categories ic ON ic.id = ii.category_id ORDER BY ii.shop_type, ii.sort_order, ii.name')->fetchAll();
$cats = db()->query('SELECT id, shop_type, name FROM inventory_categories ORDER BY shop_type, sort_order, name')->fetchAll();

render_page('Inventory', function () use ($items, $cats): void {
    ?>
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card"><div class="card-header"><h3 class="card-title">Add Item</h3></div><div class="card-body">
                <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="create_item">
                    <div class="mb-2"><select class="form-select" name="shop_type"><option value="lx">Lighting</option><option value="snd">Sound</option></select></div>
                    <div class="mb-2"><select class="form-select" name="category_id"><option value="">No category</option><?php foreach($cats as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e(strtoupper($cat['shop_type']).' · '.$cat['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><input class="form-control" name="name" placeholder="Name" required></div>
                    <div class="mb-2"><input class="form-control" name="sku" placeholder="SKU (sound)"></div>
                    <div class="row g-2"><div class="col"><input class="form-control" type="number" name="shop_quantity" placeholder="Qty"></div><div class="col"><input class="form-control" name="unit" placeholder="Unit" value="ea"></div></div>
                    <div class="mb-2 mt-2"><textarea class="form-control" name="default_note" placeholder="Default note"></textarea></div>
                    <div class="mb-2"><textarea class="form-control" name="description" placeholder="Description"></textarea></div>
                    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_spacer"><span class="form-check-label">Spacer item (hidden from order)</span></label>
                    <div class="mb-2"><input class="form-control" type="number" name="sort_order" value="0" placeholder="Sort order"></div>
                    <button class="btn btn-primary">Create item</button>
                </form>
            </div></div>
            <div class="card mt-3"><div class="card-header"><h3 class="card-title">Import (Paste CSV Text)</h3></div><div class="card-body">
                <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="import">
                    <div class="mb-2"><select class="form-select" name="shop_type"><option value="snd">Sound</option><option value="lx">Lighting</option></select></div>
                    <div class="mb-2"><textarea class="form-control" name="import_text" rows="8" placeholder="Paste CSV text with header"></textarea></div>
                    <button class="btn btn-outline-primary">Import rows</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Items</h3></div>
                <div class="card-body"><input id="inventory-filter" class="form-control" placeholder="Live search"></div>
                <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Shop</th><th>Category</th><th>Name</th><th>SKU</th><th>Qty</th><th>Unit</th><th>Spacer</th><th></th></tr></thead><tbody id="inventory-sortable">
                    <?php foreach ($items as $item): ?>
                    <tr><td><?= e(strtoupper($item['shop_type'])) ?></td><td><?= e((string) $item['category_name']) ?></td><td><?= e($item['name']) ?></td><td><?= e((string) $item['sku']) ?></td><td><?= (int)$item['shop_quantity'] ?></td><td><?= e($item['unit']) ?></td><td><?= (int)$item['is_spacer'] ? 'Yes' : 'No' ?></td><td><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            </div>
        </div>
    </div>
    <script>
      new Sortable(document.getElementById('inventory-sortable'),{animation:150});
      const input = document.getElementById('inventory-filter');
      input?.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        document.querySelectorAll('#inventory-sortable tr').forEach((tr) => {
          tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
      });
    </script>
    <?php
}, $user);
