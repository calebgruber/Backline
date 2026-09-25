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

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM inventory_categories WHERE id = ?');
        $stmt->execute([(int) post('id')]);
        flash_set('warning', 'Category deleted.');
    }

    if ($action === 'update') {
        $stmt = db()->prepare('UPDATE inventory_categories SET name = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
        $stmt->execute([post('name'), (int) post('sort_order', '0'), (int) post('id'), $shop]);
        flash_set('success', 'Category updated.');
    }

    if ($action === 'clear_shop_categories') {
        $stmt = db()->prepare('DELETE FROM inventory_categories WHERE shop_type = ?');
        $stmt->execute([$shop]);
        flash_set('warning', strtoupper($shop) . ' categories cleared.');
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
    <div class="row row-cards">
        <?php foreach ($shops as $shopKey => $shopLabel): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title"><?= e($shopLabel) ?></h3>
                        <div class="ms-auto">
                            <form method="post" onsubmit="return confirm('Clear all <?= e(strtoupper($shopKey)) ?> categories?')">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="clear_shop_categories">
                                <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                <button class="btn btn-outline-danger btn-sm">Clear <?= e(strtoupper($shopKey)) ?> Categories</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-2">Create <?= e(strtoupper($shopKey)) ?> Category</h4>
                        <form method="post" class="row g-2">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                            <div class="col-md-6"><input class="form-control" name="name" placeholder="Category Name" required></div>
                            <div class="col-md-3"><input class="form-control" type="number" name="sort_order" placeholder="Sort Order" value="0"></div>
                            <div class="col-md-3"><button class="btn btn-primary w-100">Create Category</button></div>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title"><?= e($shopLabel) ?> List</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead><tr><th>Name</th><th>Sort</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($categoriesByShop[$shopKey] as $cat): ?>
                                <tr>
                                    <td>
                                        <input class="form-control form-control-sm" name="name" value="<?= e($cat['name']) ?>" required form="cat-update-<?= (int) $cat['id'] ?>">
                                    </td>
                                    <td>
                                        <input class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int) $cat['sort_order'] ?>" form="cat-update-<?= (int) $cat['id'] ?>">
                                    </td>
                                    <td class="text-end">
                                        <form id="cat-update-<?= (int) $cat['id'] ?>" method="post" class="d-inline-block">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                            <button class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                        <form method="post" class="d-inline-block ms-2">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}, $user);
