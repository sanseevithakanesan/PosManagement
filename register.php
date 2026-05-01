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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | POS Management</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Shared Professional Styles */
        .auth-body {
            background: url('assets/images/login-bg.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .auth-body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            z-index: 0;
        }

        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
        }

        .auth-card-pro {
            background: #ffffff;
            border-radius: 4px;
            overflow: visible;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .auth-header-teal {
            background: #00d2d3;
            height: 100px;
            position: relative;
            border-radius: 4px 4px 0 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .auth-header-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid #ffffff;
            background: #fff;
            position: absolute;
            bottom: -40px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #00d2d3;
            font-size: 2rem;
        }

        .auth-body-content {
            padding: 55px 40px 40px;
        }

        .auth-title {
            text-align: center;
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .auth-subtitle {
            text-align: center;
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 25px;
        }

        .input-group-custom {
            margin-bottom: 15px;
            position: relative;
        }

        .input-group-custom i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #48dbfb;
            z-index: 10;
        }

        .input-group-custom input, 
        .input-group-custom select {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #eee;
            background: #fdfdfd;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .input-group-custom input:focus,
        .input-group-custom select:focus {
            border-color: #48dbfb;
            outline: none;
            box-shadow: 0 0 8px rgba(72, 219, 251, 0.2);
        }

        .btn-auth-teal {
            background: #00d2d3;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            width: 100%;
            margin-top: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-auth-teal:hover {
            background: #01a3a4;
            transform: translateY(-1px);
        }

        .auth-footer-links {
            margin-top: 25px;
            text-align: center;
            font-size: 0.85rem;
        }

        .auth-footer-links a {
            color: #00d2d3;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body class="auth-body">

    <div class="auth-container">
        
        <div class="auth-card-pro">
            <div class="auth-header-teal">
                <div class="auth-header-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
            </div>
            
            <div class="auth-body-content">
                <h2 class="auth-title">Create Account</h2>
                <p class="auth-subtitle">Set up your POS admin profile</p>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger py-2" style="font-size: 0.85rem;"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group-custom">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="name" placeholder="Full Name" required autofocus>
                    </div>

                    <div class="input-group-custom">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                    
                    <div class="input-group-custom">
                        <i class="fa-solid fa-user-tag"></i>
                        <select name="role" required>
                            <option value="" disabled selected>Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                        </select>
                    </div>

                    <div class="input-group-custom">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>

                    <button type="submit" name="register" class="btn-auth-teal">
                        Register Account
                    </button>
                    
                    <div class="auth-footer-links">
                        <span class="text-muted">Already have an account?</span> 
                        <a href="login.php">Login Now</a>
                    </div>
                </form>
            </div>
        </div>

    </div>

</body>
</html>