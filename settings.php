<?php
require_once 'config/config.php';
checkAuth();

// Only admin can access this page
if ($_SESSION['user_role'] != 'admin') {
    redirect('/dashboard.php');
}

$pageTitle = 'System Settings';

// Function to get setting value
function getSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] == 'general') {
            // Update general settings
            $settings = [
                'shop_name' => $_POST['shop_name'],
                'shop_address' => $_POST['shop_address'],
                'shop_phone' => $_POST['shop_phone'],
                'currency' => $_POST['currency'],
                'tax_rate' => $_POST['tax_rate'],
                'timezone' => $_POST['timezone']
            ];

            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }

            // Handle logo upload
            if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['shop_logo']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    throw new Exception('Invalid logo file type. Allowed: JPG, PNG, GIF');
                }

                $upload_path = 'uploads/';
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $new_filename = 'logo.' . $ext;
                move_uploaded_file($_FILES['shop_logo']['tmp_name'], $upload_path . $new_filename);

                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('shop_logo', ?) 
                                      ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$new_filename, $new_filename]);
            }

            $_SESSION['success'] = 'Settings updated successfully';
        } elseif (isset($_POST['action']) && $_POST['action'] == 'backup') {
            // Create database backup
            $tables = [];
            $result = $conn->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $backup = "";
            foreach ($tables as $table) {
                $result = $conn->query("SELECT * FROM $table");
                $num_fields = $result->columnCount();

                $backup .= "DROP TABLE IF EXISTS $table;\n";
                $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
                $backup .= $row2[1] . ";\n\n";

                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $backup .= "INSERT INTO $table VALUES(";
                    for ($j = 0; $j < $num_fields; $j++) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                        if (isset($row[$j])) {
                            $backup .= '"' . $row[$j] . '"';
                        } else {
                            $backup .= '""';
                        }
                        if ($j < ($num_fields - 1)) {
                            $backup .= ',';
                        }
                    }
                    $backup .= ");\n";
                }
                $backup .= "\n";
            }

            $backup_path = 'backups/';
            if (!is_dir($backup_path)) {
                mkdir($backup_path, 0777, true);
            }

            $backup_file = $backup_path . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            file_put_contents($backup_file, $backup);

            $_SESSION['success'] = 'Database backup created successfully';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    // Redirect to avoid form resubmission
    redirect('/settings.php');
}

// Get current settings
$settings = [
    'shop_name' => getSetting($conn, 'shop_name', 'POSFlix'),
    'shop_address' => getSetting($conn, 'shop_address', '123 Main Street'),
    'shop_phone' => getSetting($conn, 'shop_phone', '123-456-7890'),
    'currency' => getSetting($conn, 'currency', 'USD'),
    'tax_rate' => getSetting($conn, 'tax_rate', '10'),
    'timezone' => getSetting($conn, 'timezone', 'UTC'),
    'shop_logo' => getSetting($conn, 'shop_logo', '')
];

include 'app/views/layout/header.php';
?>

<!-- Settings Tabs -->
<div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <div class="card-body">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#general">
                    <i class="fas fa-cog me-2"></i> General Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#backup">
                    <i class="fas fa-database me-2"></i> Backup
                </a>
            </li>
        </ul>

        <div class="tab-content mt-4">
            <!-- General Settings -->
            <div class="tab-pane fade show active" id="general">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="general">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Shop Name</label>
                                <input type="text" class="form-control" name="shop_name" 
                                       value="<?php echo htmlspecialchars($settings['shop_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Shop Address</label>
                                <textarea class="form-control" name="shop_address" rows="3" 
                                          required><?php echo htmlspecialchars($settings['shop_address']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Shop Phone</label>
                                <input type="text" class="form-control" name="shop_phone" 
                                       value="<?php echo htmlspecialchars($settings['shop_phone']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Currency</label>
                                <select class="form-control" name="currency" required>
                                    <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>USD</option>
                                    <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                    <option value="GBP" <?php echo $settings['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP</option>
                                    <option value="JPY" <?php echo $settings['currency'] == 'JPY' ? 'selected' : ''; ?>>JPY</option>
                                    <option value="CNY" <?php echo $settings['currency'] == 'CNY' ? 'selected' : ''; ?>>CNY</option>
                                    <option value="PKR" <?php echo $settings['currency'] == 'PKR' ? 'selected' : ''; ?>>PKR</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" class="form-control" name="tax_rate" step="0.01" 
                                       value="<?php echo htmlspecialchars($settings['tax_rate']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Timezone</label>
                                <select class="form-control" name="timezone" required>
                                    <?php
                                    $timezones = DateTimeZone::listIdentifiers();
                                    foreach ($timezones as $tz) {
                                        $selected = $settings['timezone'] == $tz ? 'selected' : '';
                                        echo "<option value=\"$tz\" $selected>$tz</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Shop Logo</label>
                        <?php if ($settings['shop_logo']): ?>
                            <div class="mb-2">
                                <img src="uploads/<?php echo htmlspecialchars($settings['shop_logo']); ?>" 
                                     alt="Shop Logo" style="max-height: 100px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="shop_logo" accept="image/*">
                        <small class="text-muted">Recommended size: 200x200px. Leave empty to keep current logo.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Settings
                    </button>
                </form>
            </div>

            <!-- Backup Settings -->
            <div class="tab-pane fade" id="backup">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Create a backup of your database. The backup file will be saved in the 'backups' folder.
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i> Create Database Backup
                    </button>
                </form>

                <?php
                // List existing backups
                $backup_path = 'backups/';
                if (is_dir($backup_path)) {
                    $backups = glob($backup_path . '*.sql');
                    if (!empty($backups)) {
                        echo '<h5 class="mt-4">Existing Backups</h5>';
                        echo '<ul class="list-group">';
                        foreach ($backups as $backup) {
                            $filename = basename($backup);
                            $filesize = formatBytes(filesize($backup));
                            $filedate = date('Y-m-d H:i:s', filemtime($backup));
                            
                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                            echo "<div><i class='fas fa-file-alt me-2'></i> $filename</div>";
                            echo "<div class='text-muted'>";
                            echo "<small>Size: $filesize | Date: $filedate</small>";
                            echo " <a href='$backup' download class='btn btn-sm btn-outline-primary ms-2'>";
                            echo "<i class='fas fa-download'></i></a>";
                            echo '</div>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
// Show active tab from hash
let activeTab = window.location.hash || '#general';
const tabEl = document.querySelector(`a[href="${activeTab}"]`);
if (tabEl) {
    const tab = new bootstrap.Tab(tabEl);
    tab.show();
}

// Update hash on tab change
document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', (e) => {
        window.location.hash = e.target.getAttribute('href');
    });
});
</script>

<?php include 'app/views/layout/footer.php'; ?>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}