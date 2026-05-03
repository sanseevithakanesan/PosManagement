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

<style>
    /* Modern UI Components */
    .income-header {
        background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
        padding: 25px;
        border-radius: 15px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(0, 184, 148, 0.15);
    }
    .modern-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    
    /* Metrics Upgrades */
    .stats-card-modern {
        padding: 24px;
        border-radius: 16px;
        color: white;
        position: relative;
        overflow: hidden;
        border: none;
    }
    .stats-card-modern::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }
    .bg-gradient-primary { background: linear-gradient(135deg, #0984e3 0%, #6c5ce7 100%); }
    .bg-gradient-success { background: linear-gradient(135deg, #00b894 0%, #55efc4 100%); }
    .bg-gradient-dark { background: linear-gradient(135deg, #2d3436 0%, #636e72 100%); }

    /* Modern Table & Badges */
    .status-badge-sale {
        font-size: 0.7rem;
        padding: 4px 12px;
        border-radius: 50px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-completed { background: #e0fdf4; color: #10b981; }
    
    .action-circle-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        transition: all 0.2s;
        border: none;
        margin: 0 2px;
    }
    .btn-view-sec { background: #f1f5f9; color: #64748b; }
    .btn-view-sec:hover { background: #00cec9; color: white; }
    .btn-delete-sec { background: #fef2f2; color: #ef4444; }
    .btn-delete-sec:hover { background: #ef4444; color: white; }

    /* Invoice Detail View */
    .invoice-container {
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .invoice-header-block {
        background: #f8fafc;
        border-bottom: 2px dashed #cbd5e1;
    }
    .info-label { font-size: 0.75rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; }
</style>

<div class="income-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <h3 class="mb-1 fw-bold"><i class="fa-solid fa-chart-line me-2"></i> Income</h3>
        <p class="mb-0 opacity-75">Revenue Overview and Transaction History</p>
    </div>
    <?php if(isset($_GET['view'])): ?>
         <a href="dashboard.php?page=income" class="btn btn-light btn-lg fw-bold px-4 rounded-4 shadow-sm">
            <i class="fa-solid fa-arrow-left me-2 text-primary"></i> Back
         </a>
    <?php endif; ?>
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

<!-- METRICS -->
<?php if(!isset($_GET['view'])): ?>
<div class="row g-4 mb-5">
    <div class="col-12 col-md-4">
        <div class="card stats-card-modern bg-gradient-primary shadow-sm h-100">
            <div class="info-label text-white opacity-75 mb-1"><i class="fa-solid fa-calendar-day me-1"></i> Today's Income</div>
            <h2 class="mb-0 fw-bold">Rs <?= number_format((float)$todayIncome, 2) ?></h2>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card stats-card-modern bg-gradient-success shadow-sm h-100">
            <div class="info-label text-white opacity-75 mb-1"><i class="fa-solid fa-calendar-check me-1"></i> This Month</div>
            <h2 class="mb-0 fw-bold">Rs <?= number_format((float)$monthIncome, 2) ?></h2>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card stats-card-modern bg-gradient-dark shadow-sm h-100">
            <div class="info-label text-white opacity-75 mb-1"><i class="fa-solid fa-vault me-1"></i> Lifetime Earnings</div>
            <h2 class="mb-0 fw-bold">Rs <?= number_format((float)$totalIncome, 2) ?></h2>
        </div>
    </div>
</div>

<!-- DATE FILTER FORM -->
<div class="card modern-card mb-4 bg-white border-0">
    <div class="card-body p-3 p-md-4">
        <form method="GET">
            <input type="hidden" name="page" value="income">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                <div class="fw-bold text-muted small text-uppercase"><i class="fa-solid fa-filter me-1"></i> Filter Period</div>
                <div class="d-flex flex-column flex-sm-row gap-2 flex-grow-1">
                    <div class="input-group input-group-lg flex-grow-1">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-calendar"></i></span>
                        <input type="date" name="start" class="form-control border-light" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="input-group input-group-lg flex-grow-1">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-arrow-right"></i></span>
                        <input type="date" name="end" class="form-control border-light" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-lg fw-bold px-4 shadow-sm flex-grow-1">Apply</button>
                    <a href="dashboard.php?page=income" class="btn btn-light btn-lg px-3 flex-grow-1">Clear</a>
                    <a href="dashboard.php?page=reports&pdf=1&type=income&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>" class="btn btn-danger btn-lg px-3 shadow-sm">
                        <i class="fa-solid fa-file-pdf"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- ORDERS LIST -->
<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="mb-0 fw-bold text-muted text-uppercase small">Recent Sale History</h6>
    </div>
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Transaction Date</th>
                    <th>Customer Name</th>
                    <th>Processed By</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Revenue</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $o): ?>
                <tr>
                    <td class="ps-4" data-label="Date">
                        <div class="d-flex flex-column">
                            <div class="fw-bold text-dark"><?= htmlspecialchars(date('d M Y', strtotime($o['created_at']))) ?></div>
                            <div class="small text-muted"><?= date('h:i A', strtotime($o['created_at'])) ?></div>
                        </div>
                    </td>
                    <td data-label="Customer">
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($o['customer_name'] ?? 'Walk-in Customer') ?></span>
                    </td>
                    <td data-label="Cashier">
                        <span class="badge bg-light text-muted border"><?= htmlspecialchars($o['cashier_name'] ?? 'System') ?></span>
                    </td>
                    <td data-label="Status">
                        <span class="status-badge-sale badge-completed">
                            <i class="fa-solid fa-circle-check me-1"></i><?= ucfirst($o['status']) ?>
                        </span>
                    </td>
                    <td data-label="Revenue">
                        <div class="fw-bold text-dark fs-5">Rs. <?= number_format($o['total_amount'], 2) ?></div>
                    </td>
                    <td class="text-end pe-4" data-label="Actions">
                        <div class="d-flex justify-content-center justify-content-md-end gap-3 py-2 py-md-0">
                            <a href="dashboard.php?page=income&view=<?= $o['id'] ?>" class="action-circle-btn btn-view-sec" style="width: 48px; height: 48px; font-size: 1.2rem;" title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="dashboard.php?page=income&delete=<?= $o['id'] ?>" class="action-circle-btn btn-delete-sec" style="width: 48px; height: 48px; font-size: 1.2rem;"
                               onclick="return confirm('Delete record and refund stock?')" title="Delete Record">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($orders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="fa-solid fa-receipt fs-1 opacity-25 mb-3 d-block"></i>
                        Zero transactions recorded for this period.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>


<!-- VIEW ORDER DETAILS -->
<div class="card modern-card overflow-hidden invoice-container p-0">
    <div class="invoice-header-block p-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center mb-2">
                    <div class="bg-primary text-white rounded-circle p-2 me-3" style="width:45px; height:45px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-receipt fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">Transaction Receipt</h4>
                        <span class="text-muted small">ID REF: #SOC-<?= str_pad($viewOrder['id'], 5, '0', STR_PAD_LEFT) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-5 text-md-end">
                <span class="status-badge-sale badge-completed d-inline-block px-4 py-2">
                    <i class="fa-solid fa-check-double me-2"></i><?= strtoupper($viewOrder['status']) ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="card-body p-4">
        <div class="row mb-5 g-4">
            <div class="col-sm-6 col-md-3">
                <div class="info-label mb-1">Customer / Client</div>
                <div class="fw-bold text-dark fs-5"><?= htmlspecialchars($viewOrder['customer_name'] ?? 'Walk-in Customer') ?></div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="info-label mb-1">Processed By</div>
                <div class="fw-bold text-dark"><?= htmlspecialchars($viewOrder['cashier_name'] ?? 'System Operator') ?></div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="info-label mb-1">Billing Date</div>
                <div class="fw-bold text-dark"><?= htmlspecialchars(date('d F Y', strtotime($viewOrder['created_at']))) ?></div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="info-label mb-1">Record Time</div>
                <div class="fw-bold text-dark"><?= date('h:i A', strtotime($viewOrder['created_at'])) ?></div>
            </div>
        </div>
        
        <h6 class="fw-bold text-muted text-uppercase small mb-3">Itemized Breakdown</h6>
        <div class="table-responsive table-responsive-stack">
            <table class="table table-hover align-middle border-top border-light">
                <thead class="bg-light bg-opacity-50">
                    <tr>
                        <th class="border-0">Item</th>
                        <th class="text-center border-0">Qty</th>
                        <th class="text-end border-0">Price</th>
                        <th class="text-end border-0 pe-4">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($details as $item): ?>
                    <tr>
                        <td data-label="Item">
                            <div class="fw-bold text-dark text-end text-md-start"><?= htmlspecialchars($item['name']) ?></div>
                        </td>
                        <td class="text-center" data-label="Qty">
                            <div class="d-flex justify-content-end justify-content-md-center">
                                <span class="badge bg-light text-dark fw-bold border">x<?= $item['quantity'] ?></span>
                            </div>
                        </td>
                        <td class="text-end text-muted" data-label="Price">Rs. <?= number_format($item['price'], 2) ?></td>
                        <td class="text-end fw-bold text-dark pe-4" data-label="Total">Rs. <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold pt-4 border-0 d-none d-md-table-cell">
                            <span class="text-muted text-uppercase small">Grand Total</span>
                        </td>
                        <td class="text-end pt-4 border-0 pe-4" data-label="Grand Total">
                            <h4 class="mb-0 fw-bold text-primary">Rs. <?= number_format($viewOrder['total_amount'], 2) ?></h4>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
