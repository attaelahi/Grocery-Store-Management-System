<?php
require_once 'config/config.php';
checkAuth();

// Only admin can access this page
if ($_SESSION['user_role'] != 'admin') {
    redirect('/dashboard.php');
}

$pageTitle = 'User Management';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    // Validate required fields
                    if (empty($_POST['username']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['role'])) {
                        throw new Exception('All fields are required');
                    }

                    // Check if username already exists
                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$_POST['username']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Username already exists');
                    }

                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$_POST['email']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Email already exists');
                    }

                    // Hash password
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                    $stmt->execute([
                        $_POST['username'],
                        $_POST['email'],
                        $hashedPassword,
                        $_POST['role']
                    ]);

                    $_SESSION['success'] = 'User created successfully';
                    break;

                case 'update':
                    // Validate required fields
                    if (empty($_POST['id']) || empty($_POST['username']) || empty($_POST['email']) || empty($_POST['role'])) {
                        throw new Exception('All fields are required');
                    }

                    // Check if username exists for other users
                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$_POST['username'], $_POST['id']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Username already exists');
                    }

                    // Check if email exists for other users
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$_POST['email'], $_POST['id']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Email already exists');
                    }

                    // Update user
                    $sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?";
                    $params = [
                        $_POST['username'],
                        $_POST['email'],
                        $_POST['role'],
                        isset($_POST['status']) ? 1 : 0,
                        $_POST['id']
                    ];

                    // Update password if provided
                    if (!empty($_POST['password'])) {
                        $sql = "UPDATE users SET username = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?";
                        array_splice($params, 2, 0, password_hash($_POST['password'], PASSWORD_DEFAULT));
                    }

                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);

                    $_SESSION['success'] = 'User updated successfully';
                    break;

                case 'delete':
                    // Prevent self-deletion
                    if ($_POST['id'] == $_SESSION['user_id']) {
                        throw new Exception('You cannot delete your own account');
                    }

                    // Check if user exists
                    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    if (!$stmt->fetch()) {
                        throw new Exception('User not found');
                    }

                    // Delete user
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$_POST['id']]);

                    $_SESSION['success'] = 'User deleted successfully';
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    // Redirect to avoid form resubmission
    redirect('/users.php');
}

// Get all users
$stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'app/views/layout/header.php';
?>

<!-- Add User Button -->
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal" 
            style="background: #0f62fe; border: none;">
        <i class="fas fa-plus me-2"></i> Add New User
    </button>
</div>

<!-- Users List -->
<div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'info'; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span></td>
                            <td>
                                <span class="badge bg-<?php echo $user['status'] ? 'success' : 'warning'; ?>">
                                    <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info edit-user" 
                                        data-user='<?php echo json_encode($user); ?>'
                                        data-bs-toggle="modal" data-bs-target="#editUserModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-danger delete-user" 
                                            data-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo $user['username']; ?>">
                                        <i class="fas fa-trash"></i>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="role" required>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" id="edit_username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" 
                               placeholder="Leave blank to keep current password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="role" id="edit_role" required>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="status" id="edit_status">
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
// Handle Edit User
document.querySelectorAll('.edit-user').forEach(button => {
    button.addEventListener('click', () => {
        const user = JSON.parse(button.dataset.user);
        document.getElementById('edit_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_status').checked = user.status == 1;
    });
});

// Handle Delete User
document.querySelectorAll('.delete-user').forEach(button => {
    button.addEventListener('click', () => {
        const id = button.dataset.id;
        const username = button.dataset.username;
        
        if (confirm(`Are you sure you want to delete user "${username}"?`)) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
});
</script>

<?php include 'app/views/layout/footer.php'; ?>