<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/database/db.php';
require_once dirname(__DIR__) . '/includes/cart_schema.php';

ensure_cart_discount_columns($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['confirm_bill'] ?? '') !== '1') {
    header('Location: dashboard.php?page=pos');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ../login.php');
    exit;
}

$customerIdRaw = $_POST['customer_id'] ?? '';
$customerId = $customerIdRaw !== '' ? (int)$customerIdRaw : null;
if ($customerId !== null && $customerId <= 0) {
    $customerId = null;
}
if ($customerId !== null) {
    $chkCust = $pdo->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
    $chkCust->execute([$customerId]);
    if (!$chkCust->fetch()) {
        $customerId = null;
    }
}

$cash = (float)str_replace(',', '', (string)($_POST['cash'] ?? '0'));
$postTotal = (float)str_replace(',', '', (string)($_POST['total_amount'] ?? '0'));

$cart = $pdo->query(
    'SELECT c.id, c.qty, c.discount_type, c.discount_value,
            p.id AS product_id, p.name, p.price, p.stock
     FROM cart c
     JOIN products p ON p.id = c.product_id'
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart)) {
    $_SESSION['pos_bill_error'] = 'Cart is empty.';
    header('Location: dashboard.php?page=pos');
    exit;
}

$lines = [];
foreach ($cart as $row) {
    [, , $lineTotal] = pos_cart_line_totals(
        (int)$row['qty'],
        (float)$row['price'],
        (string)($row['discount_type'] ?? 'none'),
        (float)($row['discount_value'] ?? 0)
    );
    $qty = (int)$row['qty'];
    if ($qty < 1) {
        continue;
    }
    $unitEff = round($lineTotal / $qty, 2);
    $lines[] = [
        'cart_id' => (int)$row['id'],
        'product_id' => (int)$row['product_id'],
        'name' => (string)$row['name'],
        'qty' => $qty,
        'stock' => (int)$row['stock'],
        'unit_effective' => $unitEff,
        'line_total' => $lineTotal,
    ];
}

if (empty($lines)) {
    $_SESSION['pos_bill_error'] = 'Cart has no valid lines.';
    header('Location: dashboard.php?page=pos');
    exit;
}

$computed = round(array_sum(array_column($lines, 'line_total')), 2);
if (abs($computed - round($postTotal, 2)) > 0.02) {
    $_SESSION['pos_bill_error'] = 'Total mismatch. Refresh the page and try again.';
    header('Location: dashboard.php?page=pos');
    exit;
}

if ($cash < $computed) {
    $_SESSION['pos_bill_error'] = 'Cash given is less than the grand total.';
    header('Location: dashboard.php?page=pos');
    exit;
}

foreach ($lines as $ln) {
    if ($ln['qty'] > $ln['stock']) {
        $_SESSION['pos_bill_error'] = 'Not enough stock for ' . $ln['name'] . '. Update the cart.';
        header('Location: dashboard.php?page=pos');
        exit;
    }
}

try {
    $pdo->beginTransaction();

    $insOrder = $pdo->prepare(
        'INSERT INTO orders (customer_id, user_id, total_amount, status) VALUES (?,?,?, \'completed\')'
    );
    $insOrder->execute([$customerId, $userId, $computed]);
    $orderId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)'
    );
    $updStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

    foreach ($lines as $ln) {
        $insItem->execute([$orderId, $ln['product_id'], $ln['qty'], $ln['unit_effective']]);
        $updStock->execute([$ln['qty'], $ln['product_id'], $ln['qty']]);
        if ($updStock->rowCount() === 0) {
            throw new RuntimeException('stock_error');
        }
    }

    try {
        $pay = $pdo->prepare(
            'INSERT INTO payments (order_id, payment_method, amount) VALUES (?,\'cash\',?)'
        );
        $pay->execute([$orderId, $cash]);
    } catch (Throwable $e) {
        /* payments table optional in some installs */
    }

    $pdo->query('DELETE FROM cart');

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['pos_bill_error'] = 'Could not complete the bill. Please try again.';
    header('Location: dashboard.php?page=pos');
    exit;
}

header('Location: bill_receipt.php?order_id=' . $orderId . '&print=1');
exit;
