<?php
require_once "../database/db.php";

/* DELETE/REFUND ORDER */
if(isset($_GET['delete'])){
    $pdo->beginTransaction();
    try {
        $orderId = (int)$_GET['delete'];
        
        // Fetch order items to refund stock (optional, doing it based on logic)
        $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $items->execute([$orderId]);
        
        while($row = $items->fetch()) {
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
                ->execute([$row['quantity'], $row['product_id']]);
        }
        
        // Delete order records safely
        $pdo->prepare("DELETE FROM payments WHERE order_id=?")->execute([$orderId]);
        $pdo->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$orderId]);
        $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$orderId]);
        
        $pdo->commit();
        $_SESSION['message'] = "Order deleted and stock refunded successfully!";
        $_SESSION['message_type'] = "success";
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Failed to delete order.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=income");
    exit;
}

/* FETCH METRICS */
$todayIncome = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
$monthIncome = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn() ?: 0;
$totalIncome = $pdo->query("SELECT SUM(total_amount) as total FROM orders")->fetchColumn() ?: 0;

/* FETCH ORDERS */
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';

$sqlOrders = "
    SELECT o.*, c.name as customer_name, u.name as cashier_name 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON o.user_id = u.id
";
$params = [];

if ($start_date !== '' && $end_date !== '') {
    $sqlOrders .= " WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?";
    $params[] = $start_date;
    $params[] = $end_date;
}
$sqlOrders .= " ORDER BY o.id DESC";

$stmtOrders = $pdo->prepare($sqlOrders);
$stmtOrders->execute($params);
$orders = $stmtOrders->fetchAll();

/* IF VIEWING DETAILS */
$details = [];
$viewOrder = [];
if(isset($_GET['view'])){
    $vid = (int)$_GET['view'];
    $stmt = $pdo->prepare("SELECT o.*, c.name as customer_name, u.name as cashier_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$vid]);
    $viewOrder = $stmt->fetch();
    
    $itemStmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $itemStmt->execute([$vid]);
    $details = $itemStmt->fetchAll();
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h3 class="mb-0">Income & Sales History</h3>
    <?php if(isset($_GET['view'])): ?>
         <a href="dashboard.php?page=income" class="btn btn-secondary btn-sm">&larr; Back to Income</a>
    <?php endif; ?>
</div>

<!-- ALERT -->
<?php if(isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<!-- METRICS -->
<?php if(!isset($_GET['view'])): ?>
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card bg-primary text-white h-100 p-3 border-0 shadow-sm rounded-3">
            <h6 class="opacity-75 mb-1 d-flex align-items-center gap-2">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Today's Income
            </h6>
            <h3 class="mb-0">Rs <?= number_format((float)$todayIncome, 2) ?></h3>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card bg-success text-white h-100 p-3 border-0 shadow-sm rounded-3">
            <h6 class="opacity-75 mb-1 d-flex align-items-center gap-2">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                This Month
            </h6>
            <h3 class="mb-0">Rs <?= number_format((float)$monthIncome, 2) ?></h3>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card bg-dark text-white h-100 p-3 border-0 shadow-sm rounded-3">
            <h6 class="opacity-75 mb-1 d-flex align-items-center gap-2">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                All Time Income
            </h6>
            <h3 class="mb-0">Rs <?= number_format((float)$totalIncome, 2) ?></h3>
        </div>
    </div>
</div>

<!-- DATE FILTER FORM -->
<div class="card content-card p-3 mb-4 shadow-sm border-0 bg-light">
    <form method="GET" class="row g-2 align-items-center">
        <input type="hidden" name="page" value="income">
        <div class="col-auto">
            <label class="form-label text-muted small fw-bold mb-0">From</label>
        </div>
        <div class="col-sm-3 col-md-2">
            <input type="date" name="start" class="form-control form-control-sm border-primary" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label text-muted small fw-bold mb-0">To</label>
        </div>
        <div class="col-sm-3 col-md-2">
            <input type="date" name="end" class="form-control form-control-sm border-primary" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary btn-sm fw-bold">Filter</button>
            <a href="dashboard.php?page=income" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- ORDERS LIST -->
<div class="card content-card p-3 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 bg-white">
            <thead class="table-light text-muted small text-uppercase">
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Cashier</th>
                    <th>Status</th>
                    <th class="text-end">Total Amount</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $o): ?>
                <tr>
                    <td><small><?= htmlspecialchars(date('d M Y, h:i A', strtotime($o['created_at']))) ?></small></td>
                    <td><?= htmlspecialchars($o['customer_name'] ?? 'Walk-in') ?></td>
                    <td><span class="text-muted"><small><?= htmlspecialchars($o['cashier_name'] ?? 'System') ?></small></span></td>
                    <td><span class="badge bg-success"><?= ucfirst($o['status']) ?></span></td>
                    <td class="text-end fw-bold text-primary">Rs. <?= number_format($o['total_amount'], 2) ?></td>
                    <td class="text-center">
                        <a href="dashboard.php?page=income&view=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <a href="dashboard.php?page=income&delete=<?= $o['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this sale permanently and refund stock?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($orders)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No income/sales records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>

<!-- VIEW ORDER DETAILS -->
<div class="card content-card p-4 shadow-sm border-0">
    <h5 class="border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
        Order Details #<?= $viewOrder['id'] ?>
    </h5>
    
    <div class="row mb-4 bg-light p-3 rounded mx-0">
        <div class="col-md-6 mb-2 mb-md-0">
            <p class="mb-1 text-secondary small"><strong>Customer:</strong> <span class="text-dark"><?= htmlspecialchars($viewOrder['customer_name'] ?? 'Walk-in Customer') ?></span></p>
            <p class="mb-1 text-secondary small"><strong>Cashier:</strong> <span class="text-dark"><?= htmlspecialchars($viewOrder['cashier_name'] ?? 'N/A') ?></span></p>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-1 text-secondary small"><strong>Date:</strong> <span class="text-dark"><?= htmlspecialchars(date('d F Y, h:i A', strtotime($viewOrder['created_at']))) ?></span></p>
            <p class="mb-1 text-secondary small"><strong>Status:</strong> <span class="badge bg-success shadow-sm"><?= ucfirst($viewOrder['status']) ?></span></p>
        </div>
    </div>
    
    <h6 class="mb-3">Purchased Items</h6>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Item Name</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Unit Price (Rs)</th>
                    <th class="text-end">Line Total (Rs)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($details as $item): ?>
                <tr>
                    <td><span class="fw-semibold text-dark"><?= htmlspecialchars($item['name']) ?></span></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end text-muted"><?= number_format($item['price'], 2) ?></td>
                    <td class="text-end fw-bold"><?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-light">
                    <td colspan="3" class="text-end fw-bold text-uppercase pt-3">Grand Total:</td>
                    <td class="text-end fw-bold text-primary fs-5 pt-3">Rs. <?= number_format($viewOrder['total_amount'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>
