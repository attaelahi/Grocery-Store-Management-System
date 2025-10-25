<?php
session_start();
require_once('../../config/database.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $sale_id = $input['sale_id'] ?? null;
    $amount = floatval($input['amount'] ?? 0);
    $payment_method = $input['payment_method'] ?? 'cash';
    $note = $input['note'] ?? '';
    
    if (!$sale_id || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Get current sale details
    $stmt = $conn->prepare("SELECT paid_amount, due_amount, net_amount, payment_status FROM sales WHERE id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        throw new Exception('Sale not found');
    }
    
    // Handle NULL values for backward compatibility
    $current_paid_amount = floatval($sale['paid_amount'] ?? 0);
    $current_due_amount = floatval($sale['due_amount'] ?? $sale['net_amount']);
    $current_status = $sale['payment_status'] ?? 'pending';
    
    // Validate payment amount
    if ($amount > $current_due_amount) {
        throw new Exception('Payment amount exceeds due amount');
    }
    
    // Calculate new amounts
    $new_paid_amount = $current_paid_amount + $amount;
    $new_due_amount = $current_due_amount - $amount;
    
    // Determine new payment status
    if ($new_due_amount <= 0.01) { // Account for floating point precision
        $new_payment_status = 'paid';
        $new_due_amount = 0;
    } else {
        $new_payment_status = 'partial';
    }
    
    // Update sales table
    $stmt = $conn->prepare("
        UPDATE sales 
        SET paid_amount = ?, 
            due_amount = ?, 
            payment_status = ?
        WHERE id = ?
    ");
    $stmt->execute([$new_paid_amount, $new_due_amount, $new_payment_status, $sale_id]);
    
    // Insert payment history record
    $stmt = $conn->prepare("
        INSERT INTO payment_history 
        (sale_id, amount, payment_method, notes, received_by, payment_date) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $sale_id,
        $amount,
        $payment_method,
        $note,
        $_SESSION['user_id']
    ]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment collected successfully',
        'new_paid_amount' => $new_paid_amount,
        'new_due_amount' => $new_due_amount,
        'new_status' => $new_payment_status
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // Log the error for debugging
    error_log("Payment collection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'error' => $e->getFile() . ':' . $e->getLine()]);
}
?>
