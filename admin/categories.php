<?php
require_once "../database/db.php";

/* ADD */
if(isset($_POST['add'])){
    $pdo->prepare("INSERT INTO categories(name) VALUES(?)")
        ->execute([$_POST['name']]);
}

/* DELETEvbv */
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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h3 class="mb-0">Categories</h3>
</div>

<!-- FORM -->
<div class="card content-card p-3 mb-3">
    <form method="POST">

        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

        <input type="text" name="name"
               class="form-control form-control-lg"
               value="<?= $edit['name'] ?? '' ?>"
               placeholder="Category Name"
               required>

        <button class="btn btn-primary mt-3"
                name="<?= $edit ? 'update' : 'add' ?>">
            <?= $edit ? 'Update' : 'Add' ?>
        </button>

    </form>
</div>

<!-- TABLE -->
<div class="card content-card p-3">

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle mb-0">
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Action</th>
</tr>

<?php foreach($categories as $c): ?>
<tr>
    <td><?= $c['id'] ?></td>
    <td><?= $c['name'] ?></td>
    <td>
        <a href="dashboard.php?page=categories&edit=<?= $c['id'] ?>"
           class="btn btn-primary btn-sm">Edit</a>

        <a href="dashboard.php?page=categories&delete=<?= $c['id'] ?>"
           class="btn btn-danger btn-sm"
           onclick="return confirm('Delete?')">
           Delete
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>
</div>

</div>