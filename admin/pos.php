<?php 
require_once "../database/db.php";

/* =====================
   STOCK ALERT MESSAGE
===================== */
$stock_alert = '';
$alert_type  = 'warn';

/* =====================
   ADD TO CART
===================== */
if(isset($_POST['add_cart'])){
    $pid = $_POST['product_id'];
    $prod_stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $prod_stmt->execute([$pid]);
    $prod = $prod_stmt->fetch();

    if($prod['stock'] <= 0){
        $stock_alert = "Sorry! <b>{$prod['name']}</b> is OUT OF STOCK!";
        $alert_type  = 'error';
    } else {
        $check = $pdo->prepare("SELECT * FROM cart WHERE product_id=?");
        $check->execute([$pid]);
        $cart_row = $check->fetch();
        $cart_qty = $cart_row ? $cart_row['qty'] : 0;

        if($cart_qty >= $prod['stock']){
            $stock_alert = "Cannot add more! Only <b>{$prod['stock']}</b> unit(s) of <b>{$prod['name']}</b> available.";
            $alert_type  = 'warn';
        } else {
            if($cart_row){
                $pdo->prepare("UPDATE cart SET qty = qty + 1 WHERE product_id=?")
                    ->execute([$pid]);
            } else {
                $pdo->prepare("INSERT INTO cart(product_id, qty) VALUES(?,1)")
                    ->execute([$pid]);
            }
        }
    }
}

/* =====================
   UPDATE QTY
===================== */
if(isset($_POST['update_qty'])){
    $new_qty = (int)$_POST['qty'];
    $cart_id = $_POST['cart_id'];

    $cart_item = $pdo->prepare("SELECT c.*, p.stock, p.name FROM cart c JOIN products p ON p.id = c.product_id WHERE c.id=?");
    $cart_item->execute([$cart_id]);
    $ci = $cart_item->fetch();

    if($new_qty > $ci['stock']){
        $stock_alert = "Only <b>{$ci['stock']}</b> unit(s) of <b>{$ci['name']}</b> available! Set to max.";
        $alert_type  = 'warn';
        $pdo->prepare("UPDATE cart SET qty=? WHERE id=?")
            ->execute([$ci['stock'], $cart_id]);
    } else {
        $pdo->prepare("UPDATE cart SET qty=? WHERE id=?")
            ->execute([$new_qty, $cart_id]);
    }
}

/* =====================
   REMOVE ITEM
===================== */
if(isset($_GET['remove'])){
    $pdo->prepare("DELETE FROM cart WHERE id=?")
        ->execute([$_GET['remove']]);
    header("Location: dashboard.php?page=pos");
    exit;
}

/* =====================
   CLEAR CART
===================== */
if(isset($_GET['clear'])){
    $pdo->query("DELETE FROM cart");
    header("Location: dashboard.php?page=pos");
    exit;
}

/* =====================
   BARCODE SCAN
===================== */
$barcode = $_GET['barcode'] ?? '';
if($barcode != ''){
    $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode=?");
    $stmt->execute([$barcode]);
    $product = $stmt->fetch();

    if($product){
        if($product['stock'] <= 0){
            $stock_alert = "Scanned product <b>{$product['name']}</b> is OUT OF STOCK!";
            $alert_type  = 'error';
        } else {
            $check = $pdo->prepare("SELECT * FROM cart WHERE product_id=?");
            $check->execute([$product['id']]);
            $cart_row = $check->fetch();
            $cart_qty = $cart_row ? $cart_row['qty'] : 0;

            if($cart_qty >= $product['stock']){
                $stock_alert = "Only <b>{$product['stock']}</b> unit(s) of <b>{$product['name']}</b> in stock.";
                $alert_type  = 'warn';
            } else {
                if($cart_row){
                    $pdo->prepare("UPDATE cart SET qty = qty + 1 WHERE product_id=?")
                        ->execute([$product['id']]);
                } else {
                    $pdo->prepare("INSERT INTO cart(product_id, qty) VALUES(?,1)")
                        ->execute([$product['id']]);
                }
            }
        }
    }
}

/* =====================
   DATA
===================== */
$products  = $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll();
$cart      = $pdo->query("
    SELECT c.id, c.qty, p.id AS pid, p.name, p.price, p.stock,
           (c.qty * p.price) AS total
    FROM cart c
    JOIN products p ON p.id = c.product_id
")->fetchAll();
$customers = $pdo->query("SELECT * FROM customers")->fetchAll();
$total     = 0;
foreach($cart as $c) $total += $c['total'];
$cart_count   = count($cart);
$out_of_stock = count(array_filter($products, fn($p) => $p['stock'] == 0));
$low_stock    = count(array_filter($products, fn($p) => $p['stock'] > 0 && $p['stock'] <= 5));
$in_stock     = count($products) - $out_of_stock - $low_stock;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
<title>POS System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<style>
/* ============================================================
   RESET & BASE
============================================================ */
*, *::before, *::after { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
}

:root {
    --ink:     #0f0e17;
    --paper:   #f7f6f2;
    --card:    #ffffff;
    --accent:  #ff6b35;
    --green:   #22c55e;
    --red:     #ef4444;
    --yellow:  #f59e0b;
    --blue:    #3b82f6;
    --muted:   #9ca3af;
    --border:  #e5e2db;
    --receipt: #fffdf7;
    --radius:  12px;
    --shadow:  0 2px 12px rgba(0,0,0,.08);
    --safe-b:  env(safe-area-inset-bottom, 0px);
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--paper);
    color: var(--ink);
    min-height: 100vh;
    overflow-x: hidden;
}

.pos-wrap {
    max-width: 1400px;
    margin: 0 auto;
    padding: clamp(12px, 3vw, 24px);
    padding-bottom: clamp(80px, 15vh, 120px);
}

.pos-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: clamp(12px, 2vw, 20px);
    flex-wrap: wrap;
    gap: 12px;
}

.pos-header h3 {
    font-family: 'Oxanium', monospace;
    font-weight: 800;
    font-size: clamp(1.2rem, 4vw, 1.5rem);
    letter-spacing: .07em;
    color: var(--ink);
    border-left: 5px solid var(--accent);
    padding-left: 12px;
    line-height: 1.2;
}

.inv-summary {
    display: flex;
    gap: clamp(6px, 2vw, 12px);
    flex-wrap: wrap;
    margin-bottom: clamp(12px, 2vw, 16px);
}

.inv-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 30px;
    padding: clamp(4px, 1.5vw, 6px) clamp(10px, 2vw, 14px);
    font-size: clamp(0.7rem, 2.5vw, 0.8rem);
    font-weight: 600;
}

.inv-chip .dot { 
    width: clamp(6px, 2vw, 8px); 
    height: clamp(6px, 2vw, 8px); 
    border-radius: 50%; 
}

.stock-alert-banner {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #fff8e1;
    border: 2px solid var(--yellow);
    border-radius: var(--radius);
    padding: clamp(10px, 2.5vw, 14px);
    margin-bottom: clamp(12px, 2vw, 16px);
    font-size: clamp(0.85rem, 2.8vw, 0.95rem);
    color: #7d5a00;
}

.stock-alert-banner.danger {
    background: #fff0ef;
    border-color: var(--red);
    color: #8b0000;
}

.scan-bar {
    display: flex;
    gap: clamp(8px, 2vw, 12px);
    margin-bottom: clamp(14px, 2.5vw, 20px);
}

.scan-bar input {
    flex: 1;
    padding: clamp(10px, 2.5vw, 14px);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: clamp(0.9rem, 3vw, 1rem);
    background: var(--card);
}

/* Mobile Tabs */
.mobile-tabs {
    display: none;
    position: sticky;
    top: 0;
    z-index: 100;
    background: var(--card);
    border-bottom: 2px solid var(--border);
    margin: 0 -16px clamp(12px, 2vw, 16px);
    padding: 0 clamp(12px, 3vw, 20px);
}

.mobile-tabs .tab-btns {
    display: flex;
    gap: 0;
}

.tab-btn {
    flex: 1;
    padding: clamp(12px, 3vw, 16px) clamp(6px, 1.5vw, 12px);
    border: none;
    background: transparent;
    font-family: 'Oxanium', monospace;
    font-size: clamp(0.75rem, 2.8vw, 0.85rem);
    font-weight: 700;
    color: var(--muted);
    cursor: pointer;
    border-bottom: 3px solid transparent;
}

.tab-btn.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}

.tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--accent);
    color: #fff;
    font-size: 0.65rem;
    font-weight: 800;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-left: 6px;
}

/* Desktop Layout */
.pos-columns {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: clamp(16px, 3vw, 28px);
    align-items: start;
}

.col-section-title {
    font-family: 'Oxanium', monospace;
    font-weight: 700;
    font-size: clamp(0.9rem, 3vw, 1rem);
    margin-bottom: clamp(10px, 2vw, 14px);
}

/* Products */
.products-list { 
    display: flex; 
    flex-direction: column; 
    gap: clamp(8px, 2vw, 12px); 
}

.prod-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: clamp(8px, 2vw, 15px);
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: clamp(10px, 2.5vw, 14px) clamp(12px, 3vw, 16px);
    box-shadow: var(--shadow);
}

.prod-info { 
    flex: 1; 
}

.prod-name { 
    font-weight: 600; 
    font-size: clamp(0.85rem, 2.8vw, 0.95rem); 
}

.prod-price { 
    color: var(--accent); 
    font-weight: 700; 
    font-size: clamp(0.85rem, 2.5vw, 0.9rem); 
    margin-top: 4px; 
}

.stock-badge {
    display: inline-flex;
    font-size: clamp(0.65rem, 2.2vw, 0.7rem);
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    margin-top: 6px;
}

.stock-badge.ok    { background: #dcfce7; color: #15803d; }
.stock-badge.low   { background: #fef3c7; color: #92400e; }
.stock-badge.empty { background: #fee2e2; color: #991b1b; }

.btn-add {
    background: var(--green);
    color: #fff;
    border: none;
    border-radius: 9px;
    padding: clamp(8px, 2.2vw, 11px) clamp(12px, 3vw, 18px);
    font-weight: 700;
    font-size: clamp(0.8rem, 2.5vw, 0.9rem);
    cursor: pointer;
    min-width: 65px;
}

.btn-oos {
    background: #f3f4f6;
    color: #9ca3af;
    border: none;
    border-radius: 9px;
    padding: clamp(8px, 2.2vw, 11px) clamp(10px, 2.5vw, 14px);
    font-size: clamp(0.7rem, 2.2vw, 0.75rem);
    cursor: not-allowed;
}

/* Cart */
.cart-items-wrap { 
    display: flex; 
    flex-direction: column; 
    gap: clamp(8px, 2vw, 12px); 
    margin-bottom: clamp(12px, 2vw, 16px); 
}

.cart-item-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: clamp(10px, 2.5vw, 14px) clamp(12px, 3vw, 16px);
}

.cart-item-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
}

.cart-item-name { 
    font-weight: 600; 
    font-size: clamp(0.85rem, 2.8vw, 0.95rem); 
}

.cart-item-total {
    font-weight: 700;
    font-size: clamp(0.9rem, 2.8vw, 1rem);
    color: var(--accent);
}

.cart-item-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: clamp(8px, 2vw, 15px);
    flex-wrap: wrap;
}

.cart-qty-form {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cart-qty-input {
    width: clamp(55px, 15vw, 70px);
    padding: clamp(6px, 1.8vw, 8px);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    text-align: center;
}

.btn-ok {
    background: var(--blue);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: clamp(6px, 1.8vw, 8px) clamp(10px, 2.5vw, 14px);
    font-weight: 700;
    cursor: pointer;
}

.avail-lbl {
    font-size: clamp(0.65rem, 2.2vw, 0.75rem);
    color: var(--muted);
}

.btn-rm {
    background: var(--red);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: clamp(6px, 1.8vw, 8px) clamp(10px, 2.5vw, 14px);
    font-size: clamp(0.75rem, 2.2vw, 0.85rem);
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.cart-empty {
    text-align: center;
    padding: clamp(25px, 8vw, 45px);
    color: var(--muted);
    background: var(--card);
    border-radius: var(--radius);
    border: 2px dashed var(--border);
}

.grand-total-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--ink);
    color: #fff;
    border-radius: var(--radius);
    padding: clamp(12px, 2.5vw, 16px) clamp(14px, 3vw, 20px);
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.grand-total-amount {
    font-weight: 800;
    font-size: clamp(1.2rem, 4.5vw, 1.5rem);
    color: var(--accent);
}

.btn-clear {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fee2e2;
    color: var(--red);
    border: none;
    border-radius: 8px;
    padding: clamp(8px, 2vw, 10px) clamp(12px, 3vw, 18px);
    font-size: clamp(0.75rem, 2.2vw, 0.85rem);
    cursor: pointer;
    text-decoration: none;
    margin-bottom: 16px;
}

/* Checkout Form - IMPORTANT: This must be visible */
.checkout-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid var(--border);
}

.checkout-form { 
    display: flex; 
    flex-direction: column; 
    gap: clamp(10px, 2.5vw, 14px); 
}

.checkout-form select,
.checkout-form input {
    width: 100%;
    padding: clamp(11px, 2.8vw, 14px) clamp(12px, 3vw, 16px);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: clamp(0.9rem, 3vw, 1rem);
    background: var(--card);
}

.btn-preview {
    width: 100%;
    padding: clamp(12px, 3vw, 16px);
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: var(--radius);
    font-weight: 700;
    font-size: clamp(0.9rem, 3vw, 1rem);
    cursor: pointer;
    margin-top: 10px;
}

/* Sticky Bottom Bar */
.sticky-bottom-bar {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 200;
    background: var(--card);
    border-top: 2px solid var(--border);
    padding: clamp(10px, 2.5vw, 14px) clamp(12px, 3vw, 20px);
    box-shadow: 0 -4px 20px rgba(0,0,0,.1);
}

.sticky-bottom-bar .sbb-inner {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sbb-total {
    flex: 1;
}

.sbb-total .amt {
    font-weight: 800;
    font-size: clamp(1rem, 4vw, 1.2rem);
    color: var(--accent);
}

.sbb-checkout-btn {
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: var(--radius);
    padding: clamp(10px, 2.5vw, 14px) clamp(16px, 4vw, 24px);
    font-weight: 700;
    cursor: pointer;
}

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active { 
    display: flex; 
}

.modal-box {
    background: var(--receipt);
    border-radius: 20px;
    width: 90%;
    max-width: 550px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.modal-close-x {
    position: absolute;
    top: 14px; 
    right: 14px;
    background: #f3f4f6;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
}

.bill-inner { 
    padding: 20px; 
}

.bill-header { 
    text-align: center; 
    margin-bottom: 16px; 
}

.bill-items { 
    width: 100%; 
    border-collapse: collapse; 
}

.bill-items th, .bill-items td {
    padding: 8px 0;
    text-align: left;
}

.bill-total-row {
    display: flex;
    justify-content: space-between;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 2px solid var(--ink);
}

.bill-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    padding: 16px;
}

.bill-actions button, 
.bill-actions a {
    padding: 12px;
    border: none;
    border-radius: var(--radius);
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
}

.btn-confirm { 
    background: var(--accent); 
    color: #fff; 
    grid-column: span 2; 
}

/* Toast */
.pos-toast {
    position: fixed;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 99999;
    background: var(--ink);
    color: #fff;
    padding: 12px 20px;
    border-radius: 100px;
    white-space: nowrap;
    max-width: 90vw;
}

/* Responsive */
@media (max-width: 767px) {
    .pos-wrap { 
        padding: 12px 12px 100px; 
    }
    
    .mobile-tabs { 
        display: block; 
    }
    
    .pos-columns { 
        grid-template-columns: 1fr; 
        gap: 20px; 
    }
    
    .tab-panel { 
        display: none; 
    }
    
    .tab-panel.active { 
        display: block; 
    }
    
    .sticky-bottom-bar { 
        display: block; 
    }
    
    /* Make checkout section always visible when cart tab is active */
    #tab-cart.active .checkout-section {
        display: block !important;
    }
}

@media (min-width: 768px) {
    .tab-panel { 
        display: block !important; 
    }
    
    .checkout-section {
        display: block !important;
    }
}

@media print {
    body > *:not(#printArea) { display: none !important; }
    #printArea { display: block !important; }
}

#printArea { 
    display: none; 
}
</style>

<!-- ============================================================
     HTML
============================================================ -->
<div class="pos-wrap">

    <div class="pos-header">
        <h3>🧾 POS System</h3>
    </div>

    <div class="inv-summary">
        <div class="inv-chip"><span class="dot dot-green"></span>In Stock: <?= $in_stock ?></div>
        <div class="inv-chip"><span class="dot dot-yellow"></span>Low (≤5): <?= $low_stock ?></div>
        <div class="inv-chip"><span class="dot dot-red"></span>Out: <?= $out_of_stock ?></div>
    </div>

    <?php if($stock_alert): ?>
    <div class="stock-alert-banner <?= $alert_type === 'error' ? 'danger' : '' ?>">
        <span class="alert-icon"><?= $alert_type === 'error' ? '🚫' : '⚠️' ?></span>
        <div><?= $stock_alert ?></div>
    </div>
    <?php endif; ?>

    <form method="GET" class="scan-bar">
        <input type="hidden" name="page" value="pos">
        <input type="text" name="barcode" placeholder="📷 Scan barcode / QR here..." autocomplete="off">
    </form>

    <!-- MOBILE TABS -->
    <div class="mobile-tabs">
        <div class="tab-btns">
            <button class="tab-btn active" onclick="switchTab('products', this)">
                🛒 Products
            </button>
            <button class="tab-btn" onclick="switchTab('cart', this)">
                🧾 Cart
                <?php if($cart_count > 0): ?>
                    <span class="tab-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <!-- COLUMNS -->
    <div class="pos-columns">

        <!-- PRODUCTS TAB -->
        <div class="tab-panel active" id="tab-products">
            <div class="col-section-title">🛒 Products</div>
            <div class="products-list">
            <?php foreach($products as $p):
                $s = (int)$p['stock'];
                $badge_class = $s == 0 ? 'empty' : ($s <= 5 ? 'low' : 'ok');
                $badge_text  = $s == 0 ? '🚫 Out of Stock' : ($s <= 5 ? "⚠️ Only {$s} left" : "✅ {$s} in stock");
            ?>
            <div class="prod-card <?= $s == 0 ? 'out-of-stock' : '' ?>">
                <div class="prod-info">
                    <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="prod-price">💰 <?= number_format($p['price'], 2) ?></div>
                    <span class="stock-badge <?= $badge_class ?>"><?= $badge_text ?></span>
                </div>
                <?php if($s == 0): ?>
                    <button class="btn-oos" disabled>🚫 Out</button>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button name="add_cart" class="btn-add">+ Add</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
        </div>

        <!-- CART + CHECKOUT TAB -->
        <div class="tab-panel" id="tab-cart">
            <div class="col-section-title">🧾 Cart</div>

            <!-- Cart items -->
            <div class="cart-items-wrap">
            <?php if(empty($cart)): ?>
                <div class="cart-empty">
                    <div class="cart-empty-icon">🛒</div>
                    <p>Your cart is empty.<br>Add products from the Products tab.</p>
                </div>
            <?php else: ?>
                <?php foreach($cart as $c):
                    $qty_over  = $c['qty'] > $c['stock'];
                    $row_class = $qty_over ? 'row-over' : '';
                ?>
                <div class="cart-item-card <?= $row_class ?>">
                    <div class="cart-item-top">
                        <div class="cart-item-name"><?= htmlspecialchars($c['name']) ?></div>
                        <div class="cart-item-total"><?= number_format($c['total'], 2) ?></div>
                    </div>
                    <div class="cart-item-bottom">
                        <form method="POST" class="cart-qty-form">
                            <input type="hidden" name="cart_id" value="<?= $c['id'] ?>">
                            <input class="cart-qty-input" type="number"
                                   name="qty" value="<?= $c['qty'] ?>"
                                   min="1" max="<?= $c['stock'] ?>">
                            <button name="update_qty" class="btn-ok">OK</button>
                        </form>
                        <span class="avail-lbl">Avail: <?= $c['stock'] ?></span>
                        <a href="dashboard.php?page=pos&remove=<?= $c['id'] ?>" class="btn-rm">🗑 Remove</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <!-- Grand total bar -->
            <?php if(!empty($cart)): ?>
            <div class="grand-total-bar">
                <div>GRAND TOTAL</div>
                <div class="grand-total-amount">₹ <?= number_format($total, 2) ?></div>
            </div>
            <a href="dashboard.php?page=pos&clear=1" class="btn-clear">🗑 Clear Cart</a>
            <?php endif; ?>

            <!-- CHECKOUT SECTION - THIS IS THE IMPORTANT PART - BUTTON WILL SHOW HERE -->
            <div class="checkout-section">
                <div class="col-section-title" style="margin-bottom:12px;">💳 Checkout</div>
                <div class="checkout-form">
                    <select id="sel_customer" required>
                        <option value="">👤 Select Customer</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                    data-name="<?= htmlspecialchars($c['name']) ?>"
                                    data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>">
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="number" id="cash_input"
                           placeholder="💵 Cash Given" required min="0" step="0.01">

                    <input type="text" id="balance_input"
                           placeholder="🔄 Balance" readonly>

                    <button class="btn-preview" onclick="openPreview()">
                        👁 PREVIEW & GENERATE BILL
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- STICKY BOTTOM BAR -->
<div class="sticky-bottom-bar">
    <div class="sbb-inner">
        <div class="sbb-total">
            <div class="lbl">CART TOTAL</div>
            <div class="amt">₹ <?= number_format($total, 2) ?></div>
        </div>
        <button class="sbb-checkout-btn" onclick="scrollToCheckout()">
            💳 Checkout →
        </button>
    </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="billModal">
  <div class="modal-box">
    <button class="modal-close-x" onclick="closeModal()">✕</button>
    <div class="bill-inner">
      <div class="bill-header">
        <div class="shop-name">🏪 MY SHOP</div>
        <div class="shop-tag">Your Tagline · Address · Phone</div>
        <div class="bill-no" id="bBillNo"></div>
      </div>
      <div class="bill-meta">
        <span>👤 <span id="bCustomer">—</span></span>
        <span id="bDate">—</span>
      </div>
      <hr>
      <table class="bill-items">
        <thead>
          <tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
        </thead>
        <tbody id="bItems"></tbody>
      </table>
      <hr>
      <div class="bill-total-row">
        <span>GRAND TOTAL</span>
        <span class="amount" id="bTotal">0.00</span>
      </div>
      <div class="cash-summary">
        <div>Cash Given: <span id="bCash">0.00</span></div>
        <div>Balance: <span id="bBalance">0.00</span></div>
      </div>
      <div class="bill-thanks">✨ Thank you! ✨</div>
    </div>
    <div class="bill-actions">
      <!-- //<button class="btn-close" onclick="closeModal()">Close</button> -->
      <button class="btn-print" onclick="printBill()">Print</button>
      <a class="btn-wa" id="waBtn" href="#" target="_blank">WhatsApp</a>
      <button class="btn-confirm" onclick="confirmBill()">Confirm & Generate Bill</button>
    </div>
  </div>
</div>

<div id="printArea"></div>

<form id="realBillForm" action="dashboard.php?page=bill" method="POST" style="display:none;">
    <input type="hidden" name="customer_id" id="hCustomer">
    <input type="hidden" name="cash" id="hCash">
    <input type="hidden" name="total_amount" value="<?= $total ?>">
</form>

<div class="pos-toast" id="posToast" style="display:none;"></div>

<script>
const cartItems = <?= json_encode(array_map(fn($c) => [
    'name' => $c['name'], 'qty' => (int)$c['qty'], 
    'price' => (float)$c['price'], 'total' => (float)$c['total'], 
    'stock' => (int)$c['stock']
], $cart)) ?>;
const grandTotal = <?= (float)$total ?>;

<?php if($stock_alert): ?>
showToast(<?= json_encode(strip_tags($stock_alert)) ?>, '<?= $alert_type ?>');
<?php endif; ?>

function switchTab(tabName, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(`tab-${tabName}`).classList.add('active');
}

function scrollToCheckout() {
    if(window.innerWidth < 768) {
        switchTab('cart', document.querySelector('.tab-btn:nth-child(2)'));
        setTimeout(() => {
            document.querySelector('.checkout-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    } else {
        document.querySelector('.checkout-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

document.getElementById('cash_input')?.addEventListener('input', function(){
    const cash = parseFloat(this.value || 0);
    const bal = cash - grandTotal;
    document.getElementById('balance_input').value = isNaN(bal) ? '' : bal.toFixed(2);
});

function showToast(msg, type='warn') {
    const t = document.getElementById('posToast');
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}

function openPreview() {
    const custSel = document.getElementById('sel_customer');
    const cashVal = document.getElementById('cash_input').value;
    if (!custSel.value) { showToast('Please select a customer'); return; }
    if (!cashVal || parseFloat(cashVal) <= 0) { showToast('Please enter cash amount'); return; }
    if (cartItems.length === 0) { showToast('Cart is empty'); return; }

    const custName = custSel.options[custSel.selectedIndex].dataset.name;
    const custPhone = custSel.options[custSel.selectedIndex].dataset.phone || '';
    const cash = parseFloat(cashVal);
    const balance = cash - grandTotal;
    const now = new Date();
    const billNo = 'BILL-' + now.getFullYear() + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0') + '-' + Math.floor(Math.random() * 9000 + 1000);

    document.getElementById('bBillNo').textContent = billNo;
    document.getElementById('bCustomer').textContent = custName;
    document.getElementById('bDate').textContent = now.toLocaleString();
    document.getElementById('bTotal').textContent = grandTotal.toFixed(2);
    document.getElementById('bCash').textContent = cash.toFixed(2);
    document.getElementById('bBalance').textContent = balance.toFixed(2);

    let rows = '';
    cartItems.forEach(i => {
        rows += `<tr><td>${i.name}</td><td style="text-align:center">${i.qty}</td><td style="text-align:right">${i.price.toFixed(2)}</td><td style="text-align:right">${i.total.toFixed(2)}</td></tr>`;
    });
    document.getElementById('bItems').innerHTML = rows;

    let waText = `🧾 BILL RECEIPT\n━━━━━━━━━━━━━━━━━━\n📋 ${billNo}\n👤 Customer: ${custName}\n📅 Date: ${now.toLocaleString()}\n━━━━━━━━━━━━━━━━━━\n`;
    cartItems.forEach(i => { waText += `${i.name} x${i.qty} = ${i.total.toFixed(2)}\n`; });
    waText += `━━━━━━━━━━━━━━━━━━\n💰 Grand Total: ${grandTotal.toFixed(2)}\n💵 Cash: ${cash.toFixed(2)}\n🔄 Balance: ${balance.toFixed(2)}\n✨ Thank you!`;
    
    let waHref = 'https://wa.me/';
    if (custPhone) waHref += custPhone.replace(/\D/g, '');
    waHref += '?text=' + encodeURIComponent(waText);
    document.getElementById('waBtn').href = waHref;

    document.getElementById('hCustomer').value = custSel.value;
    document.getElementById('hCash').value = cash;
    document.getElementById('billModal').classList.add('active');
}

function closeModal() {
    document.getElementById('billModal').classList.remove('active');
}

function printBill() {
    const inner = document.querySelector('.bill-inner').innerHTML;
    const d = document.getElementById('printArea');
    d.innerHTML = `<style>body{font-family:sans-serif;padding:20px;}</style>${inner}`;
    d.style.display = 'block';
    window.print();
    d.style.display = 'none';
}

function confirmBill() {
    if (confirm('Confirm and generate this bill?')) {
        document.getElementById('realBillForm').submit();
    }
}
</script>