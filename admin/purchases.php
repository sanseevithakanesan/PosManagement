<?php
require_once "../database/db.php";

// Recreate or Ensure Table Exists
$colCheck = $pdo->query("SHOW COLUMNS FROM purchases LIKE 'product_id'")->fetch();
if(!$colCheck){
    $pdo->exec("DROP TABLE IF EXISTS purchases");
}
$pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(100),
    invoice_no VARCHAR(100) NULL,
    supplier VARCHAR(255) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    remaining_qty INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    buy_price DECIMAL(10,2) NULL,
    selling_price DECIMAL(10,2) NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    tax DECIMAL(10,2) DEFAULT 0.00,
    final_amount DECIMAL(10,2) NULL,
    status ENUM('Pending', 'Received') DEFAULT 'Pending',
    payment_status ENUM('Paid', 'Unpaid', 'Partial') DEFAULT 'Unpaid',
    payment_method VARCHAR(50) NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    due_amount DECIMAL(10,2) DEFAULT 0.00,
    expiry_date DATE NULL,
    batch_no VARCHAR(100) NULL,
    user_id INT NULL,
    purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

require_once dirname(__DIR__) . "/includes/product_media.php";
// Fetch all categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch products for dropdown (include cost_price, selling_price, and category)
$imgSelect = '(SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1)';
$products = $pdo->query("
    SELECT p.id, p.name, p.stock, p.cost_price, p.price, p.category_id, cat.name as cat_name, {$imgSelect} AS image_path 
    FROM products p
    LEFT JOIN categories cat ON p.category_id = cat.id
    WHERE p.deleted_at IS NULL
    ORDER BY p.name ASC
")->fetchAll();

/* ADD */
if(isset($_POST['add'])){
    $pdo->beginTransaction();
    try {
        $reference = $_POST['reference_no'] ?: 'PUR-'.time();
        $remain = ($_POST['status'] === 'Received') ? $_POST['quantity'] : 0;
        $userId = $_SESSION['user_id'] ?? null;

        $pdo->prepare("INSERT INTO purchases(
            reference_no, invoice_no, supplier, product_id, quantity, remaining_qty, 
            unit_cost, buy_price, selling_price, total_cost, 
            discount, tax, final_amount, status, payment_status, 
            payment_method, paid_amount, due_amount, expiry_date, batch_no, user_id
        ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $reference, 
                $_POST['invoice_no'] ?? null,
                $_POST['supplier'], 
                $_POST['product_id'], 
                $_POST['quantity'], 
                $remain,
                $_POST['unit_cost'], 
                $_POST['buy_price'] ?? $_POST['unit_cost'],
                $_POST['selling_price'] ?? null,
                $_POST['total_cost'],
                $_POST['discount'] ?? 0,
                $_POST['tax'] ?? 0,
                $_POST['final_amount'] ?? $_POST['total_cost'],
                $_POST['status'],
                $_POST['payment_status'],
                $_POST['payment_method'] ?? null,
                $_POST['paid_amount'] ?? 0,
                $_POST['due_amount'] ?? 0,
                $_POST['expiry_date'] ?: null,
                $_POST['batch_no'] ?? null,
                $userId
            ]);
            
        // If Received, Add to Stock (keeping this for global display) and update master prices
        if($_POST['status'] === 'Received') {
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$_POST['quantity'], $_POST['product_id']]);
            
            // Update Master Price/Cost for the catalog
            $updProd = $pdo->prepare("UPDATE products SET cost_price = ?, price = ? WHERE id = ?");
            $updProd->execute([
                $_POST['buy_price'] ?? $_POST['unit_cost'],
                $_POST['selling_price'] ?? null,
                $_POST['product_id']
            ]);
        }
        $pdo->commit();
        $_SESSION['message'] = "Purchase added successfully!";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error adding purchase: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=purchases");
    exit;
}

/* DELETE */
if(isset($_GET['delete'])){
    $pdo->beginTransaction();
    try {
        // Find purchase to revert stock if necessary
        $stmt = $pdo->prepare("SELECT product_id, quantity, status FROM purchases WHERE id=?");
        $stmt->execute([$_GET['delete']]);
        $old_p = $stmt->fetch();
        
        if($old_p) {
            // If it was already received, deduct from stock upon deletion
            if($old_p['status'] === 'Received') {
                $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$old_p['quantity'], $old_p['product_id']]);
            }
            $pdo->prepare("DELETE FROM purchases WHERE id=?")->execute([$_GET['delete']]);
        }
        $pdo->commit();
        $_SESSION['message'] = "Purchase deleted successfully!";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error deleting purchase.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=purchases");
    exit;
}

/* EDIT FETCH */
$edit = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

/* UPDATE */
if(isset($_POST['update'])){
    $pdo->beginTransaction();
    try {
        // Fetch old stats
        $stmt = $pdo->prepare("SELECT product_id, quantity, status FROM purchases WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $old_p = $stmt->fetch();
        
        $pdo->prepare("UPDATE purchases SET 
            reference_no=?, invoice_no=?, supplier=?, product_id=?, quantity=?, 
            unit_cost=?, buy_price=?, selling_price=?, total_cost=?, 
            discount=?, tax=?, final_amount=?, status=?, payment_status=?,
            payment_method=?, paid_amount=?, due_amount=?, expiry_date=?, batch_no=?
            WHERE id=?")
            ->execute([
                $_POST['reference_no'], 
                $_POST['invoice_no'] ?? null,
                $_POST['supplier'], 
                $_POST['product_id'], 
                $_POST['quantity'], 
                $_POST['unit_cost'], 
                $_POST['buy_price'] ?? $_POST['unit_cost'],
                $_POST['selling_price'] ?? null,
                $_POST['total_cost'],
                $_POST['discount'] ?? 0,
                $_POST['tax'] ?? 0,
                $_POST['final_amount'] ?? $_POST['total_cost'],
                $_POST['status'],
                $_POST['payment_status'],
                $_POST['payment_method'] ?? null,
                $_POST['paid_amount'] ?? 0,
                $_POST['due_amount'] ?? 0,
                $_POST['expiry_date'] ?: null,
                $_POST['batch_no'] ?? null,
                $_POST['id']
            ]);
            
        // Manage Stock differences
        if($old_p) {
            // Re-calculate remaining_qty if it's currently NULL or if status changed to Received
            if($_POST['status'] === 'Received') {
                $pdo->prepare("UPDATE purchases SET remaining_qty = quantity WHERE id = ? AND status = 'Received'")->execute([$_POST['id']]);
            }
            
            // First, revert the old stock if it was Received
            if($old_p['status'] === 'Received') {
                $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$old_p['quantity'], $old_p['product_id']]);
            }
            // Then, apply the new stock if the new status is Received
            if($_POST['status'] === 'Received') {
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$_POST['quantity'], $_POST['product_id']]);
                
                // Update Master Price/Cost for the catalog to reflect latest batch
                $updProd = $pdo->prepare("UPDATE products SET cost_price = ?, price = ? WHERE id = ?");
                $updProd->execute([
                    $_POST['buy_price'] ?? $_POST['unit_cost'],
                    $_POST['selling_price'] ?? null,
                    $_POST['product_id']
                ]);
            }
        }
        $pdo->commit();
        $_SESSION['message'] = "Purchase updated successfully!";
        $_SESSION['message_type'] = "success";
        header("Location: dashboard.php?page=purchases");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error updating purchase.";
        $_SESSION['message_type'] = "danger";
    }
}

/* LIST */
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';

$sqlPurch = "
    SELECT p.*, pr.name as product_name, cat.name as category_name
    FROM purchases p 
    LEFT JOIN products pr ON p.product_id = pr.id
    LEFT JOIN categories cat ON pr.category_id = cat.id
";
$paramsPurch = [];
if ($start_date !== '' && $end_date !== '') {
    $sqlPurch .= " WHERE DATE(p.purchase_date) >= ? AND DATE(p.purchase_date) <= ?";
    $paramsPurch[] = $start_date;
    $paramsPurch[] = $end_date;
}
$sqlPurch .= " ORDER BY p.id DESC";

$stmtPurch = $pdo->prepare($sqlPurch);
$stmtPurch->execute($paramsPurch);
$purchases = $stmtPurch->fetchAll();
?>


<style>
    /* Modern UI Components */
    .purchases-header {
        background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
        padding: 25px;
        border-radius: 15px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(9, 132, 227, 0.15);
    }
    .modern-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .form-section-block {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        height: 100%;
    }
    .section-title-sm {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .final-payable-box {
        background: #1e293b;
        border-radius: 10px;
        padding: 15px;
        color: white;
        border-left: 4px solid #00d2d3;
    }
    
    /* Modern Badges */
    .status-pill {
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 50px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .pill-received { background: #e0fdf4; color: #10b981; }
    .pill-pending { background: #fffbeb; color: #f59e0b; }
    .pill-paid { background: #eff6ff; color: #3b82f6; }
    .pill-partial { background: #fdf4ff; color: #a855f7; }
    .pill-unpaid { background: #fef2f2; color: #ef4444; }

    .action-btn-group .btn {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
        border: none;
    }
    .btn-action-edit { background: #f1f5f9; color: #3b82f6; }
    .btn-action-edit:hover { background: #3b82f6; color: white; }
    .btn-action-del { background: #fef2f2; color: #ef4444; }
    .btn-action-del:hover { background: #ef4444; color: white; }
    .btn-action-del { background: #fef2f2; color: #ef4444; }
    .btn-action-del:hover { background: #ef4444; color: white; }

    /* Touch Targets */
    .btn-touch {
        width: 48px !important;
        height: 48px !important;
        font-size: 1.2rem !important;
    }
</style>

<div class="purchases-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <h3 class="mb-1 fw-bold"><i class="fa-solid fa-cart-flatbed-suitcase me-2"></i> Purchases</h3>
        <p class="mb-0 opacity-75">Procurement and Inventory Inbound</p>
    </div>
    <a href="dashboard.php?page=purchases" class="btn btn-light btn-lg fw-bold px-4 rounded-4 shadow-sm <?= $edit ? '' : 'disabled opacity-50' ?>">
        <i class="fa-solid fa-plus-circle me-1 text-primary"></i> New Purchase
    </a>
</div>

<!-- ALERT -->
<?php if(isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fa-solid <?= $_SESSION['message_type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>


<!-- FORM -->
<div class="card modern-card mb-5">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-4 text-dark"><?= $edit ? '<i class="fa-solid fa-edit text-primary me-2"></i>Edit Purchase Record' : '<i class="fa-solid fa-plus-circle text-primary me-2"></i>Register New Purchase' ?></h5>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            
            <div class="row g-4">
                <!-- 1. Basic Information -->
                <div class="col-12">
                    <div class="form-section-block">
                        <div class="section-title-sm"><i class="fa-solid fa-file-invoice"></i> 1. Basic Information</div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Reference No.</label>
                                <input type="text" name="reference_no" class="form-control border-light" value="<?= htmlspecialchars($edit['reference_no'] ?? '') ?>" placeholder="PUR-XXXX (Auto)">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Invoice No.</label>
                                <input type="text" name="invoice_no" class="form-control border-light" value="<?= htmlspecialchars($edit['invoice_no'] ?? '') ?>" placeholder="Supplier Invoice #">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Supplier Name *</label>
                                <input type="text" name="supplier" class="form-control border-primary bg-white" value="<?= htmlspecialchars($edit['supplier'] ?? '') ?>" placeholder="Search or Enter Supplier" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Record Date</label>
                                <input type="text" class="form-control form-control-lg bg-light border-0 fw-bold" value="<?= isset($edit['purchase_date']) ? date('Y-m-d H:i', strtotime($edit['purchase_date'])) : date('Y-m-d H:i') ?>" readonly disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Product & Pricing -->
                <div class="col-lg-8">
                    <div class="form-section-block">
                        <div class="section-title-sm"><i class="fa-solid fa-box-open"></i> 2. Product & Inventory Inbound</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold">Category Filter</label>
                                <select id="filter_category" class="form-select border-info text-info fw-bold">
                                    <option value="">All Categories</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label text-muted small fw-bold">Select Product *</label>
                                <div class="d-flex gap-2">
                                    <div class="flex-grow-1 position-relative">
                                        <select name="product_id" id="purchase_product" class="form-select form-select-lg border-primary fw-bold" required>
                                            <option value="" data-image="" data-cost="" data-price="" data-cat="">Search Product...</option>
                                            <?php foreach($products as $prod): ?>
                                                <option value="<?= $prod['id'] ?>" 
                                                        data-image="<?= htmlspecialchars(product_image_url($prod['image_path'] ?? null)) ?>"
                                                        data-cost="<?= $prod['cost_price'] ?? 0 ?>"
                                                        data-price="<?= $prod['price'] ?? 0 ?>"
                                                        data-cat="<?= $prod['category_id'] ?>"
                                                        <?= (isset($edit['product_id']) && $edit['product_id'] == $prod['id']) ? 'selected' : '' ?>>
                                                    [<?= htmlspecialchars($prod['cat_name'] ?: 'None') ?>] <?= htmlspecialchars($prod['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <img id="product_preview_img" src="" alt="" class="rounded-pill border" style="width: 56px; height: 56px; object-fit: cover; display: none;">
                                </div>
                                <div id="price_hint" class="mt-2"></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Batch # / Expiry</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-barcode text-muted"></i></span>
                                    <input type="text" name="batch_no" class="form-control" value="<?= htmlspecialchars($edit['batch_no'] ?? '') ?>" placeholder="Batch #">
                                    <input type="date" name="expiry_date" class="form-control" value="<?= htmlspecialchars($edit['expiry_date'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6"></div>

                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold">Quantity *</label>
                                <input type="number" name="quantity" id="purchase_qty" class="form-control form-control-lg fw-bold border-primary" value="<?= htmlspecialchars($edit['quantity'] ?? '') ?>" min="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold">Unit Buy Price *</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light text-muted">Rs.</span>
                                    <input type="number" step="0.01" name="unit_cost" id="purchase_unit_cost" class="form-control fw-bold" value="<?= htmlspecialchars($edit['unit_cost'] ?? '') ?>" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold text-success">New Selling Price</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-success-subtle text-success border-success"><i class="fa-solid fa-tag"></i></span>
                                    <input type="number" step="0.01" name="selling_price" id="purchase_selling_price" class="form-control border-success text-success fw-bold" value="<?= htmlspecialchars($edit['selling_price'] ?? '') ?>" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Financials & Payables -->
                <div class="col-lg-4">
                    <div class="form-section-block bg-white border-0 shadow-sm">
                        <div class="section-title-sm"><i class="fa-solid fa-calculator"></i> 3. Tax & Payables</div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Subtotal</label>
                            <input type="number" step="0.01" name="total_cost" id="purchase_total" class="form-control bg-light border-0 fw-bold" value="<?= htmlspecialchars($edit['total_cost'] ?? '') ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Supplier Discount (Rs.)</label>
                            <input type="number" step="0.01" name="discount" id="purchase_discount" class="form-control border-light" value="<?= htmlspecialchars($edit['discount'] ?? '0.00') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Extra Tax / Fees (Rs.)</label>
                            <input type="number" step="0.01" name="tax" id="purchase_tax" class="form-control border-light" value="<?= htmlspecialchars($edit['tax'] ?? '0.00') ?>">
                        </div>
                        
                        <div class="final-payable-box mt-4">
                            <div class="small opacity-75 mb-1 text-uppercase fw-bold">Final Payable Amount</div>
                            <h3 class="mb-0 fw-bold" id="final_amount_display">Rs. 0.00</h3>
                            <input type="hidden" name="final_amount" id="purchase_final_amount" value="<?= $edit['final_amount'] ?? '0' ?>">
                        </div>
                    </div>
                </div>

                <!-- 4. Payment & Completion -->
                <div class="col-12">
                    <div class="form-section-block border-2 border-primary-subtle bg-primary-subtle bg-opacity-10">
                        <div class="row align-items-center g-4">
                            <div class="col-md-3">
                                <div class="section-title-sm text-primary mb-2"><i class="fa-solid fa-credit-card"></i> 4. Payment & Status</div>
                                <select name="payment_method" class="form-select fw-bold border-primary-subtle">
                                    <option value="Cash" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'Cash') ? 'selected' : '' ?>>Cash Payment</option>
                                    <option value="Card" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'Card') ? 'selected' : '' ?>>Bank / Card</option>
                                    <option value="Online" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'Online') ? 'selected' : '' ?>>Online Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold text-primary">Paid Amount</label>
                                <input type="number" step="0.01" name="paid_amount" id="purchase_paid" class="form-control border-primary bg-white fw-bold" value="<?= htmlspecialchars($edit['paid_amount'] ?? '0.00') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold">Due Balance</label>
                                <input type="number" step="0.01" name="due_amount" id="purchase_due" class="form-control form-control-lg bg-white text-danger fw-bold border-0 fs-5" value="<?= htmlspecialchars($edit['due_amount'] ?? '0.00') ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Stocking Status</label>
                                <select name="status" class="form-select form-select-lg fw-bold <?= (isset($edit['status']) && $edit['status'] == 'Received') ? 'text-success border-success bg-white' : 'text-warning border-warning bg-white' ?>">
                                    <option value="Pending" <?= (isset($edit['status']) && $edit['status'] == 'Pending') ? 'selected' : '' ?>>Pending Order</option>
                                    <option value="Received" <?= (isset($edit['status']) && $edit['status'] == 'Received') ? 'selected' : '' ?>>Received (Add Stock)</option>
                                </select>
                            </div>
                            <div class="col-md-2 text-end">
                                <input type="hidden" name="payment_status" id="purchase_pay_status" value="<?= $edit['payment_status'] ?? 'Unpaid' ?>">
                                <button class="btn btn-primary btn-lg w-100 shadow fw-bold py-3" name="<?= $edit ? 'update' : 'add' ?>">
                                    <?= $edit ? '<i class="fa-solid fa-save me-2"></i>Update' : '<i class="fa-solid fa-check-circle me-2"></i>Register' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- DATA TABLE -->
<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h6 class="fw-bold text-muted text-uppercase mb-0 small">Purchase Records</h6>
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center print-hide w-100 w-sm-auto">
                <input type="hidden" name="page" value="purchases">
                <div class="input-group input-group-sm flex-grow-1">
                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-calendar-alt text-muted"></i></span>
                    <input type="date" name="start" class="form-control border-light" value="<?= htmlspecialchars($start_date) ?>">
                    <input type="date" name="end" class="form-control border-light" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-dark btn-sm fw-bold px-3">Filter</button>
                    <a href="dashboard.php?page=purchases" class="btn btn-light btn-sm"><i class="fa-solid fa-refresh"></i></a>
                    <a href="dashboard.php?page=reports&pdf=1&type=purchase&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>" class="btn btn-danger btn-sm fw-bold px-3">
                        <i class="fa-solid fa-file-pdf me-1"></i> PDF
                    </a>
                </div>
            </form>
        </div>
    </div>
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Reference / Invoice</th>
                    <th>Inbound Product</th>
                    <th>Supplier</th>
                    <th>Pricing & Qty</th>
                    <th>Inventory Status</th>
                    <th>Total Stats</th>
                    <th>Status / Payment</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($purchases as $p): ?>
            <tr>
                <td class="ps-4" data-label="Ref / Invoice">
                    <div class="d-flex flex-column align-items-end align-items-md-start">
                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($p['reference_no']) ?></div>
                        <div class="small text-muted mt-1"><i class="fa-solid fa-receipt me-1 opacity-50"></i> <?= htmlspecialchars($p['invoice_no'] ?: '-') ?></div>
                    </div>
                </td>
                <td data-label="Product">
                    <div class="d-flex flex-column align-items-end align-items-md-start text-end text-md-start">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['product_name'] ?: 'Unknown') ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-layer-group me-1 opacity-50"></i> <?= htmlspecialchars($p['category_name'] ?: 'General') ?></div>
                        <div class="badge bg-primary-subtle text-primary fw-bold mt-2">SRP: Rs. <?= number_format($p['selling_price'], 2) ?></div>
                    </div>
                </td>
                <td data-label="Supplier">
                    <div class="fw-bold text-dark text-end text-md-start">
                        <i class="fa-solid fa-truck-field me-1 text-muted"></i> <?= htmlspecialchars($p['supplier']) ?>
                    </div>
                </td>
                <td data-label="Cost & Qty">
                    <div class="d-flex flex-column align-items-end align-items-md-start">
                        <div class="fw-bold">Rs. <?= number_format($p['unit_cost'], 2) ?> <small class="text-muted fw-normal">/ unit</small></div>
                        <div class="mt-1"><span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($p['quantity']) ?> Units Purchased</span></div>
                    </div>
                </td>
                <td data-label="Inventory">
                    <div class="d-flex flex-column align-items-end align-items-md-start">
                        <?php if($p['status'] == 'Received'): ?>
                            <div class="fw-bold text-success"><i class="fa-solid fa-warehouse me-1"></i> Stock: <?= $p['remaining_qty'] ?></div>
                        <?php endif; ?>
                        <div class="small text-muted mt-1">Batch: <span class="fw-semibold text-dark"><?= htmlspecialchars($p['batch_no'] ?: 'N/A') ?></span></div>
                        <?php if($p['expiry_date']): ?>
                            <div class="badge bg-danger-subtle text-danger fw-bold mt-1"><i class="fa-solid fa-calendar-xmark me-1"></i>Exp: <?= date('M d, Y', strtotime($p['expiry_date'])) ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <td data-label="Final Amount">
                    <div class="d-flex flex-column align-items-end align-items-md-start">
                        <div class="text-muted small">Sub: Rs. <?= number_format($p['total_cost'], 2) ?></div>
                        <div class="fw-bold text-primary fs-5">Rs. <?= number_format($p['final_amount'] ?? $p['total_cost'], 2) ?></div>
                    </div>
                </td>
                <td data-label="Status & Pay">
                    <div class="d-flex flex-column align-items-end align-items-md-start gap-2">
                        <span class="status-pill <?= $p['status'] == 'Received' ? 'pill-received' : 'pill-pending' ?>">
                            <i class="fa-solid <?= $p['status'] == 'Received' ? 'fa-circle-check' : 'fa-clock' ?> me-1"></i><?= $p['status'] ?>
                        </span>
                        <?php 
                            $payClass = $p['payment_status'] == 'Paid' ? 'pill-paid' : ($p['payment_status'] == 'Partial' ? 'pill-partial' : 'pill-unpaid');
                            $payIcon = $p['payment_status'] == 'Paid' ? 'fa-check-double' : ($p['payment_status'] == 'Partial' ? 'fa-adjust' : 'fa-times-circle');
                        ?>
                        <span class="status-pill <?= $payClass ?>">
                            <i class="fa-solid <?= $payIcon ?> me-1"></i><?= $p['payment_status'] ?>
                        </span>
                        <?php if($p['due_amount'] > 0): ?>
                            <div class="small text-danger fw-bold">Due: Rs. <?= number_format($p['due_amount'], 2) ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-end pe-4">
                    <div class="action-btn-group d-flex justify-content-center justify-content-md-end gap-3 py-2 py-md-0">
                        <a href="dashboard.php?page=purchases&edit=<?= $p['id'] ?>" class="btn btn-action-edit btn-touch" title="Edit Record">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <a href="dashboard.php?page=purchases&delete=<?= $p['id'] ?>" class="btn btn-action-del btn-touch" 
                           onclick="return confirm('Delete this purchase? This will revert stock if received. Confirm?')" title="Delete Record">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($purchases)): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted">
                <i class="fa-solid fa-file-lines fs-1 opacity-25 mb-3 d-block"></i>
                No purchase records found for the selected period.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const qtyInput = document.getElementById('purchase_qty');
    const unitCostInput = document.getElementById('purchase_unit_cost');
    const totalInput = document.getElementById('purchase_total');
    const discountInput = document.getElementById('purchase_discount');
    const taxInput = document.getElementById('purchase_tax');
    const finalInput = document.getElementById('purchase_final_amount');
    const finalDisplay = document.getElementById('final_amount_display');
    const paidInput = document.getElementById('purchase_paid');
    const dueInput = document.getElementById('purchase_due');
    const payStatusInput = document.getElementById('purchase_pay_status');

    function calculateAll() {
        const qty = parseFloat(qtyInput.value) || 0;
        const cost = parseFloat(unitCostInput.value) || 0;
        const discount = parseFloat(discountInput.value) || 0;
        const tax = parseFloat(taxInput.value) || 0;
        const paid = parseFloat(paidInput.value) || 0;

        const subtotal = qty * cost;
        totalInput.value = subtotal.toFixed(2);

        const final = subtotal - discount + tax;
        finalInput.value = final.toFixed(2);
        finalDisplay.innerText = 'Rs. ' + final.toLocaleString(undefined, {minimumFractionDigits: 2});

        const due = final - paid;
        dueInput.value = due.toFixed(2);

        // Update payment status automatically
        if (paid <= 0) {
            payStatusInput.value = 'Unpaid';
        } else if (paid < final) {
            payStatusInput.value = 'Partial';
        } else {
            payStatusInput.value = 'Paid';
        }
    }

    [qtyInput, unitCostInput, discountInput, taxInput, paidInput].forEach(el => {
        if(el) el.addEventListener('input', calculateAll);
    });

    const productSelect = document.getElementById('purchase_product');
    const filterCat = document.getElementById('filter_category');
    
    // Category Filter Logic
    if (filterCat) {
        filterCat.addEventListener('change', function() {
            const catId = this.value;
            const options = productSelect.options;
            
            productSelect.value = ""; // Reset product selection
            
            for (let i = 1; i < options.length; i++) {
                if (catId === "" || options[i].dataset.cat === catId) {
                    options[i].hidden = false;
                    options[i].disabled = false;
                } else {
                    options[i].hidden = true;
                    options[i].disabled = true;
                }
            }
            updateProductUI();
        });
    }

    const previewImg = document.getElementById('product_preview_img');
    const priceHint = document.getElementById('price_hint');
    const sellingPriceInput = document.getElementById('purchase_selling_price');

    function updateProductUI() {
        const opt = productSelect.options[productSelect.selectedIndex];
        if (opt && opt.value) {
            if (opt.dataset.image) {
                previewImg.src = opt.dataset.image;
                previewImg.style.display = 'block';
            } else {
                previewImg.style.display = 'none';
            }

            const oldCost = parseFloat(opt.dataset.cost) || 0;
            const oldPrice = parseFloat(opt.dataset.price) || 0;
            
            priceHint.innerHTML = `<span class='badge bg-light text-dark border me-1'>Old Cost: Rs ${oldCost.toFixed(2)}</span> <span class='badge bg-light text-dark border'>Old Price: Rs ${oldPrice.toFixed(2)}</span>`;
            
            // Auto-fill cost and selling price if they are empty
            if (!unitCostInput.value || unitCostInput.value == '0') {
                unitCostInput.value = oldCost;
            }
            if (!sellingPriceInput.value || sellingPriceInput.value == '0') {
                sellingPriceInput.value = oldPrice;
            }
            
            calculateAll();
        } else {
            previewImg.style.display = 'none';
            priceHint.innerHTML = '';
        }
    }

    if (productSelect) {
        productSelect.addEventListener('change', updateProductUI);
        // On page load, if product is selected (edit mode), update UI
        if(productSelect.value) updateProductUI();
    }
    
    // Initial calculation
    calculateAll();
});
</script>
