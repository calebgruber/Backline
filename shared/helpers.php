<?php

declare(strict_types=1);

function route_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return rtrim($uri, '/') ?: '/';
}

function path_starts_with(string $path, string $prefix): bool
{
    $normalizedPrefix = rtrim($prefix, '/') ?: '/';
    $normalizedPath = rtrim($path, '/') ?: '/';
    if ($normalizedPath === $normalizedPrefix) {
        return true;
    }
    if ($normalizedPrefix === '/') {
        return true;
    }
    $segmentPrefix = $normalizedPrefix . '/';
    return strncmp($normalizedPath, $segmentPrefix, strlen($segmentPrefix)) === 0;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function post(string $key, ?string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : (string) $default;
}
