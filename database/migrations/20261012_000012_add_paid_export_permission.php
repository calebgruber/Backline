<?php

declare(strict_types=1);

return [
    'key' => '20261012_000012_add_paid_export_permission',
    'name' => 'Add paid export permission',
    'sql' => [
        'INSERT IGNORE INTO permissions (key_name, description_text, created_at, updated_at) VALUES
            ("paid.export", "Export shop orders (Paid role)", NOW(), NOW())',
    ],
];

