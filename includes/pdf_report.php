<?php
declare(strict_types=1);

function pdf_report_escape(string $s): string {
    $latin = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
    if ($latin !== false && $latin !== '') {
        $s = $latin;
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

function pdf_report_build(
    string $title,
    string $periodStr,
    float $income,
    float $purchases,
    float $expenses,
    float $payroll,
    float $netProfit,
    float $stockValue,
    string $type = 'all',
    array $details = []
): string {
    $lines = [];
    $width = 75; 
    
    // --- HEADER ---
    $lines[] = str_repeat('=', $width);
    $lines[] = str_pad("MY SHOP", $width, " ", STR_PAD_BOTH);
    $lines[] = str_pad("FINANCIAL REPORT", $width, " ", STR_PAD_BOTH);
    $lines[] = str_repeat('=', $width);
    $lines[] = sprintf('%-15s : %s', 'REPORT TYPE', strtoupper($title));
    $lines[] = sprintf('%-15s : %s', 'PERIOD', $periodStr);
    $lines[] = sprintf('%-15s : %s', 'DATE RANGE', date('Y-m-d H:i:s'));
    $lines[] = str_repeat('-', $width);
    $lines[] = "";
    
    // --- SUMMARY SECTION (Strictly Aligned) ---
    $lines[] = "SUMMARY OVERVIEW";
    $lines[] = "------------------------------------------";
    if ($type === 'all' || $type === 'income') {
        $lines[] = sprintf('%-28s : %12s', 'Gross Sales Revenue', number_format($income, 2));
    }
    if ($type === 'all' || $type === 'purchase') {
        $lines[] = sprintf('%-28s : %12s', 'Inventory Purchases', "-" . number_format($purchases, 2));
    }
    if ($type === 'all' || $type === 'expense') {
        $lines[] = sprintf('%-28s : %12s', 'General Expenses', "-" . number_format($expenses, 2));
    }
    if ($type === 'all' || $type === 'payroll') {
        $lines[] = sprintf('%-28s : %12s', 'Total Payroll Paid', "-" . number_format($payroll, 2));
    }
    
    if ($type === 'all') {
        $lines[] = str_repeat('-', 43);
        $lines[] = sprintf('%-28s : %12s', 'NET PROFIT / LOSS', number_format($netProfit, 2));
        $lines[] = str_repeat('=', 43);
        $lines[] = "";
        $lines[] = sprintf('%-28s : %12s', 'Stock Valuation', number_format($stockValue, 2));
    }
    
    // --- TRANSACTION LIST (Spacious & Aligned) ---
    if (!empty($details)) {
        $lines[] = "";
        $lines[] = sprintf('%-12s %-45s %16s', 'DATE', 'ITEM / DETAILS', 'TOTAL (Rs)');
        $lines[] = str_repeat('-', $width);
        
        foreach ($details as $row) {
            if ($type === 'income') {
                $date = date('d/m/Y', strtotime($row['created_at']));
                $item = strtoupper($row['customer'] ?: 'Walk-in Customer');
                $amt = number_format((float)$row['total_amount'], 2);
                
                $lines[] = sprintf('%-12s %-45s %16s', $date, $item, $amt);
            } 
            
            else if ($type === 'expense') {
                $date = date('d/m/Y', strtotime($row['expense_date']));
                $item = strtoupper($row['expense_category']);
                $amt = number_format((float)$row['amount'], 2);
                
                $lines[] = sprintf('%-12s %-45s %16s', $date, $item, $amt);
                $lines[] = sprintf('%-12s Desc     : %s', '', substr($row['description'], 0, 45));
            }

            else if ($type === 'purchase') {
                $date = date('d/m/Y', strtotime($row['purchase_date']));
                $item = strtoupper($row['product'] ?: 'Unknown Product');
                $amt = number_format((float)$row['total_cost'], 2);
                $paid = number_format((float)$row['paid_amount'], 2);
                $due = number_format((float)$row['due_amount'], 2);
                
                $lines[] = sprintf('%-12s %-45s %16s', $date, $item, $amt);
                $lines[] = sprintf('%-12s Qty      : %-15s Unit    : %s', '', $row['quantity'], number_format((float)$row['unit_cost'], 2));
                $lines[] = sprintf('%-12s Supplier : %s', '', substr($row['supplier'], 0, 45));
                $lines[] = sprintf('%-12s Paid     : %-15s Due     : %s', '', $paid, $due);
            }

            else if ($type === 'payroll') {
                $date = date('d/m/Y', strtotime($row['payment_date']));
                $item = strtoupper($row['employee'] ?: 'Unknown Staff');
                $amt = number_format((float)$row['net_paid'], 2);
                $base = number_format((float)$row['base_amount'], 2);
                $adv = number_format((float)$row['advances_deducted'], 2);
                
                $lines[] = sprintf('%-12s %-45s %16s', $date, $item, $amt);
                $lines[] = sprintf('%-12s Month    : %-15s Base    : %s', '', ($row['pay_month'] ?: '-'), $base);
                $lines[] = sprintf('%-12s Adv      : %s', '', $adv);
            }
            
            $lines[] = ""; // Add breathing room between items
        }
    }
    
    $lines[] = str_repeat('-', $width);
    $lines[] = str_pad("End of Report", $width, " ", STR_PAD_BOTH);
    $lines[] = str_repeat('-', $width);

    $inner = ["BT", "/F1 11 Tf", "40 760 Td"];
    foreach ($lines as $i => $line) {
        if ($i > 0) {
            $inner[] = '0 -15 Td';
        }
        $inner[] = '(' . pdf_report_escape($line) . ') Tj';
    }
    $inner[] = 'ET';
    $stream = implode("\n", $inner);

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>', // A4
        4 => '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>', // Fixed width font
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    for ($i = 1; $i <= 5; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

    return $pdf;
}
