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

    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function auth_logout(): void
{
    unset($_SESSION['user_id']);
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
        render_page('Forbidden', function () {
            echo '<div class="alert alert-danger">You do not have permission for this action.</div>';
        }, $user);
        exit;
    }
    return $user;
}
