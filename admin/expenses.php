<?php
require_once "../database/db.php";

/* INITIALIZE DB SCHEMA FOR EXPENSES */
$pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_category VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    account_id INT DEFAULT NULL,
    expense_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$payment_accounts = $pdo->query("SELECT * FROM payment_accounts ORDER BY account_name ASC")->fetchAll();

/* ADD/UPDATE LOGIC */
$edit = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

if(isset($_POST['save'])){
    try {
        if(!empty($_POST['id'])) {
            $pdo->prepare("UPDATE expenses SET expense_category=?, description=?, amount=?, account_id=? WHERE id=?")
                ->execute([$_POST['expense_category'], $_POST['description'], $_POST['amount'], $_POST['account_id'], $_POST['id']]);
            $_SESSION['message'] = "Expense updated successfully!";
        } else {
            // Optional: let them input historical date, but this keeps it simple with NOW() or we provide a date picker
            $pdo->prepare("INSERT INTO expenses(expense_category, description, amount, account_id) VALUES(?, ?, ?, ?)")
                ->execute([$_POST['expense_category'], $_POST['description'], $_POST['amount'], $_POST['account_id']]);
            $_SESSION['message'] = "Expense logged successfully!";
        }
        $_SESSION['message_type'] = "success";
    } catch(Exception $e) {
        $_SESSION['message'] = "Error saving expense.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=expenses");
    exit;
}

/* DELETE LOGIC */
if(isset($_GET['delete'])){
    try {
        $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$_GET['delete']]);
        $_SESSION['message'] = "Expense deleted successfully!";
        $_SESSION['message_type'] = "success";
    } catch(Exception $e) {
        $_SESSION['message'] = "Error deleting expense.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=expenses");
    exit;
}

/* FETCH METRICS */
$todayExp = $pdo->query("SELECT SUM(amount) as t FROM expenses WHERE DATE(expense_date) = CURDATE()")->fetchColumn() ?: 0;
$monthExp = $pdo->query("SELECT SUM(amount) as t FROM expenses WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())")->fetchColumn() ?: 0;
$totalExp = $pdo->query("SELECT SUM(amount) as t FROM expenses")->fetchColumn() ?: 0;

/* FETCH EXPENSES */
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';

$sqlExp = "SELECT e.*, pa.account_name FROM expenses e LEFT JOIN payment_accounts pa ON pa.id = e.account_id";
$params = [];

if ($start_date !== '' && $end_date !== '') {
    $sqlExp .= " WHERE DATE(expense_date) >= ? AND DATE(expense_date) <= ?";
    $params[] = $start_date;
    $params[] = $end_date;
}
$sqlExp .= " ORDER BY id DESC";

$stmtExp = $pdo->prepare($sqlExp);
$stmtExp->execute($params);
$expensesList = $stmtExp->fetchAll();

$categories = ['Rent', 'Salaries', 'Utilities', 'Maintenance', 'Office Supplies', 'Marketing', 'Taxes', 'Miscellaneous'];
?>


<style>
    /* Modern UI Components */
    .expenses-header {
        background: linear-gradient(135deg, #ff7675 0%, #d63031 100%);
        padding: 25px;
        border-radius: 15px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(214, 48, 49, 0.15);
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
    .bg-gradient-danger { background: linear-gradient(135deg, #d63031 0%, #ff7675 100%); }
    .bg-gradient-warning { background: linear-gradient(135deg, #fdcb6e 0%, #ffeaa7 100%); color: #2d3436 !important; }
    .bg-gradient-secondary { background: linear-gradient(135deg, #636e72 0%, #b2bec3 100%); }

    /* Modern Table & Badges */
    .cat-pill {
        font-size: 0.7rem;
        padding: 4px 12px;
        border-radius: 50px;
        font-weight: 700;
        text-transform: uppercase;
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }
    
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
    .btn-edit-exp { background: #eff6ff; color: #3b82f6; }
    .btn-edit-exp:hover { background: #3b82f6; color: white; }
    .btn-delete-exp { background: #fef2f2; color: #ef4444; }
    .btn-delete-exp:hover { background: #ef4444; color: white; }
</style>

<div class="expenses-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <h3 class="mb-1 fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Expenses</h3>
        <p class="mb-0 opacity-75">Outflow Management and Cost Tracking</p>
    </div>
    <a href="dashboard.php?page=expenses" class="btn btn-light btn-lg fw-bold px-4 rounded-4 shadow-sm <?= $edit ? '' : 'disabled opacity-50' ?>">
        <i class="fa-solid fa-plus-circle me-1 text-danger"></i> Log New Expense
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

<!-- METRICS -->
<div class="row g-4 mb-5">
    <div class="col-12 col-md-4">
        <div class="card stats-card-modern bg-gradient-danger shadow-sm h-100">
            <div class="small fw-bold text-uppercase opacity-75 mb-1 text-white">Today's Spent</div>
            <h2 class="mb-0 fw-bold">Rs <?= number_format((float)$todayExp, 2) ?></h2>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card stats-card-modern bg-gradient-warning shadow-sm h-100">
            <div class="small fw-bold text-uppercase opacity-75 mb-1">This Month's Spent</div>
            <h2 class="mb-0 fw-bold">Rs <?= number_format((float)$monthExp, 2) ?></h2>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card stats-card-modern bg-gradient-secondary shadow-sm h-100">
            <div class="small fw-bold text-uppercase opacity-75 mb-1 text-white">All Time Total</div>
            <h2 class="mb-0 fw-bold">Rs <?= number_format((float)$totalExp, 2) ?></h2>
        </div>
    </div>
</div>

<!-- FORM -->
<div class="card modern-card mb-5 border-0">
    <div class="card-body p-3 p-md-4">
        <h5 class="fw-bold mb-4 text-dark"><?= $edit ? '<i class="fa-solid fa-edit text-danger me-2"></i>Modify Expense' : '<i class="fa-solid fa-plus-circle text-danger me-2"></i>Log Expenditure' ?></h5>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            
            <div class="row g-4">
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold">Paid From Account *</label>
                    <select name="account_id" class="form-select form-select-lg fw-semibold border-light bg-light" required>
                        <option value="">Select Account...</option>
                        <?php foreach($payment_accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>" <?= (isset($edit['account_id']) && (int)$edit['account_id'] === (int)$acc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($acc['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold">Amount (Rs) *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-danger-subtle text-danger border-danger-subtle"><i class="fa-solid fa-coins"></i></span>
                        <input type="number" step="0.01" name="amount" class="form-control fw-bold border-danger-subtle text-danger" value="<?= htmlspecialchars($edit['amount'] ?? '') ?>" placeholder="0.00" required>
                    </div>
                </div>
                
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold">Category *</label>
                    <select name="expense_category" class="form-select form-select-lg fw-semibold border-light bg-light" required>
                        <option value="">Select Category...</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= (isset($edit['expense_category']) && $edit['expense_category'] === $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold">Purpose / Details *</label>
                    <input type="text" name="description" class="form-control form-control-lg border-light bg-light" value="<?= htmlspecialchars($edit['description'] ?? '') ?>" placeholder="e.g. Electricity Bill" required>
                </div>
            </div>

            <div class="mt-4 d-flex flex-column flex-sm-row gap-2">
                <button class="btn btn-danger btn-lg px-5 fw-bold shadow-sm" name="save">
                    <?= $edit ? '<i class="fa-solid fa-save me-2"></i>Update Log' : '<i class="fa-solid fa-check-circle me-2"></i>Record Spend' ?>
                </button>
                <?php if($edit): ?>
                    <a href="dashboard.php?page=expenses" class="btn btn-light btn-lg px-4 border-0">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>


<!-- DATE FILTER FORM -->
<div class="card modern-card mb-4 bg-white border-0">
    <div class="card-body p-3 p-md-4">
        <form method="GET">
            <input type="hidden" name="page" value="expenses">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                <div class="fw-bold text-muted small text-uppercase"><i class="fa-solid fa-filter me-1"></i> Filter Period</div>
                <div class="d-flex flex-column flex-sm-row gap-2 flex-grow-1">
                    <div class="input-group input-group-sm flex-grow-1">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-calendar"></i></span>
                        <input type="date" name="start" class="form-control border-light" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="input-group input-group-sm flex-grow-1">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-arrow-right"></i></span>
                        <input type="date" name="end" class="form-control border-light" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-danger btn-sm fw-bold px-3 shadow-sm flex-grow-1">Apply</button>
                    <a href="dashboard.php?page=expenses" class="btn btn-light btn-sm px-3 flex-grow-1 border-0">Clear</a>
                    <a href="dashboard.php?page=reports&pdf=1&type=expense&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>" class="btn btn-dark btn-sm fw-bold px-3 shadow-sm">
                        <i class="fa-solid fa-file-pdf"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- LIST -->
<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="mb-0 fw-bold text-muted text-uppercase small">Expenditure History</h6>
    </div>
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Logged Date</th>
                    <th>Spend Category</th>
                    <th>Account</th>
                    <th>Purpose / Details</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($expensesList as $ex): ?>
                <tr>
                    <td class="ps-4" data-label="Date">
                        <div class="d-flex flex-column">
                            <div class="fw-bold text-dark"><?= htmlspecialchars(date('d M Y', strtotime($ex['expense_date']))) ?></div>
                            <div class="small text-muted"><?= date('h:i A', strtotime($ex['expense_date'])) ?></div>
                        </div>
                    </td>
                    <td data-label="Category">
                        <span class="cat-pill"><?= htmlspecialchars($ex['expense_category']) ?></span>
                    </td>
                    <td data-label="Account">
                        <div class="small fw-bold text-muted"><?= htmlspecialchars($ex['account_name'] ?? 'N/A') ?></div>
                    </td>
                    <td data-label="Details">
                        <div class="text-dark fw-medium"><?= htmlspecialchars($ex['description']) ?></div>
                    </td>
                    <td data-label="Amount">
                        <div class="fw-bold text-danger fs-5">Rs. <?= number_format($ex['amount'], 2) ?></div>
                    </td>
                    <td class="text-end pe-4" data-label="Actions">
                        <div class="d-flex justify-content-center justify-content-md-end gap-3 py-2 py-md-0">
                            <a href="dashboard.php?page=expenses&edit=<?= $ex['id'] ?>" class="action-circle-btn btn-edit-exp" style="width: 48px; height: 48px; font-size: 1.2rem;" title="Edit Entry">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <a href="dashboard.php?page=expenses&delete=<?= $ex['id'] ?>" class="action-circle-btn btn-delete-exp" style="width: 48px; height: 48px; font-size: 1.2rem;"
                               onclick="return confirm('Permanently delete this expense log?')" title="Delete Entry">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($expensesList)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">
                        <i class="fa-solid fa-receipt fs-1 opacity-25 mb-3 d-block"></i>
                        No expenditure records found for this period.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
