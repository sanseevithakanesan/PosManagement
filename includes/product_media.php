<?php

/**
 * Public URL for a product image file path (relative to site root), or no-image placeholder.
 */
function product_image_url(?string $relativePath): string
{
    static $base = null;
    if ($base === null) {
        require_once dirname(__DIR__) . '/config/config.php';
        $base = rtrim(BASE_URL, '/');
    }
    if ($relativePath === null || $relativePath === '') {
        return $base . '/assets/img/no-product.svg';
    }
    $clean = str_replace('\\', '/', $relativePath);

    return $base . '/' . ltrim($clean, '/');
}
