<?php

function generateInvoice($sale) {
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

    $html .= '
                <tr class="total">
                    <td colspan="3">Subtotal:</td>
                    <td>RS' . number_format($sale['total_amount'], 2) . '</td>
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
                    <td colspan="3"><strong>Total:</strong></td>
                    <td><strong>Rs' . number_format($sale['net_amount'], 2) . '</strong></td>
                </tr>
            </table>
            <div style="margin-top: 30px; text-align: center;">
                <p>Thank you for your business!</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}