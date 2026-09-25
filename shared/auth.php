<?php

declare(strict_types=1);

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
        header('Location: ' . app_url('auth/login'));
        exit;
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
