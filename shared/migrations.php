<?php

declare(strict_types=1);

function ensure_migrations_table(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration_key VARCHAR(190) NOT NULL UNIQUE,
        status VARCHAR(20) NOT NULL,
        applied_at DATETIME NULL,
        error_text TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function discover_migrations(): array
{
    $files = glob(__DIR__ . '/../database/migrations/*.php') ?: [];
    sort($files);
    $migrations = [];
    foreach ($files as $file) {
        $def = require $file;
        if (!is_array($def) || empty($def['key']) || empty($def['sql'])) {
            continue;
        }
        $migrations[] = $def;
    }
    return $migrations;
}

function applied_migration_map(): array
{
    ensure_migrations_table();
    $rows = db()->query('SELECT migration_key, status, applied_at, error_text FROM migrations')->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[$row['migration_key']] = $row;
    }
    return $map;
}

function migration_status_rows(): array
{
    $rows = [];
    $applied = applied_migration_map();
    foreach (discover_migrations() as $migration) {
        $key = $migration['key'];
        $rows[] = [
            'key' => $key,
            'name' => $migration['name'] ?? $key,
            'status' => $applied[$key]['status'] ?? 'pending',
            'applied_at' => $applied[$key]['applied_at'] ?? null,
            'error_text' => $applied[$key]['error_text'] ?? null,
        ];
    }
    return $rows;
}

function run_pending_migrations(): array
{
    $results = [];
    ensure_migrations_table();
    $applied = applied_migration_map();

    foreach (discover_migrations() as $migration) {
        $key = $migration['key'];
        if (($applied[$key]['status'] ?? null) === 'applied') {
            continue;
        }

        $name = $migration['name'] ?? $key;
        try {
            $startedTransaction = false;
            if (!db()->inTransaction()) {
                db()->beginTransaction();
                $startedTransaction = true;
            }
            foreach ($migration['sql'] as $sql) {
                db()->exec($sql);
            }

            $stmt = db()->prepare('INSERT INTO migrations (migration_key, status, applied_at, error_text, created_at, updated_at)
                VALUES (?, "applied", NOW(), NULL, NOW(), NOW())
                ON DUPLICATE KEY UPDATE status="applied", applied_at=NOW(), error_text=NULL, updated_at=NOW()');
            $stmt->execute([$key]);
            if ($startedTransaction && db()->inTransaction()) {
                db()->commit();
            }
            $results[] = ['key' => $key, 'name' => $name, 'status' => 'applied'];
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $stmt = db()->prepare('INSERT INTO migrations (migration_key, status, applied_at, error_text, created_at, updated_at)
                VALUES (?, "failed", NULL, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE status="failed", error_text=?, updated_at=NOW()');
            $stmt->execute([$key, $e->getMessage(), $e->getMessage()]);
            $results[] = ['key' => $key, 'name' => $name, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    return $results;
}
