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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');

    if ($action === 'update_name') {
        $name = post('name');
        if ($name === '') {
            flash_set('danger', 'Name is required.');
        } else {
            $stmt = db()->prepare('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$name, (int) $user['id']]);
            flash_set('success', 'Profile updated.');
        }
        redirect('/profile');
    }

    if ($action === 'change_password') {
        $currentPassword = post('current_password');
        $newPassword = post('new_password');
        $confirmPassword = post('confirm_password');

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            flash_set('danger', 'Current password is incorrect.');
            redirect('/profile');
        }
        if (strlen($newPassword) < 12) {
            flash_set('danger', 'New password must be at least 12 characters.');
            redirect('/profile');
        }
        if ($newPassword !== $confirmPassword) {
            flash_set('danger', 'New password and confirmation do not match.');
            redirect('/profile');
        }

        $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]);
        flash_set('success', 'Password updated.');
        redirect('/profile');
    }
}

render_page('Profile', function () use ($user): void {
    ?>
    <div class="row row-cards justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body text-center">
                    <span class="avatar avatar-xl rounded-3 mb-3" style="background-image:url(https://api.dicebear.com/9.x/thumbs/svg?seed=<?= urlencode((string) $user['email']) ?>)"></span>
                    <h3 class="mb-1"><?= e($user['name']) ?></h3>
                    <p class="text-secondary mb-0"><?= e($user['email']) ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Edit Profile</h3></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="update_name">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input class="form-control" value="<?= e($user['email']) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input class="form-control" name="name" value="<?= e($user['name']) ?>" required>
                        </div>
                        <button class="btn btn-primary">Save Profile</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Change Password</h3></div>
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input class="form-control" type="password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input class="form-control" type="password" name="new_password" minlength="12" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input class="form-control" type="password" name="confirm_password" minlength="12" required>
                        </div>
                        <button class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}, $user);
