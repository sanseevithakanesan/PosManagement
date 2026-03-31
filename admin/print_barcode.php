<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/database/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid product.');
}

$stmt = $pdo->prepare('SELECT id, name, barcode, sku FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

$code = trim((string)($product['barcode'] ?? ''));
if ($code === '') {
    http_response_code(400);
    exit('This product has no barcode to print.');
}

$name = (string)$product['name'];
$sku = trim((string)($product['sku'] ?? ''));
$codeEsc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
$nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$skuEsc = htmlspecialchars($sku, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barcode — <?= $nameEsc ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            margin: 0;
            padding: 1.5rem;
            color: #0f172a;
            background: #fff;
        }
        .sheet {
            max-width: 420px;
            margin: 0 auto;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
        }
        .product-name {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            line-height: 1.3;
        }
        .meta {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 1rem;
        }
        #barcode {
            max-width: 100%;
            height: auto;
        }
        .digits {
            font-family: ui-monospace, monospace;
            font-size: 0.95rem;
            letter-spacing: 0.12em;
            margin-top: 0.5rem;
            font-weight: 600;
        }
        .no-print {
            margin-top: 1.25rem;
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-outline { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .sheet { border: none; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="product-name"><?= $nameEsc ?></div>
        <?php if ($sku !== ''): ?>
            <div class="meta">SKU: <?= $skuEsc ?></div>
        <?php else: ?>
            <div class="meta">&nbsp;</div>
        <?php endif; ?>
        <svg id="barcode"></svg>
        <div class="digits"><?= $codeEsc ?></div>
    </div>
    <div class="no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
        <button type="button" class="btn btn-outline" onclick="window.close()">Close</button>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        (function () {
            var code = <?= json_encode($code, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            try {
                JsBarcode('#barcode', code, {
                    format: 'CODE128',
                    width: 2,
                    height: 72,
                    margin: 8,
                    displayValue: false,
                    background: '#ffffff',
                    lineColor: '#0f172a'
                });
            } catch (e) {
                document.getElementById('barcode').outerHTML = '<p style="color:#b91c1c;">Could not render barcode for this value.</p>';
            }
        })();
    </script>
</body>
</html>
