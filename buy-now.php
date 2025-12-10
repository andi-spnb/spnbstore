<?php
/**
 * Buy Now - Fixed Version
 * Menangani pembelian langsung dengan pembedaan produk lokal vs API
 */

require_once 'config.php';

// Optional: Load Atlantic H2H class if exists
if (file_exists('classes/AtlanticH2H.php')) {
    require_once 'classes/AtlanticH2H.php';
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu',
        'redirect' => 'login.php'
    ]);
    exit;
}

$user = getUserData();

// Get POST data
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Data produk tidak valid'
    ]);
    exit;
}

try {
    // Get product details
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => 'Produk tidak ditemukan atau tidak aktif'
        ]);
        exit;
    }
    
    // ============================================
    // CHECK PRODUCT AVAILABILITY BASED ON TYPE
    // ============================================
    $isApiProduct = ($product['tipe_produk'] === 'otomatis' && !empty($product['product_code']));
    
    if ($isApiProduct) {
        // API Product - Check Atlantic H2H availability
        if (class_exists('AtlanticH2H')) {
            $atlantic = new AtlanticH2H();
            $priceList = $atlantic->getPriceList('prabayar', $product['product_code']);
            
            if (!$priceList['success']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tidak dapat mengecek ketersediaan produk'
                ]);
                exit;
            }
            
            $apiProduct = $priceList['data']['data'][0] ?? null;
            if (!$apiProduct || $apiProduct['status'] !== 'available') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Produk sedang tidak tersedia dari supplier'
                ]);
                exit;
            }
            
            // Update price from API if different
            if (intval($apiProduct['price']) != $product['harga']) {
                $product['harga'] = intval($apiProduct['price']);
            }
        }
        // For API products, stock is unlimited
        $availableStock = 999;
    } else {
        // Local Product - Check database stock
        $availableStock = $product['stok'];
        
        if ($availableStock < $quantity) {
            echo json_encode([
                'success' => false,
                'message' => 'Stok tidak mencukupi! Stok tersedia: ' . $availableStock
            ]);
            exit;
        }
    }
    
    // Calculate total
    $total = $product['harga'] * $quantity;
    
    // Check user balance
    if ($user['saldo'] < $total) {
        echo json_encode([
            'success' => false,
            'message' => 'Saldo tidak mencukupi!',
            'current_balance' => $user['saldo'],
            'current_balance_formatted' => formatRupiah($user['saldo']),
            'total_price' => $total,
            'total_price_formatted' => formatRupiah($total),
            'shortage' => $total - $user['saldo'],
            'shortage_formatted' => formatRupiah($total - $user['saldo']),
            'need_topup' => true,
            'redirect' => 'topup.php'
        ]);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Generate transaction ID
        $transaction_id = 'TRX-' . time() . '-' . rand(1000, 9999);
        $order_id = 'ORDER-' . time() . '-' . rand(1000, 9999);
        
        // Determine status based on product type
        $status = $product['tipe_produk'] == 'otomatis' ? 'processing' : 'proses';
        
        // Insert transaction
        $stmt = $conn->prepare("INSERT INTO transactions 
                               (user_id, product_id, transaction_id, order_id, quantity, 
                                jumlah, total_harga, status, payment_method, keterangan, 
                                created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'saldo', ?, NOW())");
        
        $keterangan = "Pembelian langsung {$quantity}x {$product['nama']}";
        
        if (!$stmt->execute([
            $user['id'], 
            $product_id, 
            $transaction_id, 
            $order_id,
            $quantity, 
            $quantity,
            $total, 
            $status, 
            $keterangan
        ])) {
            throw new Exception('Gagal membuat transaksi');
        }
        
        $transaction_db_id = $conn->lastInsertId();
        
        // ============================================
        // HANDLE STOCK BASED ON PRODUCT TYPE
        // ============================================
        if ($isApiProduct) {
            // API PRODUCT - Don't reduce stock, create H2H record
            
            // Create H2H transaction record for processing later
            $stmt = $conn->prepare("INSERT INTO h2h_transactions 
                                   (order_id, user_id, product_id, product_code, 
                                    customer_data, price, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
            
            $customerData = json_encode([
                'email' => $user['email'] ?? '',
                'phone' => $user['whatsapp'] ?? '',
                'name' => $user['nama_lengkap'] ?? $user['username']
            ]);
            
            $stmt->execute([
                $order_id,
                $user['id'],
                $product_id,
                $product['product_code'],
                $customerData,
                $product['harga']
            ]);
            
        } else {
            // LOCAL PRODUCT - Reduce stock from database
            
            $stmt = $conn->prepare("UPDATE products SET stok = stok - ? WHERE id = ? AND stok >= ?");
            if (!$stmt->execute([$quantity, $product_id, $quantity])) {
                throw new Exception('Gagal mengupdate stok produk');
            }
            
            if ($stmt->rowCount() == 0) {
                throw new Exception('Stok tidak mencukupi');
            }
            
            // Log stock history (optional)
            if ($conn->query("SHOW TABLES LIKE 'stock_history'")->rowCount() > 0) {
                $stmt = $conn->prepare("INSERT INTO stock_history 
                                       (product_id, order_id, type, quantity, balance, notes, created_at)
                                       VALUES (?, ?, 'out', ?, ?, ?, NOW())");
                
                $newBalance = $product['stok'] - $quantity;
                $stmt->execute([
                    $product_id,
                    $order_id,
                    $quantity,
                    $newBalance,
                    "Direct purchase via buy-now"
                ]);
            }
        }
        
        // Update user balance
        $stmt = $conn->prepare("UPDATE users SET saldo = saldo - ? WHERE id = ?");
        if (!$stmt->execute([$total, $user['id']])) {
            throw new Exception('Gagal mengupdate saldo');
        }
        
        // Update transaction status to 'ready' after payment
        $finalStatus = $product['tipe_produk'] == 'otomatis' ? 'ready' : 'proses';
        $stmt = $conn->prepare("UPDATE transactions SET status = ?, payment_completed_at = NOW() WHERE id = ?");
        $stmt->execute([$finalStatus, $transaction_db_id]);
        
        // ============================================
        // TRIGGER ATLANTIC H2H IF API PRODUCT
        // ============================================
        if ($isApiProduct) {
            // Trigger Atlantic H2H processing
            triggerAtlanticH2H($order_id);
        }
        
        $conn->commit();
        
        // Get new balance
        $new_balance = $user['saldo'] - $total;
        
        echo json_encode([
            'success' => true,
            'message' => 'Pembelian berhasil!',
            'transaction_id' => $transaction_id,
            'transaction_db_id' => $transaction_db_id,
            'product_name' => $product['nama'],
            'product_type' => $isApiProduct ? 'api' : 'local',
            'quantity' => $quantity,
            'total' => $total,
            'total_formatted' => formatRupiah($total),
            'new_balance' => $new_balance,
            'new_balance_formatted' => formatRupiah($new_balance),
            'status' => $finalStatus,
            'status_text' => $finalStatus == 'ready' ? 'Pesanan siap' : 'Pesanan sedang diproses',
            'redirect' => 'transaction-detail.php?id=' . $transaction_db_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log error
    error_log("Buy Now Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

/**
 * Trigger Atlantic H2H Transaction
 */
function triggerAtlanticH2H($orderId) {
    global $conn;
    
    try {
        // Check if Atlantic function exists in config
        if (function_exists('processAtlanticTransaction')) {
            $result = processAtlanticTransaction($orderId);
            
            if ($result['success']) {
                error_log("Atlantic H2H triggered successfully for order: " . $orderId);
            } else {
                error_log("Atlantic H2H trigger failed for order: " . $orderId . " - " . $result['message']);
            }
        } else {
            // Manual trigger if function doesn't exist
            error_log("Atlantic H2H function not found, order pending: " . $orderId);
        }
    } catch (Exception $e) {
        error_log("Atlantic H2H trigger error: " . $e->getMessage());
    }
}