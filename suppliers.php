<?php
require_once 'config/config.php';
checkAuth();

if ($_SESSION['user_role'] != 'admin') {
    redirect('/dashboard.php');
}

$pageTitle = 'Suppliers Management';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if ($action == 'add' || $action == 'edit') {
            $name = sanitize($_POST['name']);
            $contact_person = sanitize($_POST['contact_person']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            
            if ($action == 'add') {
                $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $contact_person, $email, $phone, $address]);
                
                $success = "Supplier added successfully";
                logAudit($_SESSION['user_id'], 'create', 'suppliers', "Added supplier: $name");
                
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE suppliers SET name = ?, contact_person = ?, email = ?, 
                                      phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $contact_person, $email, $phone, $address, $id]);
                
                $success = "Supplier updated successfully";
                logAudit($_SESSION['user_id'], 'update', 'suppliers', "Updated supplier: $name");
            }
            
        } elseif ($action == 'delete') {
            $id = $_POST['id'];
            
            // Check if supplier has active purchases
            $stmt = $conn->prepare("SELECT COUNT(*) FROM purchases WHERE supplier_id = ? AND status != 'returned'");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cannot delete supplier with active purchases");
            }
            
            $stmt = $conn->prepare("UPDATE suppliers SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            $success = "Supplier deleted successfully";
            logAudit($_SESSION['user_id'], 'delete', 'suppliers', "Deleted supplier ID: $id");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get supplier for editing
$supplier = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        redirect('/suppliers.php');
    }
}

// Get all suppliers for listing
if ($action == 'list') {
    $stmt = $conn->prepare("SELECT s.*, 
                                  (SELECT COUNT(*) FROM purchases p WHERE p.supplier_id = s.id) 
                                  as purchase_count 
                           FROM suppliers s 
                           WHERE s.status = 1 
                           ORDER BY s.name");
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <!-- Suppliers List -->
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">All Suppliers</h5>
                <a href="?action=add" class="btn btn-primary" 
                   style="background: #0f62fe; border: none;">
                    <i class="fas fa-plus"></i> Add New Supplier
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Purchases</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo $item['contact_person']; ?></td>
                                <td><?php echo $item['email']; ?></td>
                                <td><?php echo $item['phone']; ?></td>
                                <td><?php echo $item['purchase_count']; ?></td>
                                <td>
                                    <a href="?action=edit&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteSupplier(<?php echo $item['id']; ?>, '<?php echo $item['name']; ?>')">
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
    <!-- Add/Edit Supplier Form -->
    <div class="row">
        <div class="col-md-6">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <form method="POST">
                        <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $supplier['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Supplier Name</label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   value="<?php echo $supplier ? $supplier['name'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                   value="<?php echo $supplier ? $supplier['contact_person'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $supplier ? $supplier['email'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo $supplier ? $supplier['phone'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" 
                                      rows="3"><?php echo $supplier ? $supplier['address'] : ''; ?></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" 
                                    style="background: #0f62fe; border: none;">
                                <?php echo $action == 'edit' ? 'Update' : 'Add'; ?> Supplier
                            </button>
                            <a href="suppliers.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function deleteSupplier(id, name) {
    Swal.fire({
        title: 'Delete Supplier',
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