<?php
require_once "../database/db.php";

/* ADD */
if(isset($_POST['add'])){
    $pdo->prepare("INSERT INTO categories(name) VALUES(?)")
        ->execute([$_POST['name']]);
}

/* DELETE */
if(isset($_GET['delete'])){
    $pdo->prepare("DELETE FROM categories WHERE id=?")
        ->execute([$_GET['delete']]);
}

/* EDIT */
$edit = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}

/* UPDATE */
if(isset($_POST['update'])){
    $pdo->prepare("UPDATE categories SET name=? WHERE id=?")
        ->execute([$_POST['name'], $_POST['id']]);
}

/* LIST */
$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h3 class="mb-0 text-dark fw-bold">Categories</h3>
        <p class="text-muted small mb-0">Manage product classification and groupings.</p>
    </div>
</div>

<!-- FORM -->
<div class="card content-card p-4 mb-4 shadow-sm border-0 border-start border-4 border-primary">
    <h5 class="mb-3 text-secondary"><?= $edit ? 'Edit Category Item' : 'Create New Category' ?></h5>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="row align-items-end g-3">
            <div class="col-md-8">
                <label class="form-label text-muted small fw-bold">Category Name *</label>
                <input type="text" name="name"
                       class="form-control form-control-lg border-primary"
                       value="<?= htmlspecialchars($edit['name'] ?? '') ?>"
                       placeholder="e.g. Beverages, Electronics, Hardware"
                       required>
            </div>
            
            <div class="col-md-4">
                <button class="btn btn-primary btn-lg w-100 fw-bold shadow-sm"
                        name="<?= $edit ? 'update' : 'add' ?>">
                    <?= $edit ? 'Save Changes' : 'Add Category' ?>
                </button>
            </div>
        </div>
        
        <?php if($edit): ?>
            <div class="mt-2 text-end">
                <a href="dashboard.php?page=categories" class="btn btn-light btn-sm text-secondary">Cancel Edit</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- TABLE -->
<div class="card content-card p-3 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 bg-white">
            <thead class="table-light text-muted small text-uppercase">
                <tr>
                    <th>Category Name</th>
                    <th class="text-center" style="width: 150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($categories as $c): ?>
                <tr>
                    <td class="fw-semibold text-dark"><?= htmlspecialchars($c['name']) ?></td>
                    <td>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="dashboard.php?page=categories&edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary shadow-sm px-3 text-decoration-none">Edit</a>
                            <a href="dashboard.php?page=categories&delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger shadow-sm px-3 text-decoration-none" onclick="return confirm('Delete this category permanently?')">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($categories)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">No categories created yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>