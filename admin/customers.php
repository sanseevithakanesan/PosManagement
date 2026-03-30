<?php
require_once "../database/db.php";

/* =====================
   ADD CUSTOMER
===================== */
if(isset($_POST['add'])){

    $stmt = $pdo->prepare("
        INSERT INTO customers(name,phone,email,address)
        VALUES(?,?,?,?)
    ");

    $stmt->execute([
        $_POST['name'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['address']
    ]);

    header("Location: dashboard.php?page=customers");
    exit;
}


/* =====================
   UPDATE CUSTOMER
===================== */
if(isset($_POST['update'])){

    $stmt = $pdo->prepare("
        UPDATE customers
        SET name=?, phone=?, email=?, address=?
        WHERE id=?
    ");

    $stmt->execute([
        $_POST['name'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['address'],
        $_POST['id']
    ]);

    header("Location: dashboard.php?page=customers");
    exit;
}


/* =====================
   DELETE CUSTOMER
===================== */
if(isset($_GET['delete'])){

    $stmt = $pdo->prepare("DELETE FROM customers WHERE id=?");
    $stmt->execute([$_GET['delete']]);

    header("Location: dashboard.php?page=customers");
    exit;
}


/* =====================
   EDIT FETCH
===================== */
$edit = null;

if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}


/* =====================
   LIST CUSTOMERS
===================== */
$customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();
?>

<h3>👤 Customers</h3>

<!-- FORM -->
<div class="card p-3 mb-3">

<form method="POST">

<input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

<input type="text" name="name" class="form-control mb-2"
       placeholder="Customer Name"
       value="<?= $edit['name'] ?? '' ?>" required>

<input type="text" name="phone" class="form-control mb-2"
       placeholder="Phone"
       value="<?= $edit['phone'] ?? '' ?>" required>

<input type="email" name="email" class="form-control mb-2"
       placeholder="Email"
       value="<?= $edit['email'] ?? '' ?>">

<textarea name="address" class="form-control mb-2"
          placeholder="Address"><?= $edit['address'] ?? '' ?></textarea>

<button class="btn btn-success"
        name="<?= $edit ? 'update' : 'add' ?>">

    <?= $edit ? 'Update Customer' : 'Add Customer' ?>

</button>

<?php if($edit): ?>
    <a href="dashboard.php?page=customers"
       class="btn btn-secondary">
       Cancel
    </a>
<?php endif; ?>

</form>

</div>

<!-- TABLE -->
<div class="card p-3">

<table class="table table-bordered table-hover">

<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Phone</th>
    <th>Email</th>
    <th>Address</th>
    <th>Action</th>
</tr>

<?php foreach($customers as $c): ?>

<tr>
    <td><?= $c['id'] ?></td>
    <td><?= $c['name'] ?></td>
    <td><?= $c['phone'] ?></td>
    <td><?= $c['email'] ?></td>
    <td><?= $c['address'] ?></td>

    <td>
        <a href="dashboard.php?page=customers&edit=<?= $c['id'] ?>"
           class="btn btn-primary btn-sm">Edit</a>

        <a href="dashboard.php?page=customers&delete=<?= $c['id'] ?>"
           onclick="return confirm('Delete customer?')"
           class="btn btn-danger btn-sm">
           Delete
        </a>
    </td>
</tr>

<?php endforeach; ?>

</table>

</div>