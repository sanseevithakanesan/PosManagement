<?php
require 'database/db.php';
try {
    $pdo->exec("UPDATE payments SET account_id = 1 WHERE account_id IS NULL");
    echo "SUCCESS: Updated existing null payments to Cash account.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
