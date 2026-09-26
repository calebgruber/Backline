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

$user = require_permission('categories.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');
    $shop = post('shop_type');

    if ($action === 'create') {
        $stmt = db()->prepare('INSERT INTO inventory_categories (shop_type, name, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$shop, post('name'), (int) post('sort_order', '0')]);
        flash_set('success', strtoupper($shop) . ' category created.');
    }

    if ($action === 'update') {
        $stmt = db()->prepare('UPDATE inventory_categories SET name = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
        $stmt->execute([post('name'), (int) post('sort_order', '0'), (int) post('id'), $shop]);
        flash_set('success', 'Category updated.');
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM inventory_categories WHERE id = ? AND shop_type = ?');
        $stmt->execute([(int) post('id'), $shop]);
        flash_set('warning', 'Category deleted.');
    }

    if ($action === 'clear_shop_categories') {
        $stmt = db()->prepare('DELETE FROM inventory_categories WHERE shop_type = ?');
        $stmt->execute([$shop]);
        flash_set('warning', strtoupper($shop) . ' categories cleared.');
    }

    if ($action === 'reorder_categories') {
        $orderedIds = json_decode((string) ($_POST['ordered_ids'] ?? '[]'), true);
        if (is_array($orderedIds)) {
            db()->beginTransaction();
            $stmt = db()->prepare('UPDATE inventory_categories SET sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
            foreach (array_values($orderedIds) as $idx => $id) {
                $stmt->execute([$idx + 1, (int) $id, $shop]);
            }
            db()->commit();
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    redirect('/admin/categories');
}

$categories = db()->query('SELECT * FROM inventory_categories ORDER BY shop_type, sort_order, name')->fetchAll();
$categoriesByShop = ['lx' => [], 'snd' => []];
foreach ($categories as $cat) {
    $categoriesByShop[$cat['shop_type']][] = $cat;
}

render_page('Categories', function () use ($categoriesByShop): void {
    $shops = [
        'lx' => 'Lighting Categories',
        'snd' => 'Sound Categories',
    ];
    ?>
    <ul class="nav nav-tabs mb-3" data-bs-toggle="tabs" role="tablist">
        <li class="nav-item" role="presentation"><a href="#cat-lx" class="nav-link active" data-bs-toggle="tab" aria-selected="true" role="tab">LX</a></li>
        <li class="nav-item" role="presentation"><a href="#cat-snd" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab">Sound</a></li>
    </ul>

    <div class="tab-content">
    <?php foreach ($shops as $shopKey => $shopLabel): ?>
        <div class="tab-pane <?= $shopKey === 'lx' ? 'active show' : '' ?>" id="cat-<?= e($shopKey) ?>">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title"><?= e($shopLabel) ?> File Manager</h3>
                            <div class="ms-auto">
                                <form method="post" onsubmit="return confirm('Clear all <?= e(strtoupper($shopKey)) ?> categories?')">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="clear_shop_categories">
                                    <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                    <button class="btn btn-outline-danger btn-sm">Clear <?= e(strtoupper($shopKey)) ?> Categories</button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body border-bottom">
                            <form method="post" class="row g-2 align-items-center">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                <div class="col-md-6"><input class="form-control" name="name" placeholder="New folder/category name" required></div>
                                <div class="col-md-3"><input class="form-control" type="number" name="sort_order" value="0" placeholder="Sort"></div>
                                <div class="col-md-3"><button class="btn btn-primary w-100"><i class="ti ti-folder-plus me-1"></i>Create Folder</button></div>
                            </form>
                        </div>
                        <div class="list-group list-group-flush category-manager-list" id="category-list-<?= e($shopKey) ?>" data-shop="<?= e($shopKey) ?>">
                            <?php foreach ($categoriesByShop[$shopKey] as $cat): ?>
                                <div class="list-group-item" data-category-id="<?= (int) $cat['id'] ?>">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-icon btn-ghost-secondary drag-handle" title="Drag to reorder" aria-label="Drag to reorder">
                                                <i class="ti ti-grip-vertical"></i>
                                            </button>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-center gap-2">
                                            <i class="ti ti-folder text-primary"></i>
                                            <input class="form-control" name="name" value="<?= e($cat['name']) ?>" form="cat-update-<?= (int) $cat['id'] ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input class="form-control" type="number" name="sort_order" value="<?= (int) $cat['sort_order'] ?>" form="cat-update-<?= (int) $cat['id'] ?>">
                                        </div>
                                        <div class="col-md text-end">
                                            <form id="cat-update-<?= (int) $cat['id'] ?>" method="post" class="d-inline-block">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                                <button class="btn btn-icon btn-sm btn-primary" title="Update"><i class="ti ti-check"></i></button>
                                            </form>
                                            <form method="post" class="d-inline-block ms-1">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                                <button class="btn btn-icon btn-sm btn-outline-danger" title="Delete"><i class="ti ti-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <script>
      document.querySelectorAll('.category-manager-list').forEach((list) => {
        new Sortable(list, {
          animation: 120,
          handle: '.drag-handle',
          ghostClass: 'sortable-ghost',
          chosenClass: 'sortable-chosen',
          onEnd: async () => {
            const shopType = list.getAttribute('data-shop') || '';
            const ids = Array.from(list.querySelectorAll('[data-category-id]')).map((el) => Number(el.getAttribute('data-category-id')));
            const body = new URLSearchParams();
            body.set('_csrf', '<?= e(csrf_token()) ?>');
            body.set('action', 'reorder_categories');
            body.set('shop_type', shopType);
            body.set('ordered_ids', JSON.stringify(ids));
            await fetch('/admin/categories', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
          }
        });
      });
    </script>
    <?php
}, $user);
