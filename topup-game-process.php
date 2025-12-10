<?php
/**
 * TOP UP GAME - Process Order
 * Proses pembuatan order dan pembayaran via ATLANTIC DEPOSIT
 * 
 * FIXES:
 * - OVO memerlukan nomor telepon
 * - Better error handling
 * - Improved payment type mapping
 */

require_once 'config.php';
require_once 'topup-game-helper.php';

// Include Atlantic class
if (!class_exists('AtlanticH2H')) {
    $paths = [
        __DIR__ . '/AtlanticH2H.php',
        __DIR__ . '/classes/AtlanticH2H.php'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: topup-game.php');
    exit;
}

// Get session data
$topupData = $_SESSION['topup_data'] ?? null;
if (!$topupData) {
    $_SESSION['error'] = 'Sesi berakhir. Silakan ulangi pembelian.';
    header('Location: topup-game.php');
    exit;
}

// Get form data
$paymentMethod = $_POST['payment_method'] ?? '';
$phone = isset($_POST['phone']) ? AtlanticH2H::formatPhone($_POST['phone']) : '';
$ovoPhone = isset($_POST['ovo_phone']) ? AtlanticH2H::formatPhone($_POST['ovo_phone']) : '';
$email = $_POST['email'] ?? '';
$fee = intval($_POST['fee'] ?? 0);
$total = intval($_POST['total'] ?? 0);

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getUserData() : null;
$userId = $isLoggedIn ? $user['id'] : null;

// Get contact info
if ($isLoggedIn && $user) {
    $customerName = $user['nama'] ?? $user['username'] ?? 'Customer';
    $customerPhone = $user['no_hp'] ?? $phone;
    $customerEmail = $user['email'] ?? $email;
} else {
    $customerName = 'Guest';
    $customerPhone = $phone;
    $customerEmail = $email;
}

// Validate payment method
if (empty($paymentMethod)) {
    $_SESSION['error'] = 'Mohon pilih metode pembayaran';
    header("Location: topup-game-detail.php?game=" . $topupData['game_code']);
    exit;
}

// Validate phone for guest
if (!$isLoggedIn && empty($customerPhone)) {
    $_SESSION['error'] = 'Mohon masukkan nomor WhatsApp';
    header("Location: topup-game-detail.php?game=" . $topupData['game_code']);
    exit;
}

// Validate OVO phone if payment method is OVO
if (strtoupper($paymentMethod) === 'OVO' && empty($ovoPhone)) {
    $_SESSION['error'] = 'Mohon masukkan nomor HP OVO';
    header("Location: topup-game-detail.php?game=" . $topupData['game_code']);
    exit;
}

// Get product info
$stmt = $conn->prepare("SELECT * FROM atlantic_game_products WHERE game_id = ? AND product_code = ?");
$stmt->execute([$topupData['game_id'], $topupData['product_code']]);
$product = $stmt->fetch();

if (!$product || $product['status'] !== 'available') {
    $_SESSION['error'] = 'Produk tidak tersedia';
    header("Location: topup-game-detail.php?game=" . $topupData['game_code']);
    exit;
}

// Calculate amounts
$priceAtlantic = $product['price_atlantic'];
$priceSell = $product['price_sell'];

// Validate total
if ($total <= 0) {
    $total = $priceSell + $fee;
}

// Generate order ID
$orderId = AtlanticH2H::generateOrderId('TG');

// Determine payment type for Atlantic
// Atlantic accepts: va, ewallet, bank
$paymentType = 'ewallet'; // default
$atlanticMetode = strtoupper($paymentMethod);

// Map payment method to type
$methodTypeMap = [
    // E-Wallet
    'QRIS' => 'ewallet',
    'QRISFAST' => 'ewallet',
    'OVO' => 'ewallet',
    'GOPAY' => 'ewallet',
    'DANA' => 'ewallet',
    'SHOPEEPAY' => 'ewallet',
    'LINKAJA' => 'ewallet',
    // Virtual Account (type: va)
    'BNIVA' => 'va',
    'BRIVA' => 'va',
    'MANDIRIVA' => 'va',
    'BCAVA' => 'va',
    'PERMATAVA' => 'va',
    'CIMBVA' => 'va',
    'BSIVA' => 'va',
    'BNI' => 'va',
    // Bank Transfer (type: bank)
    'BCA' => 'bank',
    'BRI' => 'bank',
    'MANDIRI' => 'bank',
    'PERMATA' => 'bank',
    'CIMB' => 'bank',
    'BANKTRANSFER' => 'bank',
    'OVO' => 'ewallet', // OVO tetap ewallet tapi lowercase untuk Atlantic
];

if (isset($methodTypeMap[strtoupper($paymentMethod)])) {
    $paymentType = $methodTypeMap[strtoupper($paymentMethod)];
}

// Determine if paying with balance
$useBalance = ($paymentMethod === 'SALDO');

// Initialize Atlantic
$atlantic = new AtlanticH2H();

// Start database transaction
$conn->beginTransaction();

try {
    // ================================================
    // OPTION 1: PAY WITH USER BALANCE
    // ================================================
    if ($useBalance) {
        if (!$isLoggedIn) {
            throw new Exception('Anda harus login untuk menggunakan saldo');
        }
        
        if ($user['saldo'] < $priceSell) {
            throw new Exception('Saldo tidak mencukupi. Saldo: Rp ' . number_format($user['saldo'], 0, ',', '.'));
        }
        
        // Deduct balance
        $stmt = $conn->prepare("UPDATE users SET saldo = saldo - ? WHERE id = ? AND saldo >= ?");
        $stmt->execute([$priceSell, $userId, $priceSell]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Gagal mengurangi saldo');
        }
        
        // Create order with status payment_success
        $stmt = $conn->prepare("INSERT INTO atlantic_orders 
            (order_id, user_id, guest_phone, order_type, game_id, product_code, product_name, 
             target, target_display, price_atlantic, price_sell, fee, total,
             payment_method, payment_type, paid_with_balance, status, created_at, paid_at)
            VALUES (?, ?, ?, 'game', ?, ?, ?, ?, ?, ?, ?, 0, ?, 'SALDO', 'balance', 1, 'payment_success', NOW(), NOW())");
        
        $stmt->execute([
            $orderId,
            $userId,
            null,
            $topupData['game_id'],
            $topupData['product_code'],
            $topupData['product_name'],
            $topupData['target'],
            $topupData['target_display'],
            $priceAtlantic,
            $priceSell,
            $priceSell
        ]);
        
        $orderDbId = $conn->lastInsertId();
        
        $conn->commit();
        
        // Process to Atlantic H2H immediately
        processAtlanticH2H($conn, $atlantic, $orderDbId, $orderId, $topupData['product_code'], $topupData['target']);
        
        // Clear session data
        unset($_SESSION['topup_data']);
        
        // Redirect to status page
        $_SESSION['success'] = 'Pembayaran berhasil! Pesanan sedang diproses.';
        header("Location: topup-game-status.php?order=$orderId");
        exit;
    }
    
    // ================================================
    // OPTION 2: PAY WITH ATLANTIC DEPOSIT
    // ================================================
    else {
        // Create order with status waiting_payment
        $stmt = $conn->prepare("INSERT INTO atlantic_orders 
            (order_id, user_id, guest_phone, order_type, game_id, product_code, product_name, 
             target, target_display, price_atlantic, price_sell, fee, total,
             payment_method, payment_type, status, created_at, payment_expired_at)
            VALUES (?, ?, ?, 'game', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting_payment', NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        
        $stmt->execute([
            $orderId,
            $userId,
            $isLoggedIn ? null : $customerPhone,
            $topupData['game_id'],
            $topupData['product_code'],
            $topupData['product_name'],
            $topupData['target'],
            $topupData['target_display'],
            $priceAtlantic,
            $priceSell,
            $fee,
            $total,
            $atlanticMetode,
            $paymentType
        ]);
        
        $orderDbId = $conn->lastInsertId();
        
        // Create deposit to Atlantic
        // Parameters: reff_id, nominal, type, metode, phone (for OVO)
        $depositResult = $atlantic->createDeposit($orderId, $total, $paymentType, $atlanticMetode, $ovoPhone);
        
        // Log the result
        error_log("Atlantic Deposit Create - Order: $orderId, Result: " . json_encode($depositResult));
        
        if (!$depositResult['success']) {
            throw new Exception($depositResult['message'] ?? 'Gagal membuat pembayaran di Atlantic');
        }
        
        $depositData = $depositResult['data']['data'] ?? $depositResult['data'] ?? [];
        
        // Extract payment info from response
        $depositId = $depositData['id'] ?? $depositData['deposit_id'] ?? $depositData['trx_id'] ?? null;
        $qrString = $depositData['qr_string'] ?? $depositData['qris_string'] ?? $depositData['qr'] ?? null;
        $qrImage = $depositData['qr_image'] ?? $depositData['qris_image'] ?? $depositData['qr_url'] ?? null;
        $vaNumber = $depositData['va_number'] ?? $depositData['virtual_account'] ?? $depositData['account_number'] ?? $depositData['nomor_va'] ?? null;
        $paymentUrl = $depositData['url'] ?? $depositData['payment_url'] ?? $depositData['checkout_url'] ?? null;
        $expiredAt = $depositData['expired_at'] ?? $depositData['expiry_time'] ?? $depositData['expired'] ?? date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update order with deposit info
        $stmt = $conn->prepare("UPDATE atlantic_orders SET 
            deposit_id = ?,
            deposit_response = ?,
            qr_string = ?,
            qr_image = ?,
            va_number = ?,
            payment_url = ?,
            payment_expired_at = ?
            WHERE id = ?");
        
        $stmt->execute([
            $depositId,
            json_encode($depositResult['data']),
            $qrString,
            $qrImage,
            $vaNumber,
            $paymentUrl,
            $expiredAt,
            $orderDbId
        ]);
        
        $conn->commit();
        
        // Clear session data
        unset($_SESSION['topup_data']);
        
        // Redirect to payment page
        header("Location: topup-game-payment.php?order=$orderId" . (!$isLoggedIn ? "&phone=$customerPhone" : ""));
        exit;
    }
    
} catch (Exception $e) {
    $conn->rollBack();
    
    error_log("Topup Game Process Error: " . $e->getMessage());
    
    $_SESSION['error'] = $e->getMessage();
    header("Location: topup-game-detail.php?game=" . $topupData['game_code']);
    exit;
}

/**
 * Process order to Atlantic H2H after payment success
 */
function processAtlanticH2H($conn, $atlantic, $orderDbId, $orderId, $productCode, $target) {
    try {
        // Update status to processing
        $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'processing', processed_at = NOW() WHERE id = ?");
        $stmt->execute([$orderDbId]);
        
        // Call Atlantic API to create H2H transaction
        $result = $atlantic->createTransaction($productCode, $target, $orderId);
        
        // Log result
        error_log("Atlantic H2H Create - Order: $orderId, Product: $productCode, Target: $target, Result: " . json_encode($result));
        
        if ($result['success']) {
            $data = $result['data']['data'] ?? $result['data'] ?? [];
            
            $h2hStatus = strtolower($data['status'] ?? 'pending');
            $h2hTrxId = $data['id'] ?? $data['trx_id'] ?? null;
            $sn = $data['sn'] ?? $data['serial_number'] ?? null;
            
            // Map status
            $orderStatus = 'processing';
            if ($h2hStatus === 'success' || $h2hStatus === 'sukses') {
                $orderStatus = 'success';
            } elseif (in_array($h2hStatus, ['failed', 'gagal', 'error'])) {
                $orderStatus = 'failed';
            }
            
            // Update order
            $stmt = $conn->prepare("UPDATE atlantic_orders SET 
                h2h_trx_id = ?,
                h2h_response = ?,
                sn_voucher = ?,
                status = ?,
                completed_at = ?
                WHERE id = ?");
            
            $stmt->execute([
                $h2hTrxId,
                json_encode($result['data']),
                $sn,
                $orderStatus,
                $orderStatus === 'success' ? date('Y-m-d H:i:s') : null,
                $orderDbId
            ]);
            
            return ['success' => true, 'status' => $orderStatus, 'data' => $data];
            
        } else {
            // API call failed
            $stmt = $conn->prepare("UPDATE atlantic_orders SET 
                h2h_response = ?,
                status = 'failed',
                status_message = ?
                WHERE id = ?");
            
            $stmt->execute([
                json_encode($result),
                $result['message'] ?? 'Atlantic H2H API call failed',
                $orderDbId
            ]);
            
            return ['success' => false, 'message' => $result['message'] ?? 'API call failed'];
        }
        
    } catch (Exception $e) {
        // Update status to failed
        $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'failed', status_message = ? WHERE id = ?");
        $stmt->execute([$e->getMessage(), $orderDbId]);
        
        error_log("Atlantic H2H Exception: Order $orderId - " . $e->getMessage());
        
        return ['success' => false, 'message' => $e->getMessage()];
    }
}