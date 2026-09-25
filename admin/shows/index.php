<?php

declare(strict_types=1);

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (post('action') === 'create') {
        $stmt = db()->prepare('INSERT INTO shows (owner_user_id, show_name, theatre_name, shop_name, lead_designer_name, ald_name, assistant_snd_designer_name, shop_manager_name, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([(int) $user['id'], post('show_name'), post('theatre_name'), post('shop_name'), post('lead_designer_name'), post('ald_name'), post('assistant_snd_designer_name'), post('shop_manager_name')]);
        flash_set('success', 'Show created.');
    }
    if (post('action') === 'delete' && user_has_permission($user, 'shows.delete')) {
        $stmt = db()->prepare('UPDATE shows SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([(int) post('show_id')]);
        flash_set('warning', 'Show deleted.');
    }
    redirect('/admin/shows');
}

if (user_has_permission($user, 'admin.access')) {
    $shows = db()->query('SELECT s.*, u.email AS owner_email FROM shows s JOIN users u ON u.id = s.owner_user_id WHERE s.deleted_at IS NULL ORDER BY s.id DESC')->fetchAll();
} else {
    $stmt = db()->prepare('SELECT s.*, u.email AS owner_email FROM shows s JOIN users u ON u.id = s.owner_user_id WHERE s.deleted_at IS NULL AND s.owner_user_id = ? ORDER BY s.id DESC');
    $stmt->execute([(int) $user['id']]);
    $shows = $stmt->fetchAll();
}

render_page('Shows', function () use ($shows, $user): void {
    ?>
    <div class="row row-cards">
        <div class="col-lg-5"><div class="card"><div class="card-header"><h3 class="card-title">Create Show</h3></div><div class="card-body">
            <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="create">
                <div class="mb-2"><input class="form-control" name="show_name" placeholder="Show Name" required></div>
                <div class="mb-2"><input class="form-control" name="theatre_name" placeholder="Theatre Name" required></div>
                <div class="mb-2"><input class="form-control" name="shop_name" placeholder="Shop Name" required></div>
                <div class="mb-2"><input class="form-control" name="lead_designer_name" placeholder="LD / SND Designer" required></div>
                <div class="mb-2"><input class="form-control" name="ald_name" placeholder="ALD" required></div>
                <div class="mb-2"><input class="form-control" name="assistant_snd_designer_name" placeholder="Assistant SND Designer PE" required></div>
                <div class="mb-2"><input class="form-control" name="shop_manager_name" placeholder="Shop Manager" required></div>
                <button class="btn btn-primary">Create show</button>
            </form>
        </div></div></div>
        <div class="col-lg-7"><div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>Show</th><th>Owner</th><th></th></tr></thead><tbody>
            <?php foreach($shows as $show): ?>
            <tr><td><?= e($show['show_name']) ?></td><td><?= e($show['owner_email']) ?></td><td>
                <?php if (user_has_permission($user, 'shows.delete')): ?>
                <form method="post" class="d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="show_id" value="<?= (int)$show['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                <?php endif; ?>
            </td></tr>
            <?php endforeach; ?>
        </tbody></table></div></div></div>
    </div>
    <?php
}, $user);
