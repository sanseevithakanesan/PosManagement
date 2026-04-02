<?php
session_start();

// user already loginvnbvn?
if(isset($_SESSION['user_id'])){
    header("Location: admin/dashboard.php");
} else {
    header("Location: login.php");
}
exit;
?>