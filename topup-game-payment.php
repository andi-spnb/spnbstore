<?php
/**
 * TOP UP GAME - Payment
 * Halaman pembayaran - menampilkan QR Code / VA / Button Bayar
 * 
 * FIXES:
 * - Timezone set AFTER config.php to avoid override
 * - ShopeePay dan LinkAja menampilkan tombol Bayar
 * - OVO menampilkan tombol Bayar
 * - Better error handling
 */

// Load config first
require_once 'config.php';
require_once 'topup-game-helper.php';

// CRITICAL: Set timezone to WIB AFTER config.php to avoid override
date_default_timezone_set('Asia/Jakarta');

// Disable error display for production
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include QRCodeHelper for generating QR locally
if (file_exists(__DIR__ . '/QRCodeHelper.php')) {
    require_once 'QRCodeHelper.php';
}

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

// Get order ID
$orderId = $_GET['order'] ?? '';
$phone = $_GET['phone'] ?? '';

if (empty($orderId)) {
    header('Location: topup-game.php');
    exit;
}

// Get order info
$stmt = $conn->prepare("SELECT o.*, g.game_name, g.game_code 
                        FROM atlantic_orders o 
                        LEFT JOIN atlantic_games g ON o.game_id = g.id 
                        WHERE o.order_id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'Order tidak ditemukan';
    header('Location: topup-game.php');
    exit;
}

// Verify access (user must be owner or provide correct phone)
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getUserData() : null;

if ($order['user_id']) {
    if (!$isLoggedIn || $user['id'] != $order['user_id']) {
        $_SESSION['error'] = 'Akses ditolak';
        header('Location: topup-game.php');
        exit;
    }
} else {
    // Guest order - verify phone
    if (empty($phone) || AtlanticH2H::formatPhone($phone) !== AtlanticH2H::formatPhone($order['guest_phone'])) {
        $_SESSION['error'] = 'Nomor WhatsApp tidak sesuai';
        header('Location: topup-game.php');
        exit;
    }
}

// If already paid, redirect to status
if (!in_array($order['status'], ['waiting_payment'])) {
    header("Location: topup-game-status.php?order=$orderId" . ($phone ? "&phone=$phone" : ""));
    exit;
}

// =========================================================
// PARSE PAYMENT DATA
// =========================================================
$expiredAt = 0;
$qrString = '';
$qrImageUrl = '';
$vaNumber = '';
$paymentUrl = '';

// Parse deposit_response to get data
if (!empty($order['deposit_response'])) {
    $depositData = json_decode($order['deposit_response'], true);
    
    // Get expired_at from response (Atlantic returns WIB time)
    $expiredAtStr = $depositData['data']['expired_at'] ?? $depositData['expired_at'] ?? null;
    if ($expiredAtStr) {
        $expiredAt = strtotime($expiredAtStr);
    }
    
    // Get QR string and image
    $qrString = $depositData['data']['qr_string'] ?? $depositData['qr_string'] ?? '';
    $qrImageUrl = $depositData['data']['qr_image'] ?? $depositData['qr_image'] ?? '';
    
    // Get VA number
    $vaNumber = $depositData['data']['va_number'] ?? $depositData['va_number'] ?? '';
    
    // Get payment URL (for ShopeePay, LinkAja, OVO, etc)
    $paymentUrl = $depositData['data']['url'] ?? $depositData['url'] ?? $depositData['data']['payment_url'] ?? $depositData['payment_url'] ?? '';
}

// Fallback to database columns
if ($expiredAt <= 0 && !empty($order['payment_expired_at'])) {
    $expiredAt = strtotime($order['payment_expired_at']);
}

// Fallback: use created_at + 1 hour
if ($expiredAt <= 0 && !empty($order['created_at'])) {
    $expiredAt = strtotime($order['created_at']) + (60 * 60);
}

// Fallback for QR data
if (empty($qrString) && !empty($order['qr_string'])) {
    $qrString = $order['qr_string'];
}
if (empty($qrImageUrl) && !empty($order['qr_image'])) {
    $qrImageUrl = $order['qr_image'];
}
if (empty($vaNumber) && !empty($order['va_number'])) {
    $vaNumber = $order['va_number'];
}
if (empty($paymentUrl) && !empty($order['payment_url'])) {
    $paymentUrl = $order['payment_url'];
}

// Calculate remaining seconds
$now = time();
$remainingSeconds = $expiredAt - $now;
$isExpired = $remainingSeconds <= 0;

// Debug info
error_log("Payment Debug - Order: {$orderId}");

// If expired, update status
if ($isExpired && $order['status'] === 'waiting_payment') {
    $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'expired', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$order['id']]);
    $order['status'] = 'expired';
}

$pageTitle = "Pembayaran - " . $orderId;

// Payment method display name and type detection
$paymentMethod = strtoupper($order['payment_method'] ?? 'QRIS');
$paymentDisplay = $paymentMethod;

// Determine payment type
$isQris = in_array($paymentMethod, ['QRIS', 'QRISFAST']);
$isVa = in_array($paymentMethod, ['BCA', 'BRI', 'BNI', 'MANDIRI', 'PERMATA', 'CIMB', 'BSI']);
$isUrlRedirect = in_array($paymentMethod, ['SHOPEEPAY', 'LINKAJA', 'DANA', 'OVO', 'GOPAY']);

// Check if we have payment URL for redirect-type payments
$hasPaymentUrl = !empty($paymentUrl);

// If it's a URL redirect type but no URL, check if we have QR as fallback
if ($isUrlRedirect && !$hasPaymentUrl && !empty($qrString)) {
    $isQris = true;
    $isUrlRedirect = false;
}

// Can generate QR locally
$canGenerateQR = class_exists('QRCodeHelper') && !empty($qrString);

// Payment method icons and colors
$paymentIcons = [
    'QRIS' => ['icon' => 'fa-qrcode', 'color' => '#00A3E0'],
    'QRISFAST' => ['icon' => 'fa-qrcode', 'color' => '#00A3E0'],
    'SHOPEEPAY' => ['icon' => 'fa-shopping-bag', 'color' => '#EE4D2D'],
    'LINKAJA' => ['icon' => 'fa-mobile-alt', 'color' => '#E31837'],
    'DANA' => ['icon' => 'fa-wallet', 'color' => '#108EE9'],
    'OVO' => ['icon' => 'fa-circle', 'color' => '#4C3494'],
    'GOPAY' => ['icon' => 'fa-wallet', 'color' => '#00AED6'],
    'BCA' => ['icon' => 'fa-university', 'color' => '#0066AE'],
    'BRI' => ['icon' => 'fa-university', 'color' => '#00529C'],
    'BNI' => ['icon' => 'fa-university', 'color' => '#F05A22'],
    'MANDIRI' => ['icon' => 'fa-university', 'color' => '#003366'],
];

$currentPaymentIcon = $paymentIcons[$paymentMethod] ?? ['icon' => 'fa-credit-card', 'color' => '#6366f1'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #1a1a2e 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }
        
        .topup-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
        }
        
        .header-container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .header-title h1 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        
        .header-title span {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .main-content {
            max-width: 500px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
        }
        
        /* Timer Card */
        .timer-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .timer-card.expired {
            background: linear-gradient(135deg, var(--danger), #dc2626);
        }
        
        .timer-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .timer-value {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        
        .timer-note {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }
        
        /* Payment Card */
        .payment-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }
        
        .payment-method-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-dark);
            border-radius: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .payment-method-badge i {
            color: <?php echo $currentPaymentIcon['color']; ?>;
        }
        
        /* QR Container */
        .qr-container {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .qr-container img {
            max-width: 250px;
            width: 100%;
            height: auto;
        }
        
        .qr-note {
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .qr-loading {
            padding: 3rem;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .qr-loading i {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        /* Payment Button (for ShopeePay, LinkAja, etc) */
        .payment-button-container {
            text-align: center;
            padding: 2rem;
        }
        
        .payment-button-container .payment-icon-large {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: white;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .payment-button-container .payment-icon-large i {
            font-size: 2.5rem;
            color: <?php echo $currentPaymentIcon['color']; ?>;
        }
        
        .btn-pay {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem 2.5rem;
            background: <?php echo $currentPaymentIcon['color']; ?>;
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            max-width: 300px;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .btn-pay i {
            font-size: 1.25rem;
        }
        
        .payment-instruction {
            margin-top: 1.5rem;
            padding: 1rem;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-align: left;
        }
        
        .payment-instruction ol {
            padding-left: 1.25rem;
            margin: 0;
        }
        
        .payment-instruction li {
            margin-bottom: 0.5rem;
        }
        
        /* VA Container */
        .va-container {
            text-align: center;
            padding: 1.5rem;
        }
        
        .va-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .va-number {
            font-size: 1.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .btn-copy {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-copy:hover {
            background: var(--primary-dark);
        }
        
        .btn-copy.copied {
            background: var(--success);
        }
        
        /* Amount Container */
        .amount-container {
            text-align: center;
            padding: 1.5rem;
            background: var(--bg-dark);
            border-radius: 0.75rem;
            margin-top: 1rem;
        }
        
        .amount-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .amount-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .amount-note {
            font-size: 0.75rem;
            color: var(--warning);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        /* Order Info */
        .order-info {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }
        
        .order-info-title {
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .order-info-title i {
            color: var(--primary);
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .order-item-label {
            color: var(--text-secondary);
        }
        
        /* Instructions */
        .instructions {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }
        
        .instructions-title {
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .instructions-title i {
            color: var(--warning);
        }
        
        .instructions ol {
            padding-left: 1.25rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .instructions li {
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }
        
        /* Status Overlay */
        .status-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            text-align: center;
            padding: 2rem;
        }
        
        .status-overlay.show {
            display: flex;
        }
        
        .status-content i {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .status-content.success i {
            color: var(--success);
        }
        
        .status-content.expired i {
            color: var(--danger);
        }
        
        .status-content h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .status-content p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }
        
        .btn-status {
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            border: none;
            background: var(--primary);
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        /* Checking Status */
        .checking-status {
            background: var(--bg-card);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .checking-status i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <header class="topup-header">
        <div class="header-container">
            <div class="header-title">
                <h1>Pembayaran</h1>
                <span><?php echo htmlspecialchars($orderId); ?></span>
            </div>
        </div>
    </header>
    
    <div class="main-content">
        <?php if ($isExpired): ?>
        <!-- Expired -->
        <div class="timer-card expired">
            <div class="timer-label">Pembayaran Kedaluwarsa</div>
            <div class="timer-value">00:00:00</div>
            <div class="timer-note">Silakan buat pesanan baru</div>
        </div>
        
        <div style="text-align: center;">
            <a href="topup-game.php" class="btn-status">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
        
        <?php else: ?>
        
        <!-- Timer -->
        <div class="timer-card" id="timerCard">
            <div class="timer-label">Selesaikan Pembayaran Dalam</div>
            <div class="timer-value" id="countdown">--:--:--</div>
            <div class="timer-note">
                Batas: <?php echo date('d M Y, H:i', $expiredAt); ?> WIB
            </div>
        </div>
        
        <!-- Checking Status -->
        <div class="checking-status" id="checkingStatus">
            <i class="fas fa-spinner"></i>
            <span>Menunggu pembayaran...</span>
        </div>
        
        <!-- Payment -->
        <div class="payment-card">
            <div class="payment-method-badge">
                <i class="fas <?php echo $currentPaymentIcon['icon']; ?>"></i>
                <?php echo $paymentDisplay; ?>
            </div>
            
            <?php if ($isQris): ?>
            <!-- QR Code Display -->
            <div class="qr-container" id="qrContainer">
                <?php if (!empty($qrImageUrl)): ?>
                    <img src="<?php echo htmlspecialchars($qrImageUrl); ?>" alt="QRIS Code" id="qrImage" 
                         onerror="handleQRError()">
                <?php elseif ($canGenerateQR): ?>
                    <div class="qr-loading" id="qrLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Memuat QRIS...</span>
                    </div>
                <?php else: ?>
                    <div style="color: red; padding: 2rem;">
                        QR Code tidak tersedia.<br>
                        <small>Silakan coba lagi.</small>
                    </div>
                <?php endif; ?>
            </div>
            <div class="qr-note">
                Scan QR code menggunakan e-wallet atau mobile banking
            </div>
            
            <?php elseif ($isUrlRedirect && $hasPaymentUrl): ?>
            <!-- Payment Button for ShopeePay, LinkAja, OVO, etc -->
            <div class="payment-button-container">
                <div class="payment-icon-large">
                    <i class="fas <?php echo $currentPaymentIcon['icon']; ?>"></i>
                </div>
                
                <a href="<?php echo htmlspecialchars($paymentUrl); ?>" class="btn-pay" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt"></i>
                    Bayar dengan <?php echo $paymentDisplay; ?>
                </a>
                
                <div class="payment-instruction">
                    <ol>
                        <li>Klik tombol <strong>"Bayar dengan <?php echo $paymentDisplay; ?>"</strong></li>
                        <li>Anda akan diarahkan ke aplikasi <?php echo $paymentDisplay; ?></li>
                        <li>Konfirmasi pembayaran di aplikasi</li>
                        <li>Kembali ke halaman ini setelah pembayaran berhasil</li>
                    </ol>
                </div>
            </div>
            
            <?php elseif ($isVa): ?>
            <!-- VA Number Display -->
            <div class="va-container">
                <div class="va-label">Nomor Virtual Account</div>
                <div class="va-number">
                    <span id="vaNumber"><?php echo htmlspecialchars($vaNumber ?: '-'); ?></span>
                    <button class="btn-copy" onclick="copyVA()" title="Salin">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Fallback - show any available info -->
            <div class="va-container">
                <?php if (!empty($vaNumber)): ?>
                <div class="va-label">Nomor Pembayaran</div>
                <div class="va-number">
                    <span id="vaNumber"><?php echo htmlspecialchars($vaNumber); ?></span>
                    <button class="btn-copy" onclick="copyVA()" title="Salin">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <?php elseif ($hasPaymentUrl): ?>
                <a href="<?php echo htmlspecialchars($paymentUrl); ?>" class="btn-pay" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt"></i>
                    Lanjutkan Pembayaran
                </a>
                <?php else: ?>
                <p style="text-align: center; color: var(--warning);">
                    <i class="fas fa-exclamation-triangle"></i>
                    Informasi pembayaran tidak tersedia
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Amount -->
            <div class="amount-container">
                <div class="amount-label">Total Pembayaran</div>
                <div class="amount-value">
                    Rp <?php echo number_format($order['total'], 0, ',', '.'); ?>
                    <button class="btn-copy" onclick="copyAmount()" title="Salin">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="amount-note">
                    <i class="fas fa-exclamation-triangle"></i>
                    Transfer sesuai nominal agar terverifikasi otomatis
                </div>
            </div>
        </div>
        
        <!-- Order Info -->
        <div class="order-info">
            <div class="order-info-title">
                <i class="fas fa-receipt"></i>
                Detail Pesanan
            </div>
            <div class="order-item">
                <span class="order-item-label">Order ID</span>
                <span><?php echo htmlspecialchars($order['order_id']); ?></span>
            </div>
            <div class="order-item">
                <span class="order-item-label">Game</span>
                <span><?php echo htmlspecialchars($order['game_name']); ?></span>
            </div>
            <div class="order-item">
                <span class="order-item-label">ID Game</span>
                <span><?php echo htmlspecialchars($order['target_display']); ?></span>
            </div>
            <div class="order-item">
                <span class="order-item-label">Produk</span>
                <span><?php echo htmlspecialchars($order['product_name']); ?></span>
            </div>
        </div>
        
        <!-- Instructions -->
        <div class="instructions">
            <div class="instructions-title">
                <i class="fas fa-info-circle"></i>
                Cara Pembayaran
            </div>
            <?php if ($isQris): ?>
            <ol>
                <li>Buka aplikasi e-wallet (GoPay, OVO, DANA, ShopeePay) atau mobile banking</li>
                <li>Pilih menu <strong>Scan/Bayar</strong></li>
                <li>Arahkan kamera ke QR Code</li>
                <li>Pastikan nominal: <strong>Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></strong></li>
                <li>Konfirmasi pembayaran</li>
            </ol>
            <?php elseif ($isUrlRedirect): ?>
            <ol>
                <li>Klik tombol <strong>"Bayar dengan <?php echo $paymentDisplay; ?>"</strong></li>
                <li>Anda akan diarahkan ke aplikasi <?php echo $paymentDisplay; ?></li>
                <li>Login jika diminta</li>
                <li>Konfirmasi pembayaran dengan nominal: <strong>Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></strong></li>
                <li>Setelah berhasil, kembali ke halaman ini</li>
            </ol>
            <?php elseif ($isVa): ?>
            <ol>
                <li>Buka mobile banking atau ATM</li>
                <li>Pilih Transfer > Virtual Account</li>
                <li>Masukkan nomor VA: <strong><?php echo htmlspecialchars($vaNumber ?: '-'); ?></strong></li>
                <li>Masukkan nominal: <strong>Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></strong></li>
                <li>Konfirmasi pembayaran</li>
            </ol>
            <?php else: ?>
            <ol>
                <li>Ikuti instruksi pembayaran yang tersedia</li>
                <li>Pastikan nominal sesuai: <strong>Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></strong></li>
                <li>Konfirmasi pembayaran</li>
            </ol>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
    </div>
    
    <!-- Status Overlay -->
    <div class="status-overlay" id="statusOverlay">
        <div class="status-content" id="statusContent"></div>
    </div>
    
    <script>
        // Server-calculated remaining seconds (most accurate)
        let remainingSeconds = <?php echo max(0, $remainingSeconds); ?>;
        const orderId = '<?php echo $orderId; ?>';
        const phone = '<?php echo $phone; ?>';
        const qrString = <?php echo json_encode($qrString); ?>;
        const qrImageUrl = <?php echo json_encode($qrImageUrl); ?>;
        let checkInterval;
        
        // Handle QR image load error
        function handleQRError() {
            const container = document.getElementById('qrContainer');
            if (qrString && qrString.length > 50) {
                generateQRLocally();
            } else {
                container.innerHTML = '<div style="color:red;padding:2rem;">Gagal memuat QR Code</div>';
            }
        }
        
        // Generate QR code locally using server endpoint
        function generateQRLocally() {
            const container = document.getElementById('qrContainer');
            container.innerHTML = '<div class="qr-loading"><i class="fas fa-spinner fa-spin"></i><span>Generating QR...</span></div>';
            
            fetch('generate-qr-atlantic.php?order=' + orderId + (phone ? '&phone=' + phone : ''))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.qr_image) {
                        container.innerHTML = '<img src="' + data.qr_image + '" alt="QRIS Code">';
                    } else if (qrImageUrl) {
                        container.innerHTML = '<img src="' + qrImageUrl + '" alt="QRIS Code">';
                    } else {
                        container.innerHTML = '<div style="color:orange;padding:1rem;">QR gagal dimuat</div>';
                    }
                })
                .catch(err => {
                    console.error('QR error:', err);
                    if (qrImageUrl) {
                        container.innerHTML = '<img src="' + qrImageUrl + '" alt="QRIS Code">';
                    }
                });
        }
        
        // Countdown timer
        function updateCountdown() {
            if (remainingSeconds <= 0) {
                document.getElementById('countdown').textContent = '00:00:00';
                const timerCard = document.getElementById('timerCard');
                if (timerCard) timerCard.classList.add('expired');
                clearInterval(checkInterval);
                showStatus('expired');
                return;
            }
            
            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            
            const countdownEl = document.getElementById('countdown');
            if (countdownEl) {
                countdownEl.textContent = 
                    String(hours).padStart(2, '0') + ':' + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0');
            }
            
            remainingSeconds--;
        }
        
        // Check payment status
        function checkStatus() {
            fetch('atlantic-deposit-check.php?order=' + orderId + '&phone=' + phone)
                .then(response => response.json())
                .then(data => {
                    console.log('Status check:', data);
                    if (data.status === 'payment_success' || data.status === 'processing' || data.status === 'success') {
                        clearInterval(checkInterval);
                        showStatus('success');
                    } else if (data.status === 'expired' || data.status === 'cancelled') {
                        clearInterval(checkInterval);
                        showStatus('expired');
                    }
                })
                .catch(err => console.error('Check error:', err));
        }
        
        // Show status overlay
        function showStatus(type) {
            const overlay = document.getElementById('statusOverlay');
            const content = document.getElementById('statusContent');
            
            if (type === 'success') {
                content.className = 'status-content success';
                content.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <h2>Pembayaran Berhasil!</h2>
                    <p>Pesanan sedang diproses</p>
                    <a href="topup-game-status.php?order=${orderId}${phone ? '&phone=' + phone : ''}" class="btn-status">
                        Lihat Status
                    </a>
                `;
            } else {
                content.className = 'status-content expired';
                content.innerHTML = `
                    <i class="fas fa-times-circle"></i>
                    <h2>Pembayaran Kedaluwarsa</h2>
                    <p>Silakan buat pesanan baru</p>
                    <a href="topup-game.php" class="btn-status">
                        Kembali
                    </a>
                `;
            }
            
            overlay.classList.add('show');
        }
        
        // Copy functions
        function copyVA() {
            const va = document.getElementById('vaNumber');
            if (va) {
                copyToClipboard(va.textContent, event);
            }
        }
        
        function copyAmount() {
            copyToClipboard('<?php echo $order['total']; ?>', event);
        }
        
        function copyToClipboard(text, e) {
            navigator.clipboard.writeText(text).then(() => {
                const btn = e.target.closest('.btn-copy');
                if (btn) {
                    btn.classList.add('copied');
                    const icon = btn.querySelector('i');
                    icon.className = 'fas fa-check';
                    setTimeout(() => {
                        btn.classList.remove('copied');
                        icon.className = 'fas fa-copy';
                    }, 2000);
                }
            });
        }
        
        // Initialize
        <?php if (!$isExpired): ?>
        updateCountdown();
        setInterval(updateCountdown, 1000);
        
        // Check status every 5 seconds
        checkInterval = setInterval(checkStatus, 5000);
        setTimeout(checkStatus, 1000); // First check after 1 second
        <?php endif; ?>
    </script>
</body>
</html>