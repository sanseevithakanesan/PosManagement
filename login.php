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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
<div class="row justify-content-center">
<div class="col-md-4">

<div class="card p-4">
    <h4 class="text-center">Login here!</h4>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" class="form-control mt-3" placeholder="Email" required>
        <input type="password" name="password" class="form-control mt-3" placeholder="Password" required>
        <button class="btn btn-primary w-100 mt-3">Login</button>
    </form>

    <a href="register.php" class="d-block text-center mt-3">
        Create Account
    </a>

</div>

</div>
</div>
</div>

</body>
</html>