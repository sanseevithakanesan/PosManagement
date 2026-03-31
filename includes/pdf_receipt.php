<?php

declare(strict_types=1);

/**
 * Minimal single-page PDF (Helvetica, Latin-1-ish) for thermal/A4 print-from-PDF.
 */
function pdf_receipt_escape(string $s): string
{
    $latin = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
    if ($latin !== false && $latin !== '') {
        $s = $latin;
    }

    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

/**
 * @param array<int, array{name:string,qty:int,unit:float,total:float}> $rows
 */
function pdf_receipt_build(
    string $shopName,
    string $tagline,
    string $billNo,
    string $customerLine,
    string $dateStr,
    array $rows,
    float $grand,
    float $cash,
    float $balance
): string {
    $lines = [];
    $lines[] = $shopName;
    if ($tagline !== '') {
        $lines[] = $tagline;
    }
    $lines[] = $billNo;
    $lines[] = str_repeat('-', 36);
    $lines[] = $customerLine;
    $lines[] = $dateStr;
    $lines[] = str_repeat('-', 36);
    $lines[] = sprintf('%-20s %3s %7s %8s', 'Item', 'Qty', 'Price', 'Total');
    foreach ($rows as $r) {
        $name = function_exists('mb_substr')
            ? mb_substr($r['name'], 0, 22)
            : substr($r['name'], 0, 22);
        $lines[] = sprintf(
            '%-22s %3d %7s %8s',
            $name,
            $r['qty'],
            number_format($r['unit'], 2, '.', ''),
            number_format($r['total'], 2, '.', '')
        );
    }
    $lines[] = str_repeat('-', 36);
    $lines[] = sprintf('GRAND TOTAL: %s', number_format($grand, 2, '.', ''));
    $lines[] = sprintf('Cash given: %s', number_format($cash, 2, '.', ''));
    $lines[] = sprintf('Balance: %s', number_format($balance, 2, '.', ''));
    $lines[] = 'Thank you!';

    $inner = ["BT", "/F1 9 Tf", "40 740 Td"];
    foreach ($lines as $i => $line) {
        if ($i > 0) {
            $inner[] = '0 -11 Td';
        }
        $inner[] = '(' . pdf_receipt_escape($line) . ') Tj';
    }
    $inner[] = 'ET';
    $stream = implode("\n", $inner);

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 420 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        4 => '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
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
