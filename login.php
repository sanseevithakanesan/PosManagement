<?php
session_start();
require_once "database/db.php";

if(isset($_POST['email'])){

    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if($user && password_verify($password, $user['password'])){

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        header("Location: admin/dashboard.php");
        exit;

    } else {
        $error = "Invalid login!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">

<div class="container auth-shell d-flex align-items-center py-4">
<div class="row justify-content-center w-100">
<div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

<div class="card auth-card p-4 p-md-5">
    <h4 class="text-center auth-brand mb-1">POS Management</h4>
    <p class="text-center text-muted mb-4">Sign in to continue</p>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" class="d-grid gap-3">
        <input type="email" name="email" class="form-control form-control-lg" placeholder="Email address" required>
        <input type="password" name="password" class="form-control form-control-lg" placeholder="Password" required>
        <button class="btn btn-primary btn-lg w-100">Login</button>
    </form>

    <a href="register.php" class="d-block text-center mt-3 text-decoration-none">
        Create Account
    </a>

</div>

</div>
</div>
</div>

</body>
</html>