<?php
require_once 'config/config.php';
checkAuth();

$pageTitle = 'Dashboard';

// Fetch today's statistics
$today = date('Y-m-d');

// Today's sales
$stmt = $conn->prepare("SELECT COUNT(*) as total_sales, COALESCE(SUM(net_amount), 0) as sales_amount 
                        FROM sales WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$sales = $stmt->fetch(PDO::FETCH_ASSOC);

// Total products
$stmt = $conn->prepare("SELECT COUNT(*) as total_products FROM products WHERE status = 1");
$stmt->execute();
$products = $stmt->fetch(PDO::FETCH_ASSOC);

// Low stock products
$stmt = $conn->prepare("SELECT COUNT(*) as low_stock FROM products 
                        WHERE stock_quantity <= reorder_level AND status = 1");
$stmt->execute();
$lowStock = $stmt->fetch(PDO::FETCH_ASSOC);

// Today's expenses
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as expense_amount FROM expenses 
                        WHERE expense_date = ?");
$stmt->execute([$today]);
$expenses = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent sales
$stmt = $conn->prepare("SELECT s.*, c.name as customer_name, u.username as cashier_name 
                        FROM sales s 
                        LEFT JOIN customers c ON s.customer_id = c.id 
                        LEFT JOIN users u ON s.created_by = u.id 
                        ORDER BY s.created_at DESC LIMIT 5");
$stmt->execute();
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock alerts
$stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.stock_quantity <= p.reorder_level AND p.status = 1 
                        ORDER BY p.stock_quantity ASC LIMIT 5");
$stmt->execute();
$lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'app/views/layout/header.php';
?>

<!-- Dashboard Cards -->
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Today's Sales</h6>
                        <h3 class="mb-0" style="color: #0f62fe;">Rs<?php echo formatCurrency($sales['sales_amount']); ?></h3>
                        <small class="text-muted"><?php echo $sales['total_sales']; ?> orders</small>
                    </div>
                    <div style="background: rgba(15,98,254,0.1); padding: 15px; border-radius: 50%;">
                        <i class="fas fa-shopping-cart" style="color: #0f62fe; font-size: 24px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Products</h6>
                        <h3 class="mb-0" style="color: #00b894;"><?php echo $products['total_products']; ?></h3>
                        <small class="text-muted">Active products</small>
                    </div>
                    <div style="background: rgba(0,184,148,0.1); padding: 15px; border-radius: 50%;">
                        <i class="fas fa-box" style="color: #00b894; font-size: 24px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Low Stock Alerts</h6>
                        <h3 class="mb-0" style="color: #ff6b6b;"><?php echo $lowStock['low_stock']; ?></h3>
                        <small class="text-muted">Products below reorder level</small>
                    </div>
                    <div style="background: rgba(255,107,107,0.1); padding: 15px; border-radius: 50%;">
                        <i class="fas fa-exclamation-triangle" style="color: #ff6b6b; font-size: 24px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Today's Expenses</h6>
                        <h3 class="mb-0" style="color: #6c5ce7;">Rs<?php echo formatCurrency($expenses['expense_amount']); ?></h3>
                        <small class="text-muted">Total expenses</small>
                    </div>
                    <div style="background: rgba(108,92,231,0.1); padding: 15px; border-radius: 50%;">
                        <i class="fas fa-wallet" style="color: #6c5ce7; font-size: 24px;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <h5 class="card-title mb-3">Quick Actions</h5>
                <a href="pos.php" class="btn btn-primary me-2" style="background: #0f62fe; border: none;">
                    <i class="fas fa-cash-register me-2"></i> New Sale
                </a>
                <?php if ($_SESSION['user_role'] == 'admin'): ?>
                <a href="products.php?action=add" class="btn btn-success me-2" style="background: #00b894; border: none;">
                    <i class="fas fa-plus me-2"></i> Add Product
                </a>
                <a href="purchases.php?action=add" class="btn btn-info me-2" style="background: #0984e3; border: none; color: white;">
                    <i class="fas fa-shopping-basket me-2"></i> New Purchase
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Sales -->
    <div class="col-md-6 mb-4">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <h5 class="card-title mb-3">Recent Sales</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Cashier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><?php echo $sale['invoice_no']; ?></td>
                                <td><?php echo $sale['customer_name'] ?? 'Walk-in'; ?></td>
                                <td>Rs<?php echo formatCurrency($sale['net_amount']); ?></td>
                                <td><?php echo $sale['cashier_name']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Products -->
    <div class="col-md-6 mb-4">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <h5 class="card-title mb-3">Low Stock Alerts</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Reorder Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockProducts as $product): ?>
                            <tr>
                                <td><?php echo $product['name']; ?></td>
                                <td><?php echo $product['category_name']; ?></td>
                                <td>
                                    <span class="badge bg-danger"><?php echo $product['stock_quantity']; ?></span>
                                </td>
                                <td><?php echo $product['reorder_level']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'app/views/layout/footer.php'; ?>