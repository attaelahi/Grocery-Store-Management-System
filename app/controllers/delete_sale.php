<?php
session_start();
require_once('../../config/database.php');

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if user has admin privileges (role = 'admin')
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Only administrators can delete sales']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $sale_id = $input['sale_id'] ?? null;
    
    if (!$sale_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Get sale details before deletion
    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        throw new Exception('Sale not found');
    }
    
    // Get sale items to restore product quantities
    $stmt = $conn->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Restore product quantities
    foreach ($sale_items as $item) {
        $stmt = $conn->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity + ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Delete payment history
    $stmt = $conn->prepare("DELETE FROM payment_history WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    
    // Delete sale items
    $stmt = $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    
    // Delete sale
    $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->execute([$sale_id]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sale deleted successfully and product quantities restored'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Delete sale error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'error' => $e->getFile() . ':' . $e->getLine()]);
}
?>
