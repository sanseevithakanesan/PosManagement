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
    supplier VARCHAR(255) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Received') DEFAULT 'Pending',
    payment_status ENUM('Paid', 'Unpaid', 'Partial') DEFAULT 'Unpaid',
    purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Fetch products for dropdown
$products = $pdo->query("SELECT id, name, stock FROM products ORDER BY name ASC")->fetchAll();

/* ADD */
if(isset($_POST['add'])){
    $pdo->beginTransaction();
    try {
        $reference = $_POST['reference_no'] ?: 'PUR-'.time();
        $pdo->prepare("INSERT INTO purchases(reference_no, supplier, product_id, quantity, unit_cost, total_cost, status, payment_status) VALUES(?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $reference, 
                $_POST['supplier'], 
                $_POST['product_id'], 
                $_POST['quantity'], 
                $_POST['unit_cost'], 
                $_POST['total_cost'],
                $_POST['status'],
                $_POST['payment_status']
            ]);
            
        // If Received, Add to Stock
        if($_POST['status'] === 'Received') {
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$_POST['quantity'], $_POST['product_id']]);
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
        
        $pdo->prepare("UPDATE purchases SET reference_no=?, supplier=?, product_id=?, quantity=?, unit_cost=?, total_cost=?, status=?, payment_status=? WHERE id=?")
            ->execute([
                $_POST['reference_no'], 
                $_POST['supplier'], 
                $_POST['product_id'], 
                $_POST['quantity'], 
                $_POST['unit_cost'], 
                $_POST['total_cost'],
                $_POST['status'],
                $_POST['payment_status'],
                $_POST['id']
            ]);
            
        // Manage Stock differences
        if($old_p) {
            // First, revert the old stock if it was Received
            if($old_p['status'] === 'Received') {
                $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$old_p['quantity'], $old_p['product_id']]);
            }
            // Then, apply the new stock if the new status is Received
            if($_POST['status'] === 'Received') {
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$_POST['quantity'], $_POST['product_id']]);
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
$purchases = $pdo->query("
    SELECT p.*, pr.name as product_name 
    FROM purchases p 
    LEFT JOIN products pr ON p.product_id = pr.id
    ORDER BY p.id DESC
")->fetchAll();
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
        
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Reference No.</label>
                <input type="text" name="reference_no" class="form-control" value="<?= htmlspecialchars($edit['reference_no'] ?? '') ?>" placeholder="(Leave blank to auto-generate)">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Supplier *</label>
                <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($edit['supplier'] ?? '') ?>" placeholder="e.g. ABC Dist" required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Product *</label>
                <select name="product_id" class="form-select" required>
                    <option value="">Select Product...</option>
                    <?php foreach($products as $prod): ?>
                        <option value="<?= $prod['id'] ?>" <?= (isset($edit['product_id']) && $edit['product_id'] == $prod['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prod['name']) ?> (Stock: <?= $prod['stock'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Purchase Date</label>
                <input type="text" class="form-control bg-light" value="<?= isset($edit['purchase_date']) ? date('Y-m-d H:i', strtotime($edit['purchase_date'])) : date('Y-m-d H:i') ?>" readonly disabled>
            </div>

            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Quantity *</label>
                <input type="number" name="quantity" id="purchase_qty" class="form-control" value="<?= htmlspecialchars($edit['quantity'] ?? '') ?>" placeholder="0" min="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Unit Cost (Rs) *</label>
                <input type="number" step="0.01" name="unit_cost" id="purchase_unit_cost" class="form-control" value="<?= htmlspecialchars($edit['unit_cost'] ?? '') ?>" placeholder="0.00" min="0" required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold border-bottom border-primary mb-1">Total Cost *</label>
                <input type="number" step="0.01" name="total_cost" id="purchase_total" class="form-control border-primary text-primary fw-bold" style="background:#f8faff" value="<?= htmlspecialchars($edit['total_cost'] ?? '') ?>" required readonly>
            </div>
            
            <div class="col-md-6 mt-4">
                <div class="d-flex gap-3 p-3 bg-light rounded border">
                    <div class="flex-grow-1">
                        <label class="form-label text-muted small fw-bold mb-1">Order Status</label>
                        <select name="status" class="form-select border-warning">
                            <option value="Pending" <?= (isset($edit['status']) && $edit['status'] == 'Pending') ? 'selected' : '' ?>>Pending (Doesn't add stock)</option>
                            <option value="Received" <?= (isset($edit['status']) && $edit['status'] == 'Received') ? 'selected' : '' ?>>Received (Adds to stock)</option>
                        </select>
                    </div>
                    <div class="flex-grow-1">
                        <label class="form-label text-muted small fw-bold mb-1">Payment Status</label>
                        <select name="payment_status" class="form-select border-info">
                            <option value="Unpaid" <?= (isset($edit['payment_status']) && $edit['payment_status'] == 'Unpaid') ? 'selected' : '' ?>>Unpaid</option>
                            <option value="Partial" <?= (isset($edit['payment_status']) && $edit['payment_status'] == 'Partial') ? 'selected' : '' ?>>Partial</option>
                            <option value="Paid" <?= (isset($edit['payment_status']) && $edit['payment_status'] == 'Paid') ? 'selected' : '' ?>>Paid</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top">
            <button class="btn btn-primary px-4 fw-bold" name="<?= $edit ? 'update' : 'add' ?>">
                <?= $edit ? 'Save Changes' : 'Create Purchase Order' ?>
            </button>
            <?php if($edit): ?>
                <a href="dashboard.php?page=purchases" class="btn btn-light px-4 ms-2 text-dark border">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DATA TABLE -->
<div class="card content-card p-3 shadow-sm border-0">
<div class="table-responsive">
<table class="table table-bordered table-hover align-middle mb-0 bg-white">
<thead class="table-light text-muted small text-uppercase">
<tr>
    <th>Ref No</th>
    <th>Product</th>
    <th>Supplier</th>
    <th>Cost & Qty</th>
    <th>Total</th>
    <th>Status</th>
    <th>Date</th>
    <th class="text-end">Action</th>
</tr>
</thead>
<tbody>
<?php foreach($purchases as $p): ?>
<tr>
    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['reference_no']) ?></span></td>
    <td><strong class="text-dark"><?= htmlspecialchars($p['product_name'] ?: 'Unknown') ?></strong></td>
    <td><?= htmlspecialchars($p['supplier']) ?></td>
    <td>
        <div><small class="text-muted">Unit:</small> <?= number_format($p['unit_cost'], 2) ?></div>
        <div><small class="text-muted">Qty:</small> <b><?= htmlspecialchars($p['quantity']) ?></b></div>
    </td>
    <td class="text-primary fw-bold">Rs. <?= number_format($p['total_cost'], 2) ?></td>
    <td>
        <?php 
            $statusColor = $p['status'] == 'Received' ? 'success' : 'warning text-dark';
            $payColor = $p['payment_status'] == 'Paid' ? 'primary' : ($p['payment_status'] == 'Partial' ? 'info text-dark' : 'danger');
        ?>
        <div class="mb-1"><span class="badge bg-<?= $statusColor ?>"><?= $p['status'] ?></span></div>
        <div><span class="badge bg-<?= $payColor ?>"><?= $p['payment_status'] ?></span></div>
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

    function calculateTotal() {
        const qty = parseFloat(qtyInput.value) || 0;
        const cost = parseFloat(unitCostInput.value) || 0;
        totalInput.value = (qty * cost).toFixed(2);
    }

    qtyInput.addEventListener('input', calculateTotal);
    unitCostInput.addEventListener('input', calculateTotal);
});
</script>
