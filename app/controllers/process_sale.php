<?php
require_once '../../config/config.php';
checkAuth();

// Prevent any output before headers
ob_start();

// Ensure we're outputting JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get POST data
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }
    
    if (!$data || !isset($data['items']) || !is_array($data['items'])) {
        throw new Exception('Invalid sale data received');
    }

    if (empty($data['payment_method'])) {
        throw new Exception('Payment method is required');
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Create sale record
    $invoice_no = 'INV' . date('Ymd') . rand(1000, 9999);
    $customer_id = !empty($data['customer_id']) ? $data['customer_id'] : null;
    $payment_method = $data['payment_method'];
    $discount_amount = floatval($data['discount_amount']);
    
    // Calculate totals
    $subtotal = 0;
    $tax_amount = 0;
    
    foreach ($data['items'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
        $tax_amount += ($item['price'] * $item['quantity']) * ($item['tax_rate'] / 100);
    }
    
    $net_amount = $subtotal + $tax_amount - $discount_amount;
    
    // Insert sale
    $stmt = $conn->prepare("INSERT INTO sales (invoice_no, customer_id, total_amount, tax_amount, 
                            discount_amount, net_amount, payment_method, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $invoice_no, 
        $customer_id, 
        $subtotal, 
        $tax_amount, 
        $discount_amount, 
        $net_amount, 
        $payment_method, 
        $_SESSION['user_id']
    ]);
    
    $sale_id = $conn->lastInsertId();
    
    // Insert sale items and update stock
    foreach ($data['items'] as $item) {
        // Insert sale item
        $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, 
                               tax_amount, total_amount) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $sale_id,
            $item['id'],
            $item['quantity'],
            $item['price'],
            ($item['price'] * $item['quantity']) * ($item['tax_rate'] / 100),
            $item['price'] * $item['quantity']
        ]);
        
        // Update stock quantity
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? 
                               WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['id']]);
    }
    
    // Get sale details for receipt
    $stmt = $conn->prepare("SELECT s.*, u.username as cashier_name 
                           FROM sales s 
                           JOIN users u ON s.created_by = u.id 
                           WHERE s.id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get sale items
    $stmt = $conn->prepare("SELECT si.*, p.name 
                           FROM sale_items si 
                           JOIN products p ON si.product_id = p.id 
                           WHERE si.sale_id = ?");
    $stmt->execute([$sale_id]);
    $sale['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $conn->commit();
    
    // Log the action
    logAudit($_SESSION['user_id'], 'create', 'sales', "Created sale #$invoice_no");
    
    echo json_encode([
        'success' => true,
        'message' => 'Sale completed successfully',
        'sale' => $sale,
        'invoice_url' => 'app/controllers/view_invoice.php?id=' . $sale_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Clear any output buffers
    ob_clean();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Ensure all output is sent
ob_end_flush();