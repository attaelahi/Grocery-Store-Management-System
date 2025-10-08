<?php
require_once 'config/config.php';
checkAuth();

if ($_SESSION['user_role'] != 'admin') {
    redirect('/dashboard.php');
}

$pageTitle = 'Categories Management';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if ($action == 'add' || $action == 'edit') {
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            
            if ($action == 'add') {
                $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                
                $success = "Category added successfully";
                logAudit($_SESSION['user_id'], 'create', 'categories', "Added category: $name");
                
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                
                $success = "Category updated successfully";
                logAudit($_SESSION['user_id'], 'update', 'categories', "Updated category: $name");
            }
            
        } elseif ($action == 'delete') {
            $id = $_POST['id'];
            
            // Check if category has products
            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND status = 1");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cannot delete category that has active products");
            }
            
            $stmt = $conn->prepare("UPDATE categories SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            $success = "Category deleted successfully";
            logAudit($_SESSION['user_id'], 'delete', 'categories', "Deleted category ID: $id");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get category for editing
$category = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        redirect('/categories.php');
    }
}

// Get all categories for listing
if ($action == 'list') {
    $stmt = $conn->prepare("SELECT c.*, 
                                  (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 1) 
                                  as product_count 
                           FROM categories c 
                           WHERE c.status = 1 
                           ORDER BY c.name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <!-- Categories List -->
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">All Categories</h5>
                <a href="?action=add" class="btn btn-primary" 
                   style="background: #0f62fe; border: none;">
                    <i class="fas fa-plus"></i> Add New Category
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo $item['description']; ?></td>
                                <td><?php echo $item['product_count']; ?></td>
                                <td>
                                    <a href="?action=edit&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteCategory(<?php echo $item['id']; ?>, '<?php echo $item['name']; ?>')">
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
    <!-- Add/Edit Category Form -->
    <div class="row">
        <div class="col-md-6">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <form method="POST">
                        <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   value="<?php echo $category ? $category['name'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="3"><?php echo $category ? $category['description'] : ''; ?></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" 
                                    style="background: #0f62fe; border: none;">
                                <?php echo $action == 'edit' ? 'Update' : 'Add'; ?> Category
                            </button>
                            <a href="categories.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function deleteCategory(id, name) {
    Swal.fire({
        title: 'Delete Category',
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