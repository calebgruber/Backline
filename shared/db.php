<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    $pdo = $GLOBALS['backline_pdo'] ?? null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $settings = app_settings();
    $defaults = default_settings();
    $cfg = array_replace($defaults['db'] ?? [], is_array($settings['db'] ?? null) ? $settings['db'] : []);
    $allowedCharsets = ['utf8mb4', 'utf8', 'latin1', 'ascii'];
    $charset = strtolower((string) ($cfg['charset'] ?? 'utf8mb4'));
    if (!in_array($charset, $allowedCharsets, true)) {
        $charset = 'utf8mb4';
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $charset);

    $GLOBALS['backline_pdo'] = new PDO($dsn, (string) $cfg['user'], (string) $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $GLOBALS['backline_pdo'];
}

function reset_db_connection(): void
{
    $GLOBALS['backline_pdo'] = null;
}

function db_ready(): bool
{
    try {
        db()->query('SELECT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}
