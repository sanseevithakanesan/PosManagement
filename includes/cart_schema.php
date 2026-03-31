<?php

/**
 * Ensure cart table supports per-line discount (safe to run repeatedly).
 */
function ensure_cart_discount_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $pdo->exec(
            "ALTER TABLE cart ADD COLUMN discount_type VARCHAR(10) NOT NULL DEFAULT 'none' AFTER qty"
        );
    } catch (Throwable $e) {
        /* column exists */
    }
    try {
        $pdo->exec(
            "ALTER TABLE cart ADD COLUMN discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_type"
        );
    } catch (Throwable $e) {
        /* column exists */
    }

    $done = true;
}

function pos_cart_line_totals(int $qty, float $price, string $discount_type, float $discount_value): array
{
    $subtotal = round($qty * $price, 2);
    $dt = $discount_type !== '' ? $discount_type : 'none';
    $dv = max(0, $discount_value);

    if ($dt === 'percent' && $dv > 0) {
        $pct = min(100, $dv);
        $disc = round($subtotal * ($pct / 100), 2);
    } elseif ($dt === 'amount' && $dv > 0) {
        $disc = min($subtotal, round($dv, 2));
    } else {
        $disc = 0;
    }

    $line_total = max(0, round($subtotal - $disc, 2));

    return [$subtotal, $disc, $line_total];
}

function pos_cart_grand_total(PDO $pdo): float
{
    $rows = $pdo->query(
        "SELECT c.qty, c.discount_type, c.discount_value, p.price
         FROM cart c
         JOIN products p ON p.id = c.product_id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $sum = 0;
    foreach ($rows as $r) {
        $sum += pos_cart_line_totals(
            (int)$r['qty'],
            (float)$r['price'],
            (string)($r['discount_type'] ?? 'none'),
            (float)($r['discount_value'] ?? 0)
        )[2];
    }

    return round($sum, 2);
}
