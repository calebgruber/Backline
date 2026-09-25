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
    if ($action === 'create') {
        $stmt = db()->prepare('INSERT INTO inventory_categories (shop_type, name, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([post('shop_type'), post('name'), (int) post('sort_order', '0')]);
    }
    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM inventory_categories WHERE id = ?');
        $stmt->execute([(int) post('id')]);
    }
    redirect('/admin/categories');
}

$categories = db()->query('SELECT * FROM inventory_categories ORDER BY shop_type, sort_order, name')->fetchAll();

render_page('Categories', function () use ($categories): void {
    ?>
    <div class="row row-cards">
        <div class="col-lg-4"><div class="card"><div class="card-header"><h3 class="card-title">Create Category</h3></div><div class="card-body">
            <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="create">
                <div class="mb-3"><select name="shop_type" class="form-select"><option value="lx">Lighting</option><option value="snd">Sound</option></select></div>
                <div class="mb-3"><input class="form-control" name="name" placeholder="Category Name" required></div>
                <div class="mb-3"><input class="form-control" type="number" name="sort_order" placeholder="Sort Order" value="0"></div>
                <button class="btn btn-primary">Create</button>
            </form>
        </div></div></div>
        <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>Shop</th><th>Name</th><th>Sort</th><th></th></tr></thead><tbody id="category-sortable">
            <?php foreach ($categories as $cat): ?>
                <tr data-id="<?= (int) $cat['id'] ?>"><td><?= e(strtoupper($cat['shop_type'])) ?></td><td><?= e($cat['name']) ?></td><td><?= (int) $cat['sort_order'] ?></td><td><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $cat['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
            <?php endforeach; ?>
        </tbody></table></div></div></div>
    </div>
    <script>new Sortable(document.getElementById('category-sortable'),{animation:150});</script>
    <?php
}, $user);
