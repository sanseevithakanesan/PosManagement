<?php
require_once "../database/db.php";
require_once dirname(__DIR__) . "/includes/product_schema.php";
require_once dirname(__DIR__) . "/includes/product_media.php";
ensure_product_extended_schema($pdo);

/* =====================
   SOFT DELETE
===================== */
if(isset($_GET['delete'])){
    $productId = $_GET['delete'];
    
    try {
        // We use soft delete (setting deleted_at) instead of hard delete 
        // to preserve order history and bypass foreign key constraints
        $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id=?");
        $stmt->execute([$productId]);
        
        $_SESSION['message'] = "✅ Product removed successfully! (Archived to preserve history)";
        $_SESSION['message_type'] = "success";
    } catch(PDOException $e) {
        // Handle any database errors
        $_SESSION['message'] = "❌ Error: Could not remove this product. " . $e->getMessage();
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
        WHERE (p.name LIKE ? OR p.barcode LIKE ?) AND p.deleted_at IS NULL
        ORDER BY p.id DESC");
    $stmt->execute(["%$search%","%$search%"]);
    $products = $stmt->fetchAll();
}else{
    $products = $pdo->query("SELECT p.*, {$imgSelect} AS image_path FROM products p WHERE p.deleted_at IS NULL ORDER BY p.id DESC")->fetchAll();
}

/* categories dropdown */
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>


<style>
    /* Modern UI Overrides */
    .products-header {
        background: linear-gradient(135deg, #48dbfb 0%, #2e86de 100%);
        padding: 25px;
        border-radius: 15px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(46, 134, 222, 0.2);
    }
    .modern-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .img-preview-lg {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        object-fit: cover;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    .img-preview-lg:hover { transform: scale(1.1); }
    
    /* Soft Pill Badges */
    .stock-badge {
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 50px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-in-stock { background: #e0fdf4; color: #10b981; }
    .badge-low-stock { background: #fffbeb; color: #f59e0b; }
    .badge-out-stock { background: #fef2f2; color: #ef4444; }
    
    /* Action Buttons */
    .action-group .btn {
        width: 36px;
        height: 36px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        transition: all 0.2s;
        border: none;
        margin: 0 2px;
    }
    .btn-edit-sec { background: #f1f5f9; color: #64748b; }
    .btn-edit-sec:hover { background: #2e86de; color: white; }
    .btn-print { background: #f1f5f9; color: #334155; }
    .btn-print:hover { background: #334155; color: white; }
    .btn-delete-sec { background: #fef2f2; color: #ef4444; }
    .btn-delete-sec:hover { background: #ef4444; color: white; }

    /* Touch Friendly Buttons */
    .btn-lg-touch {
        width: 48px !important;
        height: 48px !important;
        font-size: 1.2rem !important;
    }

    /* Modern Modal */
    .modern-modal-content {
        border-radius: 16px;
        border: none;
        overflow: hidden;
    }
    .modal-header-modern {
        background: #f8fafc;
        padding: 20px;
        text-align: center;
    }
    .modal-footer-modern {
        background: #f8fafc;
        padding: 15px;
        display: flex;
        justify-content: center;
        gap: 15px;
    }
</style>


<div class="products-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <h3 class="mb-1 fw-bold"><i class="fa-solid fa-boxes-stacked me-2"></i> Products</h3>
        <p class="mb-0 opacity-75">Inventory and SKU Management</p>
    </div>
    <a href="product_create.php" class="btn btn-light btn-lg fw-bold px-4 py-2 py-sm-3 rounded-4 shadow-sm">
        <i class="fa-solid fa-plus-circle me-2 text-primary"></i> New Product
    </a>
</div>

<!-- SEARCH -->
<div class="card modern-card mb-4">
    <div class="card-body p-3">
        <form method="GET">
            <input type="hidden" name="page" value="products">
            <div class="d-flex flex-column flex-sm-row gap-2">
                <div class="input-group input-group-lg border-0 bg-light rounded-pill px-3 flex-grow-1">
                    <span class="input-group-text bg-transparent border-0 text-muted">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="search"
                           class="form-control bg-transparent border-0"
                           placeholder="Search name or barcode..."
                           value="<?= htmlspecialchars($search) ?>">
                    <?php if($search != ''): ?>
                        <a href="dashboard.php?page=products" class="btn btn-transparent border-0 text-muted">
                            <i class="fa-solid fa-times-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <button class="btn btn-primary rounded-pill px-5 py-3 py-sm-2 shadow-sm fw-bold" type="submit">Search</button>
            </div>
        </form>
    </div>
</div>


<!-- TABLE -->
<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-muted text-uppercase small">Inventory Items</h6>
        <span class="badge bg-light text-dark fw-normal"><?= count($products) ?> Total Products</span>
    </div>
    
    <?php if(count($products) > 0): ?>
        <div class="table-responsive table-responsive-stack">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4" style="width:80px">Image</th>
                        <th>Product Details</th>
                        <th>Pricing</th>
                        <th>Inventory</th>
                        <th>Category</th>
                        <th class="text-center">Actions</th>
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
                    $stockBadgeClass = 'badge-in-stock';
                    $stockText = 'In Stock';
                    if($p['stock'] <= 0) {
                        $stockBadgeClass = 'badge-out-stock';
                        $stockText = 'Out of Stock';
                    } elseif($p['stock'] <= 10) {
                        $stockBadgeClass = 'badge-low-stock';
                        $stockText = 'Low Stock';
                    }
                    ?>
                    
                    <tr>
                        <td class="ps-4" data-label="Image">
                            <div class="d-flex justify-content-end justify-content-md-start">
                                <img src="<?= htmlspecialchars(product_image_url($p['image_path'] ?? null)) ?>"
                                     alt="" class="img-preview-lg">
                            </div>
                        </td>
                        <td data-label="Product Details">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                            <small class="text-muted"><i class="fa-solid fa-barcode me-1"></i> <?= htmlspecialchars($p['barcode'] ?: 'No Barcode') ?></small>
                        </td>
                        <td data-label="Pricing">
                            <div class="fw-semibold text-primary">Rs. <?= number_format($p['price'], 2) ?></div>
                        </td>
                        <td data-label="Inventory">
                            <div class="d-flex align-items-center justify-content-end justify-content-md-start mb-1">
                                <span class="fw-bold me-2"><?= $p['stock'] ?></span>
                                <span class="stock-badge <?= $stockBadgeClass ?>"><?= $stockText ?></span>
                            </div>
                        </td>
                        <td data-label="Category">
                            <span class="badge bg-light text-muted border"><?= htmlspecialchars($catName ?: 'General') ?></span>
                        </td>
                        <td class="text-center pe-4" data-label="Actions">
                            <div class="action-group d-flex justify-content-center justify-content-md-center gap-3 py-2 py-md-0">
                                <?php if (!empty($p['barcode'])): ?>
                                    <a href="print_barcode.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-print btn-lg-touch" title="Print Barcode">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="product_create.php?id=<?= (int)$p['id'] ?>" class="btn btn-edit-sec btn-lg-touch" title="Edit Item">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <button type="button" class="btn btn-delete-sec btn-lg-touch" 
                                        onclick="checkAndDelete(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')" title="Delete Item">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-5 text-center">
            <i class="fa-solid fa-box-open fs-1 text-muted opacity-25 mb-3"></i>
            <h5 class="text-muted">No products found.</h5>
            <p class="text-muted small">Start by adding your first product using the "New Product" button above.</p>
        </div>
    <?php endif; ?>
</div>


<!-- Custom Modal for Delete Confirmation -->
<div id="deleteModal" class="modal">
    <div class="modal-content modern-modal-content">
        <div class="modal-header-modern">
            <div class="bg-danger-subtle rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px; height:60px;">
                <i class="fa-solid fa-triangle-exclamation text-danger fs-3"></i>
            </div>
            <h4 class="fw-bold text-dark">Confirm Delete</h4>
        </div>
        <div class="modal-body p-4 text-center">
            <p id="deleteMessage" class="text-muted fs-5">Are you sure you want to delete this product?</p>
            <div id="orderInfo" class="alert alert-warning border-0 small text-start" style="display: none;"></div>
        </div>
        <div class="modal-footer-modern pb-4">
            <button class="btn btn-light px-4 rounded-pill" onclick="closeModal()">Keep Item</button>
            <button id="confirmDeleteBtn" class="btn btn-danger px-4 rounded-pill shadow-sm" onclick="proceedDelete()">Delete Permanently</button>
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
            // Product has orders - show warning but allow soft delete
            messageDiv.innerHTML = `⚠️ Archive "${productName}"?`;
            orderInfoDiv.innerHTML = `
                <strong>Note:</strong> This product is used in ${data.order_count} order(s).<br>
                It will be hidden from the system but kept in records to preserve order history.
            `;
            orderInfoDiv.style.display = 'block';
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('btn-loading');
            confirmBtn.innerHTML = 'Yes, Archive';
            confirmBtn.style.backgroundColor = '#f59e0b'; // Warning color
        } else {
            // Product can be safely soft-deleted (it's new/unused)
            messageDiv.innerHTML = `⚠️ Remove "${productName}"?`;
            orderInfoDiv.style.display = 'none';
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('btn-loading');
            confirmBtn.innerHTML = 'Yes, Remove';
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
