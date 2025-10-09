<?php
require_once '../../config/config.php';
require_once '../helpers/invoice_generator.php';
checkAuth();

if (!isset($_GET['id'])) {
    die('Invoice ID is required');
}

$sale_id = intval($_GET['id']);

// Get sale details
$stmt = $conn->prepare("SELECT s.*, u.username as cashier_name 
                       FROM sales s 
                       JOIN users u ON s.created_by = u.id 
                       WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die('Invoice not found');
}

// Get sale items
$stmt = $conn->prepare("SELECT si.*, p.name 
                       FROM sale_items si 
                       JOIN products p ON si.product_id = p.id 
                       WHERE si.sale_id = ?");
$stmt->execute([$sale_id]);
$sale['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate and display the invoice
echo generateInvoice($sale);