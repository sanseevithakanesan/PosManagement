<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/database/db.php';
require_once dirname(__DIR__) . '/includes/pdf_receipt.php';

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    header('Location: dashboard.php?page=pos');
    exit;
}

$uid = (int)($_SESSION['user_id'] ?? 0);

$st = $pdo->prepare(
    'SELECT o.*, c.name AS customer_name, c.phone AS customer_phone
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     WHERE o.id = ?'
);
$st->execute([$orderId]);
$order = $st->fetch(PDO::FETCH_ASSOC);
if (!$order || (int)$order['user_id'] !== $uid) {
    http_response_code(404);
    echo 'Bill not found.';
    exit;
}

$itemSt = $pdo->prepare(
    'SELECT oi.quantity, oi.price, p.name
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.id ASC'
);
$itemSt->execute([$orderId]);
$items = $itemSt->fetchAll(PDO::FETCH_ASSOC);

$cashPaid = (float)($order['total_amount']);
$accountName = 'Cash';
$pmt = $pdo->prepare('
    SELECT p.amount, pa.account_name 
    FROM payments p 
    LEFT JOIN payment_accounts pa ON pa.id = p.account_id 
    WHERE p.order_id = ? 
    ORDER BY p.id DESC LIMIT 1
');
$pmt->execute([$orderId]);
$rowPay = $pmt->fetch(PDO::FETCH_ASSOC);
if ($rowPay) {
    if (isset($rowPay['amount'])) $cashPaid = (float)$rowPay['amount'];
    if (!empty($rowPay['account_name'])) $accountName = $rowPay['account_name'];
}

$grand = (float)$order['total_amount'];
$balance = round($cashPaid - $grand, 2);

$billNo = 'BILL-' . date('Ymd', strtotime((string)$order['created_at'])) . '-' . $orderId;

$customerLine = 'Walk-in';
if (!empty($order['customer_phone'])) {
    $customerLine = trim((string)$order['customer_phone']);
    if (!empty($order['customer_name'])) {
        $customerLine .= ' · ' . (string)$order['customer_name'];
    }
} elseif (!empty($order['customer_name'])) {
    $customerLine = (string)$order['customer_name'];
}

$pdfRows = [];
foreach ($items as $it) {
    $qty = (int)$it['quantity'];
    $unit = (float)$it['price'];
    $total = round($qty * $unit, 2);
    $pdfRows[] = [
        'name' => (string)$it['name'],
        'qty' => $qty,
        'unit' => $unit,
        'total' => $total,
    ];
}

if (isset($_GET['pdf']) && $_GET['pdf'] === '1') {
    $pdf = pdf_receipt_build(
        'MY SHOP',
        'Your Tagline · Address · Phone',
        $billNo,
        $customerLine,
        date('n/j/Y, g:i:s A', strtotime((string)$order['created_at'])),
        $pdfRows,
        $grand,
        $cashPaid,
        $balance
    );
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="bill-' . $orderId . '.pdf"');
    echo $pdf;
    exit;
}

$doPrint = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($billNo) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; margin: 0; padding: 24px; color: #0f172a; }
        .sheet { max-width: 420px; margin: 0 auto; background: #fffdf7; border-radius: 16px; padding: 24px; box-shadow: 0 8px 30px rgba(0,0,0,.12); }
        h1 { text-align: center; font-size: 1.15rem; margin: 0 0 8px; }
        .muted { text-align: center; color: #64748b; font-size: 0.85rem; margin-bottom: 12px; }
        .bill-no { text-align: center; font-weight: 700; margin-bottom: 8px; }
        .meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 0.88rem; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 8px 0; text-align: left; }
        th { border-bottom: 1px solid #e2e8f0; }
        td.num, th.num { text-align: right; }
        td.cnt, th.cnt { text-align: center; width: 48px; }
        .tot { display: flex; justify-content: space-between; font-weight: 800; margin-top: 12px; padding-top: 12px; border-top: 2px solid #0f172a; }
        .sum { margin-top: 8px; font-size: 0.95rem; }
        .thanks { text-align: center; margin-top: 16px; color: #64748b; }
        .actions { margin-top: 20px; display: flex; flex-direction: column; gap: 10px; }
        .actions a, .actions button { display: block; text-align: center; padding: 12px 16px; border-radius: 12px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; font-size: 1rem; }
        .btn-pdf { background: #1e293b; color: #fff; }
        .btn-back { background: #e2e8f0; color: #0f172a; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .sheet { box-shadow: none; max-width: none; border-radius: 0; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <h1>🏪 MY SHOP</h1>
    <div class="muted">Your Tagline · Address · Phone</div>
    <div class="bill-no"><?= htmlspecialchars($billNo) ?></div>
    <div class="meta">
        <span>👤 <?= htmlspecialchars($customerLine) ?></span>
        <span><?= htmlspecialchars(date('n/j/Y, g:i:s A', strtotime((string)$order['created_at']))) ?></span>
    </div>
    <table>
        <thead>
        <tr><th>Item</th><th class="cnt">Qty</th><th class="num">Price</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <?php
            $q = (int)$it['quantity'];
            $p = (float)$it['price'];
            $t = round($q * $p, 2);
            ?>
            <tr>
                <td><?= htmlspecialchars((string)$it['name']) ?></td>
                <td class="cnt"><?= $q ?></td>
                <td class="num"><?= number_format($p, 2) ?></td>
                <td class="num"><?= number_format($t, 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="tot"><span>GRAND TOTAL</span><span><?= number_format($grand, 2) ?></span></div>
    <div class="sum">Account: <strong><?= htmlspecialchars($accountName) ?></strong></div>
    <div class="sum">Cash given: <strong><?= number_format($cashPaid, 2) ?></strong></div>
    <div class="sum">Balance: <strong><?= number_format($balance, 2) ?></strong></div>
    <div class="thanks">✨ Thank you! ✨</div>
    <div class="actions">
        <a class="btn-pdf" href="bill_receipt.php?order_id=<?= (int)$orderId ?>&pdf=1">Download PDF</a>
        <button type="button" class="btn-pdf" style="margin:0" onclick="window.print()">Print again</button>
        <a class="btn-back" href="dashboard.php?page=pos">Back to billing</a>
    </div>
</div>
<?php if ($doPrint): ?>
<script>
window.addEventListener('load', function () {
    function downloadPdf() {
        var a = document.createElement('a');
        a.href = 'bill_receipt.php?order_id=<?= (int)$orderId ?>&pdf=1';
        a.download = 'bill-<?= (int)$orderId ?>.pdf';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
    }
    window.addEventListener('afterprint', downloadPdf, { once: true });
    setTimeout(function () { window.print(); }, 350);
});
</script>
<?php endif; ?>
</body>
</html>
