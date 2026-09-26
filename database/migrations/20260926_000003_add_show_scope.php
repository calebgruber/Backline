<?php

declare(strict_types=1);

return [
    'key' => '20260926_000003_add_show_scope',
    'name' => 'Add show scope for LX/SND/both',
    'sql' => [
        'ALTER TABLE shows ADD COLUMN IF NOT EXISTS show_scope ENUM("lx","snd","both") NOT NULL DEFAULT "both" AFTER shop_name',
    ],
];
