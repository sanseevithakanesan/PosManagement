<?php
require_once dirname(__DIR__) . '/database/db.php';
require_once dirname(__DIR__) . '/includes/pdf_report.php';

$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';
$report_type = $_GET['type'] ?? 'all';

try {
    $hasDates = (!empty($start_date) && !empty($end_date));
    if ($hasDates) {
        $periodStr = date('d M Y', strtotime($start_date)) . " to " . date('d M Y', strtotime($end_date));
    } else {
        $periodStr = "All Historical Records";
    }

    $total_income = 0;
    $total_purchases = 0;
    $total_expenses = 0;
    $total_payroll = 0;
    $details = [];

    // --- INCOME DATA ---
    if ($report_type === 'all' || $report_type === 'income') {
        $sqlSum = "SELECT SUM(total_amount) FROM orders WHERE status = 'Completed'";
        $sqlDetails = "SELECT o.id, o.created_at, c.name as customer, u.name as cashier, o.total_amount FROM orders o LEFT JOIN customers c ON o.customer_id = c.id LEFT JOIN users u ON o.user_id = u.id WHERE o.status = 'Completed'";
        $params = [];
        if ($hasDates) {
            $sqlSum .= " AND DATE(created_at) >= ? AND DATE(created_at) <= ?";
            $sqlDetails .= " AND DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?";
            $params = [$start_date, $end_date];
        }
        $stmtSum = $pdo->prepare($sqlSum);
        $stmtSum->execute($params);
        $total_income = (float)($stmtSum->fetchColumn() ?: 0);

        if ($report_type === 'income') {
            $stmt = $pdo->prepare($sqlDetails . " ORDER BY id ASC");
            $stmt->execute($params);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- PURCHASE DATA ---
    if ($report_type === 'all' || $report_type === 'purchase') {
        $sqlSum = "SELECT SUM(total_cost) FROM purchases WHERE status = 'Received'";
        $sqlDetails = "SELECT p.id, p.purchase_date, p.supplier, pr.name as product, p.quantity, p.unit_cost, p.total_cost, p.paid_amount, p.due_amount FROM purchases p LEFT JOIN products pr ON p.product_id = pr.id";
        $params = [];
        if ($hasDates) {
            $sqlSum .= " AND DATE(purchase_date) >= ? AND DATE(purchase_date) <= ?";
            $sqlDetails .= " WHERE DATE(p.purchase_date) >= ? AND DATE(p.purchase_date) <= ?";
            $params = [$start_date, $end_date];
        }
        $stmtSum = $pdo->prepare($sqlSum);
        $stmtSum->execute($params);
        $total_purchases = (float)($stmtSum->fetchColumn() ?: 0);

        if ($report_type === 'purchase') {
            $stmt = $pdo->prepare($sqlDetails . " ORDER BY p.id ASC");
            $stmt->execute($params);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- EXPENSE DATA ---
    if ($report_type === 'all' || $report_type === 'expense') {
        $sqlSum = "SELECT SUM(amount) FROM expenses WHERE 1=1";
        $sqlDetails = "SELECT id, expense_date, expense_category, description, amount FROM expenses WHERE 1=1";
        $params = [];
        if ($hasDates) {
            $sqlSum .= " AND DATE(expense_date) >= ? AND DATE(expense_date) <= ?";
            $sqlDetails .= " AND DATE(expense_date) >= ? AND DATE(expense_date) <= ?";
            $params = [$start_date, $end_date];
        }
        $stmtSum = $pdo->prepare($sqlSum);
        $stmtSum->execute($params);
        $total_expenses = (float)($stmtSum->fetchColumn() ?: 0);

        if ($report_type === 'expense') {
            $stmt = $pdo->prepare($sqlDetails . " ORDER BY id ASC");
            $stmt->execute($params);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- PAYROLL DATA ---
    if ($report_type === 'all' || $report_type === 'payroll') {
        $sqlSum = "SELECT SUM(net_paid) FROM salary_payments WHERE 1=1";
        $sqlDetails = "SELECT p.id, p.payment_date, e.name as employee, p.pay_month, p.base_amount, p.advances_deducted, p.net_paid FROM salary_payments p LEFT JOIN employees e ON p.employee_id = e.id WHERE 1=1";
        $params = [];
        if ($hasDates) {
            $sqlSum .= " AND DATE(payment_date) >= ? AND DATE(payment_date) <= ?";
            $sqlDetails .= " AND DATE(p.payment_date) >= ? AND DATE(p.payment_date) <= ?";
            $params = [$start_date, $end_date];
        }
        $stmtSum = $pdo->prepare($sqlSum);
        $stmtSum->execute($params);
        $total_payroll = (float)($stmtSum->fetchColumn() ?: 0);

        if ($report_type === 'payroll') {
            $stmt = $pdo->prepare($sqlDetails . " ORDER BY p.id ASC");
            $stmt->execute($params);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $stockValue = ($report_type === 'all') ? (float)($pdo->query("SELECT SUM(stock * price) FROM products WHERE stock > 0")->fetchColumn() ?: 0) : 0.0;
    
    $netProfit = $total_income - ($total_purchases + $total_expenses + $total_payroll);
    $title = ucfirst($report_type) . " Report";
    
    $pdf_content = pdf_report_build($title, $periodStr, $total_income, $total_purchases, $total_expenses, $total_payroll, $netProfit, $stockValue, $report_type, $details);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$report_type.'_report_'.date('Ymd').'.pdf"');
    echo $pdf_content;
    exit;

} catch (Exception $e) {
    die("Export Error: " . $e->getMessage());
}
