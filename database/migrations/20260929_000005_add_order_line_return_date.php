<?php

declare(strict_types=1);

return [
    'key' => '20260929_000005_add_order_line_return_date',
    'name' => 'Add return date to order lines',
    'sql' => [
        'ALTER TABLE order_lines ADD COLUMN specific_return_date DATE NULL AFTER specific_pull_date',
    ],
];

