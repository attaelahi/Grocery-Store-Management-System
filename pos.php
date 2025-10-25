<?php
require_once 'config/config.php';
checkAuth();

$pageTitle = 'Point of Sale';

// Get all active products
$stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.status = 1 
                        ORDER BY p.name");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE status = 1 ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customers
$stmt = $conn->prepare("SELECT * FROM customers WHERE status = 1 ORDER BY name");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'app/views/layout/header.php';
?>

<!-- Custom styles for POS -->
<style>
    .product-card {
        cursor: pointer;
        transition: transform 0.2s;
    }
    .product-card:hover {
        transform: translateY(-5px);
    }
    .cart-item {
        background: #f8f9fa;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 8px;
    }
</style>

<div class="row">
    <!-- Products Section (Left) -->
    <div class="col-md-8">
        <div class="card mb-4" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <!-- Search and Category Filter -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="searchProduct" class="form-control" 
                               placeholder="Search products...">
                    </div>
                    <div class="col-md-6">
                        <select id="categoryFilter" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo $category['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div class="row" id="productsGrid">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-3 mb-3 product-item" 
                             data-category="<?php echo $product['category_id']; ?>">
                            <div class="card product-card" 
                                 onclick="addToCart(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                <img src="<?php echo !empty($product['image']) ? 'uploads/products/'.$product['image'] : 'https://via.placeholder.com/150'; ?>" 
                                     class="card-img-top" alt="<?php echo $product['name']; ?>"
                                     style="height: 120px; object-fit: cover;">
                                <div class="card-body p-2">
                                    <h6 class="card-title mb-1"><?php echo $product['name']; ?></h6>
                                    <p class="card-text mb-0" style="font-size: 14px;">
                                        Rs<?php echo formatCurrency($product['sell_price']); ?>
                                    </p>
                                    <small class="text-muted">Stock: <?php echo $product['stock_quantity']; ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cart Section (Right) -->
    <div class="col-md-4">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="card-body">
                <h5 class="card-title mb-3">Current Sale</h5>
                
                <!-- Customer Selection -->
                <div class="mb-3">
                    <select id="customerId" class="form-control">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo $customer['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Cart Items -->
                <div id="cartItems" class="mb-3"></div>
                
                <!-- Cart Summary -->
                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="subtotal">Rs0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (10%):</span>
                            <span id="tax">Rs0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Discount:</span>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <input type="number" id="discountAmount" class="form-control" value="0" min="0">
                                <span class="input-group-text">Rs</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong id="total">Rs0.00</strong>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Options -->
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select id="paymentMethod" class="form-control">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                    </select>
                </div>
                
                <!-- Payment Status -->
                <div class="mb-3">
                    <label class="form-label">Payment Status</label>
                    <select id="paymentStatus" class="form-control" onchange="handlePaymentStatusChange()">
                        <option value="paid">Full Payment</option>
                        <option value="partial">Partial Payment</option>
                        <option value="pending">Unpaid (On Credit)</option>
                    </select>
                </div>
                
                <!-- Paid Amount (shown for partial payment) -->
                <div class="mb-3" id="paidAmountSection" style="display: none;">
                    <label class="form-label">Amount Paid</label>
                    <div class="input-group">
                        <input type="number" id="paidAmount" class="form-control" value="0" min="0" step="0.01">
                        <span class="input-group-text">Rs</span>
                    </div>
                    <small class="text-muted" id="dueAmountText"></small>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-grid gap-2">
                    <button id="holdBtn" class="btn btn-warning">
                        <i class="fas fa-pause me-2"></i> Hold
                    </button>
                    <button id="checkoutBtn" class="btn btn-primary" style="background: #0f62fe; border: none;">
                        <i class="fas fa-shopping-cart me-2"></i> Checkout
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hold Sales Modal -->
<div class="modal fade" id="holdSalesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Held Sales</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="heldSalesList"></div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sale Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be inserted here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printReceipt()">Print</button>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
let heldSales = [];

// Handle payment status change
function handlePaymentStatusChange() {
    const paymentStatus = document.getElementById('paymentStatus').value;
    const paidAmountSection = document.getElementById('paidAmountSection');
    const paidAmountInput = document.getElementById('paidAmount');
    
    if (paymentStatus === 'partial') {
        paidAmountSection.style.display = 'block';
        paidAmountInput.value = 0;
        updateDueAmount();
    } else if (paymentStatus === 'pending') {
        paidAmountSection.style.display = 'none';
        paidAmountInput.value = 0;
    } else {
        paidAmountSection.style.display = 'none';
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const tax = subtotal * 0.10;
        const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
        const total = subtotal + tax - discount;
        paidAmountInput.value = total;
    }
}

// Update due amount display
function updateDueAmount() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const tax = subtotal * 0.10;
    const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
    const total = subtotal + tax - discount;
    const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
    const dueAmount = total - paidAmount;
    
    document.getElementById('dueAmountText').textContent = `Due Amount: Rs${formatCurrency(Math.max(0, dueAmount))}`;
}

// Update paid amount when discount changes
document.getElementById('paidAmount').addEventListener('input', updateDueAmount);

// Add to cart
function addToCart(product) {
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.sell_price),
            quantity: 1,
            tax_rate: parseFloat(product.tax_rate)
        });
    }
    
    updateCart();
}

// Update cart display
function updateCart() {
    const cartDiv = document.getElementById('cartItems');
    cartDiv.innerHTML = '';
    
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        
        cartDiv.innerHTML += `
            <div class="cart-item">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">${item.name}</h6>
                    <button class="btn btn-sm btn-danger" onclick="removeItem(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="input-group input-group-sm" style="width: 100px;">
                        <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, -1)">-</button>
                        <input type="number" class="form-control text-center" value="${item.quantity}" 
                               onchange="updateQuantity(${index}, this.value)">
                        <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, 1)">+</button>
                    </div>
                    <span>Rs${formatCurrency(itemTotal)}</span>
                </div>
            </div>
        `;
    });
    
    calculateTotals();
}

// Update quantity
function updateQuantity(index, value) {
    if (typeof value === 'number') {
        cart[index].quantity += value;
    } else {
        cart[index].quantity = parseInt(value);
    }
    
    if (cart[index].quantity < 1) {
        cart[index].quantity = 1;
    }
    
    updateCart();
}

// Remove item
function removeItem(index) {
    cart.splice(index, 1);
    updateCart();
}

// Calculate totals
function calculateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const tax = subtotal * 0.10; // 10% tax
    const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
    const total = subtotal + tax - discount;
    
    document.getElementById('subtotal').textContent = `Rs${formatCurrency(subtotal)}`;
    document.getElementById('tax').textContent = `Rs${formatCurrency(tax)}`;
    document.getElementById('total').textContent = `Rs${formatCurrency(total)}`;
}

// Format currency
function formatCurrency(amount) {
    // Ensure amount is a number
    const numAmount = parseFloat(amount) || 0;
    return numAmount.toFixed(2);
}

// Hold sale
document.getElementById('holdBtn').addEventListener('click', function() {
    if (cart.length === 0) {
        alert('Cart is empty!');
        return;
    }
    
    const holdId = Date.now();
    heldSales.push({
        id: holdId,
        cart: [...cart],
        customer: document.getElementById('customerId').value,
        discount: document.getElementById('discountAmount').value
    });
    
    cart = [];
    updateCart();
    alert('Sale has been held');
});

// Checkout
document.getElementById('checkoutBtn').addEventListener('click', function() {
    if (cart.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Empty Cart',
            text: 'Please add items to the cart before checkout'
        });
        return;
    }

    // Validate payment method
    const paymentMethod = document.getElementById('paymentMethod').value;
    if (!paymentMethod) {
        Swal.fire({
            icon: 'warning',
            title: 'Payment Method Required',
            text: 'Please select a payment method'
        });
        return;
    }

    // Validate discount amount
    const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const tax = subtotal * 0.10;
    const total = subtotal + tax - discountAmount;
    
    if (discountAmount >= (subtotal + tax)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Discount',
            text: 'Discount amount cannot be greater than or equal to total amount'
        });
        return;
    }
    
    // Get payment status and paid amount
    const paymentStatus = document.getElementById('paymentStatus').value;
    let paidAmount = 0;
    
    if (paymentStatus === 'paid') {
        paidAmount = total;
    } else if (paymentStatus === 'partial') {
        paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
        
        if (paidAmount <= 0) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Amount',
                text: 'Please enter a valid paid amount'
            });
            return;
        }
        
        if (paidAmount > total) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Amount',
                text: 'Paid amount cannot be greater than total amount'
            });
            return;
        }
    } else if (paymentStatus === 'pending') {
        paidAmount = 0;
        
        // Confirm unpaid sale
        Swal.fire({
            title: 'Unpaid Sale',
            text: 'This sale will be recorded as unpaid. Customer needs to be selected for credit tracking.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Continue',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                processSale(paymentMethod, paymentStatus, paidAmount, discountAmount);
            }
        });
        return;
    }
    
    // Show confirmation for partial payment
    if (paymentStatus === 'partial') {
        const dueAmount = total - paidAmount;
        Swal.fire({
            title: 'Partial Payment',
            html: `<p>Paid: Rs${formatCurrency(paidAmount)}</p><p>Due: Rs${formatCurrency(dueAmount)}</p>`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Continue',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                processSale(paymentMethod, paymentStatus, paidAmount, discountAmount);
            }
        });
        return;
    }
    
    // Process full payment
    processSale(paymentMethod, paymentStatus, paidAmount, discountAmount);
});

function processSale(paymentMethod, paymentStatus, paidAmount, discountAmount) {
    const saleData = {
        customer_id: document.getElementById('customerId').value,
        items: cart,
        payment_method: paymentMethod,
        payment_status: paymentStatus,
        paid_amount: paidAmount,
        discount_amount: discountAmount
    };
    
    // Send sale data to server
    fetch('app/controllers/process_sale.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(saleData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Server response:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        if (data.success) {
            showReceipt(data.sale);
            cart = [];
            updateCart();
            
            // Reset payment status to full payment
            document.getElementById('paymentStatus').value = 'paid';
            document.getElementById('paidAmountSection').style.display = 'none';
            
            // Show success message and open invoice
            let statusText = '';
            if (paymentStatus === 'paid') {
                statusText = 'Sale completed successfully';
            } else if (paymentStatus === 'partial') {
                statusText = `Partial payment received. Due: Rs${formatCurrency(data.sale.due_amount)}`;
            } else {
                statusText = 'Sale recorded as unpaid';
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: statusText,
                didClose: () => {
                    // Open invoice in new window
                    window.open(data.invoice_url, '_blank');
                }
            });
        } else {
            // Show specific error message from server
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'An error occurred while processing the sale'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Show network or parsing error with more details
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'A network error occurred while processing the sale. Please try again.'
        });
    });
}

// Show receipt
function showReceipt(sale) {
    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    const content = document.getElementById('receiptContent');
    
    // Default settings in case fetch fails
    const defaultSettings = {
        shop_name: 'POSFlix',
        shop_address: 'Default Address',
        shop_phone: 'N/A',
        footer_text: 'Thank you for your purchase!',
        currency: 'PKR'
    };
    
    // Get store settings
    fetch('app/controllers/get_settings.php')
    .then(response => {
        if (!response.ok) {
            console.warn('Failed to fetch settings, using default settings');
            return defaultSettings;
        }
        return response.json();
    })
    .then(settings => {
        const currency = settings.currency || 'PKR';
        const currencySymbol = currency === 'PKR' ? 'Rs' : currency;
        
        // Build receipt HTML
        let receiptHtml = `
            <div class="text-center mb-4">
                <h4>${settings.shop_name || 'POSFlix'}</h4>
                <p class="mb-1">${settings.shop_address || ''}</p>
                <p class="mb-1">Phone: ${settings.shop_phone || ''}</p>
                <p>Invoice #: ${sale.invoice_no}</p>
            </div>
            
            <div class="mb-4">
                <p class="mb-1">Date: ${new Date().toLocaleString()}</p>
                <p>Cashier: ${sale.cashier_name || 'N/A'}</p>
            </div>
            
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>`;
        
        // Add items
        sale.items.forEach(item => {
            receiptHtml += `
                <tr>
                    <td>${item.name}</td>
                    <td class="text-end">${item.quantity}</td>
                    <td class="text-end">${currencySymbol}${formatCurrency(item.price)}</td>
                    <td class="text-end">${currencySymbol}${formatCurrency(item.price * item.quantity)}</td>
                </tr>`;
        });
        
        // Add totals
        receiptHtml += `
                </tbody>
            </table>
            
            <div class="text-end mb-4">
                <p class="mb-1">Subtotal: ${currencySymbol}${formatCurrency(sale.subtotal)}</p>
                <p class="mb-1">Tax: ${currencySymbol}${formatCurrency(sale.tax_amount)}</p>
                <p class="mb-1">Discount: ${currencySymbol}${formatCurrency(sale.discount_amount)}</p>
                <h5>Total: ${currencySymbol}${formatCurrency(sale.net_amount)}</h5>
            </div>
            
            <div class="text-center">
                <p class="mb-1">Payment Method: ${sale.payment_method}</p>
                <p>${settings.footer_text || 'Thank you for your purchase!'}</p>
            </div>`;
        
        content.innerHTML = receiptHtml;
        modal.show();
    })
    .catch(error => {
        console.error('Error loading settings:', error);
        // Use default settings
        const currencySymbol = 'Rs';
        
        let receiptHtml = `
            <div class="text-center mb-4">
                <h4>${defaultSettings.shop_name}</h4>
                <p class="mb-1">${defaultSettings.shop_address}</p>
                <p class="mb-1">Phone: ${defaultSettings.shop_phone}</p>
                <p>Invoice #: ${sale.invoice_no}</p>
            </div>
            
            <div class="mb-4">
                <p class="mb-1">Date: ${new Date().toLocaleString()}</p>
                <p>Cashier: ${sale.cashier_name || 'N/A'}</p>
            </div>
            
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>`;
        
        // Add items
        sale.items.forEach(item => {
            receiptHtml += `
                <tr>
                    <td>${item.name}</td>
                    <td class="text-end">${item.quantity}</td>
                    <td class="text-end">${currencySymbol}${formatCurrency(item.price)}</td>
                    <td class="text-end">${currencySymbol}${formatCurrency(item.price * item.quantity)}</td>
                </tr>`;
        });
        
        // Add totals
        receiptHtml += `
                </tbody>
            </table>
            
            <div class="text-end mb-4">
                <p class="mb-1">Subtotal: ${currencySymbol}${formatCurrency(sale.subtotal)}</p>
                <p class="mb-1">Tax: ${currencySymbol}${formatCurrency(sale.tax_amount)}</p>
                <p class="mb-1">Discount: ${currencySymbol}${formatCurrency(sale.discount_amount)}</p>
                <h5>Total: ${currencySymbol}${formatCurrency(sale.net_amount)}</h5>
            </div>
            
            <div class="text-center">
                <p class="mb-1">Payment Method: ${sale.payment_method}</p>
                <p>${defaultSettings.footer_text}</p>
            </div>`;
        
        content.innerHTML = receiptHtml;
        modal.show();
    });
}

// Print receipt
function printReceipt() {
    const content = document.getElementById('receiptContent');
    const printWindow = window.open('', '', 'width=600,height=600');
    
    printWindow.document.open();
    printWindow.document.write(`
        <html>
        <head>
            <title>Receipt</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-4">
                ${content.innerHTML}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

// Search products
document.getElementById('searchProduct').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const products = document.querySelectorAll('.product-item');
    
    products.forEach(product => {
        const name = product.querySelector('.card-title').textContent.toLowerCase();
        if (name.includes(search)) {
            product.style.display = '';
        } else {
            product.style.display = 'none';
        }
    });
});

// Filter by category
document.getElementById('categoryFilter').addEventListener('change', function(e) {
    const categoryId = e.target.value;
    const products = document.querySelectorAll('.product-item');
    
    products.forEach(product => {
        if (!categoryId || product.dataset.category === categoryId) {
            product.style.display = '';
        } else {
            product.style.display = 'none';
        }
    });
});

// Update totals when discount changes
document.getElementById('discountAmount').addEventListener('input', calculateTotals);
</script>

<?php include 'app/views/layout/footer.php'; ?>