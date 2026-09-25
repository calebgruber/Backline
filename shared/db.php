<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = config('db.host');
    $port = (int) config('db.port', 3306);
    $name = config('db.name');
    $user = config('db.user');
    $pass = config('db.pass');
    $charset = config('db.charset', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
    $pdo = new PDO($dsn, (string) $user, (string) $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function db_test_connection(array $db): bool
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'] ?? '127.0.0.1',
        (int) ($db['port'] ?? 3306),
        $db['name'] ?? '',
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''));
    $pdo->query('SELECT 1');
    return true;
}
