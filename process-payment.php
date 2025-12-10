<?php
/**
 * Process Payment - FIXED VERSION dengan Atlantic H2H Integration
 * 
 * PERBAIKAN UTAMA:
 * 1. Mendeteksi produk Atlantic berdasarkan product_code (tidak kosong)
 * 2. TIDAK mengurangi stok lokal untuk produk Atlantic
 * 3. Langsung memanggil API Atlantic setelah pembayaran saldo berhasil
 * 4. Untuk pembayaran Pakasir, Atlantic dipanggil setelah callback sukses
 * 5. Menambahkan debug logging ke console
 * 
 * DEBUG: Set $DEBUG_MODE = true untuk melihat proses di response JSON
 */

require_once 'config.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// ============================================
// DEBUG MODE - Set true untuk melihat log
// ============================================
$DEBUG_MODE = true;
$debug_logs = [];

function addDebug($message, $data = null) {
    global $DEBUG_MODE, $debug_logs;
    if ($DEBUG_MODE) {
        $log = [
            'time' => date('H:i:s'),
            'step' => count($debug_logs) + 1,
            'message' => $message
        ];
        if ($data !== null) {
            $log['data'] = $data;
        }
        $debug_logs[] = $log;
        
        // Also log to file for server-side debugging
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/payment_debug_' . date('Y-m-d') . '.log';
        $logText = "[" . date('Y-m-d H:i:s') . "] " . $message;
        if ($data !== null) {
            $logText .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        @file_put_contents($logFile, $logText . "\n", FILE_APPEND);
    }
}

addDebug('=== PAYMENT PROCESS STARTED ===');

// ============================================
// Check Login
// ============================================
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu',
        'redirect' => 'login.php',
        'debug' => $debug_logs
    ]);
    exit;
}

$user = getUserData();
addDebug('User authenticated', [
    'user_id' => $user['id'], 
    'username' => $user['username'],
    'saldo' => $user['saldo']
]);

// ============================================
// Get POST data
// ============================================
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$payment_type = isset($_POST['payment_type']) ? $_POST['payment_type'] : '';
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';

// TARGET INPUT: Untuk produk Atlantic (device info, email, dll)
// Format Netflix Sharing: "Device - Lokasi" (contoh: "Samsung TV - Jakarta")
$target_input = isset($_POST['target_input']) ? trim($_POST['target_input']) : '';

addDebug('POST data received', [
    'product_id' => $product_id,
    'quantity' => $quantity,
    'payment_type' => $payment_type,
    'payment_method' => $payment_method,
    'target_input' => $target_input,
    'notes' => $notes
]);

// ============================================
// Validate input
// ============================================
if ($product_id <= 0 || $quantity <= 0) {
    addDebug('VALIDATION FAILED: Invalid product data');
    echo json_encode([
        'success' => false,
        'message' => 'Data produk tidak valid',
        'debug' => $debug_logs
    ]);
    exit;
}

if (empty($payment_type) || !in_array($payment_type, ['saldo', 'pakasir'])) {
    addDebug('VALIDATION FAILED: Invalid payment type');
    echo json_encode([
        'success' => false,
        'message' => 'Metode pembayaran tidak valid',
        'debug' => $debug_logs
    ]);
    exit;
}

try {
    // ============================================
    // Get product details
    // ============================================
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        addDebug('PRODUCT NOT FOUND', ['product_id' => $product_id]);
        echo json_encode([
            'success' => false,
            'message' => 'Produk tidak ditemukan atau tidak aktif',
            'debug' => $debug_logs
        ]);
        exit;
    }
    
    addDebug('Product found', [
        'id' => $product['id'],
        'nama' => $product['nama'],
        'product_code' => $product['product_code'] ?? 'NULL (lokal)',
        'tipe_produk' => $product['tipe_produk'],
        'stok' => $product['stok'],
        'harga' => $product['harga']
    ]);
    
    // ============================================
    // DETECT ATLANTIC PRODUCT
    // Produk Atlantic diidentifikasi dengan product_code yang tidak kosong
    // ============================================
    $isAtlanticProduct = !empty($product['product_code']);
    addDebug('Product type detection', [
        'is_atlantic_product' => $isAtlanticProduct,
        'product_code' => $product['product_code'] ?? 'EMPTY'
    ]);
    
    // ============================================
    // Validate target input for Atlantic products
    // ============================================
    if ($isAtlanticProduct) {
        if (empty($target_input)) {
            // Jika tidak ada input, gunakan email user sebagai default
            $target_input = $user['email'] ?? ($user['username'] . '@example.com');
            addDebug('Using default target (user email)', ['target' => $target_input]);
        }
        
        // Validasi format khusus untuk Netflix Sharing (NTFS1, NTSM1)
        $netflixCodes = ['NTFS1', 'NTSM1', 'NTFS', 'NTSM'];
        if (in_array($product['product_code'], $netflixCodes)) {
            // Format harus: "Device - Lokasi"
            if (strpos($target_input, '-') === false) {
                addDebug('WARNING: Netflix format should be "Device - Lokasi"', ['current' => $target_input]);
                // Tambahkan lokasi default jika tidak ada
                $target_input = $target_input . ' - Indonesia';
            }
        }
    }
    
    // ============================================
    // Check stock - SKIP for Atlantic products
    // Atlantic products check availability via API, not local stock
    // ============================================
    if (!$isAtlanticProduct && $product['stok'] < $quantity) {
        addDebug('LOCAL STOCK INSUFFICIENT', [
            'required' => $quantity,
            'available' => $product['stok']
        ]);
        echo json_encode([
            'success' => false,
            'message' => 'Stok tidak mencukupi! Stok tersedia: ' . $product['stok'],
            'debug' => $debug_logs
        ]);
        exit;
    }
    
    if ($isAtlanticProduct) {
        addDebug('Skipping local stock check (Atlantic product - will check via API)');
    }
    
    // ============================================
    // Calculate total
    // ============================================
    $total = $product['harga'] * $quantity;
    addDebug('Total calculated', [
        'price_per_item' => $product['harga'],
        'quantity' => $quantity,
        'total' => $total
    ]);
    
    // ============================================
    // Generate transaction ID
    // ============================================
    $transaction_id = 'TRX-' . date('Ymd') . '-' . time() . '-' . rand(1000, 9999);
    $order_id = 'ORD-' . time() . '-' . $user['id'];
    
    addDebug('Transaction IDs generated', [
        'transaction_id' => $transaction_id,
        'order_id' => $order_id
    ]);
    
    // ============================================
    // PROCESS BASED ON PAYMENT TYPE
    // ============================================
    
    if ($payment_type === 'saldo') {
        // ============================================
        // PAYMENT WITH BALANCE (SALDO)
        // ============================================
        addDebug('=== PROCESSING SALDO PAYMENT ===');
        
        // Check user balance
        if ($user['saldo'] < $total) {
            addDebug('INSUFFICIENT BALANCE', [
                'user_saldo' => $user['saldo'],
                'required' => $total,
                'shortage' => $total - $user['saldo']
            ]);
            echo json_encode([
                'success' => false,
                'message' => 'Saldo tidak mencukupi! Saldo Anda: ' . formatRupiah($user['saldo']),
                'current_balance' => $user['saldo'],
                'total_price' => $total,
                'need_topup' => true,
                'debug' => $debug_logs
            ]);
            exit;
        }
        
        addDebug('Balance sufficient', [
            'user_saldo' => $user['saldo'],
            'total' => $total,
            'remaining_after' => $user['saldo'] - $total
        ]);
        
        // Start database transaction
        $conn->beginTransaction();
        addDebug('Database transaction started');
        
        try {
            // ============================================
            // Determine initial status
            // ============================================
            $status = 'proses'; // Default untuk manual
            if ($isAtlanticProduct) {
                $status = 'processing'; // Akan diproses ke Atlantic API
            } elseif ($product['tipe_produk'] == 'otomatis') {
                $status = 'ready'; // Produk lokal otomatis
            }
            
            addDebug('Initial status determined', ['status' => $status]);
            
            // ============================================
            // Prepare customer data
            // ============================================
            $customer_data = json_encode([
                'user_id' => $user['id'],
                'email' => $user['email'] ?? '',
                'whatsapp' => $user['whatsapp'] ?? '',
                'target' => $target_input,
                'device_info' => $target_input,
                'notes' => $notes
            ], JSON_UNESCAPED_UNICODE);
            
            // ============================================
            // Insert transaction
            // ============================================
            $keterangan = !empty($notes) ? $notes : "Pembelian {$quantity}x {$product['nama']}";
            
            // Check if customer_data column exists
            $stmt = $conn->query("SHOW COLUMNS FROM transactions LIKE 'customer_data'");
            $hasCustomerData = $stmt->rowCount() > 0;
            
            if ($hasCustomerData) {
                $stmt = $conn->prepare("INSERT INTO transactions 
                    (user_id, product_id, transaction_id, quantity, total_harga, status, keterangan, payment_method, customer_data) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user['id'], $product_id, $transaction_id, $quantity, 
                    $total, $status, $keterangan, 'saldo', $customer_data
                ]);
            } else {
                $stmt = $conn->prepare("INSERT INTO transactions 
                    (user_id, product_id, transaction_id, quantity, total_harga, status, keterangan, payment_method) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user['id'], $product_id, $transaction_id, $quantity, 
                    $total, $status, $keterangan, 'saldo'
                ]);
            }
            
            $transaction_db_id = $conn->lastInsertId();
            addDebug('Transaction record created', [
                'db_id' => $transaction_db_id,
                'status' => $status
            ]);
            
            // ============================================
            // Update stock - handling berbeda untuk setiap tipe produk
            // ============================================
            if (!$isAtlanticProduct) {
                if ($product['tipe_produk'] == 'otomatis') {
                    // Stok akan diupdate oleh claimLocalStock() nanti
                    addDebug('Local auto stock - will be handled by claimLocalStock()');
                } else {
                    // Manual product - update stok langsung
                    $stmt = $conn->prepare("UPDATE products SET stok = stok - ? WHERE id = ?");
                    $stmt->execute([$quantity, $product_id]);
                    addDebug('Manual product stock updated', [
                        'product_id' => $product_id,
                        'reduced_by' => $quantity
                    ]);
                }
            } else {
                addDebug('SKIPPED local stock update (Atlantic product)');
            }
            
            // ============================================
            // Update user balance
            // ============================================
            $stmt = $conn->prepare("UPDATE users SET saldo = saldo - ? WHERE id = ?");
            $stmt->execute([$total, $user['id']]);
            addDebug('User balance deducted', [
                'user_id' => $user['id'],
                'amount' => $total
            ]);
            
            // ============================================
            // Commit main transaction FIRST before external API calls
            // This ensures payment is recorded even if external calls fail
            // ============================================
            $conn->commit();
            addDebug('Main transaction COMMITTED - Payment recorded');
            
            // ============================================
            // PROCESS LOCAL STOCK IF APPLICABLE
            // (For non-Atlantic products with tipe_produk = 'otomatis')
            // ============================================
            $local_account = null;
            
            if (!$isAtlanticProduct && $product['tipe_produk'] == 'otomatis') {
                addDebug('=== STARTING LOCAL STOCK CLAIM ===');
                
                // Include stock functions
                if (file_exists(__DIR__ . '/stock-functions.php')) {
                    require_once __DIR__ . '/stock-functions.php';
                    
                    // Claim stock for each quantity
                    $claimed_accounts = [];
                    for ($i = 0; $i < $quantity; $i++) {
                        $account = claimLocalStock($transaction_db_id, $product_id);
                        if ($account) {
                            $claimed_accounts[] = $account;
                            addDebug('Local stock claimed', ['account' => substr($account, 0, 20) . '...']);
                        } else {
                            addDebug('Local stock FAILED - no available stock');
                            break;
                        }
                    }
                    
                    if (count($claimed_accounts) == $quantity) {
                        // All stock claimed successfully
                        $local_account = implode("\n", $claimed_accounts);
                        
                        // Update transaction status to selesai
                        $stmt = $conn->prepare("UPDATE transactions SET status = 'selesai', account_info = ? WHERE id = ?");
                        $stmt->execute([$local_account, $transaction_db_id]);
                        
                        addDebug('Local stock claim SUCCESS', [
                            'total_claimed' => count($claimed_accounts),
                            'status' => 'selesai'
                        ]);
                    } else {
                        // Partial or no stock claimed
                        $stmt = $conn->prepare("UPDATE transactions SET status = 'pending_manual', keterangan = ? WHERE id = ?");
                        $stmt->execute(['Stok tidak cukup - perlu proses manual', $transaction_db_id]);
                        
                        addDebug('Local stock claim PARTIAL/FAILED', [
                            'requested' => $quantity,
                            'claimed' => count($claimed_accounts)
                        ]);
                    }
                } else {
                    addDebug('stock-functions.php not found');
                    
                    // Mark as ready for manual processing
                    $stmt = $conn->prepare("UPDATE transactions SET status = 'ready' WHERE id = ?");
                    $stmt->execute([$transaction_db_id]);
                }
            } elseif (!$isAtlanticProduct && $product['tipe_produk'] == 'manual') {
                // Manual product - just update status to proses (waiting admin)
                $stmt = $conn->prepare("UPDATE transactions SET status = 'proses' WHERE id = ?");
                $stmt->execute([$transaction_db_id]);
                addDebug('Manual product - waiting admin processing');
            }
            
            // ============================================
            // PROCESS ATLANTIC H2H IF APPLICABLE
            // (Outside of main DB transaction to prevent rollback)
            // ============================================
            $atlantic_result = null;
            $h2h_id = null;
            
            if ($isAtlanticProduct) {
                addDebug('=== STARTING ATLANTIC H2H PROCESS ===');
                
                // Check if h2h_transactions table exists and has correct structure
                $h2hTableExists = false;
                $h2h_id = null;
                
                try {
                    // Check table exists
                    $stmt = $conn->query("SHOW TABLES LIKE 'h2h_transactions'");
                    if ($stmt->rowCount() > 0) {
                        // Check if 'target' column exists
                        $stmt = $conn->query("SHOW COLUMNS FROM h2h_transactions LIKE 'target'");
                        $h2hTableExists = $stmt->rowCount() > 0;
                    }
                    addDebug('H2H table check', ['exists' => $h2hTableExists]);
                } catch (Exception $e) {
                    addDebug('H2H table check failed', ['error' => $e->getMessage()]);
                }
                
                if ($h2hTableExists) {
                    try {
                        // Insert H2H transaction record
                        $stmt = $conn->prepare("INSERT INTO h2h_transactions 
                            (order_id, user_id, product_id, product_code, target, amount, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                        $stmt->execute([
                            $transaction_id, $user['id'], $product_id, 
                            $product['product_code'], $target_input, $total
                        ]);
                        $h2h_id = $conn->lastInsertId();
                        addDebug('H2H transaction record created', ['h2h_id' => $h2h_id]);
                    } catch (Exception $e) {
                        addDebug('H2H insert failed - run SQL migration!', ['error' => $e->getMessage()]);
                        $h2hTableExists = false;
                    }
                } else {
                    addDebug('WARNING: h2h_transactions table not ready! Run sql-fix-h2h-columns.sql');
                }
                
                // ============================================
                // Call Atlantic API
                // ============================================
                try {
                    // Load Atlantic class
                    $atlanticClassPath = __DIR__ . '/classes/AtlanticH2H.php';
                    if (!file_exists($atlanticClassPath)) {
                        $atlanticClassPath = __DIR__ . '/AtlanticH2H.php';
                    }
                    
                    if (file_exists($atlanticClassPath)) {
                        require_once $atlanticClassPath;
                        $atlantic = new AtlanticH2H();
                        $atlantic->setDebug(true);
                        
                        addDebug('Calling Atlantic API', [
                            'product_code' => $product['product_code'],
                            'target' => $target_input,
                            'ref_id' => $transaction_id
                        ]);
                        
                        // Create transaction via Atlantic API
                        $atlantic_result = $atlantic->createTransaction(
                            $product['product_code'],
                            $target_input,
                            $transaction_id,
                            $total
                        );
                        
                        addDebug('Atlantic API Response', $atlantic_result);
                        
                        if ($atlantic_result['success']) {
                            $atlantic_data = $atlantic_result['data']['data'] ?? $atlantic_result['data'] ?? [];
                            
                            // Determine status from Atlantic response
                            $h2h_status = 'processing';
                            $atlantic_trx_id = $atlantic_data['id'] ?? '';
                            $sn_voucher = $atlantic_data['sn'] ?? null;
                            $atlantic_status = $atlantic_data['status'] ?? 'pending';
                            
                            addDebug('Atlantic transaction created', [
                                'atlantic_trx_id' => $atlantic_trx_id,
                                'atlantic_status' => $atlantic_status,
                                'sn' => $sn_voucher
                            ]);
                            
                            // Update status based on Atlantic response
                            if ($atlantic_status === 'success') {
                                $h2h_status = 'success';
                                $status = 'selesai';
                            } elseif ($atlantic_status === 'failed') {
                                $h2h_status = 'failed';
                                $status = 'gagal';
                            } else {
                                // pending or processing
                                $h2h_status = 'processing';
                                $status = 'processing';
                            }
                            
                            // Update H2H transaction if table exists
                            if ($h2hTableExists && $h2h_id) {
                                $stmt = $conn->prepare("UPDATE h2h_transactions 
                                    SET status = ?, h2h_trx_id = ?, sn_voucher = ?, h2h_response = ?, updated_at = NOW()
                                    WHERE id = ?");
                                $stmt->execute([
                                    $h2h_status, $atlantic_trx_id, $sn_voucher, 
                                    json_encode($atlantic_result), $h2h_id
                                ]);
                            }
                            
                            // Update main transaction
                            $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ?");
                            $stmt->execute([$status, $transaction_db_id]);
                            
                            addDebug('Transaction status updated', [
                                'new_status' => $status,
                                'h2h_status' => $h2h_status
                            ]);
                            
                        } else {
                            // Atlantic API failed
                            addDebug('Atlantic API FAILED', [
                                'message' => $atlantic_result['message'] ?? 'Unknown error',
                                'data' => $atlantic_result['data'] ?? null
                            ]);
                            
                            // Update H2H as failed
                            if ($h2hTableExists && $h2h_id) {
                                $stmt = $conn->prepare("UPDATE h2h_transactions 
                                    SET status = 'failed', h2h_response = ?, updated_at = NOW()
                                    WHERE id = ?");
                                $stmt->execute([json_encode($atlantic_result), $h2h_id]);
                            }
                            
                            // Mark transaction for manual processing
                            $stmt = $conn->prepare("UPDATE transactions SET status = 'pending_manual', keterangan = ? WHERE id = ?");
                            $stmt->execute(['Atlantic API Error: ' . ($atlantic_result['message'] ?? 'Unknown'), $transaction_db_id]);
                        }
                        
                    } else {
                        addDebug('ERROR: AtlanticH2H.php not found!', [
                            'searched_paths' => [
                                __DIR__ . '/classes/AtlanticH2H.php',
                                __DIR__ . '/AtlanticH2H.php'
                            ]
                        ]);
                        
                        // Mark for manual processing
                        $stmt = $conn->prepare("UPDATE transactions SET status = 'pending_manual', keterangan = ? WHERE id = ?");
                        $stmt->execute(['AtlanticH2H class not found', $transaction_db_id]);
                    }
                    
                } catch (Exception $e) {
                    addDebug('Atlantic API EXCEPTION', ['error' => $e->getMessage()]);
                    
                    // Update H2H as failed
                    if ($h2hTableExists && $h2h_id) {
                        try {
                            $stmt = $conn->prepare("UPDATE h2h_transactions SET status = 'failed', h2h_response = ? WHERE id = ?");
                            $stmt->execute([json_encode(['exception' => $e->getMessage()]), $h2h_id]);
                        } catch (Exception $ex) {
                            // Ignore
                        }
                    }
                    
                    // Update main transaction status
                    try {
                        $stmt = $conn->prepare("UPDATE transactions SET status = 'pending_manual', keterangan = CONCAT(keterangan, ' [Atlantic Error]') WHERE id = ?");
                        $stmt->execute([$transaction_db_id]);
                    } catch (Exception $ex) {
                        // Ignore
                    }
                }
            }
            
            addDebug('=== PAYMENT PROCESS COMPLETED ===');
            
            // ============================================
            // Prepare response
            // ============================================
            $response = [
                'success' => true,
                'message' => $isAtlanticProduct 
                    ? 'Pembelian berhasil! Pesanan sedang diproses.' 
                    : 'Pembelian berhasil! Saldo Anda telah dikurangi.',
                'transaction_id' => $transaction_id,
                'db_id' => $transaction_db_id,
                'is_atlantic' => $isAtlanticProduct,
                'redirect' => 'transaction-detail.php?id=' . $transaction_db_id,
                'debug' => $debug_logs
            ];
            
            if ($isAtlanticProduct && $atlantic_result) {
                $response['atlantic_response'] = $atlantic_result;
            }
            
            addDebug('=== PAYMENT PROCESS COMPLETED ===', [
                'redirect' => $response['redirect']
            ]);
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            // Only rollback if transaction is still active (before commit)
            if ($conn->inTransaction()) {
                $conn->rollBack();
                addDebug('Database transaction ROLLED BACK', ['error' => $e->getMessage()]);
            } else {
                addDebug('Exception after commit (non-critical)', ['error' => $e->getMessage()]);
            }
            
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => $debug_logs
            ]);
            exit;
        }
        
    } elseif ($payment_type === 'pakasir') {
        // ============================================
        // PAYMENT WITH PAKASIR API
        // Atlantic akan diproses setelah callback pembayaran sukses
        // ============================================
        addDebug('=== PROCESSING PAKASIR PAYMENT ===');
        
        if (empty($payment_method)) {
            echo json_encode([
                'success' => false,
                'message' => 'Silakan pilih metode pembayaran Pakasir',
                'debug' => $debug_logs
            ]);
            exit;
        }
        
        // Validate payment method
        $valid_methods = ['qris', 'bni_va', 'bri_va', 'mandiri_va', 'cimb_niaga_va', 'bca_va', 'permata_va', 'gopay', 'shopeepay', 'ovo'];
        if (!in_array($payment_method, $valid_methods)) {
            echo json_encode([
                'success' => false,
                'message' => 'Metode pembayaran tidak valid',
                'debug' => $debug_logs
            ]);
            exit;
        }
        
        addDebug('Payment method validated', ['method' => $payment_method]);
        
        // Start transaction
        $conn->beginTransaction();
        
        // Prepare customer data
        $customer_data = json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'] ?? '',
            'whatsapp' => $user['whatsapp'] ?? '',
            'target' => $target_input,
            'device_info' => $target_input,
            'notes' => $notes,
            'is_atlantic' => $isAtlanticProduct,
            'product_code' => $product['product_code'] ?? ''
        ], JSON_UNESCAPED_UNICODE);
        
        // Insert transaction with pending status
        $keterangan = !empty($notes) ? $notes : "Pembelian {$quantity}x {$product['nama']}";
        
        // Check if customer_data column exists
        $stmt = $conn->query("SHOW COLUMNS FROM transactions LIKE 'customer_data'");
        $hasCustomerData = $stmt->rowCount() > 0;
        
        if ($hasCustomerData) {
            $stmt = $conn->prepare("INSERT INTO transactions 
                (user_id, product_id, transaction_id, quantity, total_harga, status, keterangan, payment_method, pakasir_order_id, customer_data) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)");
            $stmt->execute([
                $user['id'], $product_id, $transaction_id, $quantity, 
                $total, $keterangan, 'pakasir_' . $payment_method, $order_id, $customer_data
            ]);
        } else {
            $stmt = $conn->prepare("INSERT INTO transactions 
                (user_id, product_id, transaction_id, quantity, total_harga, status, keterangan, payment_method, pakasir_order_id) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
            $stmt->execute([
                $user['id'], $product_id, $transaction_id, $quantity, 
                $total, $keterangan, 'pakasir_' . $payment_method, $order_id
            ]);
        }
        
        $transaction_db_id = $conn->lastInsertId();
        addDebug('Pending transaction created', ['db_id' => $transaction_db_id]);
        
        // Create H2H record for Atlantic products (will be processed after payment callback)
        if ($isAtlanticProduct) {
            $stmt = $conn->query("SHOW TABLES LIKE 'h2h_transactions'");
            if ($stmt->rowCount() > 0) {
                $stmt = $conn->prepare("INSERT INTO h2h_transactions 
                    (order_id, user_id, product_id, product_code, target, amount, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $stmt->execute([
                    $transaction_id, $user['id'], $product_id, 
                    $product['product_code'], $target_input, $total
                ]);
                addDebug('H2H record created (pending payment)', ['h2h_id' => $conn->lastInsertId()]);
            }
        }
        
        $conn->commit();
        
        // ============================================
        // Call Pakasir API
        // ============================================
        $pakasir_url = "https://app.pakasir.com/api/transactioncreate/{$payment_method}";
        
        $pakasir_data = [
            'project' => PAKASIR_MERCHANT_CODE,
            'order_id' => $order_id,
            'amount' => $total,
            'api_key' => PAKASIR_API_KEY
        ];
        
        addDebug('Calling Pakasir API', [
            'url' => $pakasir_url,
            'order_id' => $order_id,
            'amount' => $total
        ]);
        
        $ch = curl_init($pakasir_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pakasir_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        addDebug('Pakasir API Response', [
            'http_code' => $http_code,
            'response' => $response ? json_decode($response, true) : null,
            'curl_error' => $curl_error
        ]);
        
        // Handle curl errors
        if ($curl_error) {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'gagal', keterangan = ? WHERE id = ?");
            $stmt->execute(["Curl Error: " . $curl_error, $transaction_db_id]);
            
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menghubungi gateway pembayaran. Error: ' . $curl_error,
                'debug' => $debug_logs
            ]);
            exit;
        }
        
        if ($http_code == 200 || $http_code == 201) {
            $result = json_decode($response, true);
            
            if (!$result || !isset($result['payment'])) {
                $stmt = $conn->prepare("UPDATE transactions SET status = 'gagal' WHERE id = ?");
                $stmt->execute([$transaction_db_id]);
                
                echo json_encode([
                    'success' => false,
                    'message' => 'Response dari Pakasir tidak valid.',
                    'debug' => $debug_logs
                ]);
                exit;
            }
            
            $payment_data = $result['payment'];
            $payment_number = $payment_data['payment_number'] ?? '';
            $total_payment = $payment_data['total_payment'] ?? $total;
            $fee = $payment_data['fee'] ?? 0;
            
            // Parse expired time
            $expired_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            if (isset($payment_data['expired_at'])) {
                try {
                    $dt = new DateTime($payment_data['expired_at']);
                    $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
                    $expired_at = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // Use default
                }
            }
            
            // Update transaction with Pakasir payment details
            $stmt = $conn->prepare("UPDATE transactions 
                SET pakasir_payment_number = ?, pakasir_total_payment = ?, pakasir_expired_at = ?, pakasir_fee = ?
                WHERE id = ?");
            $stmt->execute([$payment_number, $total_payment, $expired_at, $fee, $transaction_db_id]);
            
            addDebug('Pakasir payment created', [
                'payment_number' => $payment_number,
                'total_payment' => $total_payment,
                'expired_at' => $expired_at
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat. Silakan lanjutkan pembayaran.',
                'transaction_id' => $transaction_id,
                'is_atlantic' => $isAtlanticProduct,
                'redirect' => 'payment-instruction.php?id=' . $transaction_db_id,
                'debug' => $debug_logs
            ]);
            
        } else {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'gagal' WHERE id = ?");
            $stmt->execute([$transaction_db_id]);
            
            echo json_encode([
                'success' => false,
                'message' => 'Gagal membuat pembayaran di gateway.',
                'debug' => $debug_logs
            ]);
        }
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    addDebug('FATAL EXCEPTION', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
        'debug' => $debug_logs
    ]);
}
?>
