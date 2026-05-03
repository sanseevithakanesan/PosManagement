<?php
require 'database/db.php';
try {
    $columns = $pdo->query("DESCRIBE expenses")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('account_id', $columns)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN account_id INT DEFAULT NULL AFTER amount");
        echo "SUCCESS: Added account_id to expenses table.".PHP_EOL;
    } else {
        echo "INFO: account_id already exists in expenses.".PHP_EOL;
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
