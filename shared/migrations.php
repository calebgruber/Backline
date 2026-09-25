<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function migration_files(): array
{
    $files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
    sort($files);
    return $files;
}

function ensure_migrations_table(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function applied_migrations(): array
{
    ensure_migrations_table();
    return db()->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function pending_migrations(): array
{
    $applied = array_flip(applied_migrations());
    return array_values(array_filter(migration_files(), static fn (string $file): bool => !isset($applied[basename($file)])));
}

function apply_pending_migrations(): array
{
    ensure_migrations_table();
    $pdo = db();
    return apply_pending_migrations_with_pdo($pdo, 'backline_migrations');
}

function apply_pending_migrations_with_pdo(PDO $pdo, string $lockName = 'backline_migrations'): array
{
    $results = [];
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $needsLock = $lockName !== '';
    if ($needsLock) {
        $lockQuery = $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 10)");
        if ($lockQuery === false) {
            return [['migration' => 'migration_lock', 'status' => 'failed', 'message' => 'Could not query migration lock']];
        }
        $lockAcquired = (bool) $lockQuery->fetchColumn();
        if (!$lockAcquired) {
            return [['migration' => 'migration_lock', 'status' => 'failed', 'message' => 'Could not acquire migration lock']];
        }
    }

    try {
        $appliedQuery = $pdo->query('SELECT migration FROM schema_migrations ORDER BY migration');
        if ($appliedQuery === false) {
            return [['migration' => 'schema_migrations', 'status' => 'failed', 'message' => 'Could not read applied migrations']];
        }
        $applied = $appliedQuery->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $appliedMap = array_flip($applied);
        $pendingFiles = array_values(array_filter(
            migration_files(),
            static fn (string $file): bool => !isset($appliedMap[basename($file)])
        ));

        foreach ($pendingFiles as $file) {
            $name = basename($file);
            $sqlRaw = file_get_contents($file);
            if ($sqlRaw === false) {
                $results[] = ['migration' => $name, 'status' => 'failed', 'message' => 'Could not read migration file'];
                break;
            }
            $sql = trim($sqlRaw);
            if ($sql === '') {
                $results[] = ['migration' => $name, 'status' => 'skipped', 'message' => 'Empty file'];
                continue;
            }

            try {
                $isLikelyDdl = preg_match('/\\b(CREATE|ALTER|DROP|RENAME|TRUNCATE|LOCK|UNLOCK|ANALYZE|OPTIMIZE|REPAIR|GRANT|REVOKE)\\b/i', $sql) === 1;
                if (!$isLikelyDdl) {
                    $pdo->beginTransaction();
                }
                foreach (migration_statements($sql) as $statement) {
                    $pdo->exec($statement);
                }
                $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
                $stmt->execute([$name]);
                if (!$isLikelyDdl && $pdo->inTransaction()) {
                    $pdo->commit();
                }
                $results[] = ['migration' => $name, 'status' => 'applied', 'message' => 'Applied successfully'];
            } catch (Throwable $error) {
                if (isset($isLikelyDdl) && !$isLikelyDdl && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $results[] = ['migration' => $name, 'status' => 'failed', 'message' => $error->getMessage()];
                break;
            }
        }
    } finally {
        if ($needsLock) {
            $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
        }
    }

    return $results;
}

function migration_statements(string $sql): array
{
    $segments = preg_split('/;\\s*(?:\\r?\\n|$)/', $sql) ?: [];
    $statements = [];
    foreach ($segments as $segment) {
        $statement = trim($segment);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }

    return $statements;
}
