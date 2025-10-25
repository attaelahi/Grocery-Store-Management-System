<?php
function isActive($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return $current == $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="d-flex" style="min-height: 100vh;">
        <div class="sidebar" style="background: #0b1226; width: 250px; position: fixed; height: 100vh; transition: all 0.3s;">
            <div class="p-3">
                <h3 class="text-white text-center mb-4">
                    <i class="fas fa-shopping-cart"></i> <?php echo APP_NAME; ?>
                </h3>
                
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a href="dashboard.php" class="nav-link <?php echo isActive('dashboard.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item mb-2">
                        <a href="pos.php" class="nav-link <?php echo isActive('pos.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-cash-register me-2"></i> POS
                        </a>
                    </li>
                    
                    <?php if ($_SESSION['user_role'] == 'admin'): ?>
                    <li class="nav-item mb-2">
                        <a href="products.php" class="nav-link <?php echo isActive('products.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-box me-2"></i> Products
                        </a>
                    </li>
                    
                    <li class="nav-item mb-2">
                        <a href="categories.php" class="nav-link <?php echo isActive('categories.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-tags me-2"></i> Categories
                        </a>
                    </li>
                    
                    <li class="nav-item mb-2">
                        <a href="suppliers.php" class="nav-link <?php echo isActive('suppliers.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-truck me-2"></i> Suppliers
                        </a>
                    </li>
                    
                    <li class="nav-item mb-2">
                        <a href="purchases.php" class="nav-link <?php echo isActive('purchases.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-shopping-basket me-2"></i> Purchases
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item mb-2">
                        <a href="customers.php" class="nav-link <?php echo isActive('customers.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-users me-2"></i> Customers
                        </a>
                    </li>
                    
                    <?php if ($_SESSION['user_role'] == 'admin'): ?>
                    <li class="nav-item mb-2">
                        <a href="expenses.php" class="nav-link <?php echo isActive('expenses.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-wallet me-2"></i> Expenses
                        </a>
                    </li>
                    
                    <li class="nav-item mb-2">
                        <a href="reports.php" class="nav-link <?php echo isActive('reports.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                    </li>
                    
                    <li class="nav-item mb-2">
                        <a href="users.php" class="nav-link <?php echo isActive('users.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-user-cog me-2"></i> Users
                        </a>
                    </li>
                    
                    <li class="nav-item mb-2">
                        <a href="settings.php" class="nav-link <?php echo isActive('settings.php'); ?>" 
                           style="color: #fff; border-radius: 8px; padding: 12px;">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Main content -->
        <div style="margin-left: 250px; width: calc(100% - 250px);">
            <!-- Top navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white" style="box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="container-fluid">
                    <!-- <button class="btn" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button> -->
                    
                    <div class="ms-auto d-flex align-items-center">
                        <div class="dropdown">
                            <button class="btn dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo $_SESSION['username']; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <!-- Page content -->
            <div class="container-fluid p-4">
                <?php if (isset($pageTitle)): ?>
                    <h2 class="mb-4"><?php echo $pageTitle; ?></h2>
                <?php endif; ?>