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

$user = require_auth();
$isAdmin = user_has_permission($user, 'admin.access');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');

    if ($action === 'save') {
        $showId = (int) post('show_id', '0');
        $payload = [
            post('show_name'),
            post('theatre_name'),
            post('shop_name'),
            post('lead_designer_name'),
            post('lead_designer_email', ''),
            post('lead_designer_phone', ''),
            post('ald_name'),
            post('ald_email', ''),
            post('ald_phone', ''),
            post('assistant_snd_designer_name'),
            post('assistant_snd_designer_email', ''),
            post('assistant_snd_designer_phone', ''),
            post('shop_manager_name'),
            post('shop_manager_email', ''),
            post('shop_manager_phone', ''),
            post('assistants_json', ''),
            post('pull_date', ''),
            post('return_date', ''),
            post('strike_date', ''),
            post('opening_date', ''),
            post('closing_date', ''),
            post('theatre_address', ''),
            post('shop_address', ''),
        ];

        if ($showId > 0) {
            if ($isAdmin) {
                $stmt = db()->prepare('UPDATE shows SET
                    show_name = ?, theatre_name = ?, shop_name = ?, lead_designer_name = ?, lead_designer_email = ?, lead_designer_phone = ?,
                    ald_name = ?, ald_email = ?, ald_phone = ?, assistant_snd_designer_name = ?, assistant_snd_designer_email = ?, assistant_snd_designer_phone = ?,
                    shop_manager_name = ?, shop_manager_email = ?, shop_manager_phone = ?, assistants_json = ?, pull_date = NULLIF(?, ""), return_date = NULLIF(?, ""),
                    strike_date = NULLIF(?, ""), opening_date = NULLIF(?, ""), closing_date = NULLIF(?, ""), theatre_address = ?, shop_address = ?, updated_at = NOW()
                    WHERE id = ? AND deleted_at IS NULL');
                $stmt->execute([...$payload, $showId]);
            } else {
                $stmt = db()->prepare('UPDATE shows SET
                    show_name = ?, theatre_name = ?, shop_name = ?, lead_designer_name = ?, lead_designer_email = ?, lead_designer_phone = ?,
                    ald_name = ?, ald_email = ?, ald_phone = ?, assistant_snd_designer_name = ?, assistant_snd_designer_email = ?, assistant_snd_designer_phone = ?,
                    shop_manager_name = ?, shop_manager_email = ?, shop_manager_phone = ?, assistants_json = ?, pull_date = NULLIF(?, ""), return_date = NULLIF(?, ""),
                    strike_date = NULLIF(?, ""), opening_date = NULLIF(?, ""), closing_date = NULLIF(?, ""), theatre_address = ?, shop_address = ?, updated_at = NOW()
                    WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL');
                $stmt->execute([...$payload, $showId, (int) $user['id']]);
            }
            flash_set('success', 'Show updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO shows (
                owner_user_id, show_name, theatre_name, shop_name, lead_designer_name, lead_designer_email, lead_designer_phone,
                ald_name, ald_email, ald_phone, assistant_snd_designer_name, assistant_snd_designer_email, assistant_snd_designer_phone,
                shop_manager_name, shop_manager_email, shop_manager_phone, assistants_json, pull_date, return_date, strike_date,
                opening_date, closing_date, theatre_address, shop_address, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""),
                NULLIF(?, ""), NULLIF(?, ""), ?, ?, NOW(), NOW()
            )');
            $stmt->execute([(int) $user['id'], ...$payload]);
            flash_set('success', 'Show created.');
        }
    }

    if ($action === 'delete' && $isAdmin) {
        $stmt = db()->prepare('UPDATE shows SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([(int) post('show_id')]);
        flash_set('warning', $stmt->rowCount() > 0 ? 'Show deleted.' : 'Show not found.');
    }

    redirect('/dash/shows');
}

if ($isAdmin) {
    $shows = db()->query('SELECT s.*, u.email AS owner_email FROM shows s JOIN users u ON u.id = s.owner_user_id WHERE s.deleted_at IS NULL ORDER BY s.updated_at DESC, s.id DESC')->fetchAll();
} else {
    $stmt = db()->prepare('SELECT s.*, u.email AS owner_email FROM shows s JOIN users u ON u.id = s.owner_user_id WHERE s.deleted_at IS NULL AND s.owner_user_id = ? ORDER BY s.updated_at DESC, s.id DESC');
    $stmt->execute([(int) $user['id']]);
    $shows = $stmt->fetchAll();
}

$editId = (int) ($_GET['edit'] ?? 0);
$editingShow = null;
foreach ($shows as $show) {
    if ((int) $show['id'] === $editId) {
        $editingShow = $show;
        break;
    }
}

render_page('Shows', function () use ($shows, $editingShow, $isAdmin): void {
    $value = static fn (string $key) => $editingShow[$key] ?? '';
    ?>
    <div class="row row-cards">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?= $editingShow ? 'Edit Show' : 'Create Show' ?></h3></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="show_id" value="<?= (int) ($editingShow['id'] ?? 0) ?>">
                        <h4 class="mb-2">Required</h4>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6"><input class="form-control" name="show_name" placeholder="Show Name" value="<?= e((string) $value('show_name')) ?>" required></div>
                            <div class="col-md-6"><input class="form-control" name="theatre_name" placeholder="Theatre Name" value="<?= e((string) $value('theatre_name')) ?>" required></div>
                            <div class="col-md-6"><input class="form-control" name="shop_name" placeholder="Shop Name" value="<?= e((string) $value('shop_name')) ?>" required></div>
                            <div class="col-md-6"><input class="form-control" name="lead_designer_name" placeholder="LD / SND Designer" value="<?= e((string) $value('lead_designer_name')) ?>" required></div>
                            <div class="col-md-6"><input class="form-control" name="ald_name" placeholder="ALD" value="<?= e((string) $value('ald_name')) ?>" required></div>
                            <div class="col-md-6"><input class="form-control" name="assistant_snd_designer_name" placeholder="Assistant LX / Assistant Sound" value="<?= e((string) $value('assistant_snd_designer_name')) ?>" required></div>
                            <div class="col-md-6"><input class="form-control" name="shop_manager_name" placeholder="Production Electrician / Production Audio" value="<?= e((string) $value('shop_manager_name')) ?>" required></div>
                        </div>
                        <h4 class="mb-2">Optional</h4>
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control" name="lead_designer_email" placeholder="LD/SND Email" value="<?= e((string) $value('lead_designer_email')) ?>"></div>
                            <div class="col-md-6"><input class="form-control" name="lead_designer_phone" placeholder="LD/SND Phone" value="<?= e((string) $value('lead_designer_phone')) ?>"></div>
                            <div class="col-md-6"><input class="form-control" name="ald_email" placeholder="ALD Email" value="<?= e((string) $value('ald_email')) ?>"></div>
                            <div class="col-md-6"><input class="form-control" name="ald_phone" placeholder="ALD Phone" value="<?= e((string) $value('ald_phone')) ?>"></div>
                            <div class="col-md-6"><input class="form-control" name="assistant_snd_designer_email" placeholder="Assistant LX/Sound Email" value="<?= e((string) $value('assistant_snd_designer_email')) ?>"></div>
                            <div class="col-md-6"><input class="form-control" name="assistant_snd_designer_phone" placeholder="Assistant LX/Sound Phone" value="<?= e((string) $value('assistant_snd_designer_phone')) ?>"></div>
                            <div class="col-md-6"><input class="form-control" name="shop_manager_email" placeholder="Production Electrician/Audio Email" value="<?= e((string) $value('shop_manager_email')) ?>"></div>
                            <div class="col-md-6"><input class="form-control" name="shop_manager_phone" placeholder="Production Electrician/Audio Phone" value="<?= e((string) $value('shop_manager_phone')) ?>"></div>
                            <div class="col-md-6"><label class="form-label mb-1">Pull Date</label><input class="form-control" type="date" name="pull_date" value="<?= e((string) $value('pull_date')) ?>"></div>
                            <div class="col-md-6"><label class="form-label mb-1">Return Date</label><input class="form-control" type="date" name="return_date" value="<?= e((string) $value('return_date')) ?>"></div>
                            <div class="col-md-6"><label class="form-label mb-1">Strike Date</label><input class="form-control" type="date" name="strike_date" value="<?= e((string) $value('strike_date')) ?>"></div>
                            <div class="col-md-6"><label class="form-label mb-1">Opening Date</label><input class="form-control" type="date" name="opening_date" value="<?= e((string) $value('opening_date')) ?>"></div>
                            <div class="col-md-6"><label class="form-label mb-1">Closing Date</label><input class="form-control" type="date" name="closing_date" value="<?= e((string) $value('closing_date')) ?>"></div>
                            <div class="col-12"><textarea class="form-control" name="assistants_json" rows="2" placeholder="Assist Shop Managers (one per line or JSON)"><?= e((string) $value('assistants_json')) ?></textarea></div>
                            <div class="col-md-6"><textarea class="form-control" name="theatre_address" rows="2" placeholder="Theatre Address"><?= e((string) $value('theatre_address')) ?></textarea></div>
                            <div class="col-md-6"><textarea class="form-control" name="shop_address" rows="2" placeholder="Shop Address"><?= e((string) $value('shop_address')) ?></textarea></div>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-primary"><?= $editingShow ? 'Save Show' : 'Create Show' ?></button>
                            <?php if ($editingShow): ?><a href="/dash/shows" class="btn btn-outline-secondary">Cancel Edit</a><?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Shows</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead><tr><th>Show</th><th>Owner</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php if (!$shows): ?>
                            <tr><td colspan="3" class="text-secondary">No shows yet. Add one using the form.</td></tr>
                        <?php else: ?>
                            <?php foreach ($shows as $show): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($show['show_name']) ?></strong>
                                        <div class="small text-secondary"><?= e($show['theatre_name']) ?> · <?= e($show['shop_name']) ?></div>
                                    </td>
                                    <td><?= e($show['owner_email']) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="/dash/shows?edit=<?= (int) $show['id'] ?>">Edit</a>
                                        <?php if ($isAdmin): ?>
                                            <form method="post" class="d-inline-block ms-1" onsubmit="return confirm('Delete show?')">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="show_id" value="<?= (int) $show['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}, $user);
