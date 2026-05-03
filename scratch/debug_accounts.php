<?php
require 'database/db.php';
try {
    echo "LATEST PAYMENTS:\n";
    $q = $pdo->query('SELECT * FROM payments ORDER BY id DESC LIMIT 5');
    while($r = $q->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$r['id']}, Order: {$r['order_id']}, Account: " . ($r['account_id'] ?? 'NULL') . ", Amount: {$r['amount']}\n";
    }
    echo "\nACCOUNTS:\n";
    $q = $pdo->query('SELECT * FROM payment_accounts');
    while($r = $q->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$r['id']}, Name: {$r['account_name']}\n";
    }
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
