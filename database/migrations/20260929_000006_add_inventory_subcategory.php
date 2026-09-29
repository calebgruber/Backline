<?php

declare(strict_types=1);

return [
    'key' => '20260929_000006_add_inventory_subcategory',
    'name' => 'Add inventory subcategory field',
    'sql' => [
        'ALTER TABLE inventory_items ADD COLUMN subcategory_name VARCHAR(190) NULL AFTER category_id',
    ],
];

