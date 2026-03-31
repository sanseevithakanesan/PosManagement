<?php
  session_start();
  require_once "../database/db.php";
  $page = $_GET['page'] ?? 'home';

  if (
      $page === 'pos'
      && $_SERVER['REQUEST_METHOD'] === 'POST'
      && ($_POST['pos_ajax'] ?? '') === '1'
  ) {
      require_once dirname(__DIR__) . '/includes/cart_schema.php';
      ensure_cart_discount_columns($pdo);
      require __DIR__ . '/pos_cart_ajax.php';
      exit;
  }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="app-body">
<div class="container-fluid px-0">
    <div class="row g-0">
        <aside class="col-lg-2 d-none d-lg-block admin-sidebar p-3">
            <h4 class="text-white text-center py-2 mb-3">POS ADMIN</h4>
            <nav class="nav flex-column">
                <a href="dashboard.php?page=home" class="nav-link <?= $page == 'home' ? 'active' : '' ?>">Dashboard</a>
                <a href="dashboard.php?page=categories" class="nav-link <?= $page == 'categories' ? 'active' : '' ?>">Categories</a>
                <a href="dashboard.php?page=products" class="nav-link <?= $page == 'products' ? 'active' : '' ?>">Products</a>
                <a href="dashboard.php?page=customers" class="nav-link <?= $page == 'customers' ? 'active' : '' ?>">Customers</a>
                <a href="dashboard.php?page=pos" class="nav-link <?= $page == 'pos' ? 'active' : '' ?>">Billing</a>
                <a href="../logout.php" class="nav-link mt-2">Logout</a>
            </nav>
        </aside>

        <div class="col-12 col-lg-10 min-vh-100">
            <nav class="navbar navbar-expand-lg topbar-glass px-3 py-2">
                <div class="container-fluid px-0">
                    <button class="btn btn-outline-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
                        Menu
                    </button>
                    <span class="navbar-brand mb-0 fw-semibold">Admin Panel</span>
                    <span class="badge bg-success">Admin</span>
                </div>
            </nav>

            <main class="p-3 p-md-4 p-lg-4">
                <div class="offcanvas offcanvas-start admin-sidebar text-white" tabindex="-1" id="mobileMenu">
                    <div class="offcanvas-header border-bottom border-secondary">
                        <h5 class="offcanvas-title">POS ADMIN</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
                    </div>
                    <div class="offcanvas-body">
                        <nav class="nav flex-column">
                            <a href="dashboard.php?page=home" class="nav-link <?= $page == 'home' ? 'active' : '' ?>">Dashboard</a>
                            <a href="dashboard.php?page=categories" class="nav-link <?= $page == 'categories' ? 'active' : '' ?>">Categories</a>
                            <a href="dashboard.php?page=products" class="nav-link <?= $page == 'products' ? 'active' : '' ?>">Products</a>
                            <a href="dashboard.php?page=customers" class="nav-link <?= $page == 'customers' ? 'active' : '' ?>">Customers</a>
                            <a href="dashboard.php?page=pos" class="nav-link <?= $page == 'pos' ? 'active' : '' ?>">Billing</a>
                            <a href="../logout.php" class="nav-link mt-2">Logout</a>
                        </nav>
                    </div>
                </div>

                <?php
                if($page == 'home'){
                    $sales = $pdo->query("SELECT SUM(total_amount) as total FROM orders")->fetch()['total'] ?? 0;
                    $products = $pdo->query("SELECT COUNT(*) as total FROM products")->fetch()['total'];
                    $users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
                ?>
                    <div class="mb-3">
                        <h4 class="mb-1">Dashboard Overview</h4>
                        <p class="text-muted mb-0">Quick summary of your POS data</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="card metric-card text-bg-success h-100">
                                <div class="card-body">
                                    <h6 class="mb-2">Total Sales</h6>
                                    <h3 class="mb-0">Rs <?= number_format((float)$sales, 2) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="card metric-card text-bg-primary h-100">
                                <div class="card-body">
                                    <h6 class="mb-2">Products</h6>
                                    <h3 class="mb-0"><?= $products ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="card metric-card text-bg-warning h-100">
                                <div class="card-body">
                                    <h6 class="mb-2">Users</h6>
                                    <h3 class="mb-0"><?= $users ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                }

                if($page == 'categories'){
                    require_once "categories.php";
                }
                if($page == 'products'){
                    require_once "products.php";
                }
                if($page == 'customers'){
                    require_once "customers.php";
                }
                if($page == 'pos'){
                    require_once "pos.php";
                }
                ?>
            </main>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>