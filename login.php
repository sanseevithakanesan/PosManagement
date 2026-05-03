<?php
session_start();
require_once "database/db.php";

if(isset($_POST['email'])){//uiuiuoi

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Login | POS Management</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 (for grid/utility if needed, but styling is custom) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Login Page Specialized Styles - Embedded for reliability */
        .login-body {
            background: url('assets/images/login-bg.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            margin: 0;
        }

        .login-body::before {
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

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .login-title-top {
            color: #ffffff;
            text-align: center !important;
            text-transform: uppercase;
            letter-spacing: 4px;
            font-weight: 300;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .login-card-pro {
            background: #ffffff;
            border-radius: 4px;
            overflow: visible;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .login-header-teal {
            background: #00d2d3;
            height: 120px;
            position: relative;
            border-radius: 4px 4px 0 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-avatar {
            width: 100px !important;
            height: 100px !important;
            border-radius: 50%;
            border: 4px solid #ffffff;
            background: #fff;
            position: absolute;
            bottom: -50px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            left: 50%;
            transform: translateX(-50%);
        }

        .login-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
            display: block;
        }

        .login-body-content {
            padding: 70px 40px 40px;
            text-align: center;
        }

        .login-here-text {
            font-size: 1.2rem;
            color: #444;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .input-group-custom {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group-custom i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #48dbfb;
        }

        .input-group-custom input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #eee;
            background: #fdfdfd;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .input-group-custom input:focus {
            border-color: #48dbfb;
            outline: none;
            box-shadow: 0 0 8px rgba(72, 219, 251, 0.2);
        }

        .btn-login-teal {
            background: #00d2d3;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px auto;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.2rem;
        }

        .btn-login-teal:hover {
            background: #01a3a4;
            transform: scale(1.05);
        }

        .login-footer-links {
            margin-top: 25px;
            font-size: 0.85rem;
        }

        .login-footer-links a {
            color: #00d2d3;
            text-decoration: none;
        }

        .copyright-text {
            color: rgba(255, 255, 255, 0.6);
            text-align: center;
            font-size: 0.75rem;
            margin-top: 30px;
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body class="login-body">

    <div class="login-container">
        <h1 class="login-title-top">Login Form</h1>
        
        <div class="login-card-pro">
            <div class="login-header-teal">
                <div class="login-avatar">
                    <img src="assets/images/avatar.png" alt="User Avatar">
                </div>
            </div>
            
            <div class="login-body-content">
                <div class="login-here-text">
                    Login Here <i class="fa-solid fa-rotate"></i>
                </div>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger py-2" style="font-size: 0.85rem;"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group-custom">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email" required autofocus>
                    </div>
                    
                    <div class="input-group-custom">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>

                    <button type="submit" class="btn-login-teal">
                        <i class="fa-solid fa-right-to-bracket"></i>
                    </button>
                    
                    <div class="login-footer-links">
                        <a href="#">I forgot my password</a>
                        <span class="text-muted mx-2">|</span>
                        <a href="register.php">Create Account</a>
                    </div>
                </form>
            </div>
        </div>

        <p class="copyright-text">
            &copy; 2026 Latest Login Form. All rights reserved. Design by <a href="#" class="text-white text-decoration-none opacity-75">sandigicore</a>
        </p>
    </div>

</body>
</html>