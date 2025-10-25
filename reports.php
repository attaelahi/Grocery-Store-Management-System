<?php
require_once 'config/config.php';
checkAuth();

if ($_SESSION['user_role'] != 'admin') {
    redirect('/dashboard.php');
}

$pageTitle = 'Reports';

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Get cashiers for filtering
$stmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'cashier' ORDER BY username");
$stmt->execute();
$cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filtering
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE status = 1 ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize data arrays
$data = [];
$summary = [];

try {
    switch ($report_type) {
        case 'sales':
            // Sales Report
            $sql = "SELECT s.*, u.username as cashier_name, c.name as customer_name 
                    FROM sales s 
                    LEFT JOIN users u ON s.created_by = u.id 
                    LEFT JOIN customers c ON s.customer_id = c.id 
                    WHERE DATE(s.created_at) BETWEEN ? AND ?";
            
            // Apply cashier filter
            if (!empty($_GET['cashier_id'])) {
                $sql .= " AND s.created_by = " . intval($_GET['cashier_id']);
            }
            
            // Apply payment status filter
            if (!empty($_GET['payment_status'])) {
                $sql .= " AND s.payment_status = '" . $conn->quote($_GET['payment_status']) . "'";
            }
            
            $sql .= " ORDER BY s.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summary
            $summary = [
                'total_sales' => count($data),
                'total_amount' => array_sum(array_column($data, 'total_amount')),
                'total_tax' => array_sum(array_column($data, 'tax_amount')),
                'total_discount' => array_sum(array_column($data, 'discount_amount')),
                'net_amount' => array_sum(array_column($data, 'net_amount')),
                'total_paid' => array_sum(array_column($data, 'paid_amount')),
                'total_due' => array_sum(array_column($data, 'due_amount'))
            ];
            break;
            
        case 'products':
            // Product Sales Report
            $sql = "SELECT p.name, p.sku, c.name as category_name, 
                           COUNT(si.id) as sale_count, 
                           SUM(si.quantity) as total_quantity,
                           SUM(si.total_amount) as total_amount,
                           AVG(si.unit_price) as avg_price 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    LEFT JOIN sale_items si ON p.id = si.product_id 
                    LEFT JOIN sales s ON si.sale_id = s.id 
                    WHERE DATE(s.created_at) BETWEEN ? AND ?";
            
            // Apply category filter
            if (!empty($_GET['category_id'])) {
                $sql .= " AND p.category_id = " . intval($_GET['category_id']);
            }
            
            $sql .= " GROUP BY p.id ORDER BY total_quantity DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summary
            $summary = [
                'total_products' => count($data),
                'total_quantity' => array_sum(array_column($data, 'total_quantity')),
                'total_amount' => array_sum(array_column($data, 'total_amount'))
            ];
            break;
            
        case 'profit':
            // Profit Report
            $sql = "SELECT DATE(s.created_at) as date,
                           SUM(s.total_amount) as sales_amount,
                           SUM(si.quantity * p.cost_price) as cost_amount,
                           SUM(e.amount) as expense_amount 
                    FROM sales s 
                    LEFT JOIN sale_items si ON s.id = si.sale_id 
                    LEFT JOIN products p ON si.product_id = p.id 
                    LEFT JOIN expenses e ON DATE(e.expense_date) = DATE(s.created_at) 
                    WHERE DATE(s.created_at) BETWEEN ? AND ? 
                    GROUP BY DATE(s.created_at) 
                    ORDER BY date DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summary
            $summary = [
                'total_sales' => array_sum(array_column($data, 'sales_amount')),
                'total_cost' => array_sum(array_column($data, 'cost_amount')),
                'total_expenses' => array_sum(array_column($data, 'expense_amount')),
                'net_profit' => array_sum(array_map(function($row) {
                    return $row['sales_amount'] - $row['cost_amount'] - $row['expense_amount'];
                }, $data))
            ];
            break;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

include 'app/views/layout/header.php';
?>

<!-- Report Filters -->
<div class="card mb-4" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <div class="card-body">
        <form method="GET" class="row align-items-end">
            <div class="col-md-2">
                <label for="report_type" class="form-label">Report Type</label>
                <select class="form-control" id="report_type" name="report_type">
                    <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>
                        Sales Report
                    </option>
                    <option value="products" <?php echo $report_type == 'products' ? 'selected' : ''; ?>>
                        Product Sales
                    </option>
                    <option value="profit" <?php echo $report_type == 'profit' ? 'selected' : ''; ?>>
                        Profit Report
                    </option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" 
                       value="<?php echo $start_date; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" 
                       value="<?php echo $end_date; ?>">
            </div>
            
            <?php if ($report_type == 'sales'): ?>
                <div class="col-md-2">
                    <label for="cashier_id" class="form-label">Cashier</label>
                    <select class="form-control" id="cashier_id" name="cashier_id">
                        <option value="">All Cashiers</option>
                        <?php foreach ($cashiers as $cashier): ?>
                            <option value="<?php echo $cashier['id']; ?>" 
                                    <?php echo isset($_GET['cashier_id']) && $_GET['cashier_id'] == $cashier['id'] ? 'selected' : ''; ?>>
                                <?php echo $cashier['username']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="payment_status" class="form-label">Payment Status</label>
                    <select class="form-control" id="payment_status" name="payment_status">
                        <option value="">All Status</option>
                        <option value="paid" <?php echo isset($_GET['payment_status']) && $_GET['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="partial" <?php echo isset($_GET['payment_status']) && $_GET['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="pending" <?php echo isset($_GET['payment_status']) && $_GET['payment_status'] == 'pending' ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                </div>
            <?php endif; ?>
            
            <?php if ($report_type == 'products'): ?>
                <div class="col-md-2">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo isset($_GET['category_id']) && $_GET['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo $category['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100" 
                        style="background: #0f62fe; border: none;">
                    <i class="fas fa-search me-2"></i> Generate Report
                </button>
            </div>
            
            <div class="col-md-2">
                <button type="button" class="btn btn-success w-100" onclick="exportToCSV()">
                    <i class="fas fa-download me-2"></i> Export CSV
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Summary -->
<div class="row mb-4">
    <?php if ($report_type == 'sales'): ?>
        <div class="col-md-2">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Sales</h6>
                    <h3 class="mb-0"><?php echo $summary['total_sales']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Net Amount</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['net_amount']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-success" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="mb-2">Amount Paid</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['total_paid']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-danger" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="mb-2">Amount Due</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['total_due']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Tax Amount</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['total_tax']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Discount</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['total_discount']); ?></h3>
                </div>
            </div>
        </div>
    <?php elseif ($report_type == 'products'): ?>
        <div class="col-md-4">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Products Sold</h6>
                    <h3 class="mb-0"><?php echo $summary['total_products']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Quantity</h6>
                    <h3 class="mb-0"><?php echo $summary['total_quantity']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Amount</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['total_amount']); ?></h3>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-md-3">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Sales</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['total_sales']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Cost</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['total_cost']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Expenses</h6>
                    <h3 class="mb-0">Rs<?php echo formatCurrency($summary['total_expenses']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Net Profit</h6>
                    <h3 class="mb-0" style="color: <?php echo $summary['net_profit'] >= 0 ? '#00b894' : '#ff6b6b'; ?>">
                        Rs<?php echo formatCurrency($summary['net_profit']); ?>
                    </h3>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Report Data -->
<div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <?php if ($report_type == 'sales'): ?>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Cashier</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Due</th>
                            <th>Tax</th>
                            <th>Discount</th>
                            <th>Actions</th>
                        <?php elseif ($report_type == 'products'): ?>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Sales Count</th>
                            <th>Quantity</th>
                            <th>Avg. Price</th>
                            <th>Total</th>
                        <?php else: ?>
                            <th>Date</th>
                            <th>Sales</th>
                            <th>Cost</th>
                            <th>Expenses</th>
                            <th>Profit</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php if ($report_type == 'sales'): ?>
                                <td><?php echo $row['invoice_no']; ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td><?php echo $row['customer_name'] ?? 'Walk-in'; ?></td>
                                <td><?php echo $row['cashier_name']; ?></td>
                                <td><?php echo ucfirst($row['payment_method']); ?></td>
                                <td>
                                    <?php 
                                    $paymentStatus = $row['payment_status'] ?? 'paid';
                                    $statusColor = '';
                                    $statusText = '';
                                    if ($paymentStatus == 'paid') {
                                        $statusColor = 'success';
                                        $statusText = 'PAID';
                                    } elseif ($paymentStatus == 'partial') {
                                        $statusColor = 'warning';
                                        $statusText = 'PARTIAL';
                                    } else {
                                        $statusColor = 'danger';
                                        $statusText = 'UNPAID';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusColor; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td>Rs<?php echo formatCurrency($row['net_amount']); ?></td>
                                <td class="text-success fw-bold">Rs<?php echo formatCurrency($row['paid_amount'] ?? $row['net_amount']); ?></td>
                                <td class="<?php echo ($row['due_amount'] ?? 0) > 0 ? 'text-danger fw-bold' : ''; ?>">Rs<?php echo formatCurrency($row['due_amount'] ?? 0); ?></td>
                                <td>Rs<?php echo formatCurrency($row['tax_amount']); ?></td>
                                <td>Rs<?php echo formatCurrency($row['discount_amount']); ?></td>
                                <td>
                                    <a href="app/controllers/view_invoice.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button onclick="printInvoice(<?php echo $row['id']; ?>)" class="btn btn-sm btn-secondary" title="Print Invoice">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php if (($row['payment_status'] ?? 'paid') != 'paid'): ?>
                                    <button onclick="collectPayment(<?php echo $row['id']; ?>, '<?php echo $row['invoice_no']; ?>', <?php echo $row['due_amount'] ?? 0; ?>)" class="btn btn-sm btn-success" title="Collect Payment">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button onclick="deleteSale(<?php echo $row['id']; ?>, '<?php echo $row['invoice_no']; ?>')" class="btn btn-sm btn-danger" title="Delete Sale">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            <?php elseif ($report_type == 'products'): ?>
                                <td><?php echo $row['name']; ?></td>
                                <td><?php echo $row['sku']; ?></td>
                                <td><?php echo $row['category_name']; ?></td>
                                <td><?php echo $row['sale_count']; ?></td>
                                <td><?php echo $row['total_quantity']; ?></td>
                                <td>Rs<?php echo formatCurrency($row['avg_price']); ?></td>
                                <td>Rs<?php echo formatCurrency($row['total_amount']); ?></td>
                            <?php else: ?>
                                <td><?php echo $row['date']; ?></td>
                                <td>Rs<?php echo formatCurrency($row['sales_amount']); ?></td>
                                <td>Rs<?php echo formatCurrency($row['cost_amount']); ?></td>
                                <td>Rs<?php echo formatCurrency($row['expense_amount']); ?></td>
                                <td style="color: <?php echo ($row['sales_amount'] - $row['cost_amount'] - $row['expense_amount']) >= 0 ? '#00b894' : '#ff6b6b'; ?>">
                                    Rs<?php echo formatCurrency($row['sales_amount'] - $row['cost_amount'] - $row['expense_amount']); ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Collect Payment Modal -->
<div class="modal fade" id="collectPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Collect Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="payment_sale_id">
                <div class="mb-3">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" class="form-control" id="payment_invoice_no" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Due Amount</label>
                    <input type="text" class="form-control" id="payment_due_amount" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="payment_amount" step="0.01" min="0" required>
                    <small class="text-muted">Enter amount to collect (max: due amount)</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select class="form-control" id="payment_method">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="mobile">Mobile Payment</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Note (Optional)</label>
                    <textarea class="form-control" id="payment_note" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitPayment()">Collect Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
function printInvoice(saleId) {
    const printWindow = window.open(`app/controllers/view_invoice.php?id=${saleId}`, '_blank');
    printWindow.onload = function() {
        printWindow.print();
    };
}

function collectPayment(saleId, invoiceNo, dueAmount) {
    document.getElementById('payment_sale_id').value = saleId;
    document.getElementById('payment_invoice_no').value = invoiceNo;
    document.getElementById('payment_due_amount').value = 'Rs' + dueAmount.toFixed(2);
    document.getElementById('payment_amount').value = dueAmount.toFixed(2);
    document.getElementById('payment_amount').max = dueAmount;
    
    const modal = new bootstrap.Modal(document.getElementById('collectPaymentModal'));
    modal.show();
}

function submitPayment() {
    const saleId = document.getElementById('payment_sale_id').value;
    const amount = parseFloat(document.getElementById('payment_amount').value);
    const dueAmount = parseFloat(document.getElementById('payment_due_amount').value.replace('Rs', ''));
    const paymentMethod = document.getElementById('payment_method').value;
    const note = document.getElementById('payment_note').value;
    
    if (!amount || amount <= 0) {
        alert('Please enter a valid payment amount');
        return;
    }
    
    if (amount > dueAmount) {
        alert('Payment amount cannot exceed due amount');
        return;
    }
    
    // Send payment collection request
    fetch('app/controllers/collect_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            sale_id: saleId,
            amount: amount,
            payment_method: paymentMethod,
            note: note
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Response text:', text);
        const data = JSON.parse(text);
        if (data.success) {
            alert('Payment collected successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to collect payment'));
            console.error('Error details:', data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error collecting payment. Please check console for details.');
    });
}

function deleteSale(saleId, invoiceNo) {
    if (!confirm(`Are you sure you want to delete sale ${invoiceNo}? This action cannot be undone.`)) {
        return;
    }
    
    // Send delete request
    fetch('app/controllers/delete_sale.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            sale_id: saleId
        })
    })
    .then(response => {
        console.log('Delete response status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Delete response text:', text);
        const data = JSON.parse(text);
        if (data.success) {
            alert('Sale deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete sale'));
            console.error('Error details:', data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting sale. Please check console for details.');
    });
}

function exportToCSV() {
    let csv = [];
    const rows = document.querySelectorAll("table tr");
    
    for (const row of rows) {
        const cols = row.querySelectorAll("td,th");
        const csvRow = [];
        
        for (const col of cols) {
            // Remove currency symbol and commas from numbers
            let text = col.textContent.trim();
            if (text.startsWith('$')) {
                text = text.substring(1).replace(/,/g, '');
            }
            csvRow.push('"' + text + '"');
        }
        
        csv.push(csvRow.join(","));
    }
    
    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `${report_type}_report_${start_date}_${end_date}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.getElementById('report_type').addEventListener('change', function() {
    document.querySelector('form').submit();
});
</script>

<?php include 'app/views/layout/footer.php'; ?>