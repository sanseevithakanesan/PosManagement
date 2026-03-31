<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/database/db.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/product_schema.php';

ensure_product_extended_schema($pdo);

$uploadDir = dirname(__DIR__) . '/assets/uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$errors = [];

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$existing = null;
$existingImages = [];
if ($editId > 0) {
    $ps = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $ps->execute([$editId]);
    $existing = $ps->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        $_SESSION['message'] = 'Product not found.';
        $_SESSION['message_type'] = 'danger';
        header('Location: dashboard.php?page=products');
        exit;
    }
    $im = $pdo->prepare(
        'SELECT id, file_path, sort_order, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $im->execute([$editId]);
    $existingImages = $im->fetchAll(PDO::FETCH_ASSOC);
}

$createdId = isset($_GET['created']) ? (int)$_GET['created'] : 0;
$justCreated = null;
if ($createdId > 0 && $editId === 0) {
    $ps = $pdo->prepare('SELECT id, name, barcode FROM products WHERE id = ?');
    $ps->execute([$createdId]);
    $justCreated = $ps->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($justCreated && isset($_SESSION['message'])) {
        unset($_SESSION['message'], $_SESSION['message_type']);
    }
}

$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
    'image/x-png' => 'png',
    'image/webp' => 'webp',
];

$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

/**
 * Normalize $_FILES['images'] to a list of per-file arrays.
 *
 * @return list<array{name: string, tmp_name: string, error: int}>
 */
$normalizeUploadedImages = static function (?array $filesField): array {
    if (!$filesField || !isset($filesField['name'])) {
        return [];
    }
    if (!is_array($filesField['name'])) {
        $er = (int)($filesField['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($er === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [[
            'name' => (string)$filesField['name'],
            'tmp_name' => (string)($filesField['tmp_name'] ?? ''),
            'error' => $er,
        ]];
    }
    $out = [];
    $n = count($filesField['name']);
    for ($i = 0; $i < $n; $i++) {
        $er = (int)($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        $nm = (string)($filesField['name'][$i] ?? '');
        if ($er === UPLOAD_ERR_NO_FILE && $nm === '') {
            continue;
        }
        $out[] = [
            'name' => $nm,
            'tmp_name' => (string)($filesField['tmp_name'][$i] ?? ''),
            'error' => $er,
        ];
    }

    return $out;
};

$uploadErrMessage = static function (int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image file is too large for the server. Increase upload_max_filesize / post_max_size in php.ini.',
        UPLOAD_ERR_PARTIAL => 'Image upload was interrupted. Try again.',
        UPLOAD_ERR_NO_FILE => '',
        default => 'Image upload failed (code ' . $code . ').',
    };
};

$resolveImageExt = static function (string $tmpPath, string $originalName) use ($allowedMime, $allowedExt): ?string {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $mime = strtolower(trim($mime));
    if ($mime !== '' && isset($allowedMime[$mime])) {
        $ext = $allowedMime[$mime];
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    $nameExt = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($nameExt, $allowedExt, true)) {
        return $nameExt === 'jpeg' ? 'jpg' : $nameExt;
    }

    return null;
};

$reassignPrimary = static function (PDO $pdo, int $productId): void {
    $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = ?')->execute([$productId]);
    $sel = $pdo->prepare(
        'SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1'
    );
    $sel->execute([$productId]);
    $firstId = $sel->fetchColumn();
    if ($firstId) {
        $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ?')->execute([(int)$firstId]);
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $productIdPost = (int)($_POST['product_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
    $price = (float)str_replace(',', '', (string)($_POST['price'] ?? '0'));
    $costPrice = ($_POST['cost_price'] ?? '') !== '' ? (float)str_replace(',', '', (string)$_POST['cost_price']) : null;
    $stock = (int)($_POST['stock'] ?? 0);
    $reorderLevel = (int)($_POST['reorder_level'] ?? 0);
    $sku = trim((string)($_POST['sku'] ?? ''));
    $barcodeIn = trim((string)($_POST['barcode'] ?? ''));

    if ($name === '') {
        $errors[] = 'Product name is required.';
    }
    if ($price < 0) {
        $errors[] = 'Price cannot be negative.';
    }
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative.';
    }

    $isEdit = $productIdPost > 0;
    if ($isEdit && (!$existing || (int)$existing['id'] !== $productIdPost)) {
        $errors[] = 'Invalid product.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $barcode = $barcodeIn !== '' ? $barcodeIn : (string)($existing['barcode'] ?? '');
                if ($barcode === '') {
                    $barcode = product_generate_unique_barcode($pdo);
                }

                $stmt = $pdo->prepare(
                    'UPDATE products SET category_id=?, name=?, title=?, description=?, price=?, cost_price=?,
                     stock=?, reorder_level=?, barcode=?, sku=? WHERE id=?'
                );
                $stmt->execute([
                    $categoryId,
                    $name,
                    $title !== '' ? $title : null,
                    $description !== '' ? $description : null,
                    $price,
                    $costPrice,
                    $stock,
                    $reorderLevel,
                    $barcode,
                    $sku !== '' ? $sku : null,
                    $productIdPost,
                ]);

                if ($sku === '') {
                    $pdo->prepare('UPDATE products SET sku = ? WHERE id = ? AND (sku IS NULL OR sku = \'\')')
                        ->execute(['SKU-' . $productIdPost, $productIdPost]);
                }

                $productId = $productIdPost;

                foreach ($_POST['delete_image'] ?? [] as $delId) {
                    $delId = (int)$delId;
                    if ($delId <= 0) {
                        continue;
                    }
                    $rowStmt = $pdo->prepare(
                        'SELECT file_path FROM product_images WHERE id = ? AND product_id = ? LIMIT 1'
                    );
                    $rowStmt->execute([$delId, $productId]);
                    $fp = $rowStmt->fetchColumn();
                    if ($fp) {
                        $pdo->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?')
                            ->execute([$delId, $productId]);
                        $full = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string)$fp);
                        if (is_file($full)) {
                            @unlink($full);
                        }
                    }
                }

                $reassignPrimary($pdo, $productId);

                $imgCount = (int)$pdo->query(
                    'SELECT COUNT(*) FROM product_images WHERE product_id = ' . (int)$productId
                )->fetchColumn();

                $uploadListEdit = $normalizeUploadedImages($_FILES['images'] ?? null);
                $newUploadCount = count(array_filter(
                    $uploadListEdit,
                    static fn(array $u) => $u['error'] === UPLOAD_ERR_OK
                ));
                if ($imgCount + $newUploadCount > 8) {
                    $pdo->rollBack();
                    $errors[] = 'Maximum 8 images per product (including new uploads). Remove some first.';
                } else {
                    $ordStmt = $pdo->prepare(
                        'SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?'
                    );
                    $ordStmt->execute([$productId]);
                    $sortBase = (int)$ordStmt->fetchColumn() + 1;

                    if (!empty($uploadListEdit)) {
                        $added = 0;
                        $attempted = 0;
                        foreach ($uploadListEdit as $uf) {
                            if ($added >= 8) {
                                break;
                            }
                            if ($uf['error'] !== UPLOAD_ERR_OK) {
                                if ($uf['error'] !== UPLOAD_ERR_NO_FILE) {
                                    $um = $uploadErrMessage($uf['error']);
                                    if ($um !== '') {
                                        $errors[] = $um;
                                    }
                                }
                                continue;
                            }
                            $attempted++;
                            $tmp = $uf['tmp_name'];
                            if ($tmp === '' || !is_uploaded_file($tmp)) {
                                continue;
                            }
                            $ext = $resolveImageExt($tmp, $uf['name']);
                            if ($ext === null) {
                                continue;
                            }
                            $base = bin2hex(random_bytes(8)) . '.' . $ext;
                            $dest = $uploadDir . $base;
                            if (!move_uploaded_file($tmp, $dest)) {
                                continue;
                            }
                            $relPath = 'assets/uploads/products/' . $base;
                            $imgStmt = $pdo->prepare(
                                'INSERT INTO product_images (product_id, file_path, sort_order, is_primary) VALUES (?,?,?,0)'
                            );
                            $imgStmt->execute([$productId, $relPath, $sortBase + $added]);
                            $added++;
                        }
                        if ($attempted > 0 && $added === 0) {
                            $pdo->rollBack();
                            if (empty(array_filter($errors, static fn($e) => str_contains((string)$e, 'php.ini')))) {
                                $errors[] = 'Selected images were not accepted. Use JPG, PNG or WEBP files.';
                            }
                        }
                    }
                    if (empty($errors)) {
                        $reassignPrimary($pdo, $productId);
                        $pdo->commit();
                        header('Location: product_create.php?id=' . $productId . '&saved=1');
                        exit;
                    }
                }
            } else {
                $barcode = product_generate_unique_barcode($pdo);

                $stmt = $pdo->prepare(
                    'INSERT INTO products (category_id, name, title, description, price, cost_price, stock, reorder_level, barcode, sku)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $categoryId,
                    $name,
                    $title !== '' ? $title : null,
                    $description !== '' ? $description : null,
                    $price,
                    $costPrice,
                    $stock,
                    $reorderLevel,
                    $barcode,
                    $sku !== '' ? $sku : null,
                ]);

                $productId = (int)$pdo->lastInsertId();

                if ($sku === '') {
                    $autoSku = 'SKU-' . $productId;
                    $pdo->prepare('UPDATE products SET sku = ? WHERE id = ?')->execute([$autoSku, $productId]);
                }

                $uploadListCreate = $normalizeUploadedImages($_FILES['images'] ?? null);
                if (!empty($uploadListCreate)) {
                    $added = 0;
                    $attempted = 0;
                    $sortOrder = 0;
                    foreach ($uploadListCreate as $uf) {
                        if ($added >= 8) {
                            break;
                        }
                        if ($uf['error'] !== UPLOAD_ERR_OK) {
                            if ($uf['error'] !== UPLOAD_ERR_NO_FILE) {
                                $um = $uploadErrMessage($uf['error']);
                                if ($um !== '') {
                                    $errors[] = $um;
                                }
                            }
                            continue;
                        }
                        $attempted++;
                        $tmp = $uf['tmp_name'];
                        if ($tmp === '' || !is_uploaded_file($tmp)) {
                            continue;
                        }
                        $ext = $resolveImageExt($tmp, $uf['name']);
                        if ($ext === null) {
                            continue;
                        }
                        $base = bin2hex(random_bytes(8)) . '.' . $ext;
                        $dest = $uploadDir . $base;
                        if (!move_uploaded_file($tmp, $dest)) {
                            continue;
                        }
                        $relPath = 'assets/uploads/products/' . $base;
                        $imgStmt = $pdo->prepare(
                            'INSERT INTO product_images (product_id, file_path, sort_order, is_primary) VALUES (?,?,?,?)'
                        );
                        $imgStmt->execute([$productId, $relPath, $sortOrder, $added === 0 ? 1 : 0]);
                        $sortOrder++;
                        $added++;
                    }
                    if ($attempted > 0 && $added === 0) {
                        $pdo->rollBack();
                        if (empty(array_filter($errors, static fn($e) => str_contains((string)$e, 'php.ini')))) {
                            $errors[] = 'Selected images were not accepted. Use JPG, PNG or WEBP files.';
                        }
                    }
                }

                if (!empty($errors)) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } else {
                    $pdo->commit();
                    $_SESSION['message'] = 'Product created successfully. Barcode: ' . $barcode;
                    $_SESSION['message_type'] = 'success';
                    header('Location: product_create.php?created=' . $productId);
                    exit;
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (empty($errors)) {
                $errors[] = 'Could not save product. Please try again.';
            }
        }
    }
}

$savedBanner = isset($_GET['saved']) && $_GET['saved'] === '1' && $editId > 0 && $existing;
$isPostSave = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product']);
$val = static function (string $key, $fallback = '') use ($isPostSave): string {
    if ($isPostSave && array_key_exists($key, $_POST)) {
        return (string)$_POST[$key];
    }
    return (string)$fallback;
};
$catSelected = null;
if ($isPostSave && array_key_exists('category_id', $_POST)) {
    $catSelected = ($_POST['category_id'] ?? '') !== '' ? (string)$_POST['category_id'] : '';
} elseif ($editId > 0 && $existing) {
    $v = $existing['category_id'] ?? null;
    $catSelected = $v !== null && $v !== '' ? (string)(int)$v : '';
}
if ($catSelected === null) {
    $catSelected = '';
}
$ex = $existing ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editId > 0 ? 'Edit product' : 'New product' ?> — POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .product-create-hero { border-left: 4px solid #2563eb; }
        .section-title { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 600; margin-bottom: 1rem; }
        .barcode-preview { font-family: ui-monospace, monospace; letter-spacing: 0.06em; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 0.75rem 1rem; }
        .image-drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 2rem 1.25rem;
            text-align: center;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .image-drop-zone:hover, .image-drop-zone:focus-within {
            border-color: #2563eb;
            background: #eff6ff;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.12);
        }
        .image-drop-zone.dragover {
            border-color: #2563eb;
            background: #dbeafe;
        }
        .image-drop-zone .dz-icon { font-size: 2rem; line-height: 1; margin-bottom: 0.5rem; opacity: 0.85; }
        .image-preview-tile {
            width: 88px;
            height: 88px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            border: 1px solid #e2e8f0;
            background: #fff;
            flex-shrink: 0;
        }
        .image-preview-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .image-preview-tile .rm {
            position: absolute; top: 4px; right: 4px;
            width: 22px; height: 22px; border-radius: 50%;
            border: none; background: rgba(15,23,42,0.75); color: #fff;
            font-size: 14px; line-height: 1; cursor: pointer; padding: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .image-preview-tile .rm:hover { background: #dc2626; }
        .existing-image-tile {
            width: 88px; height: 88px; border-radius: 12px; overflow: hidden; position: relative;
            border: 1px solid #e2e8f0; flex-shrink: 0;
        }
        .existing-image-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .existing-image-tile .form-check {
            position: absolute; bottom: 0; left: 0; right: 0; margin: 0;
            background: rgba(15,23,42,0.8); padding: 4px 6px;
        }
        .existing-image-tile .form-check-label { font-size: 0.7rem; color: #fff; }
    </style>
</head>
<body class="app-body">
<nav class="navbar navbar-expand-lg topbar-glass border-bottom px-3 py-2 mb-4">
    <div class="container-fluid">
        <a href="dashboard.php?page=products" class="btn btn-outline-secondary btn-sm me-2">← Back to products</a>
        <span class="navbar-text fw-semibold">Product catalog</span>
    </div>
</nav>

<div class="container pb-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card content-card product-create-hero mb-4">
                <div class="card-body p-4">
                    <h1 class="h4 mb-1"><?= $editId > 0 ? 'Edit product' : 'Create product' ?></h1>
                    <p class="text-muted small mb-0"><?= $editId > 0 ? 'Update item master, inventory, barcode, and images.' : 'Structured item master: identifiers, pricing, stock policy, and media.' ?></p>
                </div>
            </div>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>

            <?php if ($justCreated): ?>
                <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span>
                        <strong>Saved</strong> — <?= htmlspecialchars($justCreated['name']) ?>
                        <span class="text-muted">· Barcode: <code><?= htmlspecialchars((string)$justCreated['barcode']) ?></code></span>
                    </span>
                    <span class="d-flex flex-wrap gap-2">
                        <a href="print_barcode.php?id=<?= (int)$justCreated['id'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-dark">Print barcode</a>
                        <a href="dashboard.php?page=products" class="btn btn-sm btn-outline-secondary">Back to list</a>
                        <a href="product_create.php" class="btn btn-sm btn-primary">Add another</a>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($savedBanner): ?>
                <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span>
                        <strong>Updated</strong> — <?= htmlspecialchars((string)$existing['name']) ?>
                        <span class="text-muted">· Barcode/MRP checkout code: <code><?= htmlspecialchars((string)($existing['barcode'] ?? '')) ?></code></span>
                    </span>
                    <span class="d-flex flex-wrap gap-2">
                        <?php if (!empty($existing['barcode'])): ?>
                            <a href="print_barcode.php?id=<?= (int)$existing['id'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-dark">Print barcode</a>
                        <?php endif; ?>
                        <a href="dashboard.php?page=products" class="btn btn-sm btn-outline-secondary">Back to list</a>
                    </span>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="card content-card p-4" id="product_create_form">
                <input type="hidden" name="product_id" value="<?= (int)$editId ?>">
                <input type="hidden" name="save_product" value="1">

                <p class="section-title">Identification & description</p>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Product name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150"
                               placeholder="e.g. Basmati Rice 5kg"
                               value="<?= htmlspecialchars($val('name', $ex['name'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title / subtitle</label>
                        <input type="text" name="title" class="form-control" maxlength="255"
                               placeholder="Display title for labels and receipts"
                               value="<?= htmlspecialchars($val('title', $ex['title'] ?? '')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="" <?= $catSelected === '' ? 'selected' : '' ?>>— Uncategorized —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= ((string)$c['id'] === $catSelected) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Specs, shelf life, notes for staff"><?=
                            htmlspecialchars($val('description', $ex['description'] ?? ''))
                        ?></textarea>
                    </div>
                </div>

                <p class="section-title">Pricing & SKU</p>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Selling price (Rs.) <span class="text-danger">*</span></label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required
                               value="<?= htmlspecialchars($val('price', isset($ex['price']) ? (string)$ex['price'] : '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cost price (Rs.)</label>
                        <input type="number" name="cost_price" class="form-control" step="0.01" min="0"
                               placeholder="Optional"
                               value="<?= htmlspecialchars($val(
                                   'cost_price',
                                   isset($ex['cost_price']) && $ex['cost_price'] !== null && $ex['cost_price'] !== '' ? (string)$ex['cost_price'] : ''
                               )) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Internal SKU</label>
                        <input type="text" name="sku" class="form-control" maxlength="64"
                               placeholder="Auto: SKU-{id} if empty"
                               value="<?= htmlspecialchars($val('sku', $ex['sku'] ?? '')) ?>">
                    </div>
                </div>

                <p class="section-title">Inventory</p>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">On-hand stock <span class="text-danger">*</span></label>
                        <input type="number" name="stock" class="form-control" min="0" required
                               value="<?= htmlspecialchars($val('stock', isset($ex['stock']) ? (string)$ex['stock'] : '0')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reorder level</label>
                        <input type="number" name="reorder_level" class="form-control" min="0"
                               title="Alert when on-hand quantity falls at or below this level"
                               value="<?= htmlspecialchars($val('reorder_level', isset($ex['reorder_level']) ? (string)$ex['reorder_level'] : '0')) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Barcode</label>
                        <?php if ($editId > 0): ?>
                            <input type="text" name="barcode" class="form-control font-monospace mb-2" maxlength="64"
                                   value="<?= htmlspecialchars($val('barcode', $ex['barcode'] ?? '')) ?>"
                                   placeholder="Leave blank to keep current code">
                            <div class="barcode-preview text-muted small mb-0">
                                Used at POS checkout. <?= !empty($ex['barcode']) ? 'You can print a label below after save.' : '' ?>
                            </div>
                            <?php if (!empty($ex['barcode'])): ?>
                                <a href="print_barcode.php?id=<?= (int)$editId ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark mt-2">Print barcode</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="barcode-preview text-muted small mb-0">
                                Generated automatically on save (unique code for POS scanning).
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="section-title">Images</p>
                <?php if ($editId > 0 && !empty($existingImages)): ?>
                    <p class="small text-muted mb-2">Check <strong>Remove</strong> to delete an image on save. Primary is the first remaining image.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($existingImages as $img): ?>
                            <div class="existing-image-tile">
                                <img src="../<?= htmlspecialchars((string)$img['file_path']) ?>" alt="">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="delete_image[]" value="<?= (int)$img['id'] ?>" id="del_img_<?= (int)$img['id'] ?>">
                                    <label class="form-check-label" for="del_img_<?= (int)$img['id'] ?>">Remove</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <div class="image-drop-zone" id="image_drop_zone" tabindex="0" role="button" aria-label="Upload product images">
                        <div class="dz-icon" aria-hidden="true">🖼️</div>
                        <div class="fw-semibold text-body mb-1">Drop photos here or click to browse</div>
                        <div class="text-muted small mb-0">JPEG, PNG or WebP · up to 8 images · first image = primary</div>
                        <input type="file" name="images[]" id="images_input" class="d-none" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <div id="image_preview" class="d-flex flex-wrap gap-2 mt-3"></div>
                </div>

                <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                    <button type="submit" class="btn btn-primary"><?= $editId > 0 ? 'Save changes' : 'Save product' ?></button>
                    <a href="dashboard.php?page=products" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card content-card p-4 sticky-lg-top" style="top: 1rem;">
                <h6 class="text-uppercase text-muted small fw-bold mb-3">Checklist</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li class="mb-2">Name and price are required for every SKU.</li>
                    <li class="mb-2">Reorder level drives low-stock awareness in replenishment.</li>
                    <li class="mb-2">Barcode is system-assigned to avoid duplicates at checkout.</li>
                    <li class="mb-2">After save, use <strong>Print barcode</strong> for shelf labels.</li>
                    <li>Images appear on the product list and POS grid (placeholder if none).</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const zone = document.getElementById('image_drop_zone');
    const input = document.getElementById('images_input');
    const preview = document.getElementById('image_preview');
    if (!zone || !input || !preview) return;

    const existingCount = <?= (int)count($existingImages) ?>;

    function countDeleteMarked() {
        return document.querySelectorAll('input[name="delete_image[]"]:checked').length;
    }

    function maxNewFiles() {
        return Math.max(0, 8 - existingCount + countDeleteMarked());
    }

    let dt = new DataTransfer();

    function renderPreview() {
        preview.innerHTML = '';
        for (let i = 0; i < dt.files.length; i++) {
            const f = dt.files[i];
            const tile = document.createElement('div');
            tile.className = 'image-preview-tile';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.alt = '';
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'rm';
            rm.setAttribute('aria-label', 'Remove');
            rm.innerHTML = '&times;';
            rm.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const next = new DataTransfer();
                for (let j = 0; j < dt.files.length; j++) {
                    if (j !== i) next.items.add(dt.files[j]);
                }
                dt = next;
                input.files = dt.files;
                renderPreview();
            });
            tile.appendChild(img);
            tile.appendChild(rm);
            preview.appendChild(tile);
        }
        input.files = dt.files;
    }

    function fileLooksLikeImage(f) {
        if (/^image\/(jpeg|jpg|png|webp)$/i.test(f.type)) return true;
        if ((!f.type || f.type === 'application/octet-stream') && /\.(jpe?g|png|webp)$/i.test(f.name)) return true;
        return false;
    }

    function addFiles(fileList) {
        const next = new DataTransfer();
        for (let j = 0; j < dt.files.length; j++) next.items.add(dt.files[j]);
        const cap = maxNewFiles();
        for (let k = 0; k < fileList.length && next.files.length < cap; k++) {
            const f = fileList[k];
            if (!fileLooksLikeImage(f)) continue;
            next.items.add(f);
        }
        dt = next;
        input.files = dt.files;
        renderPreview();
    }

    zone.addEventListener('click', function (e) {
        if (e.target.closest('.rm')) return;
        input.click();
    });
    zone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
    });
    input.addEventListener('change', function () {
        if (!input.files.length) return;
        addFiles(input.files);
        input.files = dt.files;
    });
    var form = document.getElementById('product_create_form');
    if (form) {
        form.addEventListener('submit', function () {
            try {
                input.files = dt.files;
            } catch (err) { /* ignore */ }
        });
    }
    ['dragenter', 'dragover'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) {
            e.preventDefault();
            zone.classList.remove('dragover');
        });
    });
    zone.addEventListener('drop', function (e) {
        addFiles(e.dataTransfer.files);
    });
})();
</script>
</body>
</html>
