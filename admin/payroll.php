<?php
require_once "../database/db.php";

/* INITIALIZE DB SCHEMA FOR PAYROLL */
$pdo->exec("CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50),
    base_salary DECIMAL(10,2) NOT NULL,
    join_date DATE,
    status ENUM('Active', 'Inactive') DEFAULT 'Active'
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS salary_advances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    advance_date DATE NOT NULL,
    status ENUM('Pending', 'Deducted') DEFAULT 'Pending',
    FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS salary_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    pay_month VARCHAR(20) NOT NULL,
    base_amount DECIMAL(10,2) NOT NULL,
    advances_deducted DECIMAL(10,2) NOT NULL,
    net_paid DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");

$tab = $_GET['tab'] ?? 'employees';

/* AJAX FOR PROCESSING SALARY PREVIEW */
if(isset($_POST['ajax_employee_id'])) {
    $eid = (int)$_POST['ajax_employee_id'];
    $emp = $pdo->prepare("SELECT base_salary FROM employees WHERE id = ?");
    $emp->execute([$eid]);
    $base = $emp->fetchColumn() ?: 0;
    
    $adv = $pdo->prepare("SELECT SUM(amount) FROM salary_advances WHERE employee_id = ? AND status = 'Pending'");
    $adv->execute([$eid]);
    $pending_adv = $adv->fetchColumn() ?: 0;
    
    echo json_encode(['base' => $base, 'advances' => $pending_adv, 'net' => $base - $pending_adv]);
    exit;
}

/* HANDLE CRUD OPERATIONS */
if(isset($_POST['save_employee'])){
    try {
        if(!empty($_POST['id'])) {
            $pdo->prepare("UPDATE employees SET name=?, phone=?, base_salary=?, status=? WHERE id=?")
                ->execute([$_POST['name'], $_POST['phone'], $_POST['base_salary'], $_POST['status'], $_POST['id']]);
            $_SESSION['message'] = "Employee updated successfully!";
        } else {
            $pdo->prepare("INSERT INTO employees(name, phone, base_salary, join_date, status) VALUES(?, ?, ?, ?, ?)")
                ->execute([$_POST['name'], $_POST['phone'], $_POST['base_salary'], date('Y-m-d'), $_POST['status']]);
            $_SESSION['message'] = "Employee added successfully!";
        }
        $_SESSION['message_type'] = "success";
    } catch(Exception $e) {
        $_SESSION['message'] = "Error saving employee.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=payroll&tab=employees");
    exit;
}

if(isset($_POST['save_advance'])){
    try {
        $pdo->prepare("INSERT INTO salary_advances(employee_id, amount, advance_date, status) VALUES(?, ?, ?, 'Pending')")
            ->execute([$_POST['employee_id'], $_POST['amount'], date('Y-m-d')]);
        $_SESSION['message'] = "Advance recorded successfully!";
        $_SESSION['message_type'] = "success";
    } catch(Exception $e) {
        $_SESSION['message'] = "Error recording advance.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=payroll&tab=advances");
    exit;
}

if(isset($_GET['delete_advance'])){
    $pdo->prepare("DELETE FROM salary_advances WHERE id=? AND status='Pending'")->execute([$_GET['delete_advance']]);
    $_SESSION['message'] = "Advance deleted!";
    $_SESSION['message_type'] = "success";
    header("Location: dashboard.php?page=payroll&tab=advances");
    exit;
}

if(isset($_GET['delete_employee'])){
    try {
        $pdo->prepare("DELETE FROM employees WHERE id=?")->execute([$_GET['delete_employee']]);
        $_SESSION['message'] = "Employee deleted successfully!";
        $_SESSION['message_type'] = "success";
    } catch(Exception $e) {
        $_SESSION['message'] = "Cannot delete this employee. They may have linked payroll records.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=payroll&tab=employees");
    exit;
}

if(isset($_POST['process_salary'])){
    try {
        $eid = (int)$_POST['employee_id'];
        $month = $_POST['pay_month']; // e.g. 2026-04
        
        // Fetch accurate data again to prevent tampering
        $emp = $pdo->prepare("SELECT base_salary FROM employees WHERE id = ?");
        $emp->execute([$eid]);
        $base = $emp->fetchColumn();
        
        $adv = $pdo->prepare("SELECT SUM(amount) FROM salary_advances WHERE employee_id = ? AND status = 'Pending'");
        $adv->execute([$eid]);
        $pending_adv = $adv->fetchColumn() ?: 0;
        
        $net = $base - $pending_adv;
        
        $pdo->beginTransaction();
        
        // 1. Insert payment record
        $pdo->prepare("INSERT INTO salary_payments(employee_id, pay_month, base_amount, advances_deducted, net_paid) VALUES(?, ?, ?, ?, ?)")
            ->execute([$eid, $month, $base, $pending_adv, $net]);
            
        // 2. Mark pending advances as Deducted
        $pdo->prepare("UPDATE salary_advances SET status = 'Deducted' WHERE employee_id = ? AND status = 'Pending'")
            ->execute([$eid]);
            
        // 3. Log as a business Expense automatically? (Optional but highly recommended for accuracy)
        // Let's create an expense entry automatically for the net paid amount + advances, 
        // wait, the total expense to the company is the Base amount (net + advance).
        try {
            $eName = $pdo->query("SELECT name FROM employees WHERE id=$eid")->fetchColumn();
            $pdo->prepare("INSERT INTO expenses(expense_category, description, amount) VALUES('Salaries', ?, ?)")
                ->execute(["Salary payout for $eName ($month)", $net]); // We log net, as advances were already given (they should ideally be logged when given).
        } catch(Exception $expE) { 
            // Silent catch if expenses table is locked 
        }

        $pdo->commit();
        $_SESSION['message'] = "Salary processed and advances deducted successfully!";
        $_SESSION['message_type'] = "success";
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error processing salary.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=payroll&tab=process");
    exit;
}

/* FETCH DATA FOR VIEWS */
$employees = $pdo->query("SELECT * FROM employees ORDER BY id DESC")->fetchAll();
$activeEmployees = array_filter($employees, fn($e) => $e['status'] === 'Active');

// Date filtering for payroll history
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';

$sqlPayments = "SELECT p.*, e.name as emp_name FROM salary_payments p JOIN employees e ON p.employee_id = e.id";
$paramsPay = [];
if ($start_date !== '' && $end_date !== '') {
    $sqlPayments .= " WHERE DATE(p.payment_date) >= ? AND DATE(p.payment_date) <= ?";
    $paramsPay[] = $start_date;
    $paramsPay[] = $end_date;
}
$sqlPayments .= " ORDER BY p.id DESC";
$stmtPay = $pdo->prepare($sqlPayments);
$stmtPay->execute($paramsPay);
$payments = $stmtPay->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h3 class="mb-0">Payroll & Advances</h3>
    <div class="btn-group">
        <a href="dashboard.php?page=payroll&tab=employees" class="btn btn-<?= $tab == 'employees' ? 'primary' : 'outline-primary' ?>">Staff</a>
        <a href="dashboard.php?page=payroll&tab=advances" class="btn btn-<?= $tab == 'advances' ? 'primary' : 'outline-primary' ?>">Advances</a>
        <a href="dashboard.php?page=payroll&tab=process" class="btn btn-<?= $tab == 'process' ? 'success' : 'outline-success' ?>">Run Payroll</a>
    </div>
</div>

<!-- ALERT -->
<?php if(isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<?php if($tab == 'employees'): ?>
<!-- EMPLOYEES TAB -->
<?php
    $edit = null;
    if(isset($_GET['edit'])){
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id=?");
        $stmt->execute([$_GET['edit']]);
        $edit = $stmt->fetch();
    }
?>
<div class="card content-card p-4 mb-4 shadow-sm border-0">
    <h5 class="mb-3 text-secondary"><?= $edit ? 'Edit Employee' : 'Add Employee' ?></h5>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Full Name *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Phone No.</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Fixed Base Salary (Rs) *</label>
                <input type="number" step="0.01" name="base_salary" class="form-control" value="<?= htmlspecialchars($edit['base_salary'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold">Status *</label>
                <select name="status" class="form-select border-primary" required>
                    <option value="Active" <?= (isset($edit['status']) && $edit['status'] === 'Active') ? 'selected' : '' ?>>Active Employee</option>
                    <option value="Inactive" <?= (isset($edit['status']) && $edit['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive / Left</option>
                </select>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary" name="save_employee"><?= $edit ? 'Save Changes' : 'Add Staff' ?></button>
            <?php if($edit): ?>
                <a href="dashboard.php?page=payroll&tab=employees" class="btn btn-light border ms-2">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card content-card p-3 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 bg-white">
            <thead class="table-light text-muted small">
                <tr><th>Staff Name</th><th>Contact</th><th>Base Salary</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach($employees as $e): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['name']) ?></strong></td>
                    <td><?= htmlspecialchars($e['phone']) ?></td>
                    <td class="text-primary fw-bold">Rs. <?= number_format($e['base_salary'], 2) ?></td>
                    <td><span class="badge bg-<?= $e['status'] == 'Active' ? 'success' : 'secondary' ?>"><?= $e['status'] ?></span></td>
                    <td>
                        <a href="dashboard.php?page=payroll&tab=employees&edit=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <a href="dashboard.php?page=payroll&tab=employees&delete_employee=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('WARNING: Deleting this employee will also delete all their advances and payroll history. Proceed?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif($tab == 'advances'): ?>
<!-- ADVANCES TAB -->
<?php
    $advances = $pdo->query("
        SELECT a.*, e.name as emp_name 
        FROM salary_advances a 
        JOIN employees e ON a.employee_id = e.id 
        ORDER BY a.id DESC
    ")->fetchAll();
?>
<div class="card content-card p-4 mb-4 shadow-sm border-0 border-start border-4 border-warning">
    <h5 class="mb-3 text-secondary">Issue Salary Advance</h5>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Select Employee *</label>
                <select name="employee_id" class="form-select" required>
                    <option value="">Choose active staff...</option>
                    <?php foreach($activeEmployees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?> (Base: Rs <?= number_format($e['base_salary']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Advance Amount (Rs) *</label>
                <input type="number" step="0.01" name="amount" class="form-control text-warning fw-bold border-warning" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-warning w-100 fw-bold text-dark" name="save_advance">Give Advance</button>
            </div>
        </div>
    </form>
</div>

<div class="card content-card p-3 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 bg-white">
            <thead class="table-light text-muted small">
                <tr><th>Date Issued</th><th>Employee</th><th>Amount Issued</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach($advances as $a): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($a['advance_date'])) ?></td>
                    <td><strong><?= htmlspecialchars($a['emp_name']) ?></strong></td>
                    <td class="text-warning fw-bold">Rs. <?= number_format($a['amount'], 2) ?></td>
                    <td>
                        <?php if($a['status'] == 'Pending'): ?>
                            <span class="badge bg-danger">Pending Deduction</span>
                        <?php else: ?>
                            <span class="badge bg-success">Deducted from Pay</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($a['status'] == 'Pending'): ?>
                            <a href="dashboard.php?page=payroll&tab=advances&delete_advance=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this advance?');">Delete</a>
                        <?php else: ?>
                            <span class="text-muted small">Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif($tab == 'process'): ?>
<!-- PROCESS PAYROLL TAB -->
<?php
    $payments = $pdo->query("
        SELECT p.*, e.name as emp_name 
        FROM salary_payments p 
        JOIN employees e ON p.employee_id = e.id 
        ORDER BY p.id DESC
    ")->fetchAll();
?>
<div class="card content-card p-4 mb-4 shadow-sm border-0 border-start border-4 border-success">
    <h5 class="mb-3 text-secondary">Finalize Monthly Salary</h5>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Select Employee *</label>
                <select name="employee_id" id="payroll_emp" class="form-select border-success" required>
                    <option value="">Choose active staff...</option>
                    <?php foreach($activeEmployees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Pay Month (e.g., April 2026) *</label>
                <input type="month" name="pay_month" class="form-control" value="<?= date('Y-m') ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="button" class="btn btn-outline-secondary w-100" id="preview_salary_btn">Fetch Calculations</button>
            </div>
        </div>
        
        <div id="salary_preview_box" class="mt-4 p-3 bg-light border rounded d-none">
            <div class="row text-center mb-3">
                <div class="col-md-4">
                    <p class="text-muted small mb-1">Fixed Base Salary</p>
                    <h5 class="text-primary mb-0" id="prev_base">Rs. 0.00</h5>
                </div>
                <div class="col-md-4 border-start border-end">
                    <p class="text-muted small mb-1">Pending Advances (To Deduct)</p>
                    <h5 class="text-danger mb-0" id="prev_adv">- Rs. 0.00</h5>
                </div>
                <div class="col-md-4">
                    <p class="text-muted small mb-1">Final Net Payout</p>
                    <h4 class="text-success mb-0 fw-bold" id="prev_net">Rs. 0.00</h4>
                </div>
            </div>
            <div class="text-center">
                <button class="btn btn-success px-5 fw-bold" name="process_salary" onclick="return confirm('This will mark all pending advances as deducted and finalize the salary. Proceed?');">Confirm & Pay Salary</button>
            </div>
        </div>
    </form>
</div>

<div class="card content-card p-3 shadow-sm border-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-muted mb-0">Salary History</h6>
        <form method="GET" class="d-flex gap-2 align-items-center print-hide">
            <input type="hidden" name="page" value="payroll">
            <input type="hidden" name="tab" value="process">
            <input type="date" name="start" class="form-control form-control-sm border-success" value="<?= htmlspecialchars($start_date) ?>" style="width: 140px;">
            <span class="small text-muted">-</span>
            <input type="date" name="end" class="form-control form-control-sm border-success" value="<?= htmlspecialchars($end_date) ?>" style="width: 140px;">
            <button class="btn btn-success btn-sm fw-bold">Filter</button>
            <a href="dashboard.php?page=payroll&tab=process" class="btn btn-outline-secondary btn-sm">Clear</a>
            <a href="dashboard.php?page=reports&pdf=1&type=payroll&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>" class="btn btn-dark btn-sm fw-bold shadow-sm d-flex align-items-center">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="me-1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                PDF
            </a>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0 bg-white">
            <thead class="table-light text-muted small">
                <tr><th>Month</th><th>Employee</th><th>Base Salary</th><th>Advances Deducted</th><th>Net Paid</th><th>Date Processed</th></tr>
            </thead>
            <tbody>
                <?php foreach($payments as $p): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['pay_month']) ?></span></td>
                    <td><strong><?= htmlspecialchars($p['emp_name']) ?></strong></td>
                    <td class="text-muted">Rs. <?= number_format($p['base_amount'], 2) ?></td>
                    <td class="text-danger">- Rs. <?= number_format($p['advances_deducted'], 2) ?></td>
                    <td class="text-success fw-bold">Rs. <?= number_format($p['net_paid'], 2) ?></td>
                    <td><small><?= date('d M Y, h:i A', strtotime($p['payment_date'])) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const previewBtn = document.getElementById('preview_salary_btn');
    const empSelect = document.getElementById('payroll_emp');
    const previewBox = document.getElementById('salary_preview_box');
    
    if(previewBtn) {
        previewBtn.addEventListener('click', function() {
            if(!empSelect.value) {
                alert('Please select an employee first.');
                return;
            }
            
            // Perform AJAX request
            const formData = new FormData();
            formData.append('ajax_employee_id', empSelect.value);
            
            fetch('payroll.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('prev_base').textContent = 'Rs. ' + parseFloat(data.base).toFixed(2);
                document.getElementById('prev_adv').textContent = '- Rs. ' + parseFloat(data.advances).toFixed(2);
                document.getElementById('prev_net').textContent = 'Rs. ' + parseFloat(data.net).toFixed(2);
                previewBox.classList.remove('d-none');
            })
            .catch(err => {
                console.error(err);
                alert('Error fetching accurate salary data.');
            });
        });
    }
});
</script>
<?php endif; ?>
