<?php
require 'database/db.php';
try {
    // Ensure accounts table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert defaults if empty
    $count = $pdo->query("SELECT COUNT(*) FROM payment_accounts")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO payment_accounts (account_name) VALUES ('Cash'), ('Bank Account')");
    }

    // Add account_id to payments table
    $columns = $pdo->query("DESCRIBE payments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('account_id', $columns)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN account_id INT DEFAULT NULL AFTER payment_method");
        echo "SUCCESS: Added account_id to payments table.".PHP_EOL;
    }

    echo "SUCCESS: Database updated.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
