<?php

function generateInvoice($sale) {
    // Determine payment status badge
    $paymentStatus = $sale['payment_status'] ?? 'paid';
    $statusBadge = '';
    $statusColor = '';
    
    if ($paymentStatus == 'paid') {
        $statusBadge = 'PAID IN FULL';
        $statusColor = '#28a745';
    } elseif ($paymentStatus == 'partial') {
        $statusBadge = 'PARTIAL PAYMENT';
        $statusColor = '#ffc107';
    } else {
        $statusBadge = 'UNPAID - ON CREDIT';
        $statusColor = '#dc3545';
    }
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Invoice #' . $sale['invoice_no'] . '</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); }
            .invoice-box table { width: 100%; line-height: inherit; text-align: left; }
            .invoice-box table td { padding: 5px; vertical-align: top; }
            .invoice-box table tr.top table td { padding-bottom: 20px; }
            .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
            .invoice-box table tr.details td { padding-bottom: 20px; }
            .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
            .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
            .status-badge { display: inline-block; padding: 8px 15px; border-radius: 5px; font-weight: bold; color: white; margin-bottom: 10px; }
            .payment-highlight { background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin-top: 20px; }
            .due-amount { color: #dc3545; font-weight: bold; font-size: 1.2em; }
            @media print { .invoice-box { box-shadow: none; border: 0; } }
        </style>
    </head>
    <body>
        <div class="invoice-box">
            <table cellpadding="0" cellspacing="0">
                <tr class="top">
                    <td colspan="4">
                        <table>
                            <tr>
                                <td>
                                    <h2>INVOICE</h2>
                                    <span class="status-badge" style="background-color: ' . $statusColor . ';">' . $statusBadge . '</span><br>
                                    Invoice #: ' . $sale['invoice_no'] . '<br>
                                    Date: ' . date('F j, Y', strtotime($sale['created_at'])) . '<br>
                                    Cashier: ' . $sale['cashier_name'] . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <tr class="heading">
                    <td>Item</td>
                    <td>Quantity</td>
                    <td>Unit Price</td>
                    <td>Total</td>
                </tr>';

    foreach ($sale['items'] as $item) {
        $html .= '
                <tr class="item">
                    <td>' . htmlspecialchars($item['name']) . '</td>
                    <td>' . $item['quantity'] . '</td>
                    <td>Rs' . number_format($item['unit_price'], 2) . '</td>
                    <td>Rs' . number_format($item['total_amount'], 2) . '</td>
                </tr>';
    }

    // Get payment amounts
    $paidAmount = $sale['paid_amount'] ?? $sale['net_amount'];
    $dueAmount = $sale['due_amount'] ?? 0;
    
    $html .= '
                <tr class="total">
                    <td colspan="3">Subtotal:</td>
                    <td>Rs' . number_format($sale['total_amount'], 2) . '</td>
                </tr>
                <tr class="total">
                    <td colspan="3">Tax:</td>
                    <td>Rs' . number_format($sale['tax_amount'], 2) . '</td>
                </tr>
                <tr class="total">
                    <td colspan="3">Discount:</td>
                    <td>Rs' . number_format($sale['discount_amount'], 2) . '</td>
                </tr>
                <tr class="total">
                    <td colspan="3"><strong>Total Amount:</strong></td>
                    <td><strong>Rs' . number_format($sale['net_amount'], 2) . '</strong></td>
                </tr>';
    
    // Show payment details
    $html .= '
                <tr class="total" style="border-top: 2px solid #333;">
                    <td colspan="3">Amount Paid:</td>
                    <td style="color: #28a745;">Rs' . number_format($paidAmount, 2) . '</td>
                </tr>';
    
    if ($dueAmount > 0) {
        $html .= '
                <tr class="total">
                    <td colspan="3"><strong>Amount Due:</strong></td>
                    <td class="due-amount">Rs' . number_format($dueAmount, 2) . '</td>
                </tr>';
    }
    
    $html .= '
            </table>';
    
    // Add payment notice for partial/unpaid
    if ($paymentStatus == 'partial') {
        $html .= '
            <div class="payment-highlight">
                <strong>⚠ Partial Payment Received</strong><br>
                A payment of Rs' . number_format($paidAmount, 2) . ' has been received.<br>
                Outstanding balance: <span class="due-amount">Rs' . number_format($dueAmount, 2) . '</span>
            </div>';
    } elseif ($paymentStatus == 'pending') {
        $html .= '
            <div class="payment-highlight" style="border-left-color: #dc3545; background-color: #f8d7da;">
                <strong>⚠ Payment Pending</strong><br>
                This sale is on credit. Full amount due: <span class="due-amount">Rs' . number_format($dueAmount, 2) . '</span><br>
                Please make payment at your earliest convenience.
            </div>';
    }
    
    $html .= '
            <div style="margin-top: 30px; text-align: center;">
                <p>Thank you for your business!</p>';
    
    if ($paymentStatus == 'paid') {
        $html .= '<p style="color: #28a745; font-weight: bold;">✓ PAID IN FULL</p>';
    }
    
    $html .= '
            </div>
        </div>
    </body>
    </html>';

    return $html;
}