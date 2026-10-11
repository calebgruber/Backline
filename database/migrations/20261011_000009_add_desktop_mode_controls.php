<?php

declare(strict_types=1);

return [
    'key' => '20261011_000009_add_desktop_mode_controls',
    'name' => 'Add desktop mode controls',
    'sql' => [
        'ALTER TABLE users ADD COLUMN IF NOT EXISTS desktop_mode_override TINYINT(1) NULL AFTER password_hash',
        'INSERT INTO app_settings (key_name, value_json, created_at, updated_at)
            VALUES ("ui.desktop_mode_global", "0", NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()',
    ],
];
