<?php
/**
 * Checkout Page with Pakasir & Atlantic H2H Integration
 * Handles both local products and API products
 */

require_once 'config.php';
require_once 'classes/Pakasir.php';

// Check login
if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();
$errors = [];
$success = false;

// Get product
$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;

if ($productId <= 0) {
    redirect('index.php');
}

// Get product details
$stmt = $conn->prepare("
    SELECT p.*, c.nama as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.kategori_id = c.id 
    WHERE p.id = ? AND p.is_active = 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect('index.php');
}

// Check product availability
$isApiProduct = ($product['tipe_produk'] === 'otomatis' && !empty($product['product_code']));
$availableStock = 0;

if ($isApiProduct) {
    // For API products, check Atlantic H2H availability
    require_once 'classes/AtlanticH2H.php';
    $atlantic = new AtlanticH2H();
    
    $priceList = $atlantic->getPriceList('prabayar', $product['product_code']);
    if ($priceList['success']) {
        $apiProduct = $priceList['data']['data'][0] ?? null;
        if ($apiProduct && $apiProduct['status'] === 'available') {
            $availableStock = 999; // API products always available if status is available
            // Update price from API
            $product['harga'] = intval($apiProduct['price']);
        }
    }
} else {
    // For local products, use database stock
    $availableStock = intval($product['stok']);
}

if ($availableStock < $quantity) {
    $_SESSION['error'] = "Stok tidak mencukupi!";
    redirect('produk.php?id=' . $productId);
}

// Initialize Pakasir
$pakasir = new Pakasir($conn);
$paymentMethods = $pakasir->getPaymentMethods();

// Process checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_checkout'])) {
    try {
        // Validate inputs
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $customerData = [];
        
        // Get customer data based on product type
        if ($isApiProduct) {
            // For Netflix products
            if (in_array($product['product_code'], ['NTFS1', 'NTSM1'])) {
                // Netflix Sharing & Semi Private
                $customerDevice = trim($_POST['customer_device'] ?? '');
                $customerLocation = trim($_POST['customer_location'] ?? '');
                
                if (empty($customerDevice) || empty($customerLocation)) {
                    throw new Exception("Device dan Lokasi harus diisi!");
                }
                
                $customerData = [
                    'device_info' => $customerDevice . ' - ' . $customerLocation,
                    'device' => $customerDevice,
                    'location' => $customerLocation,
                    'email' => $user['email'],
                    'phone' => $user['no_hp'] ?? '',
                    'name' => $user['nama']
                ];
            } else {
                // Netflix Private or other API products
                $customerEmail = trim($_POST['customer_email'] ?? $user['email']);
                $customerPhone = trim($_POST['customer_phone'] ?? '');
                
                if (empty($customerEmail)) {
                    throw new Exception("Email harus diisi!");
                }
                
                $customerData = [
                    'device_info' => $customerEmail,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'name' => $user['nama']
                ];
            }
        } else {
            // For local products
            $customerData = [
                'email' => $user['email'],
                'phone' => $user['no_hp'] ?? '',
                'name' => $user['nama'],
                'notes' => trim($_POST['notes'] ?? '')
            ];
        }
        
        if (empty($paymentMethod)) {
            throw new Exception("Pilih metode pembayaran!");
        }
        
        // Calculate fees and total
        $subtotal = $product['harga'] * $quantity;
        $paymentFee = $pakasir->calculateFee($paymentMethod, $subtotal);
        $adminFee = 1500; // Fixed admin fee
        $total = $subtotal + $paymentFee + $adminFee;
        
        // Generate order ID
        $orderId = generateTransactionId();
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // 1. Insert main transaction
            $stmt = $conn->prepare("
                INSERT INTO transactions (
                    order_id, user_id, product_id, quantity,
                    subtotal, admin_fee, payment_fee, total,
                    payment_method, status, customer_data,
                    product_type, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $orderId,
                $user['id'],
                $productId,
                $quantity,
                $subtotal,
                $adminFee,
                $paymentFee,
                $total,
                $paymentMethod,
                json_encode($customerData),
                $isApiProduct ? 'api' : 'local'
            ]);
            
            $transactionId = $conn->lastInsertId();
            
            // 2. For API products, create H2H transaction record
            if ($isApiProduct) {
                $stmt = $conn->prepare("
                    INSERT INTO h2h_transactions (
                        order_id, user_id, product_id, product_code,
                        customer_data, price, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $orderId,
                    $user['id'],
                    $productId,
                    $product['product_code'],
                    json_encode($customerData),
                    $product['harga']
                ]);
            } else {
                // For local products, reserve stock
                $stmt = $conn->prepare("
                    UPDATE products 
                    SET stok = stok - ?, 
                        updated_at = NOW() 
                    WHERE id = ? AND stok >= ?
                ");
                
                $stmt->execute([$quantity, $productId, $quantity]);
                
                if ($stmt->rowCount() == 0) {
                    throw new Exception("Stok tidak mencukupi!");
                }
                
                // Create stock history
                $stmt = $conn->prepare("
                    INSERT INTO stock_history (
                        product_id, order_id, type, quantity, 
                        balance, notes, created_at
                    ) VALUES (?, ?, 'out', ?, ?, ?, NOW())
                ");
                
                $newStock = $product['stok'] - $quantity;
                $stmt->execute([
                    $productId,
                    $orderId,
                    $quantity,
                    $newStock,
                    "Order #" . $orderId
                ]);
            }
            
            // 3. Create Pakasir payment
            $pakasirOptions = [
                'customer_name' => $user['nama'],
                'customer_email' => $user['email'],
                'customer_phone' => $user['no_hp'] ?? '',
                'callback_url' => SITE_URL . '/pakasir-webhook.php',
                'return_url' => SITE_URL . '/payment-success.php?order_id=' . $orderId,
                'expired_time' => 3600, // 1 hour
                'items' => [
                    [
                        'name' => $product['nama'],
                        'quantity' => $quantity,
                        'price' => $product['harga']
                    ]
                ]
            ];
            
            $payment = $pakasir->createTransaction($paymentMethod, $orderId, $total, $pakasirOptions);
            
            if (!$payment['success']) {
                throw new Exception($payment['message'] ?? 'Gagal membuat pembayaran');
            }
            
            $paymentData = $payment['data'];
            
            // 4. Update transaction with payment info
            $stmt = $conn->prepare("
                UPDATE transactions 
                SET payment_number = ?,
                    payment_expired_at = ?,
                    payment_data = ?
                WHERE order_id = ?
            ");
            
            $stmt->execute([
                $paymentData['va_number'] ?? $paymentData['payment_code'] ?? '',
                $paymentData['expired_at'],
                json_encode($paymentData),
                $orderId
            ]);
            
            // 5. Commit transaction
            $conn->commit();
            
            // 6. Redirect based on payment method
            if ($paymentMethod === 'qris' && !empty($paymentData['qr_string'])) {
                // For QRIS, show QR on payment page
                $_SESSION['payment_data'] = $paymentData;
                redirect('payment-instruction.php?order_id=' . $orderId);
            } elseif (strpos($paymentMethod, '_va') !== false) {
                // For VA, show VA number
                $_SESSION['payment_data'] = $paymentData;
                redirect('payment-instruction.php?order_id=' . $orderId);
            } else {
                // For e-wallet, redirect to payment URL
                if (!empty($paymentData['payment_url'])) {
                    redirect($paymentData['payment_url']);
                } else {
                    redirect('payment-instruction.php?order_id=' . $orderId);
                }
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        error_log("Checkout error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($product['nama']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .checkout-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .checkout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .checkout-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .checkout-body {
            padding: 30px;
        }
        
        .product-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .product-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .product-price {
            font-size: 20px;
            color: #667eea;
            font-weight: bold;
        }
        
        .product-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .badge-api {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-local {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .form-hint {
            display: block;
            margin-top: 5px;
            font-size: 13px;
            color: #999;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .payment-method {
            position: relative;
            cursor: pointer;
        }
        
        .payment-method input {
            position: absolute;
            opacity: 0;
        }
        
        .payment-method label {
            display: block;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .payment-method input:checked + label {
            border-color: #667eea;
            background: #f3f4ff;
        }
        
        .payment-method img {
            height: 30px;
            margin-bottom: 5px;
        }
        
        .payment-method span {
            display: block;
            font-size: 12px;
            color: #666;
        }
        
        .price-breakdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
        }
        
        .price-row.total {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
            margin-right: 10px;
        }
        
        @media (max-width: 600px) {
            .payment-methods {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            
            .checkout-header h1 {
                font-size: 24px;
            }
            
            .product-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .product-price {
                margin-top: 10px;
            }
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="checkout-card">
            <div class="checkout-header">
                <h1>Checkout</h1>
                <p>Selesaikan pembayaran Anda</p>
            </div>
            
            <div class="checkout-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p>⚠️ <?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Product Summary -->
                <div class="product-summary">
                    <div class="product-info">
                        <div>
                            <div class="product-name"><?php echo htmlspecialchars($product['nama']); ?></div>
                            <span class="product-badge <?php echo $isApiProduct ? 'badge-api' : 'badge-local'; ?>">
                                <?php echo $isApiProduct ? '⚡ Produk Otomatis' : '📦 Produk Manual'; ?>
                            </span>
                        </div>
                        <div class="product-price">
                            <?php echo formatRupiah($product['harga']); ?>
                        </div>
                    </div>
                    <?php if (!empty($product['deskripsi'])): ?>
                        <p style="color: #666; font-size: 14px; margin-top: 10px;">
                            <?php echo nl2br(htmlspecialchars($product['deskripsi'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <form method="POST" id="checkoutForm">
                    <!-- Customer Information -->
                    <div class="form-section">
                        <h3 class="section-title">Informasi Pelanggan</h3>
                        
                        <?php if ($isApiProduct && in_array($product['product_code'], ['NTFS1', 'NTSM1'])): ?>
                            <!-- Netflix Sharing & Semi Private -->
                            <div class="alert alert-info">
                                ℹ️ Untuk produk ini, harap isi informasi device dan lokasi Anda
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Device *</label>
                                <input type="text" 
                                       name="customer_device" 
                                       class="form-control" 
                                       placeholder="Contoh: iPhone 14 Pro"
                                       required>
                                <span class="form-hint">Masukkan jenis device yang akan digunakan</span>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Lokasi *</label>
                                <input type="text" 
                                       name="customer_location" 
                                       class="form-control" 
                                       placeholder="Contoh: Jakarta"
                                       required>
                                <span class="form-hint">Masukkan kota/lokasi Anda</span>
                            </div>
                            
                        <?php elseif ($isApiProduct): ?>
                            <!-- Other API Products -->
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" 
                                       name="customer_email" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       required>
                                <span class="form-hint">Email untuk pengiriman akses produk</span>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">No. HP</label>
                                <input type="tel" 
                                       name="customer_phone" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user['no_hp'] ?? ''); ?>"
                                       placeholder="08123456789">
                                <span class="form-hint">Nomor HP untuk notifikasi (opsional)</span>
                            </div>
                            
                        <?php else: ?>
                            <!-- Local Products -->
                            <div class="form-group">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea name="notes" 
                                          class="form-control" 
                                          rows="3" 
                                          placeholder="Tambahkan catatan untuk penjual..."></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="form-section">
                        <h3 class="section-title">Metode Pembayaran</h3>
                        
                        <div class="payment-methods">
                            <?php foreach ($paymentMethods as $key => $method): ?>
                                <div class="payment-method">
                                    <input type="radio" 
                                           id="payment_<?php echo $key; ?>" 
                                           name="payment_method" 
                                           value="<?php echo $key; ?>"
                                           required>
                                    <label for="payment_<?php echo $key; ?>">
                                        <?php if ($method['type'] === 'e-wallet'): ?>
                                            💳
                                        <?php else: ?>
                                            🏦
                                        <?php endif; ?>
                                        <span><?php echo $method['name']; ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Price Breakdown -->
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Subtotal (<?php echo $quantity; ?> item)</span>
                            <span><?php echo formatRupiah($product['harga'] * $quantity); ?></span>
                        </div>
                        <div class="price-row">
                            <span>Biaya Admin</span>
                            <span>Rp 1.500</span>
                        </div>
                        <div class="price-row" id="paymentFeeRow" style="display: none;">
                            <span>Biaya Payment</span>
                            <span id="paymentFeeAmount">Rp 0</span>
                        </div>
                        <div class="price-row total">
                            <span>Total</span>
                            <span id="totalAmount"><?php echo formatRupiah(($product['harga'] * $quantity) + 1500); ?></span>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="margin-top: 30px; display: flex; align-items: center;">
                        <a href="produk.php?id=<?php echo $productId; ?>" class="btn btn-secondary">
                            ← Kembali
                        </a>
                        <button type="submit" 
                                name="process_checkout" 
                                class="btn btn-primary"
                                id="submitBtn">
                            Proses Pembayaran
                            <span class="loading-spinner" id="loadingSpinner"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Calculate payment fee dynamically
        const paymentMethods = <?php echo json_encode($paymentMethods); ?>;
        const subtotal = <?php echo $product['harga'] * $quantity; ?>;
        const adminFee = 1500;
        
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const method = this.value;
                if (paymentMethods[method]) {
                    let fee = 0;
                    const methodFee = paymentMethods[method].fee;
                    
                    if (methodFee < 100) {
                        // Percentage fee
                        fee = Math.round(subtotal * methodFee / 100);
                    } else {
                        // Flat fee
                        fee = methodFee;
                    }
                    
                    // Update display
                    document.getElementById('paymentFeeRow').style.display = fee > 0 ? 'flex' : 'none';
                    document.getElementById('paymentFeeAmount').textContent = 'Rp ' + fee.toLocaleString('id-ID');
                    
                    const total = subtotal + adminFee + fee;
                    document.getElementById('totalAmount').textContent = 'Rp ' + total.toLocaleString('id-ID');
                }
            });
        });
        
        // Form submission
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const spinner = document.getElementById('loadingSpinner');
            
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            btn.innerHTML = 'Memproses... <span class="loading-spinner" style="display: inline-block;"></span>';
        });
    </script>
</body>
</html>