<?php 
  ob_start();
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

  // Global AJAX handler for product status check
  if (isset($_GET['check_orders']) && isset($_GET['product_id'])) {
      if (ob_get_length()) ob_clean();
      header('Content-Type: application/json');
      $productId = (int)$_GET['product_id'];
      try {
          $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
          $checkStmt->execute([$productId]);
          $orderCount = $checkStmt->fetchColumn();
          echo json_encode([
              'has_orders' => ($orderCount > 0),
              'order_count' => (int)$orderCount
          ]);
      } catch(PDOException $e) {
          echo json_encode(['has_orders' => false, 'error' => $e->getMessage()]);
      }
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
                <a href="dashboard.php?page=accounts" class="nav-link <?= $page == 'accounts' ? 'active' : '' ?>">
                    <i class="fa-solid fa-vault"></i>Accounts
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
            <nav class="navbar navbar-expand-lg topbar-glass px-2 px-sm-3 py-2">
                <div class="container-fluid px-1 px-sm-0">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-icon-glass d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
                            <i class="fa-solid fa-bars-staggered"></i>
                        </button>
                        <span class="navbar-brand mb-0 fw-bold d-none d-sm-inline-block">Admin Panel</span>
                        <span class="navbar-brand mb-0 fw-bold d-sm-none ms-1">POS</span>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-none d-md-flex align-items-center gap-2 me-2">
                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-3">
                                <i class="fa-solid fa-circle fa-2xs me-1"></i> System Active
                            </span>
                        </div>
                        
                        <div class="dropdown">
                            <button class="btn btn-profile-top shadow-sm" type="button" data-bs-toggle="dropdown">
                                <img src="../assets/images/avatar.png" alt="User" class="rounded-circle" width="28" height="28">
                                <div class="d-none d-md-block text-start">
                                    <div class="fw-bold smallest line-height-1" style="font-size: 0.75rem;"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
                                </div>
                                <i class="fa-solid fa-chevron-down fa-2xs text-muted"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2">
                                <li class="px-3 py-2 border-bottom mb-2">
                                    <div class="fw-bold small"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
                                    <div class="text-muted smallest text-uppercase"><?= htmlspecialchars($_SESSION['role'] ?? 'Staff') ?></div>
                                </li>
                                <li><a class="dropdown-item" href="dashboard.php?page=home"><i class="fa-solid fa-house me-2 opacity-50"></i>Home</a></li>
                                <li><a class="dropdown-item" href="../logout.php text-danger"><i class="fa-solid fa-right-from-bracket me-2 opacity-50"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="p-3 p-md-4">

                <?php
                if($page == 'home'){
                    $sales = $pdo->query("SELECT SUM(total_amount) as total FROM orders")->fetch()['total'] ?? 0;
                    $products = $pdo->query("SELECT COUNT(*) as total FROM products WHERE deleted_at IS NULL")->fetch()['total'];
                    $users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
                ?>
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h4 class="mb-1 fw-bold">Dashboard Overview</h4>
                                <p class="text-muted mb-0 small">Quick summary of your POS data</p>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-light border btn-sm rounded-pill px-3 d-sm-none" onclick="location.reload()">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                                <button class="btn btn-light border btn-sm rounded-pill px-3 d-none d-sm-inline-block" onclick="location.reload()">
                                    <i class="fa-solid fa-rotate me-1"></i> Refresh Data
                                </button>
                            </div>
                        </div>
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
                if($page == 'accounts'){
                    require_once "accounts.php";
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
    <div class="offcanvas-header border-bottom border-white border-opacity-10 py-4 px-4">
        <div class="sidebar-logo">
            <i class="fa-solid fa-bolt-lightning text-primary me-2"></i>
            <span>POS NAVIGATION</span>
        </div>
        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div class="mobile-user-box mx-3 my-4 p-3 rounded-4 bg-white bg-opacity-5 border border-white border-opacity-10">
            <div class="d-flex align-items-center gap-3">
                <div class="sidebar-avatar-wrapper m-0" style="width: 50px; height: 50px;">
                    <img src="../assets/images/avatar.png" class="sidebar-avatar" alt="User">
                    <div class="status-indicator"></div>
                </div>
                <div class="user-info">
                    <span class="user-info-name fs-6"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                    <span class="user-info-role opacity-50"><?= htmlspecialchars($_SESSION['role'] ?? 'Staff') ?></span>
                </div>
            </div>
        </div>

        <nav class="nav flex-column flex-grow-1 px-3 mobile-nav">
            <a href="dashboard.php?page=home" class="nav-link py-3 mb-1 <?= $page == 'home' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i>Dashboard
            </a>
            <?php if($_SESSION['role'] === 'admin'): ?>
            <a href="dashboard.php?page=categories" class="nav-link py-3 mb-1 <?= $page == 'categories' ? 'active' : '' ?>">
                <i class="fa-solid fa-list"></i>Categories
            </a>
            <a href="dashboard.php?page=products" class="nav-link py-3 mb-1 <?= $page == 'products' ? 'active' : '' ?>">
                <i class="fa-solid fa-box"></i>Products
            </a>
            <a href="dashboard.php?page=purchases" class="nav-link py-3 mb-1 <?= $page == 'purchases' ? 'active' : '' ?>">
                <i class="fa-solid fa-cart-shopping"></i>Purchases
            </a>
            <a href="dashboard.php?page=income" class="nav-link py-3 mb-1 <?= $page == 'income' ? 'active' : '' ?>">
                <i class="fa-solid fa-hand-holding-dollar"></i>Income
            </a>
            <a href="dashboard.php?page=expenses" class="nav-link py-3 mb-1 <?= $page == 'expenses' ? 'active' : '' ?>">
                <i class="fa-solid fa-money-bill-transfer"></i>Expenses
            </a>
            <a href="dashboard.php?page=payroll" class="nav-link py-3 mb-1 <?= $page == 'payroll' ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i>Payroll
            </a>
            <a href="dashboard.php?page=reports" class="nav-link py-3 mb-1 <?= $page == 'reports' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>Reports
            </a>
            <a href="dashboard.php?page=customers" class="nav-link py-3 mb-1 <?= $page == 'customers' ? 'active' : '' ?>">
                <i class="fa-solid fa-address-book"></i>Customers
            </a>
            <a href="dashboard.php?page=accounts" class="nav-link py-3 mb-1 <?= $page == 'accounts' ? 'active' : '' ?>">
                <i class="fa-solid fa-vault"></i>Accounts
            </a>
            <?php endif; ?>
            <a href="dashboard.php?page=pos" class="nav-link py-3 mb-1 <?= $page == 'pos' ? 'active' : '' ?>">
                <i class="fa-solid fa-cash-register"></i>Billing
            </a>
        </nav>
        
        <div class="p-4 mt-auto">
            <a href="../logout.php" class="btn btn-danger w-100 py-3 rounded-4 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="fw-bold">LOGOUT</span>
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>