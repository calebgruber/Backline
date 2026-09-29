<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inventory_subcategories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shop_type ENUM("lx","snd") NOT NULL,
            category_id INT NULL,
            name VARCHAR(190) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_inventory_subcategories_shop_sort (shop_type, sort_order, name),
            INDEX idx_inventory_subcategories_category (category_id),
            CONSTRAINT fk_inventory_subcategories_category
                FOREIGN KEY (category_id) REFERENCES inventory_categories(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
