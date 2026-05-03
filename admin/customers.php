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


<style>
    /* Modern UI Components */
    .customers-header {
        background: linear-gradient(135deg, #00cec9 0%, #0984e3 100%);
        padding: 25px;
        border-radius: 15px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(0, 206, 201, 0.15);
    }
    .modern-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    
    .action-circle-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        transition: all 0.2s;
        border: none;
        margin: 0 2px;
    }
    .btn-edit-cust { background: #eff6ff; color: #3b82f6; }
    .btn-edit-cust:hover { background: #3b82f6; color: white; }
    .btn-delete-cust { background: #fef2f2; color: #ef4444; }
    .btn-delete-cust:hover { background: #ef4444; color: white; }
</style>

<div class="customers-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
        <h3 class="mb-1 fw-bold"><i class="fa-solid fa-address-book me-2"></i> Customers</h3>
        <p class="mb-0 opacity-75">Client Profiles and Relationship Data</p>
    </div>
    <a href="dashboard.php?page=customers" class="btn btn-light btn-lg fw-bold px-4 rounded-4 shadow-sm <?= $edit ? '' : 'disabled opacity-50' ?>">
        <i class="fa-solid fa-plus-circle me-1 text-info"></i> Register New
    </a>
</div>


<!-- FORM -->
<div class="card modern-card mb-5 border-0">
    <div class="card-body p-3 p-md-4">
        <h5 class="fw-bold mb-4 text-dark"><?= $edit ? '<i class="fa-solid fa-user-edit text-info me-2"></i>Modify Profile' : '<i class="fa-solid fa-user-plus text-info me-2"></i>New Registration' ?></h5>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            
            <div class="row g-4 text-start">
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label text-muted small fw-bold">Full Name *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-user text-muted"></i></span>
                        <input type="text" name="name" class="form-control border-0 bg-light" placeholder="e.g. John Doe" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label text-muted small fw-bold">Phone *</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-mobile-screen text-muted"></i></span>
                        <input type="text" name="phone" class="form-control border-0 bg-light" placeholder="e.g. 07XXXXXXXX" value="<?= htmlspecialchars($edit['phone'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label text-muted small fw-bold">Email</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                        <input type="email" name="email" class="form-control border-0 bg-light" placeholder="client@example.com" value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label text-muted small fw-bold">Physical Address</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-location-dot text-muted"></i></span>
                        <textarea name="address" class="form-control border-0 bg-light" rows="2" placeholder="Complete address..."><?= htmlspecialchars($edit['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex flex-column flex-sm-row gap-2">
                <button class="btn btn-info btn-lg px-5 fw-bold text-white shadow-sm" name="<?= $edit ? 'update' : 'add' ?>">
                    <?= $edit ? '<i class="fa-solid fa-save me-2"></i>Update Profile' : '<i class="fa-solid fa-check-circle me-2"></i>Confirm Register' ?>
                </button>
                <?php if($edit): ?>
                    <a href="dashboard.php?page=customers" class="btn btn-light btn-lg px-4 border-0">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>


<!-- TABLE -->
<div class="card modern-card overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="mb-0 fw-bold text-muted text-uppercase small">Client Directory</h6>
    </div>
    <div class="table-responsive table-responsive-stack">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-muted small text-uppercase">
                <tr>
                    <th class="ps-4">Full Name</th>
                    <th>Contact Phone</th>
                    <th>Email Address</th>
                    <th>Physical Location</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($customers as $c): ?>
                <tr>
                    <td class="ps-4" data-label="Name">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-info bg-opacity-10 text-info p-2 me-3 d-none d-md-flex" style="width:40px;height:40px;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-user-tag fs-5"></i>
                            </div>
                            <div class="text-dark fw-bold fs-6"><?= htmlspecialchars($c['name']) ?></div>
                        </div>
                    </td>
                    <td data-label="Phone"><span class="fw-semibold text-dark"><?= htmlspecialchars($c['phone']) ?></span></td>
                    <td data-label="Email"><span class="text-muted small"><?= htmlspecialchars($c['email'] ?: 'N/A') ?></span></td>
                    <td data-label="Address">
                        <div class="text-muted small" style="max-width: 250px;"><i class="fa-solid fa-location-arrow me-1 opacity-50 d-none d-md-inline"></i><?= htmlspecialchars($c['address'] ?: 'N/A') ?></div>
                    </td>
                    <td class="text-end pe-4" data-label="Actions">
                        <div class="d-flex justify-content-center justify-content-md-end gap-3 py-2 py-md-0">
                            <a href="dashboard.php?page=customers&edit=<?= $c['id'] ?>" class="action-circle-btn btn-edit-cust" style="width: 48px; height: 48px; font-size: 1.2rem;" title="Edit Profile">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <a href="dashboard.php?page=customers&delete=<?= $c['id'] ?>" 
                               onclick="return confirm('Permanently delete this customer record?')" 
                               class="action-circle-btn btn-delete-cust" style="width: 48px; height: 48px; font-size: 1.2rem;" title="Delete Profile">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($customers)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-users-slash fs-1 opacity-25 mb-3 d-block"></i>
                        No registered clients found.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>