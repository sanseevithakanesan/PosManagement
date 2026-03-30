<?php
session_start();

// user already login ah?
if(isset($_SESSION['user_id'])){
    header("Location: admin/dashboard.php");
} else {
    header("Location: login.php");
}
exit;
?>