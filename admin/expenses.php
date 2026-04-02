<?php
require_once "../database/db.php";

/* INITIALIZE DB SCHEMA FOR EXPENSES */
$pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_category VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    expense_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

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
            $pdo->prepare("UPDATE expenses SET expense_category=?, description=?, amount=? WHERE id=?")
                ->execute([$_POST['expense_category'], $_POST['description'], $_POST['amount'], $_POST['id']]);
            $_SESSION['message'] = "Expense updated successfully!";
        } else {
            // Optional: let them input historical date, but this keeps it simple with NOW() or we provide a date picker
            $pdo->prepare("INSERT INTO expenses(expense_category, description, amount) VALUES(?, ?, ?)")
                ->execute([$_POST['expense_category'], $_POST['description'], $_POST['amount']]);
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

$sqlExp = "SELECT * FROM expenses";
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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h3 class="mb-0">Operating Expenses</h3>
    <a href="dashboard.php?page=expenses" class="btn btn-outline-primary <?= $edit ? '' : 'd-none' ?>">+ Log New Expense</a>
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
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card bg-danger bg-opacity-75 text-white h-100 p-3 border-0 shadow-sm rounded-3">
            <h6 class="opacity-75 mb-1 d-flex align-items-center gap-2">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                Today's Spent
            </h6>
            <h3 class="mb-0">Rs <?= number_format((float)$todayExp, 2) ?></h3>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card bg-warning text-dark h-100 p-3 border-0 shadow-sm rounded-3">
            <h6 class="opacity-75 mb-1 d-flex align-items-center gap-2">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                This Month's Spent
            </h6>
            <h3 class="mb-0">Rs <?= number_format((float)$monthExp, 2) ?></h3>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card bg-secondary text-white h-100 p-3 border-0 shadow-sm rounded-3">
            <h6 class="opacity-75 mb-1 d-flex align-items-center gap-2">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                All Time Expenses
            </h6>
            <h3 class="mb-0">Rs <?= number_format((float)$totalExp, 2) ?></h3>
        </div>
    </div>
</div>

<!-- FORM -->
<div class="card content-card p-4 mb-4 shadow-sm border-0">
    <h5 class="mb-3 text-secondary"><?= $edit ? 'Edit Expense Record' : 'Log New Expense' ?></h5>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Amount (Rs) *</label>
                <input type="number" step="0.01" name="amount" class="form-control" style="font-size: 1.1rem; font-weight:bold; color:var(--bs-danger);" value="<?= htmlspecialchars($edit['amount'] ?? '') ?>" placeholder="0.00" required>
            </div>
            
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Category *</label>
                <select name="expense_category" class="form-select border-danger" required>
                    <option value="">Select Category...</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= (isset($edit['expense_category']) && $edit['expense_category'] === $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-6">
                <label class="form-label text-muted small fw-bold">Description / Purpose *</label>
                <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($edit['description'] ?? '') ?>" placeholder="e.g., Shop electricity bill for May, Staff bonus, etc." required>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top">
            <button class="btn btn-danger px-4 fw-bold" name="save">
                <?= $edit ? 'Save Changes' : 'Record Expense' ?>
            </button>
            <?php if($edit): ?>
                <a href="dashboard.php?page=expenses" class="btn btn-light px-4 ms-2 text-dark border">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DATE FILTER FORM -->
<div class="card content-card p-3 mb-4 shadow-sm border-0 bg-light">
    <form method="GET" class="row g-2 align-items-center">
        <input type="hidden" name="page" value="expenses">
        <div class="col-auto">
            <label class="form-label text-muted small fw-bold mb-0">From</label>
        </div>
        <div class="col-sm-3 col-md-2">
            <input type="date" name="start" class="form-control form-control-sm border-danger" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label text-muted small fw-bold mb-0">To</label>
        </div>
        <div class="col-sm-3 col-md-2">
            <input type="date" name="end" class="form-control form-control-sm border-danger" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-danger btn-sm fw-bold">Filter</button>
            <a href="dashboard.php?page=expenses" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
    </form>
</div>

<!-- LIST -->
<div class="card content-card p-3 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 bg-white">
            <thead class="table-light text-muted small text-uppercase">
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($expensesList as $ex): ?>
                <tr>
                    <td><strong>EX-<?= $ex['id'] ?></strong></td>
                    <td><small><?= htmlspecialchars(date('d F Y, H:i', strtotime($ex['expense_date']))) ?></small></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($ex['expense_category']) ?></span></td>
                    <td class="text-secondary"><?= htmlspecialchars($ex['description']) ?></td>
                    <td class="text-end fw-bold text-danger">Rs. <?= number_format($ex['amount'], 2) ?></td>
                    <td class="text-center">
                        <a href="dashboard.php?page=expenses&edit=<?= $ex['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                        <a href="dashboard.php?page=expenses&delete=<?= $ex['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this expense?');">Del</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($expensesList)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No expenses logged yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
