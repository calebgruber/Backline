<?php

declare(strict_types=1);

function auth_user(): ?array
{
    if (!isset($_SESSION['user_id']) || !app_is_installed()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function auth_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([mb_strtolower(trim($email))]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    return auth_login_user_id((int) $user['id']);
}

function auth_login_user_id(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $stmt = db()->prepare('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$userId]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id <= 0) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    return true;
}

function auth_logout(): void
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function require_auth(): array
{
    $user = auth_user();
    if (!$user) {
        redirect('/auth/login');
    }
    return $user;
}

function user_has_permission(array $user, string $permission): bool
{
    if (($user['is_super_admin'] ?? 0) == 1) {
        return true;
    }

    $stmt = db()->prepare('SELECT 1 FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ? AND p.key_name = ? LIMIT 1');
    $stmt->execute([(int) $user['id'], $permission]);
    return (bool) $stmt->fetchColumn();
}

function require_permission(string $permission): array
{
    $user = require_auth();
    if (!user_has_permission($user, $permission)) {
        http_response_code(403);
        render_page('Forbidden', function () use ($user) {
            echo '<div class="alert alert-danger">You do not have permission for this action.</div>';
        }, $user);
        exit;
    }
    return $user;
}

function user_has_paid_export_access(array $user): bool
{
    if (($user['is_super_admin'] ?? 0) == 1) {
        return true;
    }
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    $keyStmt = db()->prepare('SELECT p.key_name FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?');
    $keyStmt->execute([$userId]);
    $keys = array_map(static fn ($v): string => strtolower(trim((string) $v)), $keyStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $keySet = [];
    foreach ($keys as $key) {
        if ($key !== '') {
            $keySet[$key] = true;
        }
    }

    if (isset($keySet['paid.export'])) {
        return true;
    }

    $legacyPaidKeys = ['paid', 'paid_export', 'export.paid'];
    $hasLegacyPaid = false;
    foreach ($legacyPaidKeys as $legacyKey) {
        if (isset($keySet[$legacyKey])) {
            $hasLegacyPaid = true;
            break;
        }
    }
    if (!$hasLegacyPaid) {
        return false;
    }

    $paidPermissionIdStmt = db()->prepare('SELECT id FROM permissions WHERE key_name = ? LIMIT 1');
    $paidPermissionIdStmt->execute(['paid.export']);
    $paidPermissionId = (int) ($paidPermissionIdStmt->fetchColumn() ?: 0);
    if ($paidPermissionId > 0) {
        $linkStmt = db()->prepare('INSERT IGNORE INTO user_permissions (user_id, permission_id, created_at) VALUES (?, ?, NOW())');
        $linkStmt->execute([$userId, $paidPermissionId]);
    }

    return true;
}

function user_can_manage_sound_cables(array $user): bool
{
    if (($user['is_super_admin'] ?? 0) == 1) {
        return true;
    }
    if (user_has_permission($user, 'admin.access')) {
        return true;
    }
    if (user_has_permission($user, 'snd.cables.manage')) {
        return true;
    }
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }
    $stmt = db()->prepare('SELECT 1 FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ? AND LOWER(TRIM(p.key_name)) IN ("sound_cables_manager","snd.cable.manage","snd.cables.editor") LIMIT 1');
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

function show_user_access_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'show_user_access'");
        $exists = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        $exists = false;
    }
    return $exists;
}
