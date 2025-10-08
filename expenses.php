<?php
require_once 'config/config.php';
checkAuth();

if ($_SESSION['user_role'] != 'admin') {
    redirect('/dashboard.php');
}

$pageTitle = 'Expenses Management';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success = $error = '';

// Expense categories
$expense_categories = [
    'utilities' => 'Utilities',
    'rent' => 'Rent',
    'salary' => 'Salary',
    'supplies' => 'Supplies',
    'maintenance' => 'Maintenance',
    'others' => 'Others'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if ($action == 'add' || $action == 'edit') {
            $category = sanitize($_POST['category']);
            $amount = floatval($_POST['amount']);
            $description = sanitize($_POST['description']);
            $expense_date = $_POST['expense_date'];
            
            if ($action == 'add') {
                $stmt = $conn->prepare("INSERT INTO expenses (category, amount, description, expense_date, created_by) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$category, $amount, $description, $expense_date, $_SESSION['user_id']]);
                
                $success = "Expense added successfully";
                logAudit($_SESSION['user_id'], 'create', 'expenses', "Added expense: $amount for $category");
                
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE expenses SET category = ?, amount = ?, description = ?, 
                                      expense_date = ? WHERE id = ?");
                $stmt->execute([$category, $amount, $description, $expense_date, $id]);
                
                $success = "Expense updated successfully";
                logAudit($_SESSION['user_id'], 'update', 'expenses', "Updated expense ID: $id");
            }
            
        } elseif ($action == 'delete') {
            $id = $_POST['id'];
            
            $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$id]);
            
            $success = "Expense deleted successfully";
            logAudit($_SESSION['user_id'], 'delete', 'expenses', "Deleted expense ID: $id");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get expense for editing
$expense = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        redirect('/expenses.php');
    }
}

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get all expenses for listing
if ($action == 'list') {
    $stmt = $conn->prepare("SELECT e.*, u.username as created_by_name 
                           FROM expenses e 
                           LEFT JOIN users u ON e.created_by = u.id 
                           WHERE e.expense_date BETWEEN ? AND ? 
                           ORDER BY e.expense_date DESC");
    $stmt->execute([$start_date, $end_date]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals by category
    $category_totals = [];
    foreach ($expenses as $item) {
        if (!isset($category_totals[$item['category']])) {
            $category_totals[$item['category']] = 0;
        }
        $category_totals[$item['category']] += $item['amount'];
    }
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
    <!-- Expenses List -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary" 
                                        style="background: #0f62fe; border: none;">
                                    <i class="fas fa-filter me-2"></i> Filter
                                </button>
                                <a href="expenses.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <?php foreach ($category_totals as $category => $total): ?>
            <div class="col-md-4 mb-4">
                <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div class="card-body">
                        <h6 class="card-title text-muted mb-2"><?php echo $expense_categories[$category]; ?></h6>
                        <h3 class="mb-0" style="color: #0f62fe;">Rs<?php echo formatCurrency($total); ?></h3>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">All Expenses</h5>
                <a href="?action=add" class="btn btn-primary" 
                   style="background: #0f62fe; border: none;">
                    <i class="fas fa-plus"></i> Add New Expense
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $item): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($item['expense_date'])); ?></td>
                                <td><?php echo $expense_categories[$item['category']]; ?></td>
                                <td>Rs<?php echo formatCurrency($item['amount']); ?></td>
                                <td><?php echo $item['description']; ?></td>
                                <td><?php echo $item['created_by_name']; ?></td>
                                <td>
                                    <a href="?action=edit&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteExpense(<?php echo $item['id']; ?>)">
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
    
<?php else: ?>
    <!-- Add/Edit Expense Form -->
    <div class="row">
        <div class="col-md-6">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <form method="POST">
                        <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($expense_categories as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo ($expense && $expense['category'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                                   required value="<?php echo $expense ? $expense['amount'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="expense_date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                   required value="<?php echo $expense ? $expense['expense_date'] : date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="3"><?php echo $expense ? $expense['description'] : ''; ?></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" 
                                    style="background: #0f62fe; border: none;">
                                <?php echo $action == 'edit' ? 'Update' : 'Add'; ?> Expense
                            </button>
                            <a href="expenses.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function deleteExpense(id) {
    Swal.fire({
        title: 'Delete Expense',
        text: 'Are you sure you want to delete this expense?',
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