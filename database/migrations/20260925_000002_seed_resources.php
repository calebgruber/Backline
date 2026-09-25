<?php

return [
    'key' => '20260925_000002_seed_resources',
    'name' => 'Seed root resource folders',
    'sql' => [
        'INSERT INTO resource_nodes (parent_id, root_name, node_type, name, path_or_url, sort_order, uploaded_by, created_at, updated_at, deleted_at)
         SELECT NULL, "Lighting", "folder", "Lighting", NULL, 0, NULL, NOW(), NOW(), NULL
         WHERE NOT EXISTS (SELECT 1 FROM resource_nodes WHERE parent_id IS NULL AND root_name = "Lighting" AND name = "Lighting" AND deleted_at IS NULL)',
        'INSERT INTO resource_nodes (parent_id, root_name, node_type, name, path_or_url, sort_order, uploaded_by, created_at, updated_at, deleted_at)
         SELECT NULL, "Sound", "folder", "Sound", NULL, 1, NULL, NOW(), NOW(), NULL
         WHERE NOT EXISTS (SELECT 1 FROM resource_nodes WHERE parent_id IS NULL AND root_name = "Sound" AND name = "Sound" AND deleted_at IS NULL)',
        'INSERT INTO resource_nodes (parent_id, root_name, node_type, name, path_or_url, sort_order, uploaded_by, created_at, updated_at, deleted_at)
         SELECT NULL, "Backline Manuals", "folder", "Backline Manuals", NULL, 2, NULL, NOW(), NOW(), NULL
         WHERE NOT EXISTS (SELECT 1 FROM resource_nodes WHERE parent_id IS NULL AND root_name = "Backline Manuals" AND name = "Backline Manuals" AND deleted_at IS NULL)',
    ],
];
