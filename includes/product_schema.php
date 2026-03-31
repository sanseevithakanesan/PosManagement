<?php

/**
 * Extended product schema (ERP-style fields + product_images). Safe to run repeatedly.
 */
function ensure_product_extended_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $alters = [
        "ALTER TABLE products ADD COLUMN title VARCHAR(255) NULL DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN description TEXT NULL DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN sku VARCHAR(64) NULL DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) NULL DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN reorder_level INT(11) NOT NULL DEFAULT 0",
    ];

    foreach ($alters as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            /* column exists */
        }
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_images (
                id INT(11) NOT NULL AUTO_INCREMENT,
                product_id INT(11) NOT NULL,
                file_path VARCHAR(512) NOT NULL,
                sort_order INT(11) NOT NULL DEFAULT 0,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_product_images_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (Throwable $e) {
        /* already exists */
    }

    $done = true;
}

function product_generate_unique_barcode(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 25; $attempt++) {
        $candidate = '89' . str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE barcode = ?');
        $st->execute([$candidate]);
        if ((int)$st->fetchColumn() === 0) {
            return $candidate;
        }
    }

    return 'POS-' . strtoupper(bin2hex(random_bytes(8)));
}
