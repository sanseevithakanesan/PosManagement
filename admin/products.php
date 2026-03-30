<?php
require_once "../database/db.php";
// Add this at the top if not already in dashboard.php

/* =====================
   ADD PRODUCT
===================== */
if(isset($_POST['add'])){
    $stmt = $pdo->prepare("INSERT INTO products(name,price,stock,barcode,category_id)
                           VALUES(?,?,?,?,?)");

    $stmt->execute([
        $_POST['name'],
        $_POST['price'],
        $_POST['stock'],
        $_POST['barcode'],
        $_POST['category_id']
    ]);
    
    $_SESSION['message'] = "✅ Product added successfully!";
    $_SESSION['message_type'] = "success";
    header("Location: dashboard.php?page=products");
    exit;
}

/* =====================
   DELETE WITH CHECK
===================== */
if(isset($_GET['delete'])){
    $productId = $_GET['delete'];
    
    try {
        // First check if product exists in any orders
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $checkStmt->execute([$productId]);
        $orderCount = $checkStmt->fetchColumn();
        
        if($orderCount > 0) {
            // Product is used in orders - cannot delete
            $_SESSION['message'] = "❌ Cannot delete this product! It is used in {$orderCount} order(s). Please remove the orders first or contact administrator.";
            $_SESSION['message_type'] = "danger";
        } else {
            // Safe to delete
            $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
            $stmt->execute([$productId]);
            $_SESSION['message'] = "✅ Product deleted successfully!";
            $_SESSION['message_type'] = "success";
        }
    } catch(PDOException $e) {
        // Handle any database errors
        $_SESSION['message'] = "❌ Error: Cannot delete this product. It may be referenced in orders or other records.";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: dashboard.php?page=products");
    exit;
}

/* =====================
   EDIT FETCH
===================== */
$edit = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

/* =====================
   UPDATE
===================== */
if(isset($_POST['update'])){
    $stmt = $pdo->prepare("UPDATE products
        SET name=?, price=?, stock=?, barcode=?, category_id=?
        WHERE id=?");

    $stmt->execute([
        $_POST['name'],
        $_POST['price'],
        $_POST['stock'],
        $_POST['barcode'],
        $_POST['category_id'],
        $_POST['id']
    ]);
    
    $_SESSION['message'] = "✅ Product updated successfully!";
    $_SESSION['message_type'] = "success";
    header("Location: dashboard.php?page=products");
    exit;
}

/* =====================
   SEARCH + LIST
===================== */
$search = $_GET['search'] ?? '';

if($search != ''){
    $stmt = $pdo->prepare("SELECT * FROM products
        WHERE name LIKE ? OR barcode LIKE ?
        ORDER BY id DESC");
    $stmt->execute(["%$search%","%$search%"]);
    $products = $stmt->fetchAll();
}else{
    $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
}

/* categories dropdown */
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>

<style>
/* Alert message styles */
.alert {
    padding: 12px 20px;
    margin-bottom: 20px;
    border-radius: 5px;
    position: relative;
    animation: slideDown 0.5s ease;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

.close-alert {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 20px;
    font-weight: bold;
}

.close-alert:hover {
    opacity: 0.7;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Stock status badges */
.badge {
    display: inline-block;
    padding: 3px 6px;
    font-size: 10px;
    font-weight: bold;
    border-radius: 3px;
    margin-left: 5px;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.text-danger {
    color: #dc3545 !important;
    font-weight: bold;
}

.text-warning {
    color: #ffc107 !important;
    font-weight: bold;
}

/* Delete confirmation modal */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s;
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    animation: slideUp 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 10px 0;
    border-bottom: 1px solid #ddd;
    margin-bottom: 15px;
}

.modal-footer {
    padding: 10px 0;
    border-top: 1px solid #ddd;
    margin-top: 15px;
    text-align: right;
}

.modal-footer button {
    padding: 8px 15px;
    margin-left: 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-confirm {
    background-color: #dc3545;
    color: white;
}

.btn-cancel {
    background-color: #6c757d;
    color: white;
}

.btn-loading {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<h3>🛒 Products</h3>

<!-- Display Alert Messages -->
<?php if(isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?>">
        <?= $_SESSION['message'] ?>
        <span class="close-alert" onclick="this.parentElement.style.display='none';">&times;</span>
    </div>
    <?php 
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
    ?>
<?php endif; ?>

<!-- SEARCH -->
<form method="GET" class="mb-3">
    <input type="hidden" name="page" value="products">
    <div class="input-group">
        <input type="text" name="search"
               class="form-control"
               placeholder="Search product name / barcode"
               value="<?= htmlspecialchars($search) ?>">
        <div class="input-group-append">
            <button class="btn btn-primary" type="submit">🔍 Search</button>
            <?php if($search != ''): ?>
                <a href="dashboard.php?page=products" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- FORM -->
<div class="card p-3 mb-3">
    <h5><?= $edit ? '✏️ Edit Product' : '➕ Add New Product' ?></h5>
    <hr>
    
    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="form-group">
            <label>Product Name *</label>
            <input type="text" name="name" class="form-control mb-2"
                   placeholder="Enter product name"
                   value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
        </div>
        
        <div class="form-row">
            <div class="col-md-6">
                <label>Price (Rs.) *</label>
                <input type="number" name="price" class="form-control mb-2"
                       placeholder="0.00"
                       step="0.01"
                       value="<?= $edit['price'] ?? '' ?>" required>
            </div>
            
            <div class="col-md-6">
                <label>Stock *</label>
                <input type="number" name="stock" class="form-control mb-2"
                       placeholder="0"
                       value="<?= $edit['stock'] ?? '' ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Barcode</label>
            <input type="text" name="barcode" class="form-control mb-2"
                   placeholder="Enter barcode (optional)"
                   value="<?= htmlspecialchars($edit['barcode'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>Category</label>
            <select name="category_id" class="form-control mb-2">
                <option value="">-- Select Category --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        <?= ($edit && $edit['category_id']==$c['id'])?'selected':'' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button class="btn btn-<?= $edit ? 'warning' : 'success' ?>"
                name="<?= $edit ? 'update' : 'add' ?>">
            <?= $edit ? '🔄 Update Product' : '➕ Add Product' ?>
        </button>
        
        <?php if($edit): ?>
            <a href="dashboard.php?page=products" class="btn btn-secondary">❌ Cancel</a>
        <?php endif; ?>
    </form>
</div>

<!-- TABLE -->
<div class="card p-3">
    <h5>📋 Product List</h5>
    <hr>
    
    <?php if(count($products) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Barcode</th>
                        <th>Category</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($products as $p): ?>
                    <?php
                    $catName = '';
                    foreach($categories as $c){
                        if($c['id'] == $p['category_id']){
                            $catName = $c['name'];
                            break;
                        }
                    }
                    
                    // Stock status
                    $stockClass = '';
                    $stockBadge = '';
                    if($p['stock'] <= 0) {
                        $stockClass = 'text-danger';
                        $stockBadge = '<span class="badge badge-danger">Out of Stock</span>';
                    } elseif($p['stock'] <= 10) {
                        $stockClass = 'text-warning';
                        $stockBadge = '<span class="badge badge-warning">Low Stock</span>';
                    }
                    ?>
                    
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td>Rs. <?= number_format($p['price'], 2) ?></td>
                        <td class="<?= $stockClass ?>">
                            <?= $p['stock'] ?> <?= $stockBadge ?>
                        </td>
                        <td><?= htmlspecialchars($p['barcode'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($catName ?: '-') ?></td>
                        <td>
                            <a href="dashboard.php?page=products&edit=<?= $p['id'] ?>"
                               class="btn btn-primary btn-sm"
                               style="display: inline-block; margin-right: 5px;">
                                Edit
                            </a>
                            
                            <button type="button"
                                    class="btn btn-danger btn-sm"
                                    onclick="checkAndDelete(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')">
                                Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-2 text-muted">
            <small>Total Products: <?= count($products) ?></small>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">
            📭 No products found. Click "Add Product" to get started.
        </div>
    <?php endif; ?>
</div>

<!-- Custom Modal for Delete Confirmation -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>⚠️ Confirm Delete</h4>
        </div>
        <div class="modal-body">
            <p id="deleteMessage">Are you sure you want to delete this product?</p>
            <div id="orderInfo" style="color: #856404; background-color: #fff3cd; padding: 10px; border-radius: 4px; margin-top: 10px; display: none;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal()">Cancel</button>
            <button id="confirmDeleteBtn" class="btn-confirm" onclick="proceedDelete()">Delete</button>
        </div>
    </div>
</div>

<script>
let productToDelete = null;
let productNameToDelete = '';

// Function to check and delete
function checkAndDelete(productId, productName) {
    productToDelete = productId;
    productNameToDelete = productName;
    
    // Show loading in modal
    const modal = document.getElementById('deleteModal');
    const messageDiv = document.getElementById('deleteMessage');
    const orderInfoDiv = document.getElementById('orderInfo');
    
    messageDiv.innerHTML = `Checking if "${productName}" can be deleted...`;
    orderInfoDiv.style.display = 'none';
    modal.style.display = 'block';
    
    // Disable confirm button temporarily
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = true;
    confirmBtn.classList.add('btn-loading');
    confirmBtn.innerHTML = 'Checking...';
    
    // Check if product has orders via AJAX
    fetch(`?check_orders=1&product_id=${productId}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if(data.has_orders) {
            // Product has orders - cannot delete
            messageDiv.innerHTML = `❌ Cannot delete "${productName}"!`;
            orderInfoDiv.innerHTML = `
                <strong>Reason:</strong> This product is used in ${data.order_count} order(s).<br><br>
                <strong>Solution:</strong> Please remove all orders containing this product before deleting.<br>
                <strong>Note:</strong> Deleting this product would affect your order history.
            `;
            orderInfoDiv.style.display = 'block';
            confirmBtn.disabled = true;
            confirmBtn.classList.add('btn-loading');
            confirmBtn.innerHTML = 'Cannot Delete';
            confirmBtn.style.backgroundColor = '#6c757d';
        } else {
            // Product can be deleted
            messageDiv.innerHTML = `⚠️ Are you sure you want to delete "${productName}"?`;
            orderInfoDiv.style.display = 'none';
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('btn-loading');
            confirmBtn.innerHTML = 'Yes, Delete';
            confirmBtn.style.backgroundColor = '#dc3545';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        messageDiv.innerHTML = '❌ Error checking product status. Please try again.';
        orderInfoDiv.style.display = 'none';
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = 'Error';
    });
}

// Proceed with delete
function proceedDelete() {
    if(productToDelete) {
        window.location.href = `dashboard.php?page=products&delete=${productToDelete}`;
    }
}

// Close modal
function closeModal() {
    const modal = document.getElementById('deleteModal');
    modal.style.display = 'none';
    productToDelete = null;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s ease';
        setTimeout(function() {
            if(alert.parentElement) {
                alert.style.display = 'none';
            }
        }, 500);
    });
}, 5000);
</script>

<?php
// Handle AJAX request for checking orders
if(isset($_GET['check_orders']) && isset($_GET['product_id'])) {
    header('Content-Type: application/json');
    $productId = $_GET['product_id'];
    
    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $checkStmt->execute([$productId]);
        $orderCount = $checkStmt->fetchColumn();
        
        echo json_encode([
            'has_orders' => ($orderCount > 0),
            'order_count' => $orderCount
        ]);
    } catch(PDOException $e) {
        echo json_encode([
            'has_orders' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>