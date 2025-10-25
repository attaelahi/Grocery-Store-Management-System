<?php
// Test script to check database tables
require_once('config/database.php');

echo "<h2>Database Check</h2>";

// Check if payment_history table exists
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'payment_history'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ payment_history table exists</p>";
        
        // Check columns
        $stmt = $conn->query("DESCRIBE payment_history");
        echo "<h3>payment_history columns:</h3><ul>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<li>{$row['Field']} - {$row['Type']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ payment_history table does NOT exist</p>";
        echo "<p><strong>Please run database_fix.sql to create it!</strong></p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check sales table columns
try {
    $stmt = $conn->query("DESCRIBE sales");
    echo "<h3>sales table columns:</h3><ul>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<li>{$row['Field']} - {$row['Type']} - Default: {$row['Default']}</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check if paid_amount and due_amount exist
try {
    $stmt = $conn->query("SELECT paid_amount, due_amount FROM sales LIMIT 1");
    echo "<p style='color: green;'>✓ paid_amount and due_amount columns exist</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ paid_amount and due_amount columns missing</p>";
    echo "<p><strong>Please run database_fix.sql to add them!</strong></p>";
}

// Check current user
if (isset($_SESSION['user_id'])) {
    echo "<h3>Current User:</h3>";
    $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($user, true) . "</pre>";
} else {
    echo "<p style='color: orange;'>No user logged in</p>";
}
?>
