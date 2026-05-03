<?php
require_once "../database/db.php";

// Logic for Add/Edit/Delete
if (isset($_POST['save_account'])) {
    $name = trim($_POST['account_name']);
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($name !== '') {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE payment_accounts SET account_name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                $_SESSION['message'] = "Account updated successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO payment_accounts (account_name) VALUES (?)");
                $stmt->execute([$name]);
                $_SESSION['message'] = "Account created successfully.";
            }
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: dashboard.php?page=accounts");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM payment_accounts WHERE id = ?")->execute([$id]);
        $_SESSION['message'] = "Account deleted.";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['message'] = "Cannot delete account (it may be linked to payments/expenses).";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: dashboard.php?page=accounts");
    exit;
}

$editAccount = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM payment_accounts WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editAccount = $stmt->fetch();
}

// Global Filter for Transactions
$selected_acc_id = isset($_GET['acc_id']) ? (int)$_GET['acc_id'] : 0;

// Fetch all accounts and calculate balances
$accounts = $pdo->query("SELECT * FROM payment_accounts ORDER BY account_name ASC")->fetchAll();

$accountData = [];
$total_net_balance = 0;
$total_global_income = 0;
$total_global_expense = 0;

foreach ($accounts as $acc) {
    $id = $acc['id'];
    
    // Income from Billing (Payments)
    $incomeStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE account_id = ?");
    $incomeStmt->execute([$id]);
    $income = (float)$incomeStmt->fetchColumn() ?: 0;
    
    // Outflow from Expenses
    $expenseStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE account_id = ?");
    $expenseStmt->execute([$id]);
    $expenses = (float)$expenseStmt->fetchColumn() ?: 0;
    
    $net = $income - $expenses;
    $total_net_balance += $net;
    $total_global_income += $income;
    $total_global_expense += $expenses;

    $accountData[] = [
        'id' => $id,
        'name' => $acc['account_name'],
        'income' => $income,
        'expenses' => $expenses,
        'balance' => $net
    ];
}

// Combined Transaction History (Income & Expenses)
$historySql = "
    (SELECT 'income' as type, amount, paid_at as date, 'Customer Payment' as detail, account_id 
     FROM payments)
    UNION ALL
    (SELECT 'expense' as type, amount, expense_date as date, description as detail, account_id 
     FROM expenses)
    ORDER BY date DESC LIMIT 30
";

// If filtered
if ($selected_acc_id > 0) {
    $historySql = "
        SELECT * FROM (
            (SELECT 'income' as type, amount, paid_at as date, 'Customer Payment' as detail, account_id FROM payments WHERE account_id = $selected_acc_id)
            UNION ALL
            (SELECT 'expense' as type, amount, expense_date as date, description as detail, account_id FROM expenses WHERE account_id = $selected_acc_id)
        ) combined
        ORDER BY date DESC LIMIT 50
    ";
}

$history = $pdo->query($historySql)->fetchAll(PDO::FETCH_ASSOC);

// Map account names for history display
$accMap = [];
foreach($accounts as $a) $accMap[$a['id']] = $a['account_name'];
?>

<style>
    .accounts-header {
        background: linear-gradient(135deg, #0984e3 0%, #2980b9 100%);
        padding: 40px 30px;
        border-radius: 24px;
        color: white;
        margin-bottom: 35px;
        position: relative;
        overflow: hidden;
    }
    .accounts-header::after {
        content: '\f19c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: -20px;
        bottom: -20px;
        font-size: 150px;
        opacity: 0.1;
        transform: rotate(-15deg);
    }
    
    .stats-card-premium {
        background: white;
        border-radius: 20px;
        padding: 20px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        transition: transform 0.3s ease;
    }
    .stats-card-premium:hover { transform: translateY(-5px); }

    .account-pill {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        border-radius: 100px;
        background: #f1f5f9;
        color: #475569;
        font-weight: 600;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.2s;
        border: 1px solid transparent;
    }
    .account-pill.active {
        background: #3b82f6;
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    .account-pill:hover:not(.active) {
        background: #e2e8f0;
    }

    .txn-row {
        transition: background 0.2s;
        border-bottom: 1px solid #f1f5f9;
    }
    .txn-row:hover { background: #f8fafc; }

    .icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .icon-income { background: #dcfce7; color: #15803d; }
    .icon-expense { background: #fee2e2; color: #b91c1c; }

    .balance-number { font-family: 'Outfit', sans-serif; font-weight: 800; letter-spacing: -0.5px; }
</style>

<div class="accounts-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
    <div>
        <h2 class="fw-bold mb-1">Financial Oversight</h2>
        <p class="mb-0 opacity-75 fs-5">Monitor liquidity and transaction flow in real-time</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="dashboard.php?page=accounts" class="btn btn-light btn-lg fw-bold rounded-4 shadow-sm px-4">
            <i class="fa-solid fa-plus-circle me-1 text-primary"></i>New Account
        </a>
        <a href="dashboard.php?page=reports" class="btn btn-primary btn-lg fw-bold rounded-4 shadow-sm px-4 border border-white border-opacity-25">
            <i class="fa-solid fa-chart-pie me-1"></i>Full Report
        </a>
    </div>
</div>

<!-- GLOBAL METRICS -->
<div class="row g-4 mb-5">
    <div class="col-12 col-md-4">
        <div class="stats-card-premium">
            <div class="text-muted small fw-bold text-uppercase mb-2">Net Liquidity</div>
            <div class="balance-number fs-2 text-dark">₹ <?= number_format($total_net_balance, 2) ?></div>
            <div class="mt-2 small text-muted">Combined balance of all accounts</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stats-card-premium border-start border-4 border-success">
            <div class="text-muted small fw-bold text-uppercase mb-2 text-success">Total Inflow</div>
            <div class="balance-number fs-2 text-success">+₹ <?= number_format($total_global_income, 2) ?></div>
            <div class="mt-2 small text-muted">Cumulative billing revenue</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stats-card-premium border-start border-4 border-danger">
            <div class="text-muted small fw-bold text-uppercase mb-2 text-danger">Total Outflow</div>
            <div class="balance-number fs-2 text-danger">-₹ <?= number_format($total_global_expense, 2) ?></div>
            <div class="mt-2 small text-muted">Total business expenditures</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT: Account Cards & Form -->
    <div class="col-12 col-xl-4">
        <!-- New Account Form -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-muted text-uppercase small"><?= $editAccount ? 'Update Account' : 'Register New Account' ?></h6>
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $editAccount['id'] ?? '' ?>">
                    <div class="mb-3">
                        <input type="text" name="account_name" class="form-control form-control-lg border-light bg-light fw-semibold" 
                               placeholder="Account Name..." value="<?= htmlspecialchars($editAccount['account_name'] ?? '') ?>" required>
                    </div>
                    <button type="submit" name="save_account" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm">
                        <?= $editAccount ? 'Confirm Changes' : 'Initialize Account' ?>
                    </button>
                    <?php if($editAccount): ?>
                        <a href="dashboard.php?page=accounts" class="btn btn-link w-100 mt-2 text-muted text-decoration-none small">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Account List -->
        <div class="row g-3">
            <?php foreach($accountData as $data): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden <?= $selected_acc_id == $data['id'] ? 'border-start border-4 border-primary' : '' ?>">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0"><?= htmlspecialchars($data['name']) ?></h5>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown"><i class="fa-solid fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg">
                                    <li><a class="dropdown-item" href="dashboard.php?page=accounts&edit=<?= $data['id'] ?>">Edit</a></li>
                                    <li><a class="dropdown-item text-danger" href="dashboard.php?page=accounts&delete=<?= $data['id'] ?>" onclick="return confirm('Delete account?')">Delete</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="balance-number fs-3 text-primary mb-3">₹ <?= number_format($data['balance'], 2) ?></div>
                        <div class="d-flex gap-3">
                            <div class="small">
                                <div class="text-muted smallest text-uppercase fw-bold">Inflow</div>
                                <div class="text-success fw-bold">+<?= number_format($data['income'], 0) ?></div>
                            </div>
                            <div class="small">
                                <div class="text-muted smallest text-uppercase fw-bold">Outflow</div>
                                <div class="text-danger fw-bold">-<?= number_format($data['expenses'], 0) ?></div>
                            </div>
                            <a href="dashboard.php?page=accounts&acc_id=<?= $data['id'] ?>" class="btn btn-primary btn-sm ms-auto rounded-3 px-3">
                                History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT: Transaction History -->
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 p-4 pb-0">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Transaction History</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="dashboard.php?page=accounts" class="account-pill <?= $selected_acc_id == 0 ? 'active' : '' ?>">All Activity</a>
                        <?php foreach($accounts as $acc): ?>
                            <a href="dashboard.php?page=accounts&acc_id=<?= $acc['id'] ?>" class="account-pill <?= $selected_acc_id == $acc['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($acc['account_name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0 mt-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light text-muted small fw-bold text-uppercase">
                            <tr>
                                <th class="ps-4">Type</th>
                                <th>Account</th>
                                <th>Details / Remarks</th>
                                <th>Date & Time</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $txn): ?>
                            <tr class="txn-row">
                                <td class="ps-4">
                                    <div class="icon-circle <?= $txn['type'] == 'income' ? 'icon-income' : 'icon-expense' ?>">
                                        <i class="fa-solid <?= $txn['type'] == 'income' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($accMap[$txn['account_id']] ?? 'Unknown') ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($txn['detail']) ?></div>
                                    <div class="smallest text-muted text-uppercase"><?= $txn['type'] ?> transaction</div>
                                </td>
                                <td>
                                    <div class="small fw-bold"><?= date('d M, Y', strtotime($txn['date'])) ?></div>
                                    <div class="smallest text-muted"><?= date('h:i A', strtotime($txn['date'])) ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="fw-bold fs-6 <?= $txn['type'] == 'income' ? 'text-success' : 'text-danger' ?>">
                                        <?= $txn['type'] == 'income' ? '+' : '-' ?> ₹ <?= number_format($txn['amount'], 2) ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($history)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="opacity-25 mb-3"><i class="fa-solid fa-receipt fs-1"></i></div>
                                        <p class="text-muted">No transactions found for this selection.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
