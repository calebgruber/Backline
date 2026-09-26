<?php

declare(strict_types=1);

return [
    'key' => '20260926_000004_add_shop_role_permissions',
    'name' => 'Add shop role permissions',
    'sql' => [
        'INSERT IGNORE INTO permissions (key_name, description_text, created_at, updated_at) VALUES
            ("lx.shop", "Lighting shop role", NOW(), NOW()),
            ("snd.shop", "Sound shop role", NOW(), NOW())',
    ],
];

