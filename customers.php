<?php
require_once 'config/config.php';
checkAuth();

$pageTitle = 'Customers Management';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if ($action == 'add' || $action == 'edit') {
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            
            if ($action == 'add') {
                $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $address]);
                
                $success = "Customer added successfully";
                logAudit($_SESSION['user_id'], 'create', 'customers', "Added customer: $name");
                
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $address, $id]);
                
                $success = "Customer updated successfully";
                logAudit($_SESSION['user_id'], 'update', 'customers', "Updated customer: $name");
            }
            
        } elseif ($action == 'delete') {
            $id = $_POST['id'];
            
            // Check if customer has sales
            $stmt = $conn->prepare("SELECT COUNT(*) FROM sales WHERE customer_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cannot delete customer with sales history");
            }
            
            $stmt = $conn->prepare("UPDATE customers SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            $success = "Customer deleted successfully";
            logAudit($_SESSION['user_id'], 'delete', 'customers', "Deleted customer ID: $id");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get customer for editing
$customer = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        redirect('/customers.php');
    }
}

// Get customer details for viewing
if ($action == 'view' && isset($_GET['id'])) {
    // Get customer info
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        redirect('/customers.php');
    }
    
    // Get customer's sales history
    $stmt = $conn->prepare("SELECT s.*, u.username as cashier_name 
                           FROM sales s 
                           LEFT JOIN users u ON s.created_by = u.id 
                           WHERE s.customer_id = ? 
                           ORDER BY s.created_at DESC");
    $stmt->execute([$_GET['id']]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all customers for listing
if ($action == 'list') {
    $stmt = $conn->prepare("SELECT c.*, 
                                  (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.id) as sale_count,
                                  (SELECT SUM(net_amount) FROM sales s WHERE s.customer_id = c.id) as total_spent 
                           FROM customers c 
                           WHERE c.status = 1 
                           ORDER BY c.name");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'app/views/layout/header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($action == 'list'): ?>
    <!-- Customers List -->
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">All Customers</h5>
                <a href="?action=add" class="btn btn-primary" 
                   style="background: #0f62fe; border: none;">
                    <i class="fas fa-plus"></i> Add New Customer
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Total Sales</th>
                            <th>Total Spent</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo $item['email']; ?></td>
                                <td><?php echo $item['phone']; ?></td>
                                <td><?php echo $item['sale_count']; ?></td>
                                <td>Rs<?php echo formatCurrency($item['total_spent'] ?? 0); ?></td>
                                <td>
                                    <a href="?action=view&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?action=edit&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteCustomer(<?php echo $item['id']; ?>, '<?php echo $item['name']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
<?php elseif ($action == 'view'): ?>
    <!-- View Customer -->
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Customer Details</h6>
                    <p class="mb-1"><strong>Name:</strong> <?php echo $customer['name']; ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo $customer['email']; ?></p>
                    <p class="mb-1"><strong>Phone:</strong> <?php echo $customer['phone']; ?></p>
                    <p class="mb-1"><strong>Address:</strong> <?php echo $customer['address']; ?></p>
                </div>
            </div>
            
            <h6>Sales History</h6>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Cashier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo $sale['invoice_no']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($sale['created_at'])); ?></td>
                                <td>Rs<?php echo formatCurrency($sale['net_amount']); ?></td>
                                <td><?php echo ucfirst($sale['payment_method']); ?></td>
                                <td><?php echo $sale['cashier_name']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <a href="customers.php" class="btn btn-secondary">Back to List</a>
                <a href="?action=edit&id=<?php echo $customer['id']; ?>" 
                   class="btn btn-warning">
                    <i class="fas fa-edit me-2"></i> Edit Customer
                </a>
            </div>
        </div>
    </div>
    
<?php else: ?>
    <!-- Add/Edit Customer Form -->
    <div class="row">
        <div class="col-md-6">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <form method="POST">
                        <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   value="<?php echo $customer ? $customer['name'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $customer ? $customer['email'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo $customer ? $customer['phone'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" 
                                      rows="3"><?php echo $customer ? $customer['address'] : ''; ?></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" 
                                    style="background: #0f62fe; border: none;">
                                <?php echo $action == 'edit' ? 'Update' : 'Add'; ?> Customer
                            </button>
                            <a href="customers.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function deleteCustomer(id, name) {
    Swal.fire({
        title: 'Delete Customer',
        text: `Are you sure you want to delete ${name}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=delete';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php include 'app/views/layout/footer.php'; ?>