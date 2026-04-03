<?php
require_once "database/db.php";

if(isset($_POST['register'])){

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'] ?? 'admin';

    // check email exists
    $check = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $check->execute([$email]);

    if($check->rowCount() > 0){
        $error = "Email already exists!";
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO users(name,email,password,role)
            VALUES(?,?,?,?)
        ");

        $stmt->execute([$name,$email,$password,$role]);

        header("Location: index.php?success=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">

<div class="container auth-shell d-flex align-items-center py-4">
<div class="row justify-content-center w-100">
<div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

<div class="card auth-card p-4 p-md-5">
    <h4 class="text-center auth-brand mb-1">Create Account</h4>
    <p class="text-center text-muted mb-4">Set up your POS admin profile</p>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" class="d-grid gap-2">

        <input type="text" name="name" class="form-control form-control-lg mt-2" placeholder="Full name" required>

        <input type="email" name="email" class="form-control form-control-lg mt-2" placeholder="Email address" required>

        <select name="role" class="form-select form-control-lg mt-2" required>
            <option value="" disabled selected>Select Role</option>
            <option value="admin">Admin</option>
            <option value="cashier">Cashier</option>
        </select>

        <input type="password" name="password" class="form-control form-control-lg mt-2" placeholder="Password" required>

        <button name="register" class="btn btn-primary btn-lg w-100 mt-3">
            Register
        </button>

    </form>

    <a href="login.php" class="d-block text-center mt-3 text-decoration-none">
        Already have account? Login
    </a>

</div>

</div>
</div>
</div>

</body>
</html>