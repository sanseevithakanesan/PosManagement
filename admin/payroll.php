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
    $month = $_POST['ajax_month'] ?? date('Y-m');
    
    $emp = $pdo->prepare("SELECT base_salary FROM employees WHERE id = ?");
    $emp->execute([$eid]);
    $base = $emp->fetchColumn() ?: 0;
    
    $adv = $pdo->prepare("SELECT SUM(amount) FROM salary_advances WHERE employee_id = ? AND status = 'Pending'");
    $adv->execute([$eid]);
    $pending_adv = $adv->fetchColumn() ?: 0;
    
    // Check if already paid for this month
    $paid = $pdo->prepare("SELECT COUNT(*) FROM salary_payments WHERE employee_id = ? AND pay_month = ?");
    $paid->execute([$eid, $month]);
    $already_paid = $paid->fetchColumn() > 0;
    
    echo json_encode([
        'base' => $base, 
        'advances' => $pending_adv, 
        'net' => $base - $pending_adv,
        'already_paid' => $already_paid
    ]);
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
        
        // Check for duplicate payment
        $chk = $pdo->prepare("SELECT id FROM salary_payments WHERE employee_id = ? AND pay_month = ?");
        $chk->execute([$eid, $month]);
        if($chk->fetch()){
            throw new Exception("Salary for this month has already been processed for this employee.");
        }
        
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


<style>
    /* Modern UI Components */
    .payroll-header {
        background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
        padding: 25px;
        border-radius: 15px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(108, 92, 231, 0.15);
    }
    .modern-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .modern-pills-nav {
        background: #f1f5f9;
        padding: 6px;
        border-radius: 50px;
        display: inline-flex;
        gap: 4px;
    }
    .modern-pills-nav .btn {
        border-radius: 50px;
        padding: 8px 24px;
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        transition: all 0.2s;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .modern-pills-nav .btn-active { background: white; color: #6c5ce7 !important; transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .modern-pills-nav .btn-inactive { color: #64748b !important; background: transparent; }
    .modern-pills-nav .btn-inactive:hover { background: rgba(255,255,255,0.5); }

    /* Badges */
    .status-pill {
        font-size: 0.7rem;
        padding: 4px 12px;
        border-radius: 50px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .pill-active { background: #e0fdf4; color: #10b981; }
    .pill-inactive { background: #f8fafc; color: #94a3b8; }
    .pill-pending { background: #fffbeb; color: #f59e0b; }
    .pill-deducted { background: #eff6ff; color: #3b82f6; }

    /* Salary Slip Preview */
    .salary-slip-preview {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 30px;
        position: relative;
        transition: all 0.3s ease;
        min-height: 400px;
    }
    .salary-slip-preview.already-paid {
        border-color: #ef4444;
        background: #fef2f2;
    }
    .slip-metric-box {
        padding: 20px;
        background: #f8fafc;
        border-radius: 12px;
        text-align: center;
        transition: all 0.2s;
    }
    .slip-metric-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .slip-label { font-size: 0.75rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; }

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
    .btn-edit-sec { background: #eff6ff; color: #3b82f6; }
    .btn-edit-sec:hover { background: #3b82f6; color: white; }
    .btn-delete-sec { background: #fef2f2; color: #ef4444; }
    .btn-delete-sec:hover { background: #ef4444; color: white; }

    /* Empty State */
    .payroll-empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #f8fafc;
        border: 2px dashed #e2e8f0;
        border-radius: 20px;
        color: #94a3b8;
    }
    .payroll-empty-state i { font-size: 3rem; margin-bottom: 20px; opacity: 0.5; }
</style>

<div class="payroll-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-4">
    <div>
        <h3 class="mb-1 fw-bold"><i class="fa-solid fa-users-gear me-2"></i> Payroll</h3>
        <p class="mb-0 opacity-75">Workforce Compensation and Salary Schedules</p>
    </div>
    <div class="modern-pills-nav overflow-auto flex-nowrap shadow-sm">
        <a href="dashboard.php?page=payroll&tab=employees" class="btn <?= $tab == 'employees' ? 'btn-active' : 'btn-inactive' ?>">Staff</a>
        <a href="dashboard.php?page=payroll&tab=advances" class="btn <?= $tab == 'advances' ? 'btn-active' : 'btn-inactive' ?>">Advances</a>
        <a href="dashboard.php?page=payroll&tab=process" class="btn <?= $tab == 'process' ? 'btn-active' : 'btn-inactive' ?>">Run Payroll</a>
    </div>
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
<div class="card modern-card mb-5 border-0">
    <div class="card-body p-3 p-md-4">
        <h5 class="fw-bold mb-4 text-dark"><?= $edit ? '<i class="fa-solid fa-user-edit text-primary me-2"></i>Modify Profile' : '<i class="fa-solid fa-user-plus text-primary me-2"></i>Onboard Staff' ?></h5>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            <div class="row g-4">
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label text-muted small fw-bold">Full Name *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-user text-muted"></i></span>
                        <input type="text" name="name" class="form-control border-0 bg-light" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label text-muted small fw-bold">Phone</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-phone text-muted"></i></span>
                        <input type="text" name="phone" class="form-control border-0 bg-light" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label text-muted small fw-bold">Base Salary (Rs) *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-primary-subtle border-0 text-primary"><i class="fa-solid fa-money-bill-wave"></i></span>
                        <input type="number" step="0.01" name="base_salary" class="form-control border-0 bg-light fw-bold text-primary" value="<?= htmlspecialchars($edit['base_salary'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label text-muted small fw-bold">Status *</label>
                    <select name="status" class="form-select form-select-lg border-0 bg-light fw-bold" required>
                        <option value="Active" <?= (isset($edit['status']) && $edit['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= (isset($edit['status']) && $edit['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 d-flex flex-column flex-sm-row gap-2">
                <button class="btn btn-primary btn-lg px-5 fw-bold shadow-sm" name="save_employee">
                    <?= $edit ? '<i class="fa-solid fa-save me-2"></i>Update' : '<i class="fa-solid fa-check-circle me-2"></i>Register' ?>
                </button>
                <?php if($edit): ?>
                    <a href="dashboard.php?page=payroll&tab=employees" class="btn btn-light btn-lg px-4 border-0">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="mb-0 fw-bold text-muted text-uppercase small">Staff Directory</h6>
    </div>
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Employee</th>
                    <th>Contact</th>
                    <th>Salary</th>
                    <th class="text-center">Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($employees as $e): ?>
                <tr>
                    <td class="ps-4" data-label="Employee">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 me-3 d-none d-md-flex" style="width:40px;height:40px;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-user-circle fs-5"></i>
                            </div>
                            <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($e['name']) ?></div>
                        </div>
                    </td>
                    <td data-label="Contact">
                        <span class="text-muted"><i class="fa-solid fa-phone-flip me-2 opacity-50 d-none d-md-inline"></i><?= htmlspecialchars($e['phone'] ?: 'N/A') ?></span>
                    </td>
                    <td data-label="Salary">
                        <span class="fw-bold text-primary fs-6">Rs. <?= number_format($e['base_salary'], 2) ?></span>
                    </td>
                    <td data-label="Status">
                        <div class="d-flex justify-content-end justify-content-md-center">
                            <span class="status-pill <?= $e['status'] == 'Active' ? 'pill-active' : 'pill-inactive' ?>">
                                <?= $e['status'] ?>
                            </span>
                        </div>
                    </td>
                    <td class="text-end pe-4" data-label="Actions">
                        <div class="d-flex justify-content-center justify-content-md-end gap-3 py-2 py-md-0">
                            <a href="dashboard.php?page=payroll&tab=employees&edit=<?= $e['id'] ?>" class="action-circle-btn btn-edit-sec" style="width: 48px; height: 48px; font-size: 1.2rem;" title="Edit Profile">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <a href="dashboard.php?page=payroll&tab=employees&delete_employee=<?= $e['id'] ?>" class="action-circle-btn btn-delete-sec" style="width: 48px; height: 48px; font-size: 1.2rem;"
                               onclick="return confirm('WARNING: Permanently delete this employee and all related history?')" title="Delete Profile">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($employees)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">No registered staff found.</td></tr>
                <?php endif; ?>
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
<div class="card modern-card mb-5 border-start border-4 border-warning shadow-sm border-0">
    <div class="card-body p-3 p-md-4">
        <h5 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-hand-holding-dollar text-warning me-2"></i> Issue Advance</h5>
        <form method="POST">
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <label class="form-label text-muted small fw-bold">Beneficiary *</label>
                    <select name="employee_id" class="form-select form-select-lg border-0 bg-light fw-semibold" required>
                        <option value="">Select Staff...</option>
                        <?php foreach($activeEmployees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label text-muted small fw-bold">Amount (Rs) *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-warning-subtle border-0 text-warning"><i class="fa-solid fa-coins"></i></span>
                        <input type="number" step="0.01" name="amount" class="form-control border-0 bg-light fw-bold text-dark" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-warning btn-lg px-5 fw-bold py-3 shadow-sm w-100 w-sm-auto" name="save_advance">
                    <i class="fa-solid fa-share-from-square me-2"></i>Disburse Advance
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="mb-0 fw-bold text-muted text-uppercase small">Recent Advance Disbursements</h6>
    </div>
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Date</th>
                    <th>Staff Member</th>
                    <th>Amount</th>
                    <th class="text-center">Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($advances as $a): ?>
                <tr>
                    <td class="ps-4" data-label="Date">
                        <div class="fw-bold text-dark"><?= date('d M Y', strtotime($a['advance_date'])) ?></div>
                    </td>
                    <td data-label="Staff">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($a['emp_name']) ?></div>
                    </td>
                    <td data-label="Amount">
                        <span class="fw-bold text-warning fs-6">Rs. <?= number_format($a['amount'], 2) ?></span>
                    </td>
                    <td data-label="Status">
                        <div class="d-flex justify-content-end justify-content-md-center">
                            <span class="status-pill <?= $a['status'] == 'Pending' ? 'pill-pending' : 'pill-deducted' ?>">
                                <i class="fa-solid <?= $a['status'] == 'Pending' ? 'fa-clock' : 'fa-check-circle' ?> me-1"></i><?= $a['status'] == 'Pending' ? 'Outstanding' : 'Deducted' ?>
                            </span>
                        </div>
                    </td>
                    <td class="text-end pe-4" data-label="Actions">
                        <div class="d-flex justify-content-center justify-content-md-end py-2 py-md-0">
                            <?php if($a['status'] == 'Pending'): ?>
                                <a href="dashboard.php?page=payroll&tab=advances&delete_advance=<?= $a['id'] ?>" class="btn btn-lg btn-outline-danger px-4 fw-bold rounded-pill" onclick="return confirm('Revoke this pending advance disbursement?');">
                                    <i class="fa-solid fa-rotate-left me-1"></i>Revoke
                                </a>
                            <?php else: ?>
                                <span class="text-muted small italic"><i class="fa-solid fa-lock me-1"></i> Audited</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($advances)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">No advance disbursement records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php elseif($tab == 'process'): ?>
<!-- PROCESS PAYROLL TAB -->
<div class="row g-4 mb-5">
    <div class="col-lg-4">
        <div class="card modern-card border-0 shadow-sm sticky-top" style="top: 20px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-calculator text-success me-2"></i> Payroll Engine</h5>
                <form method="POST" id="salary_process_form">
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">Select Staff Member</label>
                        <select name="employee_id" id="payroll_emp" class="form-select form-select-lg border-0 bg-light fw-bold" required>
                            <option value="">Choose Employee...</option>
                            <?php foreach($activeEmployees as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">Payment Month</label>
                        <input type="month" name="pay_month" id="payroll_month" class="form-control form-control-lg border-0 bg-light fw-bold" value="<?= date('Y-m') ?>" required>
                    </div>
                    <div class="alert alert-info border-0 small mb-0">
                        <i class="fa-solid fa-circle-info me-2"></i> Select an employee to automatically calculate earnings and deductions for the chosen month.
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div id="salary_preview_box" class="salary-slip-preview shadow-sm h-100 d-flex flex-column justify-content-center">
            <!-- Initial State -->
            <div id="preview_empty" class="payroll-empty-state">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <h5 class="fw-bold text-dark">Awaiting Selection</h5>
                <p class="mb-0">Please select a staff member to generate a salary preview.</p>
            </div>

            <!-- Loading State -->
            <div id="preview_loading" class="text-center d-none py-5">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted fw-bold">Calculating payroll data...</p>
            </div>

            <!-- Result State -->
            <div id="preview_content" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom dashed">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark" id="prev_name_display">Staff Name</h5>
                        <p class="text-muted small mb-0" id="prev_month_display">Month</p>
                    </div>
                    <div id="status_badge_container">
                        <div class="badge bg-success bg-opacity-10 text-success px-3 py-2 fw-bold">READY TO DISBURSE</div>
                    </div>
                </div>
                
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="slip-metric-box">
                            <div class="slip-label">Base Salary</div>
                            <h4 class="text-dark fw-bold mb-0" id="prev_base">Rs. 0.00</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="slip-metric-box">
                            <div class="slip-label text-danger">Deductions</div>
                            <h4 class="text-danger fw-bold mb-0" id="prev_adv">- Rs. 0.00</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="slip-metric-box bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="slip-label text-success">Net Payout</div>
                            <h3 class="text-success fw-bold mb-0" id="prev_net">Rs. 0.00</h3>
                        </div>
                    </div>
                </div>

                <div id="deduction_details" class="mb-4 d-none">
                    <h6 class="small fw-bold text-muted text-uppercase mb-3">Deduction Breakdown</h6>
                    <div class="bg-light rounded-3 p-3">
                        <div class="d-flex justify-content-between small">
                            <span>Pending Advances</span>
                            <span class="fw-bold text-danger" id="prev_adv_detail">Rs. 0.00</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-auto text-center" id="action_button_container">
                    <button form="salary_process_form" class="btn btn-success btn-lg px-5 fw-bold py-3 shadow w-100" name="process_salary" 
                            onclick="return confirm('Are you sure? This will permanently log this payout and mark all pending advances as deducted.');">
                        <i class="fa-solid fa-check-double me-2"></i>Authorize & Log Payout
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-muted text-uppercase small">Compensation Record Log</h6>
            <form method="GET" class="d-flex gap-2 align-items-center print-hide">
                <input type="hidden" name="page" value="payroll">
                <input type="hidden" name="tab" value="process">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-calendar"></i></span>
                    <input type="date" name="start" class="form-control border-light" value="<?= htmlspecialchars($start_date) ?>" style="width: 140px;">
                    <input type="date" name="end" class="form-control border-light" value="<?= htmlspecialchars($end_date) ?>" style="width: 140px;">
                </div>
                <button class="btn btn-dark btn-sm fw-bold px-3 shadow-sm">Filter</button>
                <a href="dashboard.php?page=payroll&tab=process" class="btn btn-light btn-sm"><i class="fa-solid fa-refresh"></i></a>
                <a href="dashboard.php?page=reports&pdf=1&type=payroll&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>" class="btn btn-danger btn-sm fw-bold px-3 shadow-sm">
                    <i class="fa-solid fa-file-pdf me-1"></i> PDF
                </a>
            </form>
        </div>
    </div>
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Pay Month</th>
                    <th>Staff Member</th>
                    <th>Gross Base</th>
                    <th class="text-danger">Deductions</th>
                    <th class="text-success">Net Paid</th>
                    <th class="text-end pe-4">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payments as $p): ?>
                <tr>
                    <td class="ps-4" data-label="Month">
                        <span class="badge bg-light border text-dark fw-bold px-3 py-2"><?= htmlspecialchars($p['pay_month']) ?></span>
                    </td>
                    <td data-label="Beneficiary">
                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($p['emp_name']) ?></div>
                    </td>
                    <td data-label="Base Gross" class="text-muted">Rs. <?= number_format($p['base_amount'], 2) ?></td>
                    <td data-label="Deductions" class="text-danger fw-semibold">- Rs. <?= number_format($p['advances_deducted'], 2) ?></td>
                    <td data-label="Net Paid">
                        <span class="fw-bold text-success fs-6">Rs. <?= number_format($p['net_paid'], 2) ?></span>
                    </td>
                    <td class="text-end pe-4 text-muted small" data-label="Processed">
                        <div><?= date('d M Y', strtotime($p['payment_date'])) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($payments)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">Zero compensation records found in the log.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const empSelect = document.getElementById('payroll_emp');
    const monthInput = document.getElementById('payroll_month');
    
    const previewBox = document.getElementById('salary_preview_box');
    const previewEmpty = document.getElementById('preview_empty');
    const previewLoading = document.getElementById('preview_loading');
    const previewContent = document.getElementById('preview_content');

    function updatePreview() {
        const eid = empSelect.value;
        const month = monthInput.value;

        if(!eid) {
            previewEmpty.classList.remove('d-none');
            previewLoading.classList.add('d-none');
            previewContent.classList.add('d-none');
            previewBox.classList.remove('already-paid');
            return;
        }

        previewEmpty.classList.add('d-none');
        previewLoading.classList.remove('d-none');
        previewContent.classList.add('d-none');
        previewBox.classList.remove('already-paid');

        const formData = new FormData();
        formData.append('ajax_employee_id', eid);
        formData.append('ajax_month', month);

        fetch('payroll.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            previewLoading.classList.add('d-none');
            previewContent.classList.remove('d-none');

            document.getElementById('prev_name_display').textContent = empSelect.options[empSelect.selectedIndex].text;
            document.getElementById('prev_month_display').textContent = 'Payroll for ' + month;
            
            document.getElementById('prev_base').textContent = 'Rs. ' + parseFloat(data.base).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('prev_adv').textContent = '- Rs. ' + parseFloat(data.advances).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('prev_net').textContent = 'Rs. ' + parseFloat(data.net).toLocaleString(undefined, {minimumFractionDigits: 2});
            
            // Deductions detail
            if(parseFloat(data.advances) > 0) {
                document.getElementById('deduction_details').classList.remove('d-none');
                document.getElementById('prev_adv_detail').textContent = 'Rs. ' + parseFloat(data.advances).toLocaleString(undefined, {minimumFractionDigits: 2});
            } else {
                document.getElementById('deduction_details').classList.add('d-none');
            }

            const statusBadgeContainer = document.getElementById('status_badge_container');
            const actionButtonContainer = document.getElementById('action_button_container');

            if(data.already_paid) {
                previewBox.classList.add('already-paid');
                statusBadgeContainer.innerHTML = '<div class="badge bg-danger px-3 py-2 fw-bold"><i class="fa-solid fa-circle-check me-1"></i> ALREADY DISBURSED</div>';
                actionButtonContainer.innerHTML = '<div class="alert alert-danger border-0 mb-0 fw-bold text-center"><i class="fa-solid fa-triangle-exclamation me-2"></i> This staff member has already been paid for the selected month.</div>';
            } else {
                statusBadgeContainer.innerHTML = '<div class="badge bg-success bg-opacity-10 text-success px-3 py-2 fw-bold">READY TO DISBURSE</div>';
                actionButtonContainer.innerHTML = `<button form="salary_process_form" class="btn btn-success btn-lg px-5 fw-bold py-3 shadow w-100" name="process_salary" 
                            onclick="return confirm('Are you sure? This will permanently log this payout and mark all pending advances as deducted.');">
                        <i class="fa-solid fa-check-double me-2"></i>Authorize & Log Payout
                    </button>`;
            }
        })
        .catch(err => {
            console.error(err);
            previewLoading.classList.add('d-none');
            previewEmpty.classList.remove('d-none');
            previewEmpty.innerHTML = '<i class="fa-solid fa-circle-exclamation text-danger fs-1"></i><h5 class="fw-bold text-danger mt-3">Error</h5><p>Could not fetch payroll data.</p>';
        });
    }

    if(empSelect) empSelect.addEventListener('change', updatePreview);
    if(monthInput) monthInput.addEventListener('change', updatePreview);
});
</script>
<?php endif; ?>
