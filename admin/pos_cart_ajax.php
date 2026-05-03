<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/cart_schema.php';
require_once dirname(__DIR__) . '/includes/customer_lookup.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_qty') {
        $cart_id = (int)($_POST['cart_id'] ?? 0);
        $new_qty = (int)($_POST['qty'] ?? 0);

        if ($cart_id <= 0 || $new_qty < 1) {
            echo json_encode(['ok' => false, 'message' => 'Invalid cart or quantity']);
            exit;
        }

        $cart_item = $pdo->prepare(
            'SELECT c.*, p.stock, p.name, p.price FROM cart c
             JOIN products p ON p.id = c.product_id WHERE c.id = ?'
        );
        $cart_item->execute([$cart_id]);
        $ci = $cart_item->fetch(PDO::FETCH_ASSOC);

        if (!$ci) {
            echo json_encode(['ok' => false, 'message' => 'Cart row not found']);
            exit;
        }

        $stock = (int)$ci['stock'];
        if ($new_qty > $stock) {
            $new_qty = $stock;
        }

        $pdo->prepare('UPDATE cart SET qty = ? WHERE id = ?')->execute([$new_qty, $cart_id]);

        $dt = (string)($ci['discount_type'] ?? 'none');
        $dv = (float)($ci['discount_value'] ?? 0);
        [$subtotal, $disc, $line_total] = pos_cart_line_totals(
            $new_qty,
            (float)$ci['price'],
            $dt,
            $dv
        );

        $grand = pos_cart_grand_total($pdo);

        echo json_encode([
            'ok' => true,
            'cart_id' => $cart_id,
            'qty' => $new_qty,
            'subtotal' => $subtotal,
            'discount' => $disc,
            'line_total' => $line_total,
            'grand_total' => $grand,
            'capped' => (int)($_POST['qty'] ?? 0) > $stock,
            'max_stock' => $stock,
        ]);
        exit;
    }

    if ($action === 'update_discount') {
        $cart_id = (int)($_POST['cart_id'] ?? 0);
        $dtype = $_POST['discount_type'] ?? 'none';
        $dval = (float)($_POST['discount_value'] ?? 0);

        if ($cart_id <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Invalid cart']);
            exit;
        }

        if (!in_array($dtype, ['none', 'percent', 'amount'], true)) {
            $dtype = 'none';
        }

        if ($dtype === 'none' || $dval <= 0) {
            $dtype = 'none';
            $dval = 0;
        }

        $cart_item = $pdo->prepare(
            'SELECT c.*, p.price FROM cart c JOIN products p ON p.id = c.product_id WHERE c.id = ?'
        );
        $cart_item->execute([$cart_id]);
        $ci = $cart_item->fetch(PDO::FETCH_ASSOC);

        if (!$ci) {
            echo json_encode(['ok' => false, 'message' => 'Cart row not found']);
            exit;
        }

        $qty = (int)$ci['qty'];
        $price = (float)$ci['price'];

        if ($dtype === 'percent') {
            $dval = min(100, max(0, $dval));
        } elseif ($dtype === 'amount') {
            $sub = $qty * $price;
            $dval = min($sub, max(0, $dval));
        }

        $pdo->prepare('UPDATE cart SET discount_type = ?, discount_value = ? WHERE id = ?')
            ->execute([$dtype, $dval, $cart_id]);

        [$subtotal, $disc, $line_total] = pos_cart_line_totals($qty, $price, $dtype, $dval);
        $grand = pos_cart_grand_total($pdo);

        echo json_encode([
            'ok' => true,
            'cart_id' => $cart_id,
            'discount_type' => $dtype,
            'discount_value' => $dval,
            'subtotal' => $subtotal,
            'discount' => $disc,
            'line_total' => $line_total,
            'grand_total' => $grand,
        ]);
        exit;
    }

    if ($action === 'customer_search') {
        $q = trim((string)($_POST['q'] ?? ''));
        if (strlen($q) < 1) {
            echo json_encode(['ok' => true, 'items' => []]);
            exit;
        }

        $qd = customer_normalize_digits($q);
        $likeName = '%' . $q . '%';
        $likePhone = '%' . ($qd !== '' ? $qd : $q) . '%';

        $stmt = $pdo->prepare(
            'SELECT id, name, phone FROM customers
             WHERE name LIKE ? OR phone LIKE ?
             ORDER BY id DESC
             LIMIT 25'
        );
        $stmt->execute([$likeName, $likePhone]);

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'phone' => (string)($row['phone'] ?? ''),
                'label' => customer_format_label((string)$row['name'], $row['phone'] ?? ''),
                'digits' => customer_normalize_digits($row['phone'] ?? ''),
            ];
        }

        echo json_encode(['ok' => true, 'items' => $items]);
        exit;
    }

    if ($action === 'customer_resolve') {
        $display = trim((string)($_POST['display'] ?? ''));
        $nameExtra = trim((string)($_POST['name'] ?? ''));

        $parsed = customer_parse_lookup_line($display);
        $digits = $parsed['digits'];
        $name = $parsed['name'] !== '' ? $parsed['name'] : $nameExtra;

        if ($digits === '' || strlen($digits) < 7) {
            echo json_encode(['ok' => false, 'message' => 'Enter a valid phone number (at least 7 digits).']);
            exit;
        }

        $existing = customer_find_by_digits($pdo, $digits);
        if ($existing) {
            $label = customer_format_label((string)$existing['name'], $existing['phone'] ?? '');
            echo json_encode([
                'ok' => true,
                'customer_id' => (int)$existing['id'],
                'created' => false,
                'label' => $label,
                'name' => (string)$existing['name'],
                'phone' => (string)($existing['phone'] ?? ''),
            ]);
            exit;
        }

        if ($name === '') {
            $name = 'Customer ' . $digits;
        }

        $phoneStore = $parsed['phone_raw'] !== '' && customer_normalize_digits($parsed['phone_raw']) === $digits
            ? $parsed['phone_raw']
            : $digits;

        $ins = $pdo->prepare(
            'INSERT INTO customers (name, phone, email, address) VALUES (?,?,?,?)'
        );
        $ins->execute([$name, $phoneStore, '', '']);
        $newId = (int)$pdo->lastInsertId();

        $label = customer_format_label($name, $phoneStore);

        echo json_encode([
            'ok' => true,
            'customer_id' => $newId,
            'created' => true,
            'label' => $label,
            'name' => $name,
            'phone' => $phoneStore,
        ]);
        exit;
    }

    if ($action === 'add_account') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            echo json_encode(['ok' => false, 'message' => 'Account name cannot be empty']);
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM payment_accounts WHERE account_name = ?");
        $check->execute([$name]);
        if ($check->fetch()) {
            echo json_encode(['ok' => false, 'message' => 'Account already exists']);
            exit;
        }

        $ins = $pdo->prepare("INSERT INTO payment_accounts (account_name) VALUES (?)");
        $ins->execute([$name]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'ok' => true, 
            'id' => $newId, 
            'name' => $name
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Server error']);
}
