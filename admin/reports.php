<?php
require_once "../database/db.php";

// Set default date range to current month
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

try {
    // 1. Total Income (Sales)
    $stmtIncome = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
    $stmtIncome->execute([$start_date, $end_date]);
    $total_income = (float)($stmtIncome->fetchColumn() ?: 0);

    // 2. Total Purchases (Cost of Goods)
    $stmtPurchases = $pdo->prepare("SELECT SUM(total_cost) FROM purchases WHERE DATE(purchase_date) >= ? AND DATE(purchase_date) <= ? AND status = 'Received'");
    $stmtPurchases->execute([$start_date, $end_date]);
    $total_purchases = (float)($stmtPurchases->fetchColumn() ?: 0);

    // 3. Total Expenses (Operations)
    $stmtExpenses = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE NOT (expense_category = 'Salaries' AND description LIKE 'Salary payout for %') AND DATE(expense_date) >= ? AND DATE(expense_date) <= ?");
    $stmtExpenses->execute([$start_date, $end_date]);
    $total_expenses = (float)($stmtExpenses->fetchColumn() ?: 0);

    // 4. Total Payroll (Salaries Paid)
    $stmtPayroll = $pdo->prepare("SELECT SUM(net_paid) FROM salary_payments WHERE DATE(payment_date) >= ? AND DATE(payment_date) <= ?");
    $stmtPayroll->execute([$start_date, $end_date]);
    $total_payroll = (float)($stmtPayroll->fetchColumn() ?: 0);

    // Current Inventory Valuation (Global, not date dependent)
    $stockValue = $pdo->query("SELECT SUM(stock * price) FROM products WHERE stock > 0 AND deleted_at IS NULL")->fetchColumn() ?: 0;
    
} catch(Exception $e) {
    $total_income = $total_purchases = $total_expenses = $total_payroll = $stockValue = 0;
}

// Calculate Net Profit
$total_outings = $total_purchases + $total_expenses + $total_payroll;
$net_profit = $total_income - $total_outings;
$profit_class = $net_profit >= 0 ? 'text-success' : 'text-danger';

?>


<style>
    /* Modern UI Components */
    .reports-header {
        background: linear-gradient(135deg, #4834d4 0%, #686de0 100%);
        padding: 25px;
        border-radius: 15px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(72, 52, 212, 0.15);
    }
    .modern-card {
        border-radius: 15px;
        border: none;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    
    /* Metrics Upgrades */
    .report-stat-card {
        padding: 24px;
        border-radius: 16px;
        color: white;
        position: relative;
        overflow: hidden;
        border: none;
    }
    .report-stat-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }
    .bg-grad-blue { background: linear-gradient(135deg, #4834d4 0%, #686de0 100%); }
    .bg-grad-orange { background: linear-gradient(135deg, #f0932b 0%, #ffbe76 100%); }
    .bg-grad-red { background: linear-gradient(135deg, #eb4d4b 0%, #ff7979 100%); }
    .bg-grad-teal { background: linear-gradient(135deg, #22a6b3 0%, #7ed6df 100%); }

    /* Statement Styling */
    .statement-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
    }
    .statement-row {
        padding: 15px 0;
        border-bottom: 1px dashed #e2e8f0;
    }
    .statement-row:last-child { border-bottom: none; }
    
    .export-trigger {
        width: 30px;
        height: 30px;
        background: rgba(255,255,255,0.2);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        float: right;
        transition: all 0.2s;
    }
    .export-trigger:hover { background: white; color: black; transform: translateY(-2px); }

    @media print {
        /* Hide navigation and non-essential elements for printing */
        body { background-color: white !important; }
        .admin-sidebar, .topbar-glass, .print-hide, .btn-print, .offcanvas { display: none !important; }
        .col-lg-10 { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
        main { padding: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
        .modern-card { box-shadow: none !important; border: 1px solid #eee !important; }
        .page-break { page-break-before: always; }
        .reports-header { background: #eee !important; color: black !important; border: 1px solid #ccc; box-shadow: none; }
    }
</style>

<div class="reports-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 print-hide">
    <div>
        <h3 class="mb-1 fw-bold"><i class="fa-solid fa-chart-line me-2"></i> Reports</h3>
        <p class="mb-0 opacity-75">Business Intelligence & Performance</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-light btn-lg fw-bold px-4 rounded-4 shadow-sm w-100 w-sm-auto">
            <i class="fa-solid fa-print me-2 text-dark"></i> Print
        </button>
    </div>
</div>


<!-- DATE FILTER FORM -->
<div class="card modern-card mb-5 border-0 print-hide">
    <div class="card-body p-3 p-md-4">
        <form method="GET" class="row g-4">
            <input type="hidden" name="page" value="reports">
            <div class="col-12 col-md-4">
                <label class="form-label text-muted small fw-bold">From *</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-calendar text-primary"></i></span>
                    <input type="date" name="start" class="form-control border-0 bg-light fw-semibold" value="<?= htmlspecialchars($start_date) ?>" required>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label text-muted small fw-bold">To *</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light border-0"><i class="fa-solid fa-calendar-check text-primary"></i></span>
                    <input type="date" name="end" class="form-control border-0 bg-light fw-semibold" value="<?= htmlspecialchars($end_date) ?>" required>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <button class="btn btn-primary btn-lg w-100 fw-bold shadow-sm py-3"><i class="fa-solid fa-sync me-2"></i>Generate</button>
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <a href="dashboard.php?page=reports&start=<?= date('Y-m-d') ?>&end=<?= date('Y-m-d') ?>" class="btn btn-light border-0 fw-bold text-muted small px-4 py-2 rounded-pill">Today</a>
                    <a href="dashboard.php?page=reports&start=<?= date('Y-m-01') ?>&end=<?= date('Y-m-t') ?>" class="btn btn-light border-0 fw-bold text-muted small px-4 py-2 rounded-pill">This Month</a>
                    <a href="dashboard.php?page=reports&start=<?= date('Y-01-01') ?>&end=<?= date('Y-12-31') ?>" class="btn btn-light border-0 fw-bold text-muted small px-4 py-2 rounded-pill">This Year</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- PRINT HEADER (ONLY VISIBLE ON PRINT) -->
<div class="d-none d-print-block mb-4 text-center">
    <h2 class="fw-bold">Business Financial Report</h2>
    <p class="text-muted mb-0">Reporting Period: <strong><?= date('d M Y', strtotime($start_date)) ?></strong> to <strong><?= date('d M Y', strtotime($end_date)) ?></strong></p>
    <hr>
</div>


<h5 class="text-dark fw-bold mb-4 d-print-block"><i class="fa-solid fa-folder-open text-primary me-2"></i> Financial Summaries <span class="text-muted fs-6 fw-normal">(<?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?>)</span></h5>

<!-- METRICS HIGHLIGHTS -->
<div class="row g-4 mb-5">
    <!-- Revenue -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card report-stat-card bg-grad-blue shadow-sm h-100">
            <a href="dashboard.php?page=reports&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>&pdf=1&type=income" class="export-trigger print-hide" title="Export PDF">
                <i class="fa-solid fa-file-pdf"></i>
            </a>
            <div class="small fw-bold text-uppercase opacity-75 mb-1">Total Sales Income</div>
            <h2 class="mb-0 fw-bold">Rs. <?= number_format($total_income, 2) ?></h2>
        </div>
    </div>
    <!-- Purchases -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card report-stat-card bg-grad-orange shadow-sm h-100">
            <a href="dashboard.php?page=reports&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>&pdf=1&type=purchase" class="export-trigger print-hide" title="Export PDF">
                <i class="fa-solid fa-file-pdf"></i>
            </a>
            <div class="small fw-bold text-uppercase opacity-75 mb-1">Cost of Goods</div>
            <h2 class="mb-0 fw-bold">Rs. <?= number_format($total_purchases, 2) ?></h2>
        </div>
    </div>
    <!-- Expenses -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card report-stat-card bg-grad-red shadow-sm h-100">
            <a href="dashboard.php?page=reports&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>&pdf=1&type=expense" class="export-trigger print-hide" title="Export PDF">
                <i class="fa-solid fa-file-pdf"></i>
            </a>
            <div class="small fw-bold text-uppercase opacity-75 mb-1">Operating Expenses</div>
            <h2 class="mb-0 fw-bold">Rs. <?= number_format($total_expenses, 2) ?></h2>
        </div>
    </div>
    <!-- Payroll -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card report-stat-card bg-grad-teal shadow-sm h-100">
            <a href="dashboard.php?page=reports&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>&pdf=1&type=payroll" class="export-trigger print-hide" title="Export PDF">
                <i class="fa-solid fa-file-pdf"></i>
            </a>
            <div class="small fw-bold text-uppercase opacity-75 mb-1">Payroll Salaries</div>
            <h2 class="mb-0 fw-bold">Rs. <?= number_format($total_payroll, 2) ?></h2>
        </div>
    </div>
</div>


<!-- PROFIT AND LOSS MASTER SUMMARY -->
<div class="card modern-card mb-5 overflow-hidden border-0">
    <div class="bg-dark text-white p-3 p-md-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-receipt me-2 text-warning"></i> P&L Statement</h5>
        <div class="small fw-bold opacity-75">ACCRUAL BASIS SUMMARY</div>
    </div>
    <div class="p-3 p-md-5 bg-white">
        <div class="mx-auto" style="max-width: 700px;">
            <div class="statement-row d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <div class="fw-bold text-muted text-uppercase small">Item Description</div>
                <div class="fw-bold text-muted text-uppercase small">Amount</div>
            </div>
            
            <div class="statement-row d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-1">
                <div class="text-dark fw-bold">Gross Sales Revenue</div>
                <div class="text-primary fw-bold fs-4">Rs. <?= number_format($total_income, 2) ?></div>
            </div>
            
            <div class="mt-3">
                <div class="statement-row d-flex flex-column flex-sm-row justify-content-between align-items-sm-center bg-light bg-opacity-50 p-3 rounded-4 mb-2 gap-1">
                    <div class="text-muted small fw-bold text-uppercase">Purchases</div>
                    <div class="text-danger fw-bold">- Rs. <?= number_format($total_purchases, 2) ?></div>
                </div>
                <div class="statement-row d-flex flex-column flex-sm-row justify-content-between align-items-sm-center bg-light bg-opacity-50 p-3 rounded-4 mb-2 gap-1">
                    <div class="text-muted small fw-bold text-uppercase">Expenses</div>
                    <div class="text-danger fw-bold">- Rs. <?= number_format($total_expenses, 2) ?></div>
                </div>
                <div class="statement-row d-flex flex-column flex-sm-row justify-content-between align-items-sm-center bg-light bg-opacity-50 p-3 rounded-4 mb-2 gap-1">
                    <div class="text-muted small fw-bold text-uppercase">Payroll</div>
                    <div class="text-danger fw-bold">- Rs. <?= number_format($total_payroll, 2) ?></div>
                </div>
            </div>
            
            <div class="mt-5 p-4 rounded-4 <?= $net_profit >= 0 ? 'bg-success bg-opacity-10 border border-success' : 'bg-danger bg-opacity-10 border border-danger' ?>">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div class="text-center text-sm-start">
                        <div class="small fw-bold text-uppercase text-muted opacity-75">Net Performance</div>
                        <h4 class="mb-0 fw-bold <?= $profit_class ?>"><?= $net_profit >= 0 ? 'NET PERIOD PROFIT' : 'NET PERIOD LOSS' ?></h4>
                    </div>
                    <div class="text-center text-sm-end">
                        <h2 class="mb-0 fw-bold <?= $profit_class ?>">Rs. <?= number_format($net_profit, 2) ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- ASSETS SUMMARY -->
<div class="card modern-card mb-5 border-start border-5 border-secondary shadow-sm">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-warehouse text-secondary me-2"></i> Current Inventory Valuation</h5>
                <p class="text-muted small mb-0">Total estimated market value of all physical stock currently available across all product categories.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="small fw-bold text-muted text-uppercase mb-1">AGGREGATE ASSET VALUE</div>
                <h2 class="fw-bold mb-0 text-dark">Rs. <?= number_format((float)$stockValue, 2) ?></h2>
            </div>
        </div>
    </div>
</div>
