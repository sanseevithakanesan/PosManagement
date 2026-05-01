<?php
  require_once "../includes/auth.php";
  require_once "../database/db.php";
  $page = $_GET['page'] ?? 'home';

  // Role based access control
  if ($_SESSION['role'] == 'cashier' && !in_array($page, ['home', 'pos'])) {
      $page = 'home';
  }

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

  if (
      $page === 'reports'
      && isset($_GET['pdf']) && $_GET['pdf'] === '1'
  ) {
      require __DIR__ . '/report_pdf_export.php';
      exit;
  }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="app-body">
<div class="container-fluid px-0">
    <div class="row g-0">
        <aside class="col-lg-2 d-none d-lg-block admin-sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php?page=home" class="sidebar-logo">
                    <i class="fa-solid fa-bolt-lightning"></i>
                    <span>POS ADMIN</span>
                </a>
            </div>

            <div class="sidebar-user-profile">
                <div class="sidebar-avatar-wrapper">
                    <img src="../assets/images/avatar.png" class="sidebar-avatar" alt="User" width="50" height="50">
                    <div class="status-indicator"></div>
                </div>
                <div class="user-info">
                    <span class="user-info-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                    <div class="d-flex align-items-center">
                        <span class="user-info-role"><?= htmlspecialchars($_SESSION['role'] ?? 'Staff') ?></span>
                        <span class="ms-2 badge rounded-pill bg-success" style="padding: 3px; font-size: 0;">Online</span>
                    </div>
                </div>
            </div>
            
            <nav class="nav flex-column flex-grow-1">
                <a href="dashboard.php?page=home" class="nav-link <?= $page == 'home' ? 'active' : '' ?>">
                    <i class="fa-solid fa-house"></i>Dashboard
                </a>
                <?php if($_SESSION['role'] === 'admin'): ?>
                <a href="dashboard.php?page=categories" class="nav-link <?= $page == 'categories' ? 'active' : '' ?>">
                    <i class="fa-solid fa-list"></i>Categories
                </a>
                <a href="dashboard.php?page=products" class="nav-link <?= $page == 'products' ? 'active' : '' ?>">
                    <i class="fa-solid fa-box"></i>Products
                </a>
                <a href="dashboard.php?page=purchases" class="nav-link <?= $page == 'purchases' ? 'active' : '' ?>">
                    <i class="fa-solid fa-cart-shopping"></i>Purchases
                </a>
                <a href="dashboard.php?page=income" class="nav-link <?= $page == 'income' ? 'active' : '' ?>">
                    <i class="fa-solid fa-hand-holding-dollar"></i>Income
                </a>
                <a href="dashboard.php?page=expenses" class="nav-link <?= $page == 'expenses' ? 'active' : '' ?>">
                    <i class="fa-solid fa-money-bill-transfer"></i>Expenses
                </a>
                <a href="dashboard.php?page=payroll" class="nav-link <?= $page == 'payroll' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear"></i>Payroll
                </a>
                <a href="dashboard.php?page=reports" class="nav-link <?= $page == 'reports' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-line"></i>Reports
                </a>
                <a href="dashboard.php?page=customers" class="nav-link <?= $page == 'customers' ? 'active' : '' ?>">
                    <i class="fa-solid fa-address-book"></i>Customers
                </a>
                <?php endif; ?>
                <a href="dashboard.php?page=pos" class="nav-link <?= $page == 'pos' ? 'active' : '' ?>">
                    <i class="fa-solid fa-cash-register"></i>Billing
                </a>
            </nav>

            <div class="mt-auto mb-4 px-3">
                <div class="border-top border-white border-opacity-10 mb-3"></div>
                <a href="../logout.php" class="nav-link logout-link">
                    <i class="fa-solid fa-right-from-bracket"></i>Log Out
                </a>
            </div>
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
                if($page == 'purchases'){
                    require_once "purchases.php";
                }
                if($page == 'income'){
                    require_once "income.php";
                }
                if($page == 'expenses'){
                    require_once "expenses.php";
                }
                if($page == 'payroll'){
                    require_once "payroll.php";
                }
                if($page == 'reports'){
                    require_once "reports.php";
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

<div class="offcanvas offcanvas-start admin-mobile-offcanvas text-white" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header border-bottom border-light border-opacity-10">
        <div class="sidebar-logo">
            <i class="fa-solid fa-bolt-lightning text-primary me-2"></i>
            <span>POS NAVIGATION</span>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div class="sidebar-user-profile py-4">
            <div class="sidebar-avatar-wrapper">
                <img src="../assets/images/avatar.png" class="sidebar-avatar" alt="User" width="50" height="50">
                <div class="status-indicator"></div>
            </div>
            <div class="user-info">
                <span class="user-info-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                <div class="d-flex align-items-center">
                    <span class="user-info-role"><?= htmlspecialchars($_SESSION['role'] ?? 'Staff') ?></span>
                    <span class="ms-2 badge rounded-pill bg-success" style="padding: 3px; font-size: 0;">Online</span>
                </div>
            </div>
        </div>
        <nav class="nav flex-column flex-grow-1 px-3">
            <a href="dashboard.php?page=home" class="nav-link <?= $page == 'home' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i>Dashboard
            </a>
            <?php if($_SESSION['role'] === 'admin'): ?>
            <a href="dashboard.php?page=categories" class="nav-link <?= $page == 'categories' ? 'active' : '' ?>">
                <i class="fa-solid fa-list"></i>Categories
            </a>
            <a href="dashboard.php?page=products" class="nav-link <?= $page == 'products' ? 'active' : '' ?>">
                <i class="fa-solid fa-box"></i>Products
            </a>
            <a href="dashboard.php?page=purchases" class="nav-link <?= $page == 'purchases' ? 'active' : '' ?>">
                <i class="fa-solid fa-cart-shopping"></i>Purchases
            </a>
            <a href="dashboard.php?page=income" class="nav-link <?= $page == 'income' ? 'active' : '' ?>">
                <i class="fa-solid fa-hand-holding-dollar"></i>Income
            </a>
            <a href="dashboard.php?page=expenses" class="nav-link <?= $page == 'expenses' ? 'active' : '' ?>">
                <i class="fa-solid fa-money-bill-transfer"></i>Expenses
            </a>
            <a href="dashboard.php?page=payroll" class="nav-link <?= $page == 'payroll' ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i>Payroll
            </a>
            <a href="dashboard.php?page=reports" class="nav-link <?= $page == 'reports' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>Reports
            </a>
            <a href="dashboard.php?page=customers" class="nav-link <?= $page == 'customers' ? 'active' : '' ?>">
                <i class="fa-solid fa-address-book"></i>Customers
            </a>
            <?php endif; ?>
            <a href="dashboard.php?page=pos" class="nav-link <?= $page == 'pos' ? 'active' : '' ?>">
                <i class="fa-solid fa-cash-register"></i>Billing
            </a>
        </nav>
        <div class="px-3 mt-auto mb-4">
            <a href="../logout.php" class="nav-link logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>Log Out
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>