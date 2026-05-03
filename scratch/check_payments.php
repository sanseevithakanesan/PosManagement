<?php
require 'database/db.php';
try {
    $q = $pdo->query('DESCRIBE payments');
    while($r = $q->fetch()) {
        echo $r[0].' - '.$r[1].PHP_EOL;
    }
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
