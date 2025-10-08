<?php
require_once 'config/config.php';
checkAuth();

if ($_SESSION['user_role'] != 'admin') {
    redirect('/dashboard.php');
}

$pageTitle = 'Products Management';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success = $error = '';

// Get all categories for form
$stmt = $conn->prepare("SELECT * FROM categories WHERE status = 1 ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if ($action == 'add' || $action == 'edit') {
            $name = sanitize($_POST['name']);
            $sku = sanitize($_POST['sku']);
            $barcode = sanitize($_POST['barcode']);
            $category_id = $_POST['category_id'];
            $cost_price = floatval($_POST['cost_price']);
            $sell_price = floatval($_POST['sell_price']);
            $tax_rate = floatval($_POST['tax_rate']);
            $unit = sanitize($_POST['unit']);
            $stock_quantity = intval($_POST['stock_quantity']);
            $reorder_level = intval($_POST['reorder_level']);
            $description = sanitize($_POST['description']);
            
            // Handle image upload
            $image = '';
            if (!empty($_FILES['image']['name'])) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                    throw new Exception('Invalid image format. Only JPG, PNG and GIF are allowed.');
                }
                
                $image = time() . '_' . $_FILES['image']['name'];
                $target = UPLOAD_PATH . '/products/' . $image;
                
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    throw new Exception('Failed to upload image');
                }
            }
            
            if ($action == 'add') {
                $stmt = $conn->prepare("INSERT INTO products (name, sku, barcode, category_id, cost_price, 
                                        sell_price, tax_rate, unit, stock_quantity, reorder_level, image, 
                                        description) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name, $sku, $barcode, $category_id, $cost_price, $sell_price, $tax_rate,
                    $unit, $stock_quantity, $reorder_level, $image, $description
                ]);
                
                $success = "Product added successfully";
                logAudit($_SESSION['user_id'], 'create', 'products', "Added product: $name");
                
            } else {
                $id = $_POST['id'];
                
                if ($image) {
                    $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldImage = $stmt->fetchColumn();
                    
                    if ($oldImage && file_exists(UPLOAD_PATH . '/products/' . $oldImage)) {
                        unlink(UPLOAD_PATH . '/products/' . $oldImage);
                    }
                    
                    $stmt = $conn->prepare("UPDATE products SET name = ?, sku = ?, barcode = ?, 
                                            category_id = ?, cost_price = ?, sell_price = ?, tax_rate = ?, 
                                            unit = ?, stock_quantity = ?, reorder_level = ?, image = ?, 
                                            description = ? WHERE id = ?");
                    $stmt->execute([
                        $name, $sku, $barcode, $category_id, $cost_price, $sell_price, $tax_rate,
                        $unit, $stock_quantity, $reorder_level, $image, $description, $id
                    ]);
                } else {
                    $stmt = $conn->prepare("UPDATE products SET name = ?, sku = ?, barcode = ?, 
                                            category_id = ?, cost_price = ?, sell_price = ?, tax_rate = ?, 
                                            unit = ?, stock_quantity = ?, reorder_level = ?, 
                                            description = ? WHERE id = ?");
                    $stmt->execute([
                        $name, $sku, $barcode, $category_id, $cost_price, $sell_price, $tax_rate,
                        $unit, $stock_quantity, $reorder_level, $description, $id
                    ]);
                }
                
                $success = "Product updated successfully";
                logAudit($_SESSION['user_id'], 'update', 'products', "Updated product: $name");
            }
            
        } elseif ($action == 'delete') {
            $id = $_POST['id'];
            
            $stmt = $conn->prepare("UPDATE products SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            $success = "Product deleted successfully";
            logAudit($_SESSION['user_id'], 'delete', 'products', "Deleted product ID: $id");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get product for editing
$product = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        redirect('/products.php');
    }
}

// Get all products for listing
if ($action == 'list') {
    $stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.status = 1 
                           ORDER BY p.name");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <!-- Products List -->
    <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">All Products</h5>
                <a href="?action=add" class="btn btn-primary" 
                   style="background: #0f62fe; border: none;">
                    <i class="fas fa-plus"></i> Add New Product
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Cost Price</th>
                            <th>Sell Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $item): ?>
                            <tr>
                                <td>
                                    <?php if ($item['image']): ?>
                                        <img src="uploads/products/<?php echo $item['image']; ?>" 
                                             alt="<?php echo $item['name']; ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #eee; 
                                                  display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo $item['sku']; ?></td>
                                <td><?php echo $item['category_name']; ?></td>
                                <td>Rs<?php echo formatCurrency($item['cost_price']); ?></td>
                                <td>Rs<?php echo formatCurrency($item['sell_price']); ?></td>
                                <td>
                                    <?php if ($item['stock_quantity'] <= $item['reorder_level']): ?>
                                        <span class="badge bg-danger">
                                            <?php echo $item['stock_quantity']; ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo $item['stock_quantity']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?action=edit&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteProduct(<?php echo $item['id']; ?>, '<?php echo $item['name']; ?>')">
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
    <!-- Add/Edit Product Form -->
    <div class="row">
        <div class="col-md-8">
            <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?php echo $product ? $product['name'] : ''; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo ($product && $product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo $category['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" required 
                                       value="<?php echo $product ? $product['sku'] : ''; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="barcode" class="form-label">Barcode</label>
                                <input type="text" class="form-control" id="barcode" name="barcode" 
                                       value="<?php echo $product ? $product['barcode'] : ''; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="cost_price" class="form-label">Cost Price</label>
                                <input type="number" step="0.01" class="form-control" id="cost_price" 
                                       name="cost_price" required 
                                       value="<?php echo $product ? $product['cost_price'] : ''; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="sell_price" class="form-label">Sell Price</label>
                                <input type="number" step="0.01" class="form-control" id="sell_price" 
                                       name="sell_price" required 
                                       value="<?php echo $product ? $product['sell_price'] : ''; ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                <input type="number" step="0.01" class="form-control" id="tax_rate" 
                                       name="tax_rate" required 
                                       value="<?php echo $product ? $product['tax_rate'] : '0'; ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="unit" class="form-label">Unit</label>
                                <input type="text" class="form-control" id="unit" name="unit" 
                                       value="<?php echo $product ? $product['unit'] : ''; ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                <input type="number" class="form-control" id="stock_quantity" 
                                       name="stock_quantity" required 
                                       value="<?php echo $product ? $product['stock_quantity'] : '0'; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="reorder_level" class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" id="reorder_level" 
                                       name="reorder_level" required 
                                       value="<?php echo $product ? $product['reorder_level'] : '10'; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="image" class="form-label">Product Image</label>
                                <input type="file" class="form-control" id="image" name="image" 
                                       accept="image/*">
                                <?php if ($product && $product['image']): ?>
                                    <img src="uploads/products/<?php echo $product['image']; ?>" 
                                         alt="Current Image" class="mt-2" style="max-width: 100px;">
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3"><?php echo $product ? $product['description'] : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" 
                                    style="background: #0f62fe; border: none;">
                                <?php echo $action == 'edit' ? 'Update' : 'Add'; ?> Product
                            </button>
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function deleteProduct(id, name) {
    Swal.fire({
        title: 'Delete Product',
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