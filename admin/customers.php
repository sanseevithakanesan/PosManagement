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

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h3 class="mb-0 text-dark fw-bold">Customers</h3>
        <p class="text-muted small mb-0">Manage customer details and contact profiles.</p>
    </div>
</div>

<!-- FORM -->
<div class="card content-card p-4 mb-4 shadow-sm border-0 border-start border-4 border-primary">
    <h5 class="mb-3 text-secondary"><?= $edit ? 'Edit Customer Profile' : 'Add New Customer' ?></h5>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        
        <div class="row g-3 text-start">
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Customer Name *</label>
                <input type="text" name="name" class="form-control"
                       placeholder="Full Name"
                       value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Phone Number *</label>
                <input type="text" name="phone" class="form-control"
                       placeholder="e.g. 0773029020"
                       value="<?= htmlspecialchars($edit['phone'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold">Email Address</label>
                <input type="email" name="email" class="form-control"
                       placeholder="name@example.com"
                       value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label text-muted small fw-bold">Physical Address</label>
                <textarea name="address" class="form-control" rows="2"
                          placeholder="Complete address/location"><?= htmlspecialchars($edit['address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-start">
            <button class="btn btn-primary px-4 fw-bold shadow-sm" name="<?= $edit ? 'update' : 'add' ?>">
                <?= $edit ? 'Save Changes' : 'Register Customer' ?>
            </button>
            <?php if($edit): ?>
                <a href="dashboard.php?page=customers" class="btn btn-white text-secondary ms-3 border shadow-sm">Cancel Editing</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- TABLE -->
<div class="card content-card p-3 shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 bg-white">
            <thead class="table-light text-muted small text-uppercase">
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th class="text-center" style="width: 150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($customers as $c): ?>
                <tr>
                    <td class="text-dark fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= htmlspecialchars($c['phone']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($c['email'] ?: 'N/A') ?></td>
                    <td class="text-muted"><small><?= htmlspecialchars($c['address'] ?: 'N/A') ?></small></td>
                    <td>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="dashboard.php?page=customers&edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary shadow-sm px-3 text-decoration-none">Edit</a>
                            <a href="dashboard.php?page=customers&delete=<?= $c['id'] ?>" onclick="return confirm('Delete this customer profile?')" class="btn btn-sm btn-outline-danger shadow-sm px-3 text-decoration-none">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($customers)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No customers registered yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>