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

function categories_has_subcategory_table(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'inventory_subcategories'");
        $exists = (bool) $stmt?->fetchColumn();
        if (!$exists) {
            db()->exec(
                'CREATE TABLE IF NOT EXISTS inventory_subcategories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    shop_type ENUM("lx","snd") NOT NULL,
                    category_id INT NULL,
                    name VARCHAR(190) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_inventory_subcategories_shop_sort (shop_type, sort_order, name),
                    INDEX idx_inventory_subcategories_category (category_id),
                    CONSTRAINT fk_inventory_subcategories_category
                        FOREIGN KEY (category_id) REFERENCES inventory_categories(id)
                        ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $stmt = db()->query("SHOW TABLES LIKE 'inventory_subcategories'");
            $exists = (bool) $stmt?->fetchColumn();
        }
    } catch (Throwable) {
        $exists = false;
    }
    return $exists;
}

$hasSubcategoryTable = categories_has_subcategory_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');
    $shop = post('shop_type');

    if ($action === 'create') {
        $requestedSortOrder = (int) post('sort_order', '0');
        if ($requestedSortOrder <= 0) {
            $nextSortStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM inventory_categories WHERE shop_type = ?');
            $nextSortStmt->execute([$shop]);
            $requestedSortOrder = (int) $nextSortStmt->fetchColumn();
        }
        $stmt = db()->prepare('INSERT INTO inventory_categories (shop_type, name, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$shop, post('name'), $requestedSortOrder]);
        flash_set('success', strtoupper($shop) . ' category created.');
    }

    if ($action === 'update') {
        $stmt = db()->prepare('UPDATE inventory_categories SET name = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
        $stmt->execute([post('name'), (int) post('sort_order', '0'), (int) post('id'), $shop]);
        flash_set('success', 'Category updated.');
    }

    if ($action === 'save_category_live') {
        $stmt = db()->prepare('UPDATE inventory_categories SET name = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
        $stmt->execute([post('name'), (int) post('sort_order', '0'), (int) post('id'), $shop]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
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

    if ($hasSubcategoryTable && $action === 'create_subcategory') {
        $requestedSortOrder = (int) post('sort_order', '0');
        if ($requestedSortOrder <= 0) {
            $nextSortStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM inventory_subcategories WHERE shop_type = ?');
            $nextSortStmt->execute([$shop]);
            $requestedSortOrder = (int) $nextSortStmt->fetchColumn();
        }
        $categoryId = (int) post('category_id', '0');
        $categoryId = $categoryId > 0 ? $categoryId : null;
        $stmt = db()->prepare('INSERT INTO inventory_subcategories (shop_type, category_id, name, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$shop, $categoryId, post('name'), $requestedSortOrder]);
        flash_set('success', strtoupper($shop) . ' subcategory created.');
    }

    if ($hasSubcategoryTable && $action === 'update_subcategory') {
        $categoryId = (int) post('category_id', '0');
        $categoryId = $categoryId > 0 ? $categoryId : null;
        $stmt = db()->prepare('UPDATE inventory_subcategories SET category_id = ?, name = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
        $stmt->execute([$categoryId, post('name'), (int) post('sort_order', '0'), (int) post('id'), $shop]);
        flash_set('success', 'Subcategory updated.');
    }

    if ($hasSubcategoryTable && $action === 'save_subcategory_live') {
        $categoryId = (int) post('category_id', '0');
        $categoryId = $categoryId > 0 ? $categoryId : null;
        $stmt = db()->prepare('UPDATE inventory_subcategories SET category_id = ?, name = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
        $stmt->execute([$categoryId, post('name'), (int) post('sort_order', '0'), (int) post('id'), $shop]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($hasSubcategoryTable && $action === 'delete_subcategory') {
        $stmt = db()->prepare('DELETE FROM inventory_subcategories WHERE id = ? AND shop_type = ?');
        $stmt->execute([(int) post('id'), $shop]);
        flash_set('warning', 'Subcategory deleted.');
    }

    if ($hasSubcategoryTable && $action === 'clear_shop_subcategories') {
        $stmt = db()->prepare('DELETE FROM inventory_subcategories WHERE shop_type = ?');
        $stmt->execute([$shop]);
        flash_set('warning', strtoupper($shop) . ' subcategories cleared.');
    }

    if ($hasSubcategoryTable && $action === 'reorder_subcategories') {
        $orderedIds = json_decode((string) ($_POST['ordered_ids'] ?? '[]'), true);
        if (is_array($orderedIds)) {
            db()->beginTransaction();
            $stmt = db()->prepare('UPDATE inventory_subcategories SET sort_order = ?, updated_at = NOW() WHERE id = ? AND shop_type = ?');
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
$subcategoriesByShop = ['lx' => [], 'snd' => []];
if ($hasSubcategoryTable) {
    $subcategoryRows = db()->query('SELECT sc.*, ic.name AS category_name FROM inventory_subcategories sc LEFT JOIN inventory_categories ic ON ic.id = sc.category_id ORDER BY sc.shop_type, sc.sort_order, sc.name')->fetchAll();
    foreach ($subcategoryRows as $subcat) {
        $subcategoriesByShop[$subcat['shop_type']][] = $subcat;
    }
}

render_page('Categories', function () use ($categoriesByShop, $subcategoriesByShop, $hasSubcategoryTable): void {
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
                        <div class="list-group list-group-flush category-manager-list" id="category-list-<?= e($shopKey) ?>" data-shop="<?= e($shopKey) ?>" data-sortable='{"animation":150,"handle":".sortable-handle"}'>
                            <?php foreach ($categoriesByShop[$shopKey] as $cat): ?>
                                <div class="list-group-item" data-category-id="<?= (int) $cat['id'] ?>">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-auto">
                                            <span class="sortable-handle cursor-move text-secondary d-inline-flex align-items-center" title="Drag to reorder" aria-hidden="true">
                                                <i class="ti ti-grip-vertical"></i>
                                            </span>
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
                                        <div class="card-body border-top py-2">
                                            <div class="small text-secondary js-category-save-status" data-shop="<?= e($shopKey) ?>">All category changes auto-save as you edit.</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php if ($hasSubcategoryTable): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title"><?= e($shopLabel) ?> Subcategories</h3>
                            <div class="ms-auto">
                                <form method="post" onsubmit="return confirm('Clear all <?= e(strtoupper($shopKey)) ?> subcategories?')">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="clear_shop_subcategories">
                                    <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                    <button class="btn btn-outline-danger btn-sm">Clear <?= e(strtoupper($shopKey)) ?> Subcategories</button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body border-bottom">
                            <form method="post" class="row g-2 align-items-center">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="create_subcategory">
                                <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                <div class="col-md-4"><input class="form-control" name="name" placeholder="New subcategory name" required></div>
                                <div class="col-md-3">
                                    <select class="form-select" name="category_id">
                                        <option value="0">All Categories</option>
                                        <?php foreach ($categoriesByShop[$shopKey] as $cat): ?>
                                            <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><input class="form-control" type="number" name="sort_order" value="0" placeholder="Sort"></div>
                                <div class="col-md-3"><button class="btn btn-primary w-100"><i class="ti ti-folder-plus me-1"></i>Create Subcategory</button></div>
                            </form>
                        </div>
                        <div class="list-group list-group-flush subcategory-manager-list" id="subcategory-list-<?= e($shopKey) ?>" data-shop="<?= e($shopKey) ?>" data-sortable='{"animation":150,"handle":".sortable-handle"}'>
                            <?php foreach ($subcategoriesByShop[$shopKey] as $subcat): ?>
                                <div class="list-group-item" data-subcategory-id="<?= (int) $subcat['id'] ?>">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-auto">
                                            <span class="sortable-handle cursor-move text-secondary d-inline-flex align-items-center" title="Drag to reorder" aria-hidden="true">
                                                <i class="ti ti-grip-vertical"></i>
                                            </span>
                                        </div>
                                        <div class="col-md-4"><input class="form-control" name="name" value="<?= e($subcat['name']) ?>" form="subcat-update-<?= (int) $subcat['id'] ?>" required></div>
                                        <div class="col-md-3">
                                            <select class="form-select" name="category_id" form="subcat-update-<?= (int) $subcat['id'] ?>">
                                                <option value="0" <?= (int) ($subcat['category_id'] ?? 0) === 0 ? 'selected' : '' ?>>All Categories</option>
                                                <?php foreach ($categoriesByShop[$shopKey] as $cat): ?>
                                                    <option value="<?= (int) $cat['id'] ?>" <?= (int) $cat['id'] === (int) ($subcat['category_id'] ?? 0) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2"><input class="form-control" type="number" name="sort_order" value="<?= (int) $subcat['sort_order'] ?>" form="subcat-update-<?= (int) $subcat['id'] ?>"></div>
                                        <div class="col-md text-end">
                                            <form id="subcat-update-<?= (int) $subcat['id'] ?>" method="post" class="d-inline-block">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="update_subcategory">
                                                <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $subcat['id'] ?>">
                                                <button class="btn btn-icon btn-sm btn-primary" title="Update"><i class="ti ti-check"></i></button>
                                            </form>
                                            <form method="post" class="d-inline-block ms-1">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="delete_subcategory">
                                                <input type="hidden" name="shop_type" value="<?= e($shopKey) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $subcat['id'] ?>">
                                                <button class="btn btn-icon btn-sm btn-outline-danger" title="Delete"><i class="ti ti-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-body border-top py-2">
                            <div class="small text-secondary js-subcategory-save-status" data-shop="<?= e($shopKey) ?>">All subcategory changes auto-save as you edit.</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <script>
      document.querySelectorAll('.category-manager-list').forEach((list) => {
        const SortableLib = window.Sortable;
        if (!SortableLib) return;
        let config = { animation: 150, handle: '.sortable-handle' };
        const rawConfig = list.getAttribute('data-sortable') || '';
        if (rawConfig) {
          try {
            config = { ...config, ...JSON.parse(rawConfig) };
          } catch {}
        }
        new SortableLib(list, {
          ...config,
          ghostClass: 'sortable-ghost',
          chosenClass: 'sortable-chosen',
          onEnd: async () => {
            const shopType = list.getAttribute('data-shop') || '';
            const ids = Array.from(list.querySelectorAll('[data-category-id]')).map((el) => Number(el.getAttribute('data-category-id')));
            list.querySelectorAll('[data-category-id]').forEach((row, idx) => {
              const sortInput = row.querySelector('input[name="sort_order"]');
              if (sortInput instanceof HTMLInputElement) sortInput.value = String(idx + 1);
            });
            const body = new URLSearchParams();
            body.set('_csrf', '<?= e(csrf_token()) ?>');
            body.set('action', 'reorder_categories');
            body.set('shop_type', shopType);
            body.set('ordered_ids', JSON.stringify(ids));
            const response = await fetch('/admin/categories', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: body.toString(),
            });
            if (!response.ok) {
              alert('Could not save new category order. Please try again.');
            }
          }
        });
      });
      document.querySelectorAll('.subcategory-manager-list').forEach((list) => {
        const SortableLib = window.Sortable;
        if (!SortableLib) return;
        new SortableLib(list, {
          animation: 150,
          handle: '.sortable-handle',
          ghostClass: 'sortable-ghost',
          chosenClass: 'sortable-chosen',
          onEnd: async () => {
            const shopType = list.getAttribute('data-shop') || '';
            const ids = Array.from(list.querySelectorAll('[data-subcategory-id]')).map((el) => Number(el.getAttribute('data-subcategory-id')));
            list.querySelectorAll('[data-subcategory-id]').forEach((row, idx) => {
              const sortInput = row.querySelector('input[name="sort_order"]');
              if (sortInput instanceof HTMLInputElement) sortInput.value = String(idx + 1);
            });
            const body = new URLSearchParams();
            body.set('_csrf', '<?= e(csrf_token()) ?>');
            body.set('action', 'reorder_subcategories');
            body.set('shop_type', shopType);
            body.set('ordered_ids', JSON.stringify(ids));
            const response = await fetch('/admin/categories', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: body.toString(),
            });
            if (!response.ok) {
              alert('Could not save new subcategory order. Please try again.');
            }
          }
        });
      });

      (() => {
        const saveTimers = new Map();
        const setStatus = (selector, shop, text) => {
          const el = document.querySelector(`${selector}[data-shop="${shop}"]`);
          if (el) el.textContent = text;
        };

        document.querySelectorAll('.category-manager-list [data-category-id]').forEach((row) => {
          const shop = row.closest('.category-manager-list')?.getAttribute('data-shop') || '';
          const id = row.getAttribute('data-category-id') || '';
          const nameInput = row.querySelector('input[name="name"]');
          const sortInput = row.querySelector('input[name="sort_order"]');
          if (!nameInput || !sortInput || !shop || !id) return;
          const triggerSave = () => {
            const key = `cat:${shop}:${id}`;
            if (saveTimers.has(key)) window.clearTimeout(saveTimers.get(key));
            saveTimers.set(key, window.setTimeout(async () => {
              setStatus('.js-category-save-status', shop, 'Saving…');
              const body = new URLSearchParams();
              body.set('_csrf', '<?= e(csrf_token()) ?>');
              body.set('action', 'save_category_live');
              body.set('shop_type', shop);
              body.set('id', id);
              body.set('name', nameInput.value || '');
              body.set('sort_order', sortInput.value || '0');
              try {
                const response = await fetch('/admin/categories', {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
                  body: body.toString(),
                });
                const payload = await response.json();
                if (!response.ok || !payload?.ok) throw new Error('save failed');
                setStatus('.js-category-save-status', shop, 'Saved');
              } catch {
                setStatus('.js-category-save-status', shop, 'Autosave failed. Use update button.');
              }
            }, 300));
          };
          nameInput.addEventListener('input', triggerSave);
          nameInput.addEventListener('change', triggerSave);
          sortInput.addEventListener('input', triggerSave);
          sortInput.addEventListener('change', triggerSave);
        });

        document.querySelectorAll('.subcategory-manager-list [data-subcategory-id]').forEach((row) => {
          const shop = row.closest('.subcategory-manager-list')?.getAttribute('data-shop') || '';
          const id = row.getAttribute('data-subcategory-id') || '';
          const nameInput = row.querySelector('input[name="name"]');
          const sortInput = row.querySelector('input[name="sort_order"]');
          const catSelect = row.querySelector('select[name="category_id"]');
          if (!nameInput || !sortInput || !catSelect || !shop || !id) return;
          const triggerSave = () => {
            const key = `sub:${shop}:${id}`;
            if (saveTimers.has(key)) window.clearTimeout(saveTimers.get(key));
            saveTimers.set(key, window.setTimeout(async () => {
              setStatus('.js-subcategory-save-status', shop, 'Saving…');
              const body = new URLSearchParams();
              body.set('_csrf', '<?= e(csrf_token()) ?>');
              body.set('action', 'save_subcategory_live');
              body.set('shop_type', shop);
              body.set('id', id);
              body.set('name', nameInput.value || '');
              body.set('sort_order', sortInput.value || '0');
              body.set('category_id', catSelect.value || '0');
              try {
                const response = await fetch('/admin/categories', {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
                  body: body.toString(),
                });
                const payload = await response.json();
                if (!response.ok || !payload?.ok) throw new Error('save failed');
                setStatus('.js-subcategory-save-status', shop, 'Saved');
              } catch {
                setStatus('.js-subcategory-save-status', shop, 'Autosave failed. Use update button.');
              }
            }, 300));
          };
          nameInput.addEventListener('input', triggerSave);
          nameInput.addEventListener('change', triggerSave);
          sortInput.addEventListener('input', triggerSave);
          sortInput.addEventListener('change', triggerSave);
          catSelect.addEventListener('change', triggerSave);
        });
      })();
    </script>
    <?php
}, $user);
