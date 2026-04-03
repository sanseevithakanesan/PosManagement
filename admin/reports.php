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
    $stmtExpenses = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE DATE(expense_date) >= ? AND DATE(expense_date) <= ?");
    $stmtExpenses->execute([$start_date, $end_date]);
    $total_expenses = (float)($stmtExpenses->fetchColumn() ?: 0);

    // 4. Total Payroll (Salaries Paid)
    $stmtPayroll = $pdo->prepare("SELECT SUM(net_paid) FROM salary_payments WHERE DATE(payment_date) >= ? AND DATE(payment_date) <= ?");
    $stmtPayroll->execute([$start_date, $end_date]);
    $total_payroll = (float)($stmtPayroll->fetchColumn() ?: 0);

    // Current Inventory Valuation (Global, not date dependent)
    $stockValue = $pdo->query("SELECT SUM(stock * price) FROM products WHERE stock > 0")->fetchColumn() ?: 0;
    
} catch(Exception $e) {
    $total_income = $total_purchases = $total_expenses = $total_payroll = $stockValue = 0;
}

// Calculate Net Profit
$total_outings = $total_purchases + $total_expenses + $total_payroll;
$net_profit = $total_income - $total_outings;
$profit_class = $net_profit >= 0 ? 'text-success' : 'text-danger';

?>

<style>
@media print {
    /* Hide navigation and non-essential elements for printing */
    body { background-color: white !important; }
    .admin-sidebar, .topbar-glass, .print-hide, .btn-print, .offcanvas { display: none !important; }
    .col-lg-10 { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
    main { padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .page-break { page-break-before: always; }
}
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 print-hide">
    <div>
        <h3 class="mb-0 text-dark fw-bold">Reports & Analytics</h3>
        <p class="text-muted small mb-0">Generate financial reports based on a date range.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="dashboard.php?page=reports&start=<?= urlencode($start_date) ?>&end=<?= urlencode($end_date) ?>&pdf=1" class="btn btn-primary fw-bold shadow-sm d-flex align-items-center">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="me-1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Download PDF
        </a>
        <button onclick="window.print()" class="btn btn-dark fw-bold shadow-sm btn-print d-flex align-items-center">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="me-1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Report
        </button>
    </div>
</div>

<!-- DATE FILTER FORM -->
<div class="card content-card p-4 mb-4 shadow-sm border-0 print-hide">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="reports">
        <div class="col-md-3">
            <label class="form-label text-muted small fw-bold">Start Date</label>
            <input type="date" name="start" class="form-control border-primary" value="<?= htmlspecialchars($start_date) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label text-muted small fw-bold">End Date</label>
            <input type="date" name="end" class="form-control border-primary" value="<?= htmlspecialchars($end_date) ?>" required>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100 fw-bold">Generate Report</button>
        </div>
        <div class="col-md-3 text-end">
            <a href="dashboard.php?page=reports&start=<?= date('Y-m-d') ?>&end=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
            <a href="dashboard.php?page=reports&start=<?= date('Y-m-01') ?>&end=<?= date('Y-m-t') ?>" class="btn btn-outline-secondary btn-sm">This Month</a>
            <a href="dashboard.php?page=reports&start=<?= date('Y-01-01') ?>&end=<?= date('Y-12-31') ?>" class="btn btn-outline-secondary btn-sm">This Year</a>
        </div>
    </form>
</div>

<!-- PRINT HEADER (ONLY VISIBLE ON PRINT) -->
<div class="d-none d-print-block mb-4 text-center">
    <h2 class="fw-bold">Business Financial Report</h2>
    <p class="text-muted mb-0">Reporting Period: <strong><?= date('d M Y', strtotime($start_date)) ?></strong> to <strong><?= date('d M Y', strtotime($end_date)) ?></strong></p>
    <hr>
</div>

<h5 class="text-dark fw-bold mb-3 d-print-block">Financial Period Summaries <span class="text-muted fs-6 fw-normal">(<?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?>)</span></h5>

<!-- METRICS HIGHLIGHTS -->
<div class="row g-3 mb-4">
    <!-- Revenue -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card bg-white p-3 border-0 shadow-sm border-start border-4 border-primary h-100">
            <h6 class="text-muted small mb-2 text-uppercase fw-bold">Total Sales Income</h6>
            <h3 class="text-primary mb-0 fw-bold">Rs. <?= number_format($total_income, 2) ?></h3>
        </div>
    </div>
    <!-- Purchases -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card bg-white p-3 border-0 shadow-sm border-start border-4 border-warning h-100">
            <h6 class="text-muted small mb-2 text-uppercase fw-bold">Cost of Goods (Purchases)</h6>
            <h3 class="text-warning mb-0 fw-bold">Rs. <?= number_format($total_purchases, 2) ?></h3>
        </div>
    </div>
    <!-- Expenses -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card bg-white p-3 border-0 shadow-sm border-start border-4 border-danger h-100">
            <h6 class="text-muted small mb-2 text-uppercase fw-bold">Operating Expenses</h6>
            <h3 class="text-danger mb-0 fw-bold">Rs. <?= number_format($total_expenses, 2) ?></h3>
        </div>
    </div>
    <!-- Payroll -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card bg-white p-3 border-0 shadow-sm border-start border-4 border-info h-100">
            <h6 class="text-muted small mb-2 text-uppercase fw-bold">Payroll Salaries</h6>
            <h3 class="text-info mb-0 fw-bold">Rs. <?= number_format($total_payroll, 2) ?></h3>
        </div>
    </div>
</div>

<!-- PROFIT AND LOSS MASTER SUMMARY -->
<div class="card content-card p-0 mb-4 shadow-sm border-0 overflow-hidden">
    <div class="bg-dark text-white p-3">
        <h5 class="mb-0">Profit & Loss (P&L) Statement</h5>
    </div>
    <div class="p-4">
        <table class="table table-borderless mx-auto" style="max-width: 600px; font-size: 1.1rem;">
            <tbody>
                <tr class="border-bottom">
                    <td class="text-muted fw-bold">Gross Sales Revenue:</td>
                    <td class="text-end text-primary fw-bold">Rs. <?= number_format($total_income, 2) ?></td>
                </tr>
                <tr>
                    <td class="text-muted ps-4">(-) Inventory Purchases (Received):</td>
                    <td class="text-end text-danger">- Rs. <?= number_format($total_purchases, 2) ?></td>
                </tr>
                <tr>
                    <td class="text-muted ps-4">(-) General Expenses:</td>
                    <td class="text-end text-danger">- Rs. <?= number_format($total_expenses, 2) ?></td>
                </tr>
                <tr class="border-bottom">
                    <td class="text-muted ps-4">(-) Total Payroll Paid:</td>
                    <td class="text-end text-danger">- Rs. <?= number_format($total_payroll, 2) ?></td>
                </tr>
                <tr class="table-light">
                    <td class="fw-bold fs-5 pt-3">NET PROFIT / LOSS</td>
                    <td class="text-end fw-bold fs-4 pt-3 <?= $profit_class ?>">Rs. <?= number_format($net_profit, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ASSETS SUMMARY -->
<div class="card content-card p-4 shadow-sm border-0 border-start border-4 border-secondary">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h5 class="fw-bold text-secondary mb-1">Current Inventory Valuation</h5>
            <p class="text-muted small mb-0">This represents the estimated retail value of all physical stock currently sitting in your shop across all products.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <h3 class="fw-bold mb-0 text-dark">Rs. <?= number_format((float)$stockValue, 2) ?></h3>
        </div>
    </div>
</div>
