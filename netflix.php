<?php
/**
 * ============================================
 * CHECKOUT FORM UNTUK PRODUK NETFLIX
 * Copy paste kode ini ke file checkout.php Anda
 * atau tambahkan ke form checkout existing
 * ============================================
 */

require_once 'config.php';

// Check login
if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();

// Get product from URL
$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($productId > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        redirect('index.php');
    }
} else {
    redirect('index.php');
}

// Process checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_checkout'])) {
    
    $errors = [];
    
    try {
        $quantity = 1; // Netflix biasanya 1 qty
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        
        // Get customer input based on product type
        if (in_array($product['product_code'], ['NTFS1', 'NTSM1'])) {
            // Netflix Sharing & Semi Private - butuh Device & Lokasi
            $customerDevice = trim($_POST['customer_device'] ?? '');
            $customerLocation = trim($_POST['customer_location'] ?? '');
            
            if (empty($customerDevice) || empty($customerLocation)) {
                throw new Exception("Device dan Lokasi harus diisi!");
            }
            
            // Format: DEVICE - LOKASI
            $deviceInfo = $customerDevice . ' - ' . $customerLocation;
            
            $customerData = json_encode([
                'device_info' => $deviceInfo,
                'device' => $customerDevice,
                'location' => $customerLocation,
                'email' => $user['email'],
                'phone' => $user['no_hp'] ?? '',
                'name' => $user['nama']
            ]);
            
        } else {
            // Netflix Private - butuh Email/Phone saja
            $customerEmail = trim($_POST['customer_email'] ?? $user['email']);
            $customerPhone = trim($_POST['customer_phone'] ?? $user['no_hp'] ?? '');
            
            if (empty($customerEmail)) {
                throw new Exception("Email harus diisi!");
            }
            
            $deviceInfo = $customerEmail;
            
            $customerData = json_encode([
                'device_info' => $customerEmail,
                'email' => $customerEmail,
                'phone' => $customerPhone,
                'name' => $user['nama']
            ]);
        }
        
        if (empty($paymentMethod)) {
            throw new Exception("Pilih metode pembayaran!");
        }
        
        // Calculate total
        $subtotal = $product['harga'] * $quantity;
        $adminFee = 1500;
        $total = $subtotal + $adminFee;
        
        // Generate order ID
        $orderId = generateTransactionId();
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // 1. Insert main transaction
            $stmt = $conn->prepare("
                INSERT INTO transactions (
                    order_id, user_id, product_id, quantity,
                    subtotal, admin_fee, total,
                    payment_method, status, customer_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $stmt->execute([
                $orderId,
                $user['id'],
                $productId,
                $quantity,
                $subtotal,
                $adminFee,
                $total,
                $paymentMethod,
                $customerData
            ]);
            
            $transactionId = $conn->lastInsertId();
            
            // 2. Insert H2H transaction (untuk produk otomatis)
            if ($product['tipe_produk'] === 'otomatis' && !empty($product['product_code'])) {
                $stmt = $conn->prepare("
                    INSERT INTO h2h_transactions (
                        order_id, user_id, product_id, product_code,
                        customer_data, price, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                $stmt->execute([
                    $orderId,
                    $user['id'],
                    $productId,
                    $product['product_code'],
                    $customerData,
                    $product['harga']
                ]);
            }
            
            // 3. Create Pakasir payment (implement sesuai Pakasir API Anda)
            // ... kode create payment di sini ...
            
            // 4. Update transaction dengan payment info
            // ... kode update payment info ...
            
            // 5. Commit
            $conn->commit();
            
            // Redirect ke halaman payment
            $_SESSION['success_message'] = 'Order berhasil dibuat!';
            redirect('payment.php?order_id=' . $orderId);
            
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
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
        .checkout-form {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-hint {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
        }
        .alert-info {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            color: #1976d2;
        }
        .product-info {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
            width: 100%;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="checkout-form">
        <h2>Checkout - <?php echo htmlspecialchars($product['nama']); ?></h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="product-info">
            <h3><?php echo htmlspecialchars($product['nama']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($product['deskripsi'])); ?></p>
            <p><strong>Harga: <?php echo formatRupiah($product['harga']); ?></strong></p>
        </div>
        
        <form method="POST">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            
            <?php if (in_array($product['product_code'], ['NTFS1', 'NTSM1'])): ?>
                <!-- Form untuk Netflix Sharing & Semi Private -->
                <div class="alert alert-info">
                    <strong>⚠️ Format Pengisian:</strong><br>
                    Untuk produk ini, Anda harus mengisi informasi Device dan Lokasi Anda.
                </div>
                
                <div class="form-group">
                    <label class="form-label">Device *</label>
                    <input type="text" 
                           name="customer_device" 
                           class="form-control" 
                           placeholder="Contoh: Iphone 20 Pro"
                           required>
                    <span class="form-hint">Masukkan jenis device yang akan digunakan</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Lokasi *</label>
                    <input type="text" 
                           name="customer_location" 
                           class="form-control" 
                           placeholder="Contoh: Bandung"
                           required>
                    <span class="form-hint">Masukkan kota/lokasi Anda</span>
                </div>
                
            <?php else: ?>
                <!-- Form untuk Netflix Private -->
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" 
                           name="customer_email" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($user['email']); ?>"
                           required>
                    <span class="form-hint">Email untuk akun Netflix Anda</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">No. HP (Opsional)</label>
                    <input type="tel" 
                           name="customer_phone" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($user['no_hp'] ?? ''); ?>"
                           placeholder="08123456789">
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label">Metode Pembayaran *</label>
                <select name="payment_method" class="form-control" required>
                    <option value="">Pilih Metode Pembayaran</option>
                    <option value="qris">QRIS</option>
                    <option value="bni_va">BNI Virtual Account</option>
                    <option value="bri_va">BRI Virtual Account</option>
                    <option value="mandiri_va">Mandiri Virtual Account</option>
                    <option value="bca_va">BCA Virtual Account</option>
                </select>
            </div>
            
            <div class="product-info">
                <table style="width: 100%;">
                    <tr>
                        <td>Subtotal:</td>
                        <td style="text-align: right;"><?php echo formatRupiah($product['harga']); ?></td>
                    </tr>
                    <tr>
                        <td>Biaya Admin:</td>
                        <td style="text-align: right;">Rp 1.500</td>
                    </tr>
                    <tr style="font-weight: bold; font-size: 1.2rem;">
                        <td>Total:</td>
                        <td style="text-align: right;"><?php echo formatRupiah($product['harga'] + 1500); ?></td>
                    </tr>
                </table>
            </div>
            
            <button type="submit" name="process_checkout" class="btn btn-primary">
                Bayar Sekarang
            </button>
        </form>
    </div>
</body>
</html>