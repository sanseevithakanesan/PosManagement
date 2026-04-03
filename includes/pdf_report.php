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
    float $stockValue
): string {
    $lines = [];
    $lines[] = "MY SHOP - FINANCIAL REPORT";
    $lines[] = $title;
    $lines[] = "Period: " . $periodStr;
    $lines[] = str_repeat('-', 42);
    $lines[] = sprintf('%-25s %16s', 'Category', 'Amount (Rs)');
    $lines[] = str_repeat('-', 42);
    
    $lines[] = sprintf('%-25s %16s', 'Gross Sales Revenue:', number_format($income, 2));
    $lines[] = sprintf('%-25s %16s', 'Inventory Purchases:', "-" . number_format($purchases, 2));
    $lines[] = sprintf('%-25s %16s', 'General Expenses:', "-" . number_format($expenses, 2));
    $lines[] = sprintf('%-25s %16s', 'Total Payroll Paid:', "-" . number_format($payroll, 2));
    
    $lines[] = str_repeat('-', 42);
    $lines[] = sprintf('%-25s %16s', 'NET PROFIT / LOSS:', number_format($netProfit, 2));
    $lines[] = str_repeat('=', 42);
    $lines[] = "";
    $lines[] = sprintf('%-25s %16s', 'Current Stock Valuation:', number_format($stockValue, 2));
    $lines[] = "";
    $lines[] = "Report generated on: " . date('Y-m-d H:i:s');

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
