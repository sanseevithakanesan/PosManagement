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


<style>
    .category-header {
        background: linear-gradient(135deg, #00d2d3 0%, #01a3a4 100%);
        padding: 20px;
        border-radius: 12px;
        color: white;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0, 210, 211, 0.2);
    }
    .modern-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        transition: transform 0.2s;
    }
    .action-btn {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.3s;
        border: none;
    }
    .btn-edit { background: #e0f2fe; color: #0369a1; }
    .btn-edit:hover { background: #00d2d3; color: white; }
    .btn-delete { background: #fee2e2; color: #b91c1c; }
    .btn-delete:hover { background: #ff4757; color: white; }
    
    .category-input-group {
        position: relative;
    }
    .category-input-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #00d2d3;
    }
    .category-input-group input {
        padding-left: 45px;
        border-radius: 8px;
    }
</style>

<div class="category-header d-flex justify-content-between align-items-center">
    <div>
        <h3 class="mb-1 fw-bold"><i class="fa-solid fa-layer-group me-2"></i> Categories</h3>
        <p class="mb-0 opacity-75">Manage your product classifications</p>
    </div>
</div>

<!-- FORM -->
<div class="card modern-card mb-4">
    <div class="card-body p-4">
        <h5 class="card-title text-dark fw-bold mb-4">
            <i class="fa-solid <?= $edit ? 'fa-pen-to-square' : 'fa-plus-circle' ?> text-primary me-2"></i>
            <?= $edit ? 'Edit Category Item' : 'Create New Category' ?>
        </h5>
        
        <form method="POST">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            
            <div class="row align-items-end g-3">
                <div class="col-md-9">
                    <label class="form-label text-muted small fw-bold">CATEGORY NAME</label>
                    <div class="category-input-group">
                        <i class="fa-solid fa-tag"></i>
                        <input type="text" name="name"
                               class="form-control form-control-lg border-light bg-light"
                               value="<?= htmlspecialchars($edit['name'] ?? '') ?>"
                               placeholder="Enter category name..."
                               required>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <button class="btn <?= $edit ? 'btn-info text-white' : 'btn-primary' ?> btn-lg w-100 fw-bold py-2"
                            name="<?= $edit ? 'update' : 'add' ?>">
                        <?= $edit ? '<i class="fa-solid fa-save me-2"></i>Update' : '<i class="fa-solid fa-plus me-2"></i>Create' ?>
                    </button>
                </div>
            </div>
            
            <?php if($edit): ?>
                <div class="mt-3 text-start">
                    <a href="dashboard.php?page=categories" class="text-secondary small text-decoration-none">
                        <i class="fa-solid fa-times me-1"></i> Cancel Editing
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- TABLE -->
<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="mb-0 fw-bold text-muted text-uppercase small">Category List</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Category Name</th>
                    <th class="text-center" style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($categories as $c): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-light p-2 me-3 text-primary">
                                <i class="fa-solid fa-folder-open"></i>
                            </div>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($c['name']) ?></span>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-2">
                            <a href="dashboard.php?page=categories&edit=<?= $c['id'] ?>" 
                               class="action-btn btn-edit" 
                               title="Edit">
                               <i class="fa-solid fa-pen-nib"></i>
                            </a>
                            <a href="dashboard.php?page=categories&delete=<?= $c['id'] ?>" 
                               class="action-btn btn-delete" 
                               title="Delete"
                               onclick="return confirm('Confirm deletion?')">
                               <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($categories)): ?>
                    <tr><td colspan="2" class="text-center text-muted py-5">
                        <i class="fa-solid fa-box-open d-block mb-2 fs-2 opacity-25"></i>
                        No categories found.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>