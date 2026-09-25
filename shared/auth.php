<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_role(string $role): void
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== $role) {
        http_response_code(403);
        require_once __DIR__ . '/ui.php';
        render_page('Forbidden', function (): void {
            echo '<p>You do not have permission for this area.</p>';
        });
        exit;
    }
}

function require_any_access(array $concentrations): void
{
    $user = current_user();
    if (!$user) {
        $loginUrl = app_url('auth/login');
        if (!headers_sent()) {
            header('Location: ' . $loginUrl);
        } else {
            echo '<script>window.location.href=' . json_encode($loginUrl) . ';</script>';
        }
        exit;
    }
    if (($user['role'] ?? '') === 'admin') {
        return;
    }

    $grants = $user['concentrations'] ?? [];
    foreach ($concentrations as $area) {
        if (in_array($area, $grants, true)) {
            return;
        }
    }

    http_response_code(403);
    require_once __DIR__ . '/ui.php';
    render_page('Forbidden', function (): void {
        echo '<p>Your account does not have access to this concentration.</p>';
    });
    exit;
}
