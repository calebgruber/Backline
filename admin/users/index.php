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

$user = require_permission('users.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');

    if ($action === 'invite') {
        $name = post('name');
        $email = mb_strtolower(post('email'));
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, is_super_admin, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())');
        $stmt->execute([$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $newUserId = (int) db()->lastInsertId();

        foreach (['admin.access', 'inventory.manage', 'categories.manage', 'users.manage', 'shows.delete', 'resources.manage', 'lx.access', 'snd.access'] as $perm) {
            if (!isset($_POST['perm_' . $perm])) {
                continue;
            }
            $pidStmt = db()->prepare('SELECT id FROM permissions WHERE key_name = ? LIMIT 1');
            $pidStmt->execute([$perm]);
            $pid = $pidStmt->fetchColumn();
            if ($pid) {
                $link = db()->prepare('INSERT IGNORE INTO user_permissions (user_id, permission_id, created_at) VALUES (?, ?, NOW())');
                $link->execute([$newUserId, (int) $pid]);
            }
        }

        $token = bin2hex(random_bytes(32));
        $stmt = db()->prepare('INSERT INTO password_tokens (user_id, token_hash, token_type, expires_at, created_at) VALUES (?, ?, "invite", DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())');
        $stmt->execute([$newUserId, hash('sha256', $token)]);
        $url = (config('base_url') ?: '') . '/auth/invite?token=' . urlencode($token);
        send_basic_mail($email, 'Backline invite', 'Open this link within 24 hours to set your password: <a href="' . e($url) . '">' . e($url) . '</a>');

        flash_set('success', 'User invited.');
        audit_log((int) $user['id'], 'user', 'invite', $newUserId, ['email' => $email]);
    }

    if ($action === 'delete') {
        $target = (int) post('user_id');
        $stmt = db()->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$target]);
        flash_set('warning', 'User deleted.');
        audit_log((int) $user['id'], 'user', 'delete', $target);
    }

    redirect('/admin/users');
}

$users = db()->query('SELECT id, name, email, created_at FROM users WHERE deleted_at IS NULL ORDER BY id DESC')->fetchAll();

render_page('Users', function () use ($users): void {
    $perms = ['admin.access', 'inventory.manage', 'categories.manage', 'users.manage', 'shows.delete', 'resources.manage', 'lx.access', 'snd.access'];
    ?>
    <div class="row row-cards">
        <div class="col-lg-5">
            <div class="card"><div class="card-header"><h3 class="card-title">Invite User</h3></div><div class="card-body">
                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="invite">
                    <div class="mb-3"><input class="form-control" name="name" placeholder="Name" required></div>
                    <div class="mb-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <?php foreach ($perms as $perm): ?>
                            <label class="form-check"><input class="form-check-input" type="checkbox" name="perm_<?= e($perm) ?>" checked><span class="form-check-label"><?= e($perm) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-primary">Send invite</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card"><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Name</th><th>Email</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($users as $u): ?>
                <tr><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td>
                    <form method="post" onsubmit="return confirm('Delete user?')" class="d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                </td></tr>
            <?php endforeach; ?>
            </tbody></table></div></div>
        </div>
    </div>
    <?php
}, $user);
