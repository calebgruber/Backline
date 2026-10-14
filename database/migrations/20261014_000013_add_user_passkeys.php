<?php

declare(strict_types=1);

return [
    'key' => '20261014_000013_add_user_passkeys',
    'name' => 'Add user passkeys table for WebAuthn sign-in',
    'sql' => [
        'CREATE TABLE IF NOT EXISTS user_passkeys (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            credential_id_b64 VARCHAR(700) NOT NULL UNIQUE,
            display_name VARCHAR(190) NOT NULL,
            public_key_pem LONGTEXT NOT NULL,
            sign_count INT UNSIGNED NOT NULL DEFAULT 0,
            transports_json LONGTEXT NULL,
            last_used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            CONSTRAINT fk_user_passkeys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_user_passkeys_user (user_id),
            KEY idx_user_passkeys_active (user_id, revoked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    ],
];

