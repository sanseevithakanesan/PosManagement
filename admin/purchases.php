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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h3 class="mb-0">Purchases & Replenishment</h3>
    <a href="dashboard.php?page=purchases" class="btn btn-outline-primary <?= $edit ? '' : 'd-none' ?>">Add New</a>
</div>

<!-- ALERT -->
<?php if(isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<!-- FORM -->
<div class="card content-card p-4 mb-4 shadow-sm border-0">
    <h5 class="mb-3 text-secondary"><?= $edit ? 'Edit Purchase' : 'New Purchase' ?></h5>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="row g-4">
            <!-- SECTION 1: BASIC INFO -->
            <div class="col-12">
                <div class="p-3 bg-light rounded-3 border">
                    <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-info-circle me-2"></i>1. Basic Information</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Reference No.</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= htmlspecialchars($edit['reference_no'] ?? '') ?>" placeholder="Auto-generate">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Invoice No.</label>
                            <input type="text" name="invoice_no" class="form-control" value="<?= htmlspecialchars($edit['invoice_no'] ?? '') ?>" placeholder="Supplier Invoice #">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Supplier *</label>
                            <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($edit['supplier'] ?? '') ?>" placeholder="e.g. ABC Dist" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Purchase Date</label>
                            <input type="text" class="form-control bg-white" value="<?= isset($edit['purchase_date']) ? date('Y-m-d H:i', strtotime($edit['purchase_date'])) : date('Y-m-d H:i') ?>" readonly disabled>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2 & 3: PRODUCT & PRICING -->
            <div class="col-lg-8">
                <div class="p-3 bg-white rounded-3 border h-100">
                    <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-box me-2"></i>2 & 3. Product & Pricing</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Filter Category</label>
                            <select id="filter_category" class="form-select border-info">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold">Select Product *</label>
                            <div class="d-flex gap-2 align-items-start">
                                <select name="product_id" id="purchase_product" class="form-select border-primary" required>
                                    <option value="" data-image="" data-cost="" data-price="" data-cat="">Select Product...</option>
                                    <?php foreach($products as $prod): ?>
                                        <option value="<?= $prod['id'] ?>" 
                                                data-image="<?= htmlspecialchars(product_image_url($prod['image_path'] ?? null)) ?>"
                                                data-cost="<?= $prod['cost_price'] ?? 0 ?>"
                                                data-price="<?= $prod['price'] ?? 0 ?>"
                                                data-cat="<?= $prod['category_id'] ?>"
                                                <?= (isset($edit['product_id']) && $edit['product_id'] == $prod['id']) ? 'selected' : '' ?>>
                                            [<?= htmlspecialchars($prod['cat_name'] ?: 'No Category') ?>] <?= htmlspecialchars($prod['name']) ?> (Stock: <?= $prod['stock'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <img id="product_preview_img" src="" alt="" class="rounded border bg-light" style="width: 45px; height: 45px; object-fit: cover; display: none;">
                            </div>
                            <div id="price_hint" class="small mt-1 text-muted"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label text-muted small fw-bold">Batch Number / Expiry</label>
                            <div class="input-group">
                                <input type="text" name="batch_no" class="form-control" value="<?= htmlspecialchars($edit['batch_no'] ?? '') ?>" placeholder="Batch #">
                                <input type="date" name="expiry_date" class="form-control" value="<?= htmlspecialchars($edit['expiry_date'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Quantity *</label>
                            <input type="number" name="quantity" id="purchase_qty" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars($edit['quantity'] ?? '') ?>" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Buy Price (Unit) *</label>
                            <input type="number" step="0.01" name="unit_cost" id="purchase_unit_cost" class="form-control form-control-lg" value="<?= htmlspecialchars($edit['unit_cost'] ?? '') ?>" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold text-success">New Selling Price</label>
                            <input type="number" step="0.01" name="selling_price" id="purchase_selling_price" class="form-control form-control-lg border-success text-success" value="<?= htmlspecialchars($edit['selling_price'] ?? '') ?>" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Total Cost</label>
                            <input type="number" step="0.01" name="total_cost" id="purchase_total" class="form-control form-control-lg bg-light" value="<?= htmlspecialchars($edit['total_cost'] ?? '') ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: DISCOUNT & TAX -->
            <div class="col-lg-4">
                <div class="p-3 bg-white rounded-3 border h-100">
                    <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-percent me-2"></i>4. Discount & Tax</h6>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Supplier Discount (Rs)</label>
                        <input type="number" step="0.01" name="discount" id="purchase_discount" class="form-control" value="<?= htmlspecialchars($edit['discount'] ?? '0.00') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Tax / GST (Rs)</label>
                        <input type="number" step="0.01" name="tax" id="purchase_tax" class="form-control" value="<?= htmlspecialchars($edit['tax'] ?? '0.00') ?>">
                    </div>
                    <div class="p-2 bg-dark text-white rounded">
                        <label class="small opacity-75">Final Payable Amount:</label>
                        <h4 class="mb-0 fw-bold" id="final_amount_display">Rs. 0.00</h4>
                        <input type="hidden" name="final_amount" id="purchase_final_amount" value="<?= $edit['final_amount'] ?? '0' ?>">
                    </div>
                </div>
            </div>

            <!-- SECTION 6: PAYMENT INFO -->
            <div class="col-12">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="row align-items-end g-3">
                        <div class="col-md-3">
                            <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-wallet2 me-2"></i>6. Payment Information</h6>
                            <select name="payment_method" class="form-select">
                                <option value="Cash" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'Cash') ? 'selected' : '' ?>>Cash Payment</option>
                                <option value="Card" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'Card') ? 'selected' : '' ?>>Card / Bank</option>
                                <option value="Online" <?= (isset($edit['payment_method']) && $edit['payment_method'] == 'Online') ? 'selected' : '' ?>>Online Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small fw-bold">Paid Amount (Rs)</label>
                            <input type="number" step="0.01" name="paid_amount" id="purchase_paid" class="form-control" value="<?= htmlspecialchars($edit['paid_amount'] ?? '0.00') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small fw-bold">Due Balance (Rs)</label>
                            <input type="number" step="0.01" name="due_amount" id="purchase_due" class="form-control text-danger fw-bold bg-white" value="<?= htmlspecialchars($edit['due_amount'] ?? '0.00') ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">Purchase Status</label>
                            <select name="status" class="form-select fw-bold <?= (isset($edit['status']) && $edit['status'] == 'Received') ? 'text-success border-success' : 'text-warning border-warning' ?>">
                                <option value="Pending" <?= (isset($edit['status']) && $edit['status'] == 'Pending') ? 'selected' : '' ?>>Pending (Order Placed)</option>
                                <option value="Received" <?= (isset($edit['status']) && $edit['status'] == 'Received') ? 'selected' : '' ?>>Received (Adds to Stock)</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <input type="hidden" name="payment_status" id="purchase_pay_status" value="<?= $edit['payment_status'] ?? 'Unpaid' ?>">
                            <button class="btn btn-primary btn-lg w-100 shadow-sm fw-bold" name="<?= $edit ? 'update' : 'add' ?>">
                                <?= $edit ? 'Update Record' : 'Save Purchase' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- DATA TABLE -->
<div class="card content-card p-3 shadow-sm border-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-muted mb-0">Record List</h6>
        <form method="GET" class="d-flex gap-2 align-items-center print-hide">
            <input type="hidden" name="page" value="purchases">
            <input type="date" name="start" class="form-control form-control-sm border-primary" value="<?= htmlspecialchars($start_date) ?>" style="width: 140px;">
            <span class="small text-muted">-</span>
            <input type="date" name="end" class="form-control form-control-sm border-primary" value="<?= htmlspecialchars($end_date) ?>" style="width: 140px;">
            <button class="btn btn-primary btn-sm fw-bold">Filter</button>
            <a href="dashboard.php?page=purchases" class="btn btn-outline-secondary btn-sm">Clear</a>
            <a href="dashboard.php?page=reports&pdf=1&type=purchase&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>" class="btn btn-dark btn-sm fw-bold shadow-sm d-flex align-items-center">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="me-1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                PDF
            </a>
        </form>
    </div>
<div class="table-responsive">
<table class="table table-bordered table-hover align-middle mb-0 bg-white">
<thead class="table-light text-muted small text-uppercase">
<tr>
    <th>Ref / Invoice</th>
    <th>Product</th>
    <th>Supplier</th>
    <th>Cost & Qty</th>
    <th>Batch Info</th>
    <th>Total / Final</th>
    <th>Status / Pay</th>
    <th>Date</th>
    <th class="text-end">Action</th>
</tr>
</thead>
<tbody>
<?php foreach($purchases as $p): ?>
<tr>
    <td>
        <div class="fw-bold text-dark"><?= htmlspecialchars($p['reference_no']) ?></div>
        <div class="small text-muted"><?= htmlspecialchars($p['invoice_no'] ?: '-') ?></div>
    </td>
    <td>
        <div class="fw-bold text-dark"><?= htmlspecialchars($p['product_name'] ?: 'Unknown') ?></div>
        <div class="small text-muted"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($p['category_name'] ?: 'Uncategorized') ?></div>
        <div class="small text-primary">Sell Price: Rs <?= number_format($p['selling_price'], 2) ?></div>
    </td>
    <td><?= htmlspecialchars($p['supplier']) ?></td>
    <td>
        <div><small class="text-muted">Unit Cost:</small> <?= number_format($p['unit_cost'], 2) ?></div>
        <div><small class="text-muted">Total Qty:</small> <b><?= htmlspecialchars($p['quantity']) ?></b></div>
    </td>
    <td>
        <?php if($p['status'] == 'Received'): ?>
            <div class="small">Rem: <b class="<?= $p['remaining_qty'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $p['remaining_qty'] ?></b></div>
        <?php endif; ?>
        <div class="small text-muted">Batch: <?= htmlspecialchars($p['batch_no'] ?: '-') ?></div>
        <?php if($p['expiry_date']): ?>
            <div class="small text-danger">Exp: <?= date('d M Y', strtotime($p['expiry_date'])) ?></div>
        <?php endif; ?>
    </td>
    <td>
        <div class="text-muted small">Sub: <?= number_format($p['total_cost'], 2) ?></div>
        <div class="fw-bold text-primary">Final: <?= number_format($p['final_amount'] ?? $p['total_cost'], 2) ?></div>
    </td>
    <td>
        <?php 
            $statusColor = $p['status'] == 'Received' ? 'success' : 'warning text-dark';
            $payColor = $p['payment_status'] == 'Paid' ? 'primary' : ($p['payment_status'] == 'Partial' ? 'info text-dark' : 'danger');
        ?>
        <div class="mb-1"><span class="badge bg-<?= $statusColor ?>"><?= $p['status'] ?></span></div>
        <div><span class="badge bg-<?= $payColor ?>"><?= $p['payment_status'] ?></span></div>
        <div class="small mt-1 text-danger">Due: <?= number_format($p['due_amount'], 2) ?></div>
    </td>
    <td><small><?= htmlspecialchars(date('M d, Y', strtotime($p['purchase_date']))) ?></small></td>
    <td class="text-end">
        <a href="dashboard.php?page=purchases&edit=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm me-1">Edit</a>
        <a href="dashboard.php?page=purchases&delete=<?= $p['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this purchase? This will revert stock if it was Received. Are you sure?')">Del</a>
    </td>
</tr>
<?php endforeach; ?>
<?php if(empty($purchases)): ?>
<tr>
    <td colspan="8" class="text-center py-4 text-muted">No purchases found. Create one above!</td>
</tr>
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
