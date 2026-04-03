<?php
require_once dirname(__DIR__) . '/database/db.php';
require_once dirname(__DIR__) . '/includes/pdf_report.php';

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

try {
    $stmtIncome = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
    $stmtIncome->execute([$start_date, $end_date]);
    $total_income = (float)($stmtIncome->fetchColumn() ?: 0);

    $stmtPurchases = $pdo->prepare("SELECT SUM(total_cost) FROM purchases WHERE DATE(purchase_date) >= ? AND DATE(purchase_date) <= ? AND status = 'Received'");
    $stmtPurchases->execute([$start_date, $end_date]);
    $total_purchases = (float)($stmtPurchases->fetchColumn() ?: 0);

    $stmtExpenses = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE DATE(expense_date) >= ? AND DATE(expense_date) <= ?");
    $stmtExpenses->execute([$start_date, $end_date]);
    $total_expenses = (float)($stmtExpenses->fetchColumn() ?: 0);

    $stmtPayroll = $pdo->prepare("SELECT SUM(net_paid) FROM salary_payments WHERE DATE(payment_date) >= ? AND DATE(payment_date) <= ?");
    $stmtPayroll->execute([$start_date, $end_date]);
    $total_payroll = (float)($stmtPayroll->fetchColumn() ?: 0);

    $stockValue = $pdo->query("SELECT SUM(stock * price) FROM products WHERE stock > 0")->fetchColumn() ?: 0;
} catch(Exception $e) {
    $total_income = $total_purchases = $total_expenses = $total_payroll = $stockValue = 0;
}

$net_profit = $total_income - ($total_purchases + $total_expenses + $total_payroll);

$periodStr = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));

$pdf = pdf_report_build(
    "Business Financial Report",
    $periodStr,
    $total_income,
    $total_purchases,
    $total_expenses,
    $total_payroll,
    $net_profit,
    (float)$stockValue
);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Report-' . $start_date . '_to_' . $end_date . '.pdf"');
echo $pdf;
