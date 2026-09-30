<?php

declare(strict_types=1);

return [
    'key' => '20260930_000008_show_access_and_production_contacts',
    'name' => 'Add show access invites and production contact fields',
    'sql' => [
        'ALTER TABLE shows
            ADD COLUMN IF NOT EXISTS production_contact_name VARCHAR(255) NULL AFTER assistant_snd_designer_phone,
            ADD COLUMN IF NOT EXISTS production_contact_email VARCHAR(190) NULL AFTER production_contact_name,
            ADD COLUMN IF NOT EXISTS production_contact_phone VARCHAR(64) NULL AFTER production_contact_email',
        'UPDATE shows SET show_scope = "both" WHERE deleted_at IS NULL',
        'CREATE TABLE IF NOT EXISTS show_user_access (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            show_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            invited_by_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_show_user_access (show_id, user_id),
            KEY idx_show_user_access_user (user_id),
            CONSTRAINT fk_show_user_access_show FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
            CONSTRAINT fk_show_user_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_show_user_access_invited_by FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    ],
];
