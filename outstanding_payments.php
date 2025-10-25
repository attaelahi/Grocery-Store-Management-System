<?php
require_once 'config/config.php';
checkAuth();

$pageTitle = 'Outstanding Payments';

// Get filter parameters
$customer_filter = isset($_GET['customer_id']) ? $_GET['customer_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Handle payment collection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'collect_payment') {
    try {
        $sale_id = intval($_POST['sale_id']);
        $amount = floatval($_POST['amount']);
        $payment_method = sanitize($_POST['payment_method']);
        $notes = sanitize($_POST['notes']);
        
        // Get sale details
        $stmt = $conn->prepare("SELECT net_amount, paid_amount, due_amount, payment_status FROM sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            throw new Exception("Sale not found");
        }
        
        if ($amount <= 0 || $amount > $sale['due_amount']) {
            throw new Exception("Invalid payment amount");
        }
        
        $conn->beginTransaction();
        
        // Update sale
        $new_paid = $sale['paid_amount'] + $amount;
        $new_due = $sale['due_amount'] - $amount;
        $new_status = ($new_due == 0) ? 'paid' : 'partial';
        
        $stmt = $conn->prepare("UPDATE sales SET paid_amount = ?, due_amount = ?, payment_status = ? WHERE id = ?");
        $stmt->execute([$new_paid, $new_due, $new_status, $sale_id]);
        
        // Insert payment history
        $stmt = $conn->prepare("INSERT INTO payment_history (sale_id, amount, payment_method, notes, received_by) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sale_id, $amount, $payment_method, $notes, $_SESSION['user_id']]);
        
        $conn->commit();
        
        $success = "Payment collected successfully";
        logAudit($_SESSION['user_id'], 'payment', 'sales', "Collected payment of $amount for sale #$sale_id");
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get all customers
$stmt = $conn->prepare("SELECT id, name FROM customers WHERE status = 1 ORDER BY name");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query for outstanding sales
$sql = "SELECT s.*, c.name as customer_name, u.username as cashier_name 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN users u ON s.created_by = u.id 
        WHERE s.payment_status IN ('partial', 'pending')";

if ($customer_filter) {
    $sql .= " AND s.customer_id = " . intval($customer_filter);
}

if ($status_filter) {
    $sql .= " AND s.payment_status = '" . $conn->quote($status_filter) . "'";
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$outstanding_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_due = 0;
$total_partial = 0;
$total_unpaid = 0;

foreach ($outstanding_sales as $sale) {
    $total_due += $sale['due_amount'];
    if ($sale['payment_status'] == 'partial') {
        $total_partial += $sale['due_amount'];
    } else {
        $total_unpaid += $sale['due_amount'];
    }
}

include 'app/views/layout/header.php';
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-money-bill-wave me-2"></i> Outstanding Payments</h4>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-danger" style="border-radius: 10px;">
            <div class="card-body">
                <h6 class="card-title">Total Outstanding</h6>
                <h3>Rs<?php echo formatCurrency($total_due); ?></h3>
                <small><?php echo count($outstanding_sales); ?> sales</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning" style="border-radius: 10px;">
            <div class="card-body">
                <h6 class="card-title">Partial Payments</h6>
                <h3>Rs<?php echo formatCurrency($total_partial); ?></h3>
                <small>Partially paid sales</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-secondary" style="border-radius: 10px;">
            <div class="card-body">
                <h6 class="card-title">Unpaid Sales</h6>
                <h3>Rs<?php echo formatCurrency($total_unpaid); ?></h3>
                <small>Completely unpaid</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-control">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>" 
                                <?php echo ($customer_filter == $customer['id']) ? 'selected' : ''; ?>>
                            <?php echo $customer['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Payment Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="partial" <?php echo ($status_filter == 'partial') ? 'selected' : ''; ?>>Partial</option>
                    <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Unpaid</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Outstanding Sales Table -->
<div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outstanding_sales)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No outstanding payments found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($outstanding_sales as $sale): ?>
                            <tr>
                                <td><?php echo $sale['invoice_no']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($sale['created_at'])); ?></td>
                                <td><?php echo $sale['customer_name'] ?? 'Walk-in'; ?></td>
                                <td>Rs<?php echo formatCurrency($sale['net_amount']); ?></td>
                                <td>Rs<?php echo formatCurrency($sale['paid_amount']); ?></td>
                                <td><strong>Rs<?php echo formatCurrency($sale['due_amount']); ?></strong></td>
                                <td>
                                    <?php if ($sale['payment_status'] == 'partial'): ?>
                                        <span class="badge bg-warning">Partial</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="collectPayment(<?php echo htmlspecialchars(json_encode($sale)); ?>)">
                                        <i class="fas fa-dollar-sign"></i> Collect
                                    </button>
                                    <a href="app/controllers/view_invoice.php?id=<?php echo $sale['id']; ?>" 
                                       class="btn btn-sm btn-info" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Collection Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Collect Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="collect_payment">
                    <input type="hidden" name="sale_id" id="payment_sale_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Invoice Number</label>
                        <input type="text" class="form-control" id="payment_invoice" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Due Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="text" class="form-control" id="payment_due" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount to Collect <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" class="form-control" name="amount" id="payment_amount" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <small class="text-muted">Maximum: <span id="max_amount"></span></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Collect Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

function collectPayment(sale) {
    document.getElementById('payment_sale_id').value = sale.id;
    document.getElementById('payment_invoice').value = sale.invoice_no;
    document.getElementById('payment_due').value = parseFloat(sale.due_amount).toFixed(2);
    document.getElementById('payment_amount').value = '';
    document.getElementById('payment_amount').max = sale.due_amount;
    document.getElementById('max_amount').textContent = 'Rs' + parseFloat(sale.due_amount).toFixed(2);
    
    paymentModal.show();
}

// Validate amount
document.getElementById('payment_amount').addEventListener('input', function() {
    const max = parseFloat(this.max);
    const val = parseFloat(this.value);
    
    if (val > max) {
        this.value = max;
    }
});
</script>

<?php include 'app/views/layout/footer.php'; ?>
