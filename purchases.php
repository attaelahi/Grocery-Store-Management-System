<?php
require_once 'config/config.php';
checkAuth();

if ($_SESSION['user_role'] != 'admin') {
    redirect('/dashboard.php');
}

$pageTitle = 'Purchases Management';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success = $error = '';

// Get all active suppliers
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE status = 1 ORDER BY name");
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active products
$stmt = $conn->prepare("SELECT * FROM products WHERE status = 1 ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if ($action == 'add' || $action == 'edit') {
            $supplier_id = $_POST['supplier_id'];
            $reference_no = sanitize($_POST['reference_no']);
            $notes = sanitize($_POST['notes']);
            $items = json_decode($_POST['items'], true);
            
            if (empty($items)) {
                throw new Exception("Please add at least one product");
            }
            
            $total_amount = array_sum(array_map(function($item) {
                return $item['quantity'] * $item['cost_price'];
            }, $items));
            
            $conn->beginTransaction();
            
            if ($action == 'add') {
                $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, reference_no, total_amount, 
                                      status, notes, created_by) VALUES (?, ?, ?, 'pending', ?, ?)");
                $stmt->execute([
                    $supplier_id, $reference_no, $total_amount, $notes, $_SESSION['user_id']
                ]);
                
                $purchase_id = $conn->lastInsertId();
                
                // Add purchase items
                $stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, 
                                      cost_price, total_amount) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($items as $item) {
                    $item_total = $item['quantity'] * $item['cost_price'];
                    $stmt->execute([
                        $purchase_id, $item['product_id'], $item['quantity'], 
                        $item['cost_price'], $item_total
                    ]);
                }
                
                $success = "Purchase order created successfully";
                logAudit($_SESSION['user_id'], 'create', 'purchases', "Created purchase: $reference_no");
                
            } else {
                $id = $_POST['id'];
                
                // Check if purchase can be edited
                $stmt = $conn->prepare("SELECT status FROM purchases WHERE id = ?");
                $stmt->execute([$id]);
                $status = $stmt->fetchColumn();
                
                if ($status != 'pending') {
                    throw new Exception("Only pending purchases can be edited");
                }
                
                $stmt = $conn->prepare("UPDATE purchases SET supplier_id = ?, reference_no = ?, 
                                      total_amount = ?, notes = ? WHERE id = ?");
                $stmt->execute([$supplier_id, $reference_no, $total_amount, $notes, $id]);
                
                // Delete old items
                $stmt = $conn->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
                $stmt->execute([$id]);
                
                // Add new items
                $stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, 
                                      cost_price, total_amount) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($items as $item) {
                    $item_total = $item['quantity'] * $item['cost_price'];
                    $stmt->execute([
                        $id, $item['product_id'], $item['quantity'], 
                        $item['cost_price'], $item_total
                    ]);
                }
                
                $success = "Purchase order updated successfully";
                logAudit($_SESSION['user_id'], 'update', 'purchases', "Updated purchase: $reference_no");
            }
            
            $conn->commit();
            
        } elseif ($action == 'receive') {
            $id = $_POST['id'];
            
            $conn->beginTransaction();
            
            // Update purchase status
            $stmt = $conn->prepare("UPDATE purchases SET status = 'received' WHERE id = ?");
            $stmt->execute([$id]);
            
            // Get purchase items
            $stmt = $conn->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update product stock
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? 
                                  WHERE id = ?");
            
            foreach ($items as $item) {
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $conn->commit();
            
            $success = "Purchase order received successfully";
            logAudit($_SESSION['user_id'], 'receive', 'purchases', "Received purchase ID: $id");
            
        } elseif ($action == 'return') {
            $id = $_POST['id'];
            
            $conn->beginTransaction();
            
            // Update purchase status
            $stmt = $conn->prepare("UPDATE purchases SET status = 'returned' WHERE id = ?");
            $stmt->execute([$id]);
            
            // Get purchase items
            $stmt = $conn->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update product stock
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? 
                                  WHERE id = ?");
            
            foreach ($items as $item) {
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $conn->commit();
            
            $success = "Purchase order returned successfully";
            logAudit($_SESSION['user_id'], 'return', 'purchases', "Returned purchase ID: $id");
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get purchase for editing
$purchase = null;
$purchase_items = [];
if (($action == 'edit' || $action == 'view') && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT p.*, s.name as supplier_name, u.username as created_by_name 
                           FROM purchases p 
                           LEFT JOIN suppliers s ON p.supplier_id = s.id 
                           LEFT JOIN users u ON p.created_by = u.id 
                           WHERE p.id = ?");
    $stmt->execute([$_GET['id']]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$purchase) {
        redirect('/purchases.php');
    }
    
    $stmt = $conn->prepare("SELECT pi.*, p.name as product_name, p.sku 
                           FROM purchase_items pi 
                           JOIN products p ON pi.product_id = p.id 
                           WHERE pi.purchase_id = ?");
    $stmt->execute([$_GET['id']]);
    $purchase_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all purchases for listing
if ($action == 'list') {
    $stmt = $conn->prepare("SELECT p.*, s.name as supplier_name, u.username as created_by_name 
                           FROM purchases p 
                           LEFT JOIN suppliers s ON p.supplier_id = s.id 
                           LEFT JOIN users u ON p.created_by = u.id 
                           ORDER BY p.created_at DESC");
    $stmt->execute();
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <!-- Purchases List -->
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">All Purchases</h5>
                <a href="?action=add" class="btn btn-primary" 
                   style="background: #0f62fe; border: none;">
                    <i class="fas fa-plus"></i> New Purchase
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Supplier</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $item): ?>
                            <tr>
                                <td><?php echo $item['reference_no']; ?></td>
                                <td><?php echo $item['supplier_name']; ?></td>
                                <td>Rs<?php echo formatCurrency($item['total_amount']); ?></td>
                                <td>
                                    <?php if ($item['status'] == 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php elseif ($item['status'] == 'received'): ?>
                                        <span class="badge bg-success">Received</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Returned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['created_by_name']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <a href="?action=view&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($item['status'] == 'pending'): ?>
                                        <a href="?action=edit&id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="receivePurchase(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($item['status'] == 'received'): ?>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="returnPurchase(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
<?php elseif ($action == 'view'): ?>
    <!-- View Purchase -->
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Purchase Details</h6>
                    <p class="mb-1"><strong>Reference:</strong> <?php echo $purchase['reference_no']; ?></p>
                    <p class="mb-1"><strong>Supplier:</strong> <?php echo $purchase['supplier_name']; ?></p>
                    <p class="mb-1"><strong>Status:</strong> 
                        <?php if ($purchase['status'] == 'pending'): ?>
                            <span class="badge bg-warning">Pending</span>
                        <?php elseif ($purchase['status'] == 'received'): ?>
                            <span class="badge bg-success">Received</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Returned</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><strong>Date:</strong> <?php echo date('Y-m-d', strtotime($purchase['created_at'])); ?></p>
                    <p class="mb-1"><strong>Created By:</strong> <?php echo $purchase['created_by_name']; ?></p>
                    <p class="mb-1"><strong>Total Amount:</strong> Rs<?php echo formatCurrency($purchase['total_amount']); ?></p>
                </div>
            </div>
            
            <?php if ($purchase['notes']): ?>
                <div class="mb-4">
                    <h6>Notes</h6>
                    <p class="mb-0"><?php echo $purchase['notes']; ?></p>
                </div>
            <?php endif; ?>
            
            <h6>Purchase Items</h6>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Cost Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchase_items as $item): ?>
                            <tr>
                                <td><?php echo $item['product_name']; ?></td>
                                <td><?php echo $item['sku']; ?></td>
                                <td class="text-end"><?php echo $item['quantity']; ?></td>
                                <td class="text-end">Rs<?php echo formatCurrency($item['cost_price']); ?></td>
                                <td class="text-end">Rs<?php echo formatCurrency($item['total_amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Total:</th>
                            <th class="text-end">Rs<?php echo formatCurrency($purchase['total_amount']); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="mt-3">
                <a href="purchases.php" class="btn btn-secondary">Back to List</a>
                <?php if ($purchase['status'] == 'pending'): ?>
                    <button type="button" class="btn btn-success" 
                            onclick="receivePurchase(<?php echo $purchase['id']; ?>)">
                        <i class="fas fa-check me-2"></i> Receive Purchase
                    </button>
                <?php endif; ?>
                <?php if ($purchase['status'] == 'received'): ?>
                    <button type="button" class="btn btn-danger" 
                            onclick="returnPurchase(<?php echo $purchase['id']; ?>)">
                        <i class="fas fa-undo me-2"></i> Return Purchase
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
<?php else: ?>
    <!-- Add/Edit Purchase Form -->
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <form method="POST" id="purchaseForm">
                <?php if ($action == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $purchase['id']; ?>">
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="supplier_id" class="form-label">Supplier</label>
                            <select class="form-control" id="supplier_id" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>" 
                                            <?php echo ($purchase && $purchase['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                        <?php echo $supplier['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reference_no" class="form-label">Reference No</label>
                            <input type="text" class="form-control" id="reference_no" name="reference_no" 
                                   value="<?php echo $purchase ? $purchase['reference_no'] : generateReference('PO'); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" 
                                      rows="4"><?php echo $purchase ? $purchase['notes'] : ''; ?></textarea>
                        </div>
                    </div>
                </div>
                
                <h6>Purchase Items</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th width="150">Quantity</th>
                                <th width="150">Cost Price</th>
                                <th width="150">Total</th>
                                <th width="50"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Items will be added here dynamically -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5">
                                    <button type="button" class="btn btn-sm btn-success" onclick="addItem()">
                                        <i class="fas fa-plus"></i> Add Item
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th class="text-end" id="grandTotal">$0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <input type="hidden" name="items" id="itemsJson">
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" style="background: #0f62fe; border: none;">
                        <?php echo $action == 'edit' ? 'Update' : 'Create'; ?> Purchase
                    </button>
                    <a href="purchases.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Product selection modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control mb-3" id="searchProduct" placeholder="Search products...">
                <div class="table-responsive">
                    <table class="table table-hover" id="productsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>SKU</th>
                                <th>Cost Price</th>
                                <th>Stock</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['name']; ?></td>
                                    <td><?php echo $product['sku']; ?></td>
                                    <td>Rs<?php echo formatCurrency($product['cost_price']); ?></td>
                                    <td><?php echo $product['stock_quantity']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="selectProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                            Select
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let items = <?php echo $purchase_items ? json_encode($purchase_items) : '[]'; ?>;
const productModal = new bootstrap.Modal(document.getElementById('productModal'));
let selectedRow = null;

// Add item row
function addItem() {
    selectedRow = document.createElement('tr');
    selectedRow.innerHTML = `
        <td>
            <button type="button" class="btn btn-sm btn-secondary w-100" onclick="openProductModal()">
                Select Product
            </button>
            <input type="hidden" class="product-id">
            <div class="product-name"></div>
        </td>
        <td>
            <input type="number" class="form-control quantity" min="1" value="1" 
                   onchange="calculateTotal(this.parentElement.parentElement)">
        </td>
        <td>
            <input type="number" step="0.01" class="form-control cost-price" 
                   onchange="calculateTotal(this.parentElement.parentElement)">
        </td>
        <td class="text-end total">$0.00</td>
        <td>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    document.querySelector('#itemsTable tbody').appendChild(selectedRow);
    openProductModal();
}

// Open product selection modal
function openProductModal() {
    productModal.show();
}

// Select product from modal
function selectProduct(product) {
    selectedRow.querySelector('.product-id').value = product.id;
    selectedRow.querySelector('.product-name').textContent = product.name;
    selectedRow.querySelector('.cost-price').value = product.cost_price;
    calculateTotal(selectedRow);
    productModal.hide();
}

// Calculate row total
function calculateTotal(row) {
    const quantity = parseFloat(row.querySelector('.quantity').value);
    const costPrice = parseFloat(row.querySelector('.cost-price').value);
    const total = quantity * costPrice;
    row.querySelector('.total').textContent = `$${formatCurrency(total)}`;
    calculateGrandTotal();
}

// Calculate grand total
function calculateGrandTotal() {
    const rows = document.querySelectorAll('#itemsTable tbody tr');
    let total = 0;
    
    rows.forEach(row => {
        const totalText = row.querySelector('.total').textContent;
        total += parseFloat(totalText.replace('$', ''));
    });
    
    document.getElementById('grandTotal').textContent = `$${formatCurrency(total)}`;
}

// Remove item row
function removeItem(button) {
    button.closest('tr').remove();
    calculateGrandTotal();
}

// Format currency
function formatCurrency(amount) {
    return amount.toFixed(2);
}

// Search products in modal
document.getElementById('searchProduct').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#productsTable tbody tr');
    
    rows.forEach(row => {
        const name = row.children[0].textContent.toLowerCase();
        const sku = row.children[1].textContent.toLowerCase();
        if (name.includes(search) || sku.includes(search)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Load existing items if editing
if (items.length > 0) {
    items.forEach(item => {
        selectedRow = document.createElement('tr');
        selectedRow.innerHTML = `
            <td>
                <input type="hidden" class="product-id" value="${item.product_id}">
                <div class="product-name">${item.product_name}</div>
            </td>
            <td>
                <input type="number" class="form-control quantity" min="1" value="${item.quantity}" 
                       onchange="calculateTotal(this.parentElement.parentElement)">
            </td>
            <td>
                <input type="number" step="0.01" class="form-control cost-price" value="${item.cost_price}" 
                       onchange="calculateTotal(this.parentElement.parentElement)">
            </td>
            <td class="text-end total">$${formatCurrency(item.total_amount)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        document.querySelector('#itemsTable tbody').appendChild(selectedRow);
    });
    calculateGrandTotal();
}

// Form submission
document.getElementById('purchaseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const rows = document.querySelectorAll('#itemsTable tbody tr');
    const items = [];
    
    rows.forEach(row => {
        items.push({
            product_id: row.querySelector('.product-id').value,
            quantity: parseFloat(row.querySelector('.quantity').value),
            cost_price: parseFloat(row.querySelector('.cost-price').value)
        });
    });
    
    document.getElementById('itemsJson').value = JSON.stringify(items);
    this.submit();
});

// Receive purchase
function receivePurchase(id) {
    Swal.fire({
        title: 'Receive Purchase',
        text: 'Are you sure you want to receive this purchase?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, receive it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=receive';
            
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

// Return purchase
function returnPurchase(id) {
    Swal.fire({
        title: 'Return Purchase',
        text: 'Are you sure you want to return this purchase?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, return it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=return';
            
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