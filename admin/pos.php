<?php 
require_once "../database/db.php";
require_once dirname(__DIR__) . "/includes/cart_schema.php";
require_once dirname(__DIR__) . "/includes/product_schema.php";
require_once dirname(__DIR__) . "/includes/product_media.php";
ensure_cart_discount_columns($pdo);
ensure_product_extended_schema($pdo);

/* =====================
   STOCK ALERT MESSAGE
===================== */
$stock_alert = '';
$alert_type  = 'warn';

/* =====================
   FETCH ACCOUNTS
===================== */
$accounts = $pdo->query("SELECT * FROM payment_accounts ORDER BY account_name ASC")->fetchAll();

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
   UPDATE QTY (non-AJAX fallback)
===================== */
if(isset($_POST['update_qty'])){
    $new_qty = (int)$_POST['qty'];
    $cart_id = (int)$_POST['cart_id'];

    $cart_item = $pdo->prepare("SELECT c.*, p.stock, p.name FROM cart c JOIN products p ON p.id = c.product_id WHERE c.id=?");
    $cart_item->execute([$cart_id]);
    $ci = $cart_item->fetch();

    if($ci){
        if($new_qty > $ci['stock']){
            $stock_alert = "Only <b>{$ci['stock']}</b> unit(s) of <b>{$ci['name']}</b> available! Set to max.";
            $alert_type  = 'warn';
            $pdo->prepare("UPDATE cart SET qty=? WHERE id=?")
                ->execute([(int)$ci['stock'], $cart_id]);
        } elseif($new_qty >= 1){
            $pdo->prepare("UPDATE cart SET qty=? WHERE id=?")
                ->execute([$new_qty, $cart_id]);
        }
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
$imgSel = '(SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1)';
$products  = $pdo->query("SELECT p.*, {$imgSel} AS image_path FROM products p WHERE p.deleted_at IS NULL ORDER BY p.name")->fetchAll();

$cart = $pdo->query("
    SELECT c.id, c.qty, c.discount_type, c.discount_value,
           p.id AS pid, p.name, p.price, p.stock,
           (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image_path
    FROM cart c
    JOIN products p ON p.id = c.product_id
")->fetchAll(PDO::FETCH_ASSOC);
$total     = 0;
foreach ($cart as &$row) {
    $dt = (string)($row['discount_type'] ?? 'none');
    $dv = (float)($row['discount_value'] ?? 0);
    [, , $row['line_total']] = pos_cart_line_totals(
        (int)$row['qty'],
        (float)$row['price'],
        $dt,
        $dv
    );
    $total += $row['line_total'];
}
unset($row);
$total = round($total, 2);
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    --ink:     #0f172a;
    --paper:   #f8fafc;
    --card:    #ffffff;
    --accent:  #3b82f6;
    --accent-glow: rgba(59, 130, 246, 0.5);
    --green:   #10b981;
    --red:     #ef4444;
    --yellow:  #f59e0b;
    --blue:    #3b82f6;
    --muted:   #64748b;
    --border:  #e2e8f0;
    --receipt: #ffffff;
    --radius:  16px;
    --shadow:  0 4px 20px rgba(15, 23, 42, 0.05);
    --safe-b:  env(safe-area-inset-bottom, 0px);
}

body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--paper);
    color: var(--ink);
    min-height: 100vh;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
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
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: clamp(1.25rem, 4.5vw, 1.75rem);
    letter-spacing: -0.5px;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 10px;
    line-height: 1.2;
}

.pos-header h3 i {
    color: var(--accent);
    filter: drop-shadow(0 0 10px var(--accent-glow));
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
    justify-content: end;
}

.scan-bar input {
    /* flex: 1; */
    padding: clamp(10px, 2.5vw, 14px);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: clamp(0.9rem, 3vw, 1rem);
    background: var(--card);
        width: 50% !important;
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
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: clamp(10px, 2.2vw, 14px);
}

/* Let cards shrink inside grid tracks (default min-width:auto ignores overflow) */
.products-list > * {
    min-width: 0;
}

.prod-form {
    margin: 0;
    min-width: 0;
}

.prod-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
    box-shadow: var(--shadow);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    height: 100%;
}

.prod-card { cursor: pointer; }
.prod-card.out-of-stock { cursor: not-allowed; }

.prod-card:not(.out-of-stock):hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.prod-click-btn {
    position: absolute;
    inset: 0;
    border: 0;
    padding: 0;
    background: transparent;
    border-radius: var(--radius);
    cursor: pointer;
}

.prod-click-btn:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
}

.prod-card.out-of-stock .prod-click-btn {
    display: none;
}

.prod-card-inner {
    display: flex;
    align-items: center;
    gap: clamp(8px, 2vw, 12px);
    flex: 1;
    min-width: 0;
}

.prod-thumb-wrap {
    width: 100%;
    aspect-ratio: 1 / 1;
    height: auto;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    background: #f8fafc;
    border: 1px solid rgba(0, 0, 0, 0.03);
}

.prod-thumb-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.prod-info { 
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.prod-name { 
    font-weight: 600; 
    font-size: clamp(0.78rem, 2.5vw, 0.92rem);
    line-height: 1.25;
    overflow-wrap: anywhere;
    word-break: break-word;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.prod-price { 
    color: var(--accent); 
    font-weight: 700; 
    font-size: clamp(0.80rem, 2.2vw, 0.88rem); 
    margin-top: 4px; 
}

.stock-badge {
    display: inline-flex;
    font-size: clamp(0.58rem, 2.0vw, 0.66rem);
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
    position: relative;
    background: var(--card);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    padding-right: 48px;
    transition: all 0.2s ease;
}

.cart-item-card:hover {
    border-color: var(--accent);
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.cart-remove-x {
    position: absolute;
    top: clamp(8px, 2vw, 10px);
    right: clamp(8px, 2vw, 10px);
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fee2e2;
    color: var(--red);
    text-decoration: none;
    font-size: 1.35rem;
    line-height: 1;
    font-weight: 700;
    border: 1px solid #fecaca;
    transition: background 0.15s ease, transform 0.15s ease;
}

.cart-remove-x:hover {
    background: #fecaca;
    color: #7f1d1d;
    transform: scale(1.05);
}

.cart-item-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
    padding-right: 4px;
}

.cart-thumb-wrap {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    background: #f1f5f9;
    border: 1px solid var(--border);
}

.cart-thumb-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.cart-item-top-left {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.cart-item-name { 
    font-weight: 600; 
    font-size: clamp(0.85rem, 2.8vw, 0.95rem); 
}

.cart-item-name-wrap {
    flex: 1;
    min-width: 0;
}

.cart-discount-hint {
    font-size: clamp(0.65rem, 2vw, 0.72rem);
    font-weight: 600;
    color: #15803d;
    margin-top: 3px;
}

.cart-item-total {
    font-weight: 700;
    font-size: clamp(0.9rem, 2.8vw, 1rem);
    color: var(--accent);
    white-space: nowrap;
}

.cart-item-bottom {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: clamp(8px, 2vw, 12px);
    flex-wrap: wrap;
}

.cart-qty-wrap {
    display: inline-flex;
    align-items: center;
}

.cart-qty-input {
    width: clamp(55px, 15vw, 70px);
    padding: clamp(6px, 1.8vw, 8px);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    text-align: center;
}

.avail-lbl {
    font-size: clamp(0.65rem, 2.2vw, 0.75rem);
    color: var(--muted);
    margin-left: auto;
}

.cart-discount-btn {
    white-space: nowrap;
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
    padding: 18px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: #fff;
    border: none;
    border-radius: var(--radius);
    font-weight: 800;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
    cursor: pointer;
    margin-top: 10px;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    transition: all 0.2s;
}

.btn-preview:active {
    transform: scale(0.98);
}

/* Customer phone / name autocomplete (checkout) */
.customer-ac-wrap {
    position: relative;
}

.customer-ac-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 400;
    max-height: 240px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
}

.customer-ac-dropdown.show {
    display: block;
}

.customer-ac-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: 10px 14px;
    border: 0;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
    font-size: 0.9rem;
    cursor: pointer;
    color: var(--ink, #0f172a);
}

.customer-ac-item:last-child {
    border-bottom: 0;
}

.customer-ac-item:hover,
.customer-ac-item:focus {
    background: #eff6ff;
    outline: none;
}

.customer-ac-hint {
    font-size: clamp(0.68rem, 2vw, 0.78rem);
    color: var(--muted);
    margin-top: 8px;
    line-height: 1.35;
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
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px;
}

.bill-actions button {
    padding: 14px 16px;
    border: none;
    border-radius: var(--radius);
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    width: 100%;
}

.btn-confirm { 
    background: var(--accent); 
    color: #fff; 
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
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
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
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
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
    /* Customer Autocomplete Dropdown */
    .customer-ac-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        z-index: 1000;
        max-height: 250px;
        overflow-y: auto;
        display: none;
        border: 1px solid var(--border);
        margin-top: 5px;
    }
    .customer-ac-dropdown.show { display: block; }
    .customer-ac-item {
        width: 100%;
        padding: 12px 16px;
        text-align: left;
        border: none;
        background: transparent;
        font-size: 0.95rem;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        transition: background 0.2s;
    }
    .customer-ac-item:hover { background: #f8fafc; color: var(--accent); }
    .customer-ac-item:last-child { border-bottom: none; }
    
    .customer-ac-hint { font-size: 0.75rem; color: var(--muted); margin-top: 8px; line-height: 1.4; }

    /* Checkout Modal Enhancements */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(8px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 16px;
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
        background: white;
        border-radius: 24px;
        width: 100%;
        max-width: 480px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    .modal-close-x {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #f1f5f9;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .bill-inner { padding: 32px; font-family: 'Inter', sans-serif; }
    .bill-header { text-align: center; margin-bottom: 24px; }
    .shop-name { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.5rem; margin-bottom: 4px; }
    .shop-tag { font-size: 0.85rem; color: var(--muted); margin-bottom: 12px; }
    .bill-no { font-family: 'Oxanium', monospace; font-weight: 700; font-size: 0.9rem; color: var(--accent); }
    
    .bill-meta { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 20px; color: var(--muted); }
    .bill-items { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.9rem; }
    .bill-items th { text-align: left; padding: 10px 0; border-bottom: 2px solid var(--paper); color: var(--muted); font-weight: 700; text-uppercase; font-size: 0.75rem; }
    .bill-items td { padding: 12px 0; border-bottom: 1px solid var(--paper); }
    
    .bill-total-row { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 2px dashed var(--border); }
    .bill-total-row span { font-weight: 800; font-size: 1.1rem; }
    .bill-total-row .amount { font-size: 1.5rem; color: var(--ink); }
    
    .cash-summary { margin-top: 16px; background: var(--paper); padding: 16px; border-radius: 16px; font-size: 0.95rem; }
    .cash-summary div { display: flex; justify-content: space-between; margin-bottom: 4px; }
    .cash-summary div:last-child { margin-bottom: 0; font-weight: 700; color: var(--green); }
    
    .bill-thanks { text-align: center; margin-top: 32px; font-style: italic; color: var(--muted); }
    .bill-actions { padding: 0 32px 32px; }
    .btn-confirm { width: 100%; padding: 16px; border-radius: 16px; background: var(--accent); color: white; border: none; font-weight: 700; font-size: 1.1rem; cursor: pointer; box-shadow: 0 10px 20px var(--accent-glow); transition: transform 0.2s; }
    .btn-confirm:active { transform: scale(0.98); }

    @media (max-width: 576px) {
        .modal-box { border-radius: 0; max-height: 100vh; height: 100vh; }
        .bill-inner { padding: 24px; padding-top: 60px; }
    }
</style>

<!-- ============================================================
     HTML
============================================================ -->
<div class="pos-wrap">

    <div class="pos-header">
        <h3><i class="fa-solid fa-cash-register"></i> POS System</h3>
    </div>

    <div class="inv-summary">
        <div class="inv-chip shadow-sm border-0"><span class="dot" style="background: var(--green);"></span> In Stock: <?= $in_stock ?></div>
        <div class="inv-chip shadow-sm border-0"><span class="dot" style="background: var(--yellow);"></span> Low Stock: <?= $low_stock ?></div>
        <div class="inv-chip shadow-sm border-0"><span class="dot" style="background: var(--red);"></span> Out of Stock: <?= $out_of_stock ?></div>
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
            <div class="col-section-title text-muted text-uppercase small fw-bold mb-3"><i class="fa-solid fa-boxes-stacked me-2"></i> Catalog</div>
            <div class="products-list">
            <?php foreach($products as $p):
                $s = (int)$p['stock'];
                $badge_class = $s == 0 ? 'empty' : ($s <= 5 ? 'low' : 'ok');
                $badge_icon  = $s == 0 ? 'fa-circle-xmark' : ($s <= 5 ? "fa-triangle-exclamation" : "fa-circle-check");
                $badge_text  = $s == 0 ? 'Out of Stock' : ($s <= 5 ? "Only {$s} left" : "{$s} Available");
                $prodImgUrl = product_image_url($p['image_path'] ?? null);
            ?>
            <?php if($s == 0): ?>
                <div class="prod-card out-of-stock opacity-75">
                    <div class="prod-thumb-wrap">
                        <img src="<?= htmlspecialchars($prodImgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    </div>
                    <div class="prod-info text-center">
                        <div class="prod-name mb-1"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="prod-price fw-bold mb-2">Rs. <?= number_format($p['price'], 2) ?></div>
                        <span class="stock-badge <?= $badge_class ?> w-100 justify-content-center">
                            <i class="fa-solid <?= $badge_icon ?> me-1"></i> <?= $badge_text ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" class="prod-form">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <div class="prod-card">
                        <button
                            type="submit"
                            name="add_cart"
                            class="prod-click-btn"
                            aria-label="Add <?= htmlspecialchars($p['name']) ?> to cart">
                        </button>
                        <div class="prod-thumb-wrap">
                            <img src="<?= htmlspecialchars($prodImgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        </div>
                        <div class="prod-info text-center">
                            <div class="prod-name mb-1"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="prod-price fw-bold mb-2">Rs. <?= number_format($p['price'], 2) ?></div>
                            <span class="stock-badge <?= $badge_class ?> w-100 justify-content-center">
                                <i class="fa-solid <?= $badge_icon ?> me-1"></i> <?= $badge_text ?>
                            </span>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
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
                    $dt = (string)($c['discount_type'] ?? 'none');
                    $dv = (float)($c['discount_value'] ?? 0);
                    $cartImgUrl = product_image_url($c['image_path'] ?? null);
                    $disc_hint = '';
                    if ($dt === 'percent' && $dv > 0) {
                        $disc_hint = '−' . rtrim(rtrim(number_format($dv, 2), '0'), '.') . '%';
                    } elseif ($dt === 'amount' && $dv > 0) {
                        $line_sub = (float)$c['qty'] * (float)$c['price'];
                        $disc_hint = '−' . number_format(min($line_sub, $dv), 2);
                    }
                ?>
                <div class="cart-item-card <?= $row_class ?>"
                     data-cart-id="<?= (int)$c['id'] ?>"
                     data-unit-price="<?= htmlspecialchars((string)$c['price'], ENT_QUOTES, 'UTF-8') ?>"
                     data-discount-type="<?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?>"
                     data-discount-value="<?= htmlspecialchars((string)$dv, ENT_QUOTES, 'UTF-8') ?>"
                     data-max-stock="<?= (int)$c['stock'] ?>">
                    <a href="dashboard.php?page=pos&remove=<?= (int)$c['id'] ?>"
                       class="cart-remove-x"
                       title="Remove"
                       aria-label="Remove">&times;</a>
                    <div class="cart-item-top">
                        <div class="cart-item-top-left">
                            <div class="cart-thumb-wrap">
                                <img src="<?= htmlspecialchars($cartImgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                            </div>
                            <div class="cart-item-name-wrap">
                                <div class="cart-item-name"><?= htmlspecialchars($c['name']) ?></div>
                                <div class="cart-discount-hint"><?= $disc_hint !== '' ? htmlspecialchars($disc_hint) : '' ?></div>
                            </div>
                        </div>
                        <div class="cart-item-total" data-amount="<?= htmlspecialchars((string)$c['line_total'], ENT_QUOTES, 'UTF-8') ?>"><?= number_format($c['line_total'], 2) ?></div>
                    </div>
                    <div class="cart-item-bottom">
                        <label class="cart-qty-wrap mb-0">
                            <span class="visually-hidden">Quantity</span>
                            <input type="number"
                                   class="cart-qty-input"
                                   inputmode="numeric"
                                   value="<?= (int)$c['qty'] ?>"
                                   min="1"
                                   max="<?= (int)$c['stock'] ?>"
                                   data-cart-id="<?= (int)$c['id'] ?>"
                                   aria-label="Quantity for <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <button type="button"
                                class="btn btn-sm btn-outline-primary cart-discount-btn"
                                data-cart-id="<?= (int)$c['id'] ?>">
                            Discount
                        </button>
                        <span class="avail-lbl">Avail: <?= (int)$c['stock'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <!-- Grand total bar -->
            <?php if(!empty($cart)): ?>
            <div class="grand-total-bar">
                <div>GRAND TOTAL</div>
                <div class="grand-total-amount" id="grand_total_main">₹ <?= number_format($total, 2) ?></div>
            </div>
            <a href="dashboard.php?page=pos&clear=1" class="btn-clear">🗑 Clear Cart</a>
            <?php endif; ?>

            <!-- CHECKOUT SECTION -->
            <div class="checkout-section mt-4">
                <div class="col-section-title d-flex align-items-center gap-2 mb-3">
                    <i class="fa-solid fa-credit-card text-accent"></i> Finalize Bill
                </div>
                <div class="checkout-form bg-white p-4 rounded-4 border border-light shadow-sm">
                    <div class="customer-ac-wrap position-relative" id="customer_lookup_wrap">
                        <label class="form-label text-muted small fw-bold mb-1">Select Customer</label>
                        <div class="input-group input-group-lg mb-2">
                            <span class="input-group-text bg-light border-0"><i class="fa-solid fa-user-magnifying text-muted"></i></span>
                            <input type="text" id="customer_lookup" class="form-control border-0 bg-light fw-semibold" placeholder="Phone or Name..." autocomplete="off">
                        </div>
                        <div id="customer_ac_dropdown" class="customer-ac-dropdown" role="listbox"></div>
                        
                        <div id="new_cust_fields" style="display:none;">
                            <input type="text" id="new_customer_name" class="form-control form-control-lg border-0 bg-light fw-semibold mt-2" placeholder="Customer Name (for new entries)" autocomplete="name">
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label text-muted small fw-bold mb-0">Account Type *</label>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#accountModal">
                                <i class="fa-solid fa-plus-circle me-1"></i>New Account
                            </button>
                        </div>
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text bg-light border-0"><i class="fa-solid fa-building-columns text-muted"></i></span>
                            <select id="account_type" class="form-select border-0 bg-light fw-semibold">
                                <?php foreach($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <label class="form-label text-muted small fw-bold mb-1">Cash Payment (Rs)</label>
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text bg-success-subtle border-0 text-success"><i class="fa-solid fa-money-bill-wave"></i></span>
                            <input type="number" id="cash_input" class="form-control border-0 bg-light fw-bold text-success" placeholder="0.00" required min="0" step="0.01">
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <div class="p-3 rounded-4 bg-light text-center">
                                    <div class="text-muted small fw-bold text-uppercase mb-1">Grand Total</div>
                                    <div class="fw-bold fs-5" id="checkout_total_display">₹ 0.00</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-4 bg-primary bg-opacity-10 text-center border border-primary border-opacity-10">
                                    <div class="text-primary small fw-bold text-uppercase mb-1">Change Due</div>
                                    <input type="text" id="balance_input" class="fw-bold fs-5 text-primary text-center border-0 bg-transparent w-100 p-0" placeholder="0.00" readonly>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-primary btn-lg w-100 py-3 rounded-4 fw-bold shadow-lg d-flex align-items-center justify-content-center gap-2" onclick="void openPreview()">
                            <i class="fa-solid fa-receipt"></i>
                            GENERATE BILL & RECEIPT
                        </button>
                    </div>
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
            <div class="amt" id="grand_total_sticky">₹ <?= number_format($total, 2) ?></div>
        </div>
        <button class="sbb-checkout-btn" onclick="scrollToCheckout()">
            💳 Checkout →
        </button>
    </div>
</div>

<!-- Line discount (Bootstrap modal — styles/JS from admin layout) -->
<div class="modal fade" id="discountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2 px-3">
                <h6 class="modal-title mb-0" id="discountModalTitle">Discount</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3 px-3">
                <input type="hidden" id="discount_cart_id" value="">
                <div class="d-flex gap-2 mb-2">
                    <input type="radio" class="btn-check" name="discount_mode" id="dm_pct" value="percent" autocomplete="off" checked>
                    <label class="btn btn-outline-primary btn-sm flex-fill" for="dm_pct">Percent %</label>
                    <input type="radio" class="btn-check" name="discount_mode" id="dm_amt" value="amount" autocomplete="off">
                    <label class="btn btn-outline-primary btn-sm flex-fill" for="dm_amt">Fixed amount</label>
                </div>
                <label for="discount_value_input" class="form-label small mb-1">Value</label>
                <input type="number" class="form-control form-control-sm" id="discount_value_input" min="0" step="0.01" placeholder="0">
                <small class="text-muted d-block mt-2">Percent applies to line subtotal (qty × price). Amount cannot exceed that subtotal.</small>
            </div>
            <div class="modal-footer py-2 px-3 gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="discount_clear_btn">Clear</button>
                <button type="button" class="btn btn-sm btn-primary" id="discount_apply_btn">Apply</button>
            </div>
        </div>
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
      <button type="button" class="btn-confirm" onclick="confirmBill()">Confirm & generate bill (PDF + print)</button>
    </div>
  </div>
</div>

<form id="realBillForm" action="bill_finalize.php" method="POST" style="display:none;">
    <input type="hidden" name="confirm_bill" value="1">
    <input type="hidden" name="customer_id" id="hCustomer">
    <input type="hidden" name="cash" id="hCash">
    <input type="hidden" name="account_id" id="hAccountId">
    <input type="hidden" name="total_amount" id="hTotalAmount" value="<?= htmlspecialchars((string)$total, ENT_QUOTES, 'UTF-8') ?>">
</form>

<!-- Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">New Payment Account</h6>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label text-muted small fw-bold">Account Name</label>
                <input type="text" id="new_account_name" class="form-control form-control-lg border-0 bg-light fw-semibold" placeholder="e.g. HNB Bank, Petty Cash">
                <small class="text-muted d-block mt-2">Create separate accounts to track where money is received.</small>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-primary w-100 py-2 fw-bold rounded-3" id="save_account_btn">Create Account</button>
            </div>
        </div>
    </div>
</div>

<div class="pos-toast" id="posToast" style="display:none;"></div>

<script>
const POS_AJAX_URL = 'dashboard.php?page=pos';
const qtyDebounce = new Map();

let discountModalInstance = null;
function getDiscountModal() {
    const el = document.getElementById('discountModal');
    if (!el || !window.bootstrap) return null;
    if (!discountModalInstance) discountModalInstance = new bootstrap.Modal(el);
    return discountModalInstance;
}
window.addEventListener('load', () => { 
    getDiscountModal(); 
    updateGrandTotalUI(getGrandTotal());
});

<?php if($stock_alert): ?>
showToast(<?= json_encode(strip_tags($stock_alert)) ?>, '<?= $alert_type ?>');
<?php endif; ?>
<?php if (!empty($_SESSION['pos_bill_error'])): ?>
showToast(<?= json_encode((string)$_SESSION['pos_bill_error']) ?>, 'error');
<?php unset($_SESSION['pos_bill_error']); endif; ?>

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

function getGrandTotal() {
    let s = 0;
    document.querySelectorAll('.cart-item-total').forEach(el => {
        s += parseFloat(el.dataset.amount || '0') || 0;
    });
    return Math.round(s * 100) / 100;
}

function updateGrandTotalUI(total) {
    const t = typeof total === 'number' ? total : getGrandTotal();
    const fmt = t.toFixed(2);
    const main = document.getElementById('grand_total_main');
    const sticky = document.getElementById('grand_total_sticky');
    const checkout = document.getElementById('checkout_total_display');
    if (main) main.textContent = '₹ ' + fmt;
    if (sticky) sticky.textContent = '₹ ' + fmt;
    if (checkout) checkout.textContent = '₹ ' + fmt;
    const h = document.getElementById('hTotalAmount');
    if (h) h.value = fmt;
}

function refreshBalanceRow() {
    const cashEl = document.getElementById('cash_input');
    const balEl = document.getElementById('balance_input');
    if (!cashEl || !balEl) return;
    const cash = parseFloat(cashEl.value || 0);
    const bal = cash - getGrandTotal();
    balEl.value = isNaN(bal) ? '' : bal.toFixed(2);
}

document.getElementById('cash_input')?.addEventListener('input', refreshBalanceRow);

function showToast(msg, type='warn') {
    const t = document.getElementById('posToast');
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}

function calcLineTotalFromCard(card) {
    const qty = parseInt(card.querySelector('.cart-qty-input')?.value, 10) || 0;
    const price = parseFloat(card.dataset.unitPrice || '0');
    const dt = card.dataset.discountType || 'none';
    const dv = parseFloat(card.dataset.discountValue || '0');
    let subtotal = Math.round(qty * price * 100) / 100;
    let disc = 0;
    if (dt === 'percent' && dv > 0) {
        disc = Math.round(subtotal * (Math.min(100, dv) / 100) * 100) / 100;
    } else if (dt === 'amount' && dv > 0) {
        disc = Math.min(subtotal, Math.round(dv * 100) / 100);
    }
    return Math.max(0, Math.round((subtotal - disc) * 100) / 100);
}

function updateDiscountHint(card) {
    const hint = card.querySelector('.cart-discount-hint');
    if (!hint) return;
    const qty = parseInt(card.querySelector('.cart-qty-input')?.value, 10) || 0;
    const price = parseFloat(card.dataset.unitPrice || '0');
    const dt = card.dataset.discountType || 'none';
    const dv = parseFloat(card.dataset.discountValue || '0');
    const subtotal = qty * price;
    let text = '';
    if (dt === 'percent' && dv > 0) text = '−' + dv + '%';
    else if (dt === 'amount' && dv > 0) text = '−' + Math.min(subtotal, dv).toFixed(2);
    hint.textContent = text;
}

function updateLineTotalEl(card, amount) {
    const el = card.querySelector('.cart-item-total');
    if (!el) return;
    const num = typeof amount === 'number' ? amount : parseFloat(amount);
    el.dataset.amount = String(num);
    el.textContent = num.toFixed(2);
}

function getCartSnapshot() {
    const items = [];
    document.querySelectorAll('.cart-item-card').forEach(card => {
        const nameEl = card.querySelector('.cart-item-name');
        const qtyIn = card.querySelector('.cart-qty-input');
        const totEl = card.querySelector('.cart-item-total');
        if (!nameEl || !qtyIn || !totEl) return;
        items.push({
            name: nameEl.textContent.trim(),
            qty: parseInt(qtyIn.value, 10) || 0,
            price: parseFloat(card.dataset.unitPrice || '0'),
            total: parseFloat(totEl.dataset.amount || '0')
        });
    });
    return items;
}

async function posAjax(payload) {
    const body = new URLSearchParams();
    body.set('pos_ajax', '1');
    Object.keys(payload).forEach(k => body.set(k, String(payload[k])));
    const res = await fetch(POS_AJAX_URL, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body
    });
    return res.json();
}

let acSearchTimer = null;

function hideCustomerAc() {
    const el = document.getElementById('customer_ac_dropdown');
    if (!el) return;
    el.classList.remove('show');
    el.innerHTML = '';
}

function renderCustomerAc(items) {
    const el = document.getElementById('customer_ac_dropdown');
    if (!el) return;
    el.innerHTML = '';
    if (!items || !items.length) {
        el.classList.remove('show');
        return;
    }
    items.forEach(it => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'customer-ac-item';
        b.setAttribute('role', 'option');
        b.textContent = it.label;
        b.addEventListener('click', () => pickCustomerFromAc(it));
        el.appendChild(b);
    });
    el.classList.add('show');
}

function pickCustomerFromAc(it) {
    document.getElementById('hCustomer').value = String(it.id);
    document.getElementById('customer_lookup').value = it.label;
    const wrap = document.getElementById('customer_lookup_wrap');
    wrap.dataset.customerName = it.name;
    wrap.dataset.customerPhone = it.phone || '';
    hideCustomerAc();
}

function clearCustomerPickState() {
    document.getElementById('hCustomer').value = '';
    const wrap = document.getElementById('customer_lookup_wrap');
    if (!wrap) return;
    delete wrap.dataset.customerName;
    delete wrap.dataset.customerPhone;
}

async function runCustomerSearch() {
    const input = document.getElementById('customer_lookup');
    const newField = document.getElementById('new_cust_fields');
    if (!input) return;
    const q = input.value.trim();
    if (q.length < 1) {
        hideCustomerAc();
        if(newField) newField.style.display = 'none';
        return;
    }
    try {
        const data = await posAjax({ action: 'customer_search', q });
        if (!data.ok) return;
        renderCustomerAc(data.items || []);
        if(newField) {
            newField.style.display = (data.items && data.items.length) ? 'none' : 'block';
        }
    } catch (e) {
        hideCustomerAc();
    }
}

document.getElementById('customer_lookup')?.addEventListener('input', function() {
    clearCustomerPickState();
    clearTimeout(acSearchTimer);
    acSearchTimer = setTimeout(runCustomerSearch, 220);
});

document.getElementById('customer_lookup')?.addEventListener('focus', function() {
    if (this.value.trim().length >= 1) {
        clearTimeout(acSearchTimer);
        runCustomerSearch();
    }
});

document.addEventListener('click', function(e) {
    const wrap = document.getElementById('customer_lookup_wrap');
    if (wrap && !wrap.contains(e.target)) hideCustomerAc();
});

async function ensureCustomerForCheckout() {
    const hid = document.getElementById('hCustomer');
    if (hid.value) return true;
    const line = document.getElementById('customer_lookup').value.trim();
    const extra = document.getElementById('new_customer_name').value.trim();
    if (!line) {
        showToast('Enter customer phone or pick from the list');
        return false;
    }
    try {
        const data = await posAjax({
            action: 'customer_resolve',
            display: line,
            name: extra
        });
        if (!data.ok) {
            showToast(data.message || 'Could not save customer');
            return false;
        }
        hid.value = String(data.customer_id);
        document.getElementById('customer_lookup').value = data.label;
        const wrap = document.getElementById('customer_lookup_wrap');
        wrap.dataset.customerName = data.name;
        wrap.dataset.customerPhone = data.phone || '';
        if (data.created) showToast('New customer saved');
        return true;
    } catch (e) {
        showToast('Network error');
        return false;
    }
}

function onQtyInput(input) {
    const card = input.closest('.cart-item-card');
    if (!card) return;

    let q = parseInt(input.value, 10);
    if (!Number.isFinite(q) || q < 1) q = 1;
    const max = parseInt(card.dataset.maxStock || '0', 10);
    if (max > 0 && q > max) q = max;
    if (parseInt(input.value, 10) !== q) input.value = String(q);

    const line = calcLineTotalFromCard(card);
    updateLineTotalEl(card, line);
    updateDiscountHint(card);
    updateGrandTotalUI(getGrandTotal());
    refreshBalanceRow();

    const id = card.dataset.cartId;
    clearTimeout(qtyDebounce.get(id));
    qtyDebounce.set(id, setTimeout(() => syncQtyFromServer(card, q), 320));
}

async function syncQtyFromServer(card, qty) {
    try {
        const data = await posAjax({
            action: 'update_qty',
            cart_id: card.dataset.cartId,
            qty: String(qty)
        });
        if (!data.ok) {
            showToast(data.message || 'Could not update quantity');
            return;
        }
        if (data.qty !== undefined) {
            const inp = card.querySelector('.cart-qty-input');
            if (inp) inp.value = String(data.qty);
        }
        updateLineTotalEl(card, data.line_total);
        updateDiscountHint(card);
        updateGrandTotalUI(data.grand_total);
        refreshBalanceRow();
        if (data.capped) {
            showToast('Quantity limited to available stock (' + data.max_stock + ')');
        }
    } catch (e) {
        showToast('Network error updating cart');
    }
}

document.querySelectorAll('.cart-qty-input').forEach(input => {
    input.addEventListener('input', () => onQtyInput(input));
    input.addEventListener('change', () => onQtyInput(input));
});

function openDiscountForCartId(cartId) {
    const card = document.querySelector('.cart-item-card[data-cart-id="' + cartId + '"]');
    if (!card) return;

    document.getElementById('discount_cart_id').value = String(cartId);
    const title = document.getElementById('discountModalTitle');
    if (title) title.textContent = 'Discount · ' + card.querySelector('.cart-item-name').textContent.trim();

    const dt = card.dataset.discountType || 'none';
    const dv = parseFloat(card.dataset.discountValue || '0');

    document.getElementById('dm_pct').checked = (dt === 'percent');
    document.getElementById('dm_amt').checked = (dt === 'amount');
    if (dt === 'none') {
        document.getElementById('dm_pct').checked = true;
        document.getElementById('discount_value_input').value = '';
    } else {
        document.getElementById('discount_value_input').value = String(dv);
    }

    const dm = getDiscountModal();
    if (dm) dm.show();
    else showToast('Discount modal unavailable (reload page)');
}

document.querySelectorAll('.cart-discount-btn').forEach(btn => {
    btn.addEventListener('click', () => openDiscountForCartId(btn.getAttribute('data-cart-id')));
});

document.getElementById('discount_apply_btn')?.addEventListener('click', async () => {
    const cartId = document.getElementById('discount_cart_id').value;
    const modeEl = document.querySelector('input[name="discount_mode"]:checked');
    const mode = modeEl ? modeEl.value : 'percent';
    let val = parseFloat(document.getElementById('discount_value_input').value || '0');

    if (!cartId) return;
    if (!val || val <= 0) {
        showToast('Enter a discount value');
        return;
    }

    try {
        const data = await posAjax({
            action: 'update_discount',
            cart_id: cartId,
            discount_type: mode,
            discount_value: String(val)
        });
        if (!data.ok) {
            showToast(data.message || 'Could not apply discount');
            return;
        }
        const card = document.querySelector('.cart-item-card[data-cart-id="' + cartId + '"]');
        if (card) {
            card.dataset.discountType = data.discount_type;
            card.dataset.discountValue = String(data.discount_value);
            updateDiscountHint(card);
            updateLineTotalEl(card, data.line_total);
        }
        updateGrandTotalUI(data.grand_total);
        refreshBalanceRow();
        getDiscountModal()?.hide();
    } catch (e) {
        showToast('Network error');
    }
});

document.getElementById('discount_clear_btn')?.addEventListener('click', async () => {
    const cartId = document.getElementById('discount_cart_id').value;
    if (!cartId) return;
    try {
        const data = await posAjax({
            action: 'update_discount',
            cart_id: cartId,
            discount_type: 'none',
            discount_value: '0'
        });
        if (!data.ok) {
            showToast(data.message || 'Could not clear discount');
            return;
        }
        const card = document.querySelector('.cart-item-card[data-cart-id="' + cartId + '"]');
        if (card) {
            card.dataset.discountType = 'none';
            card.dataset.discountValue = '0';
            updateDiscountHint(card);
            updateLineTotalEl(card, data.line_total);
        }
        document.getElementById('discount_value_input').value = '';
        updateGrandTotalUI(data.grand_total);
        refreshBalanceRow();
        getDiscountModal()?.hide();
    } catch (e) {
        showToast('Network error');
    }
});

async function openPreview() {
    const cashVal = document.getElementById('cash_input').value;
    const cartItems = getCartSnapshot();
    const grandTotalNum = getGrandTotal();

    if (!cashVal || parseFloat(cashVal) <= 0) { showToast('Please enter cash amount'); return; }
    if (cartItems.length === 0) { showToast('Cart is empty'); return; }

    if (!(await ensureCustomerForCheckout())) return;

    const wrap = document.getElementById('customer_lookup_wrap');
    const custName = wrap?.dataset.customerName
        || document.getElementById('customer_lookup').value.split(/\s*[-–—]\s+/).pop()
        || 'Customer';
    const custPhone = wrap?.dataset.customerPhone || '';
    const cash = parseFloat(cashVal);
    const balance = cash - grandTotalNum;
    const now = new Date();
    const billNo = 'BILL-' + now.getFullYear() + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0') + '-' + Math.floor(Math.random() * 9000 + 1000);

    document.getElementById('bBillNo').textContent = billNo;
    document.getElementById('bCustomer').textContent = custName;
    document.getElementById('bDate').textContent = now.toLocaleString();
    document.getElementById('bTotal').textContent = grandTotalNum.toFixed(2);
    document.getElementById('bCash').textContent = cash.toFixed(2);
    document.getElementById('bBalance').textContent = balance.toFixed(2);

    let rows = '';
    cartItems.forEach(i => {
        rows += `<tr><td>${i.name}</td><td style="text-align:center">${i.qty}</td><td style="text-align:right">${i.price.toFixed(2)}</td><td style="text-align:right">${i.total.toFixed(2)}</td></tr>`;
    });
    document.getElementById('bItems').innerHTML = rows;

    document.getElementById('hCash').value = cash;
    document.getElementById('hAccountId').value = document.getElementById('account_type').value;
    document.getElementById('billModal').classList.add('active');
}

function closeModal() {
    document.getElementById('billModal').classList.remove('active');
}

function confirmBill() {
    updateGrandTotalUI(getGrandTotal());
    const cashEl = document.getElementById('cash_input');
    if (cashEl) {
        document.getElementById('hCash').value = cashEl.value;
    }
    const hTot = document.getElementById('hTotalAmount');
    if (hTot) {
        hTot.value = getGrandTotal().toFixed(2);
    }
    closeModal();
    document.getElementById('realBillForm').submit();
}
document.getElementById('save_account_btn')?.addEventListener('click', async () => {
    const name = document.getElementById('new_account_name').value.trim();
    if (!name) {
        showToast('Please enter an account name', 'error');
        return;
    }
    try {
        const data = await posAjax({ action: 'add_account', name });
        if (!data.ok) {
            showToast(data.message || 'Error creating account', 'error');
            return;
        }
        const select = document.getElementById('account_type');
        const opt = new Option(data.name, data.id);
        select.add(opt);
        select.value = data.id;
        
        document.getElementById('new_account_name').value = '';
        bootstrap.Modal.getInstance(document.getElementById('accountModal'))?.hide();
        showToast('Account created successfully', 'success');
    } catch (e) {
        showToast('Network error', 'error');
    }
});
</script>