CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    password_hash VARCHAR(255) NULL,
    invite_token VARCHAR(128) NULL,
    invite_expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_concentrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    concentration ENUM('lx','snd') NOT NULL,
    UNIQUE KEY uniq_user_concentration (user_id, concentration),
    CONSTRAINT fk_user_concentration_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    concentration ENUM('lx','snd') NOT NULL,
    show_name VARCHAR(255) NOT NULL,
    theatre_name VARCHAR(255) NOT NULL,
    shop_name VARCHAR(255) NOT NULL,
    show_image_url VARCHAR(500) NULL,
    pull_date DATE NULL,
    return_date DATE NULL,
    strike_date DATE NULL,
    opening_date DATE NULL,
    closing_date DATE NULL,
    theatre_address TEXT NULL,
    shop_address TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_shows_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS show_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL,
    role_key ENUM('designer','ald','assistant_designer_pe','shop_manager','assistant_shop_manager') NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_show_contacts_show FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concentration ENUM('lx','snd') NOT NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_concentration_category (concentration, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concentration ENUM('lx','snd') NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(120) NULL,
    shop_quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(40) NOT NULL DEFAULT 'ea',
    default_note TEXT NULL,
    description TEXT NULL,
    is_spacer TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS show_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL,
    concentration ENUM('lx','snd') NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_show_orders_show FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    CONSTRAINT fk_show_orders_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    revision_code VARCHAR(30) NOT NULL,
    revision_index INT NOT NULL,
    revision_date DATE NOT NULL,
    is_initial TINYINT(1) NOT NULL DEFAULT 0,
    summary_note TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_order_revision_index (order_id, revision_index),
    CONSTRAINT fk_order_revisions_order FOREIGN KEY (order_id) REFERENCES show_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_revisions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS revision_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revision_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    rent_quantity INT NOT NULL DEFAULT 0,
    spare_quantity INT NOT NULL DEFAULT 0,
    total_quantity INT NOT NULL DEFAULT 0,
    action ENUM('','add','return','exchange','note') NOT NULL DEFAULT '',
    line_note TEXT NULL,
    specific_pull_date DATE NULL,
    specific_return_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_revision_inventory_item (revision_id, inventory_item_id),
    CONSTRAINT fk_revision_items_revision FOREIGN KEY (revision_id) REFERENCES order_revisions(id) ON DELETE CASCADE,
    CONSTRAINT fk_revision_items_inventory FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS paperwork_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NULL,
    setting_key VARCHAR(190) NOT NULL,
    setting_value TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_show_setting (show_id, setting_key),
    CONSTRAINT fk_paperwork_show FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resource_folders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    root ENUM('Lighting','Sound','Backline Manuals') NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_resource_parent FOREIGN KEY (parent_id) REFERENCES resource_folders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resource_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folder_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_resource_files_folder FOREIGN KEY (folder_id) REFERENCES resource_folders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concentration ENUM('lx','snd') NOT NULL,
    trigger_item_id BIGINT UNSIGNED NOT NULL,
    trigger_qty INT NOT NULL,
    required_item_id BIGINT UNSIGNED NOT NULL,
    required_qty INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rules_trigger_item FOREIGN KEY (trigger_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_rules_required_item FOREIGN KEY (required_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sound_labels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    show_id BIGINT UNSIGNED NOT NULL,
    revision_id BIGINT UNSIGNED NULL,
    item_name VARCHAR(255) NOT NULL,
    color VARCHAR(100) NOT NULL,
    description TEXT NULL,
    source VARCHAR(255) NULL,
    destination VARCHAR(255) NULL,
    cable_type VARCHAR(255) NOT NULL,
    cable_length VARCHAR(120) NOT NULL,
    bundle_key VARCHAR(120) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sound_labels_show FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    CONSTRAINT fk_sound_labels_revision FOREIGN KEY (revision_id) REFERENCES order_revisions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
