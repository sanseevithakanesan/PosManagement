<?php
require_once "database/db.php";

if(isset($_POST['register'])){

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // check email exists
    $check = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $check->execute([$email]);

    if($check->rowCount() > 0){
        $error = "Email already exists!";
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO users(name,email,password)
            VALUES(?,?,?)
        ");

        $stmt->execute([$name,$email,$password]);

        header("Location: index.php?success=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
<div class="row justify-content-center">
<div class="col-md-4">

<div class="card p-4">
    <h4 class="text-center">Register</h4>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">

        <input type="text" name="name" class="form-control mt-2" placeholder="Name" required>

        <input type="email" name="email" class="form-control mt-2" placeholder="Email" required>

        <input type="password" name="password" class="form-control mt-2" placeholder="Password" required>

        <button name="register" class="btn btn-success w-100 mt-3">
            Register
        </button>

    </form>

    <a href="login.php" class="d-block text-center mt-3">
        Already have account? Login
    </a>

</div>

</div>
</div>
</div>

</body>
</html>