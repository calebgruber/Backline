<?php

declare(strict_types=1);

return [
    'key' => '20261012_000010_sound_cables_workflow',
    'name' => 'Expand sound cables workflow schema',
    'sql' => [
        'ALTER TABLE sound_cables
            ADD COLUMN IF NOT EXISTS overall_label VARCHAR(190) NULL AFTER cable_length,
            ADD COLUMN IF NOT EXISTS adapter_text VARCHAR(190) NULL AFTER destination_text,
            ADD COLUMN IF NOT EXISTS notes_text TEXT NULL AFTER adapter_text,
            ADD COLUMN IF NOT EXISTS loom_name VARCHAR(190) NULL AFTER notes_text,
            ADD COLUMN IF NOT EXISTS loom_id_text VARCHAR(120) NULL AFTER loom_name,
            ADD COLUMN IF NOT EXISTS category_name VARCHAR(120) NULL AFTER loom_id_text,
            ADD COLUMN IF NOT EXISTS source_revision_id BIGINT UNSIGNED NULL AFTER category_name,
            ADD COLUMN IF NOT EXISTS source_line_id BIGINT UNSIGNED NULL AFTER source_revision_id,
            ADD COLUMN IF NOT EXISTS source_line_index INT NULL AFTER source_line_id,
            ADD COLUMN IF NOT EXISTS auto_created TINYINT(1) NOT NULL DEFAULT 0 AFTER source_line_index',
        'CREATE TABLE IF NOT EXISTS sound_cable_colors (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_sound_cable_color_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'INSERT IGNORE INTO sound_cable_colors (name, sort_order, created_at, updated_at) VALUES
            ("Black", 10, NOW(), NOW()),
            ("Red", 20, NOW(), NOW()),
            ("Blue", 30, NOW(), NOW()),
            ("Green", 40, NOW(), NOW()),
            ("Yellow", 50, NOW(), NOW()),
            ("White", 60, NOW(), NOW()),
            ("Orange", 70, NOW(), NOW()),
            ("Purple", 80, NOW(), NOW()),
            ("Gray", 90, NOW(), NOW())',
    ],
];
