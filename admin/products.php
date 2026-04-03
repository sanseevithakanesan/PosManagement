<?php
require_once "../database/db.php";
require_once dirname(__DIR__) . "/includes/product_schema.php";
require_once dirname(__DIR__) . "/includes/product_media.php";
ensure_product_extended_schema($pdo);

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
   SEARCH + LIST
===================== */
$search = $_GET['search'] ?? '';

$imgSelect = '(SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1)';

if($search != ''){
    $stmt = $pdo->prepare("SELECT p.*, {$imgSelect} AS image_path FROM products p
        WHERE p.name LIKE ? OR p.barcode LIKE ?
        ORDER BY p.id DESC");
    $stmt->execute(["%$search%","%$search%"]);
    $products = $stmt->fetchAll();
}else{
    $products = $pdo->query("SELECT p.*, {$imgSelect} AS image_path FROM products p ORDER BY p.id DESC")->fetchAll();
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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h3 class="mb-0">Products</h3>
    <a href="product_create.php" class="btn btn-primary">+ New product</a>
</div>

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
        <button class="btn btn-primary" type="submit">Search</button>
        <?php if($search != ''): ?>
            <a href="dashboard.php?page=products" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- TABLE -->
<div class="card content-card p-3">
    <h5>Product List</h5>
    <hr>
    
    <?php if(count($products) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:64px">Image</th>
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
                        <td class="p-1">
                            <img src="<?= htmlspecialchars(product_image_url($p['image_path'] ?? null)) ?>"
                                 alt="" width="48" height="48" class="rounded border" style="width:48px;height:48px;object-fit:cover;">
                        </td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td>Rs. <?= number_format($p['price'], 2) ?></td>
                        <td class="<?= $stockClass ?>">
                            <?= $p['stock'] ?> <?= $stockBadge ?>
                        </td>
                        <td><?= htmlspecialchars($p['barcode'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($catName ?: '-') ?></td>
                        <td>
                            <?php if (!empty($p['barcode'])): ?>
                                <a href="print_barcode.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline-dark btn-sm me-1">Print label</a>
                            <?php endif; ?>
                            <a href="product_create.php?id=<?= (int)$p['id'] ?>"
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
            No products found. Use <strong>New product</strong> to create the first SKU.
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