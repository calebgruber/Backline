<?php

return [
    'key' => '20260925_000001_initial_schema',
    'name' => 'Initial schema for Backline core systems',
    'sql' => [
        'CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS permissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(190) NOT NULL UNIQUE,
            description_text VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS user_permissions (
            user_id BIGINT UNSIGNED NOT NULL,
            permission_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, permission_id),
            CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS app_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(190) NOT NULL UNIQUE,
            value_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS password_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            token_type ENUM("invite","reset") NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_password_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS inventory_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shop_type ENUM("lx","snd") NOT NULL,
            name VARCHAR(190) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_shop_category (shop_type, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS inventory_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shop_type ENUM("lx","snd") NOT NULL,
            category_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(120) NULL,
            shop_quantity INT NOT NULL DEFAULT 0,
            unit VARCHAR(32) NOT NULL DEFAULT "ea",
            default_note TEXT NULL,
            description TEXT NULL,
            internal_note TEXT NULL,
            is_spacer TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT fk_inventory_items_category FOREIGN KEY (category_id) REFERENCES inventory_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS shows (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            show_name VARCHAR(255) NOT NULL,
            theatre_name VARCHAR(255) NOT NULL,
            shop_name VARCHAR(255) NOT NULL,
            lead_designer_name VARCHAR(255) NOT NULL,
            lead_designer_email VARCHAR(190) NULL,
            lead_designer_phone VARCHAR(64) NULL,
            ald_name VARCHAR(255) NOT NULL,
            ald_email VARCHAR(190) NULL,
            ald_phone VARCHAR(64) NULL,
            assistant_snd_designer_name VARCHAR(255) NOT NULL,
            assistant_snd_designer_email VARCHAR(190) NULL,
            assistant_snd_designer_phone VARCHAR(64) NULL,
            shop_manager_name VARCHAR(255) NOT NULL,
            shop_manager_email VARCHAR(190) NULL,
            shop_manager_phone VARCHAR(64) NULL,
            assistants_json LONGTEXT NULL,
            show_image_path VARCHAR(255) NULL,
            pull_date DATE NULL,
            return_date DATE NULL,
            strike_date DATE NULL,
            opening_date DATE NULL,
            closing_date DATE NULL,
            theatre_address TEXT NULL,
            shop_address TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            CONSTRAINT fk_shows_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS show_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            show_id BIGINT UNSIGNED NOT NULL,
            key_name VARCHAR(190) NOT NULL,
            value_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_show_setting (show_id, key_name),
            CONSTRAINT fk_show_settings_show FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            show_id BIGINT UNSIGNED NOT NULL,
            shop_type ENUM("lx","snd") NOT NULL,
            order_kind ENUM("initial") NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_show_shop_initial (show_id, shop_type, order_kind),
            CONSTRAINT fk_orders_show FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
            CONSTRAINT fk_orders_user FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS order_revisions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            revision_number INT NOT NULL,
            revision_label VARCHAR(64) NOT NULL,
            revised_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_order_revision (order_id, revision_number),
            CONSTRAINT fk_order_revisions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_revisions_user FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS order_lines (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            revision_id BIGINT UNSIGNED NOT NULL,
            inventory_item_id BIGINT UNSIGNED NOT NULL,
            qty INT NOT NULL DEFAULT 0,
            spares INT NOT NULL DEFAULT 0,
            line_note TEXT NULL,
            specific_pull_date DATE NULL,
            action_code ENUM("blank","add","return","exchange","notes") NOT NULL DEFAULT "blank",
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT fk_order_lines_revision FOREIGN KEY (revision_id) REFERENCES order_revisions(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_lines_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS order_revision_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            revision_id BIGINT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_history_revision FOREIGN KEY (revision_id) REFERENCES order_revisions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shop_type ENUM("lx","snd") NOT NULL,
            name VARCHAR(190) NOT NULL,
            trigger_item_id BIGINT UNSIGNED NOT NULL,
            trigger_qty INT NOT NULL,
            dependent_item_id BIGINT UNSIGNED NOT NULL,
            dependent_qty INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            CONSTRAINT fk_rules_trigger_item FOREIGN KEY (trigger_item_id) REFERENCES inventory_items(id),
            CONSTRAINT fk_rules_dependent_item FOREIGN KEY (dependent_item_id) REFERENCES inventory_items(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS resource_nodes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NULL,
            root_name VARCHAR(190) NOT NULL,
            node_type ENUM("folder","file") NOT NULL,
            name VARCHAR(190) NOT NULL,
            path_or_url VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            CONSTRAINT fk_resource_parent FOREIGN KEY (parent_id) REFERENCES resource_nodes(id) ON DELETE CASCADE,
            CONSTRAINT fk_resource_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS sound_cables (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            show_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            inventory_item_id BIGINT UNSIGNED NULL,
            cable_type VARCHAR(120) NOT NULL,
            cable_length VARCHAR(120) NOT NULL,
            display_name VARCHAR(190) NULL,
            color VARCHAR(64) NULL,
            description_text TEXT NULL,
            source_text VARCHAR(190) NULL,
            destination_text VARCHAR(190) NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            bundle_key VARCHAR(120) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT fk_sound_cables_show FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
            CONSTRAINT fk_sound_cables_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
            CONSTRAINT fk_sound_cables_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            entity_type VARCHAR(120) NOT NULL,
            entity_id BIGINT UNSIGNED NULL,
            action_type VARCHAR(120) NOT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'INSERT IGNORE INTO permissions (key_name, description_text, created_at, updated_at) VALUES
            ("admin.access", "Admin dashboard and settings", NOW(), NOW()),
            ("inventory.manage", "Manage inventory and imports", NOW(), NOW()),
            ("categories.manage", "Manage inventory categories", NOW(), NOW()),
            ("users.manage", "Manage users and invites", NOW(), NOW()),
            ("shows.delete", "Delete shows", NOW(), NOW()),
            ("resources.manage", "Manage resources", NOW(), NOW()),
            ("lx.access", "Access lighting app", NOW(), NOW()),
            ("snd.access", "Access sound app", NOW(), NOW())',

        'INSERT IGNORE INTO app_settings (key_name, value_json, created_at, updated_at) VALUES
            ("paperwork.master.header_text", "\"\"", NOW(), NOW()),
            ("paperwork.master.top_right_header_text", "\"\"", NOW(), NOW()),
            ("paperwork.master.footer_text", "\"\"", NOW(), NOW()),
            ("paperwork.master.important_notes", "\"\"", NOW(), NOW()),
            ("paperwork.master.cover_show_image", "\"true\"", NOW(), NOW()),
            ("paperwork.master.cover_show_title", "\"true\"", NOW(), NOW()),
            ("paperwork.master.page_x_of_x", "\"true\"", NOW(), NOW()),
            ("paperwork.master.revision_summary_block", "\"true\"", NOW(), NOW())',
    ],
];
