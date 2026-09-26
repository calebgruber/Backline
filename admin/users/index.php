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
$roleOptions = [
    'lighting_major' => 'Lighting Major',
    'sound_major' => 'Sound Major',
    'lighting_shop' => 'Lighting Shop',
    'sound_shop' => 'Sound Shop',
    'admin' => 'Admin',
];
$rolePermissionMap = [
    'lighting_major' => ['lx.access'],
    'sound_major' => ['snd.access'],
    'lighting_shop' => ['lx.shop'],
    'sound_shop' => ['snd.shop'],
    'admin' => ['admin.access', 'inventory.manage', 'categories.manage', 'users.manage', 'shows.delete', 'resources.manage', 'lx.access', 'snd.access'],
];
$permissionDescriptions = [
    'admin.access' => 'Admin dashboard and settings',
    'inventory.manage' => 'Manage inventory',
    'categories.manage' => 'Manage inventory categories',
    'users.manage' => 'Manage users',
    'shows.delete' => 'Delete shows',
    'resources.manage' => 'Manage resources library',
    'lx.access' => 'Access lighting app',
    'snd.access' => 'Access sound app',
    'lx.shop' => 'Lighting shop role',
    'snd.shop' => 'Sound shop role',
];
$allRolePermissions = [];
foreach ($rolePermissionMap as $permissions) {
    foreach ($permissions as $permissionKey) {
        $allRolePermissions[$permissionKey] = true;
    }
}
$seedPermissionStmt = db()->prepare('INSERT IGNORE INTO permissions (key_name, description_text, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
foreach (array_keys($allRolePermissions) as $permissionKey) {
    $seedPermissionStmt->execute([$permissionKey, $permissionDescriptions[$permissionKey] ?? null]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');
    $isSuperAdmin = (int) ($user['is_super_admin'] ?? 0) === 1;
    $canManageAllPerms = $isSuperAdmin || user_has_permission($user, 'admin.access');
    if ($action === 'invite') {
        $name = post('name');
        $email = mb_strtolower(post('email'));
        $existingUserStmt = db()->prepare('SELECT id, deleted_at FROM users WHERE email = ? LIMIT 1');
        $existingUserStmt->execute([$email]);
        $existingUser = $existingUserStmt->fetch();
        if ($existingUser) {
            flash_set('warning', 'A user with that email already exists.');
            redirect('/admin/users');
        }
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, is_super_admin, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())');
        $stmt->execute([$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $newUserId = (int) db()->lastInsertId();

        $selectedRoles = $_POST['roles'] ?? [];
        if (!is_array($selectedRoles)) {
            $selectedRoles = [];
        }
        $selectedRoles = array_values(array_intersect(array_map('strval', $selectedRoles), array_keys($rolePermissionMap)));
        $selectedPermissions = [];
        foreach ($selectedRoles as $roleKey) {
            foreach ($rolePermissionMap[$roleKey] as $permissionKey) {
                $selectedPermissions[$permissionKey] = true;
            }
        }
        foreach (array_keys($selectedPermissions) as $perm) {
            if (!$canManageAllPerms && $perm !== 'lx.access' && $perm !== 'snd.access' && $perm !== 'lx.shop' && $perm !== 'snd.shop') {
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
        if ($target === (int) $user['id']) {
            flash_set('danger', 'You cannot delete your own account.');
            redirect('/admin/users');
        }
        $targetStmt = db()->prepare('SELECT id, is_super_admin FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $targetStmt->execute([$target]);
        $targetUser = $targetStmt->fetch();
        if (!$targetUser) {
            flash_set('warning', 'User not found.');
            redirect('/admin/users');
        }
        if ((int) $targetUser['is_super_admin'] === 1 && !$isSuperAdmin) {
            flash_set('danger', 'Only super admins can delete super admin accounts.');
            redirect('/admin/users');
        }
        $stmt = db()->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$target]);
        flash_set('warning', 'User deleted.');
        audit_log((int) $user['id'], 'user', 'delete', $target);
    }

    if ($action === 'update_permissions') {
        $target = (int) post('user_id');
        if ($target === (int) $user['id']) {
            flash_set('danger', 'You cannot change your own permissions.');
            redirect('/admin/users');
        }
        $targetStmt = db()->prepare('SELECT id, is_super_admin FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $targetStmt->execute([$target]);
        $targetUser = $targetStmt->fetch();
        if (!$targetUser) {
            flash_set('warning', 'User not found.');
            redirect('/admin/users');
        }
        if ((int) $targetUser['is_super_admin'] === 1 && !$isSuperAdmin) {
            flash_set('danger', 'Only super admins can edit super admin permissions.');
            redirect('/admin/users');
        }
        $selectedRoles = $_POST['roles'] ?? [];
        if (!is_array($selectedRoles)) {
            $selectedRoles = [];
        }
        $selectedRoles = array_values(array_intersect(array_map('strval', $selectedRoles), array_keys($rolePermissionMap)));
        $selectedPermSet = [];
        foreach ($selectedRoles as $roleKey) {
            foreach ($rolePermissionMap[$roleKey] as $permissionKey) {
                $selectedPermSet[$permissionKey] = true;
            }
        }
        $selectedPerms = array_keys($selectedPermSet);
        if (!$canManageAllPerms) {
            $selectedPerms = array_values(array_intersect($selectedPerms, ['lx.access', 'snd.access', 'lx.shop', 'snd.shop']));
        }

        db()->beginTransaction();
        try {
            $stmt = db()->prepare('DELETE FROM user_permissions WHERE user_id = ?');
            $stmt->execute([$target]);

            if (!empty($selectedPerms)) {
                $placeholders = implode(',', array_fill(0, count($selectedPerms), '?'));
                $permStmt = db()->prepare('SELECT id, key_name FROM permissions WHERE key_name IN (' . $placeholders . ')');
                $permStmt->execute($selectedPerms);
                $permRows = $permStmt->fetchAll();

                $ins = db()->prepare('INSERT IGNORE INTO user_permissions (user_id, permission_id, created_at) VALUES (?, ?, NOW())');
                foreach ($permRows as $permRow) {
                    $ins->execute([$target, (int) $permRow['id']]);
                }
            }
            db()->commit();
            flash_set('success', 'User roles updated.');
            audit_log((int) $user['id'], 'user', 'permissions_update', $target, ['roles' => $selectedRoles, 'permissions' => $selectedPerms]);
        } catch (Throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash_set('danger', 'Could not update permissions. Please retry.');
        }
    }

    redirect('/admin/users');
}

$users = db()->query('SELECT id, name, email, created_at FROM users WHERE deleted_at IS NULL ORDER BY id DESC')->fetchAll();
$userPermissionRows = db()->query('SELECT up.user_id, p.key_name FROM user_permissions up JOIN permissions p ON p.id = up.permission_id')->fetchAll();
$userPermMap = [];
foreach ($userPermissionRows as $row) {
    $userPermMap[(int) $row['user_id']][] = (string) $row['key_name'];
}

render_page('Users', function () use ($users, $userPermMap, $roleOptions, $rolePermissionMap): void {
    $userRoleMap = [];
    foreach ($users as $u) {
        $uid = (int) $u['id'];
        $userPerms = $userPermMap[$uid] ?? [];
        $assignedRoles = [];
        foreach ($rolePermissionMap as $roleKey => $requiredPerms) {
            $matches = true;
            foreach ($requiredPerms as $requiredPerm) {
                if (!in_array($requiredPerm, $userPerms, true)) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $assignedRoles[] = $roleKey;
            }
        }
        $userRoleMap[$uid] = $assignedRoles;
    }
    $renderRoleDropdown = static function (array $roleOptions, array $selectedRoles): void {
        $selectedLabels = [];
        foreach ($selectedRoles as $roleKey) {
            if (isset($roleOptions[$roleKey])) {
                $selectedLabels[] = $roleOptions[$roleKey];
            }
        }
        $summary = empty($selectedLabels) ? 'Select roles' : implode(', ', $selectedLabels);
        ?>
        <details class="role-dropdown">
            <summary class="form-select role-dropdown-summary"><?= e($summary) ?></summary>
            <div class="role-dropdown-menu">
                <?php foreach ($roleOptions as $roleKey => $roleLabel): ?>
                    <label class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="<?= e($roleKey) ?>" <?= in_array($roleKey, $selectedRoles, true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= e($roleLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </details>
        <?php
    };
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
                        <label class="form-label">Roles</label>
                        <?php $renderRoleDropdown($roleOptions, []); ?>
                        <div class="form-hint">Open the dropdown and check all roles that apply.</div>
                    </div>
                    <button class="btn btn-primary">Send invite</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card"><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Name</th><th>Email</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#perms-<?= (int) $u['id'] ?>">Roles</button>
                        <form method="post" onsubmit="return confirm('Delete user?')" class="d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                    </td>
                </tr>
                <tr class="collapse" id="perms-<?= (int) $u['id'] ?>">
                    <td colspan="3">
                        <form method="post" class="row g-2 align-items-start">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="update_permissions">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <div class="col-12">
                                <label class="form-label">Roles</label>
                                <?php $renderRoleDropdown($roleOptions, $userRoleMap[(int) $u['id']] ?? []); ?>
                                <div class="form-hint">Open the dropdown and check all roles that apply.</div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-sm">Save Roles</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div></div>
        </div>
    </div>
    <?php
}, $user);
