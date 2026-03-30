<?php
  session_start();
  require_once "../database/db.php";
  $page = $_GET['page'] ?? 'home';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { overflow-x: hidden; }

        .sidebar {
            width: 240px;
            height: 100vh;
            position: fixed;
            background: #212529;
            padding-top: 20px;
        }

        .sidebar a {
            display: block;
            color: #fff;
            padding: 12px 18px;
            text-decoration: none;
        }

        .sidebar a:hover {
            background: #0d6efd;
        }

        .main {
            margin-left: 240px;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main { margin-left: 0; }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h4 class="text-white text-center">POS ADMIN</h4>
    <a href="dashboard.php?page=home">🏠 Dashboard</a>
    <a href="dashboard.php?page=categories">📂 Categories</a>
    <a href="dashboard.php?page=products">🛒 Products</a>
    <a href="dashboard.php?page=customers">👥 Customers</a>
    <a href="dashboard.php?page=pos">📦 Billing</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<!-- MAIN -->
<div class="main">

<?php
// ================= HOME DASHBOARD =================
if($page == 'home'){

    $sales = $pdo->query("SELECT SUM(total_amount) as total FROM orders")->fetch()['total'] ?? 0;
    $products = $pdo->query("SELECT COUNT(*) as total FROM products")->fetch()['total'];
    $users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
?>

    <nav class="navbar navbar-light bg-light shadow-sm mb-4">
        <span class="navbar-brand">Dashboard</span>
        <span class="badge bg-success">Admin</span>
    </nav>

    <div class="row">

        <div class="col-md-4 mb-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5>Total Sales</h5>
                    <h3>₹ <?= $sales ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5>Products</h5>
                    <h3><?= $products ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5>Users</h5>
                    <h3><?= $users ?></h3>
                </div>
            </div>
        </div>

    </div>

<?php
}

// ================= CATEGORIES PAGE =================
if($page == 'categories'){
  require_once"categories.php";
}

// ================= PRODUCTS (placeholder) =================
if($page == 'products'){
  require_once"products.php";
}

if($page == 'customers'){
  require_once"customers.php";
}

if($page == 'pos'){
  require_once"pos.php";
}
if($page == 'logout'){
  session_destroy();
  require_once dirname(__DIR__) . "/index.php";
 
  exit;
}

?>

</div>

</body>
</html>