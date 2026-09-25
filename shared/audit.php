<?php

declare(strict_types=1);

function audit_log(?int $userId, string $entity, string $action, ?int $entityId = null, array $meta = []): void
{
    if (!app_is_installed()) {
        return;
    }

    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, entity_type, entity_id, action_type, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $entity, $entityId, $action, json_encode($meta, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable) {
        // Keep audit failures non-blocking.
    }
}
