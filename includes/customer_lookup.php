<?php

/**
 * Phone / customer helpers for POS checkout (autocomplete + dedupe by digits).
 */
function customer_normalize_digits(?string $phone): string
{
    return preg_replace('/\D+/', '', (string)$phone);
}

/**
 * Parse "0772844417 - thinu" or raw phone / name entry.
 *
 * @return array{digits: string, name: string, phone_raw: string}
 */
function customer_parse_lookup_line(string $line): array
{
    $line = trim($line);
    if ($line === '') {
        return ['digits' => '', 'name' => '', 'phone_raw' => ''];
    }

    if (preg_match('/^(.+?)\s*[-–—]\s*(.+)$/u', $line, $m)) {
        $raw = trim($m[1]);
        $name = trim($m[2]);

        return [
            'digits' => customer_normalize_digits($raw),
            'name' => $name,
            'phone_raw' => $raw,
        ];
    }

    return [
        'digits' => customer_normalize_digits($line),
        'name' => '',
        'phone_raw' => $line,
    ];
}

function customer_format_label(string $name, ?string $phone): string
{
    $name = trim($name);
    $phone = trim((string)$phone);
    if ($phone !== '' && $name !== '') {
        return $phone . ' - ' . $name;
    }
    if ($phone !== '') {
        return $phone;
    }

    return $name;
}

function customer_find_by_digits(PDO $pdo, string $digits): ?array
{
    if ($digits === '') {
        return null;
    }

    $stmt = $pdo->query('SELECT id, name, phone FROM customers');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (customer_normalize_digits($row['phone'] ?? '') === $digits) {
            return $row;
        }
    }

    return null;
}
