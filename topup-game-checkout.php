<?php
/**
 * TOP UP GAME - Checkout
 * Halaman untuk memilih metode pembayaran
 * Menggunakan ATLANTIC H2H untuk payment gateway
 * 
 * FIXES:
 * - Metode pembayaran dengan fee yang benar dari Atlantic
 * - Filter berdasarkan minimum pembayaran
 * - Icon dari img_url
 * - Input nomor HP untuk OVO
 * - Error handling yang lebih baik
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

// Validate POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: topup-game.php');
    exit;
}

$gameCode = $_POST['game_code'] ?? '';
$gameId = intval($_POST['game_id'] ?? 0);
$productCode = $_POST['product_code'] ?? '';
$productPrice = intval($_POST['product_price'] ?? 0);
$input1 = trim($_POST['input1'] ?? '');
$input2 = trim($_POST['input2'] ?? '');

if (empty($gameCode) || empty($productCode) || $productPrice <= 0 || empty($input1)) {
    $_SESSION['error'] = 'Data tidak lengkap. Silakan ulangi.';
    header('Location: topup-game.php');
    exit;
}

// Get game info
$stmt = $conn->prepare("SELECT * FROM atlantic_games WHERE id = ? AND is_active = 1");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    $_SESSION['error'] = 'Game tidak ditemukan';
    header('Location: topup-game.php');
    exit;
}

// Get product info
$stmt = $conn->prepare("SELECT * FROM atlantic_game_products WHERE game_id = ? AND product_code = ? AND is_active = 1");
$stmt->execute([$gameId, $productCode]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['error'] = 'Produk tidak ditemukan';
    header("Location: topup-game-detail.php?game=$gameCode");
    exit;
}

// Format target based on game's target_format
$targetFormat = $game['target_format'] ?? '{input1}';
$target = str_replace(['{input1}', '{input2}'], [$input1, $input2], $targetFormat);

// Create display target
if (!empty($input2)) {
    $targetDisplay = $input1 . ' (' . $input2 . ')';
} else {
    $targetDisplay = $input1;
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getUserData() : null;

// Definisi metode pembayaran dengan data lengkap dari Atlantic
$allPaymentMethods = [
    [
        'metode' => 'QRIS',
        'code' => 'QRIS',
        'type' => 'ewallet',
        'name' => 'QRIS',
        'min' => 500,
        'max' => 5000000,
        'fee' => 200,
        'fee_persen' => 0.7,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1699928452_bf58d7f0dd0491fed9b1.png',
        'description' => 'Scan QR dari semua e-wallet & m-banking',
        'requires_phone' => false
    ],
    [
        'metode' => 'QRISFAST',
        'code' => 'QRISFAST',
        'type' => 'ewallet',
        'name' => 'QRIS INSTANT',
        'min' => 2000,
        'max' => 10000000,
        'fee' => 200,
        'fee_persen' => 1.4,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1712479589_ade5d0e7d3fa26e73879.png',
        'description' => 'QRIS dengan konfirmasi cepat',
        'requires_phone' => false
    ],
    [
        'metode' => 'OVO',
        'code' => 'OVO',
        'type' => 'ewallet',
        'name' => 'OVO',
        'min' => 2000,
        'max' => 5000000,
        'fee' => 0,
        'fee_persen' => 1.65,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1699928509_175ae776a7ce3eb6ea57.png',
        'description' => 'Pembayaran via OVO',
        'requires_phone' => true
    ],
    [
        'metode' => 'DANA',
        'code' => 'DANA',
        'type' => 'ewallet',
        'name' => 'DANA',
        'min' => 2000,
        'max' => 5000000,
        'fee' => 0,
        'fee_persen' => 1.5,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1699928468_984670a3b034e1c8312b.png',
        'description' => 'Pembayaran via DANA',
        'requires_phone' => false
    ],
    [
        'metode' => 'SHOPEEPAY',
        'code' => 'SHOPEEPAY',
        'type' => 'ewallet',
        'name' => 'ShopeePay',
        'min' => 2000,
        'max' => 5000000,
        'fee' => 0,
        'fee_persen' => 2.1,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1699928544_70a6a11387077f9b087d.png',
        'description' => 'Pembayaran via ShopeePay',
        'requires_phone' => false
    ],
    [
        'metode' => 'LINKAJA',
        'code' => 'LINKAJA',
        'type' => 'ewallet',
        'name' => 'LinkAja',
        'min' => 2000,
        'max' => 5000000,
        'fee' => 0,
        'fee_persen' => 1.65,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1699928622_df1bc16fb0cb67c68d43.png',
        'description' => 'Pembayaran via LinkAja',
        'requires_phone' => false
    ],
    [
        'metode' => 'BCA',
        'code' => 'BCA',
        'type' => 'bank',
        'name' => 'BCA Virtual Account',
        'min' => 10000,
        'max' => 50000000,
        'fee' => 0,
        'fee_persen' => 0,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1699928422_d5362a875a3600ecd477.png',
        'description' => 'Transfer ke VA BCA',
        'requires_phone' => false
    ],
    [
        'metode' => 'BRI',
        'code' => 'BRI',
        'type' => 'bank',
        'name' => 'BRI Virtual Account',
        'min' => 10000,
        'max' => 50000000,
        'fee' => 0,
        'fee_persen' => 0,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1716981119_48856206f9873c018b50.png',
        'description' => 'Transfer ke VA BRI',
        'requires_phone' => false
    ],
    [
        'metode' => 'ovo',
        'code' => 'OVOTRANSFER',
        'type' => 'bank',
        'name' => 'OVO Transfer',
        'min' => 10000,
        'max' => 10000000,
        'fee' => 0,
        'fee_persen' => 0,
        'img_url' => 'https://s3.atlantic-pedia.co.id/1704450988_4942011ce32a72b92fb3.png',
        'description' => 'Transfer ke OVO',
        'requires_phone' => false
    ]
];

// Filter metode pembayaran berdasarkan harga produk
$paymentMethods = [];
foreach ($allPaymentMethods as $method) {
    $minAmount = intval($method['min'] ?? 0);
    $maxAmount = intval($method['max'] ?? 999999999);
    
    // Hitung total dengan fee
    $feeFlat = intval($method['fee'] ?? 0);
    $feePercent = floatval($method['fee_persen'] ?? 0);
    $calculatedFee = $feeFlat + ceil($productPrice * $feePercent / 100);
    $total = $productPrice + $calculatedFee;
    
    // Skip jika total di luar range
    if ($total < $minAmount || $total > $maxAmount) {
        continue;
    }
    
    $method['calculated_fee'] = $calculatedFee;
    $method['total'] = $total;
    $paymentMethods[] = $method;
}

// Group by type
$groupedMethods = [
    'ewallet' => [],
    'bank' => []
];

foreach ($paymentMethods as $method) {
    $type = strtolower($method['type'] ?? 'other');
    if (isset($groupedMethods[$type])) {
        $groupedMethods[$type][] = $method;
    }
}

// Store data in session for next step
$_SESSION['topup_data'] = [
    'game_id' => $gameId,
    'game_code' => $gameCode,
    'game_name' => $game['game_name'],
    'product_code' => $productCode,
    'product_name' => $product['product_name'],
    'product_price' => $product['price_sell'],
    'price_atlantic' => $product['price_atlantic'],
    'target' => $target,
    'target_display' => $targetDisplay,
    'input1' => $input1,
    'input2' => $input2
];

$pageTitle = "Checkout - " . $game['game_name'] . " - " . getSiteName();

// Check user balance for saldo payment
$userBalance = 0;
$canPayWithBalance = false;
if ($isLoggedIn && $user) {
    $userBalance = $user['saldo'] ?? 0;
    $canPayWithBalance = $userBalance >= $productPrice;
}
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
            --bg-input: #0f172a;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-dark);
            min-height: 100vh;
            color: var(--text-primary);
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .header {
            margin-bottom: 2rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            margin-bottom: 1rem;
        }
        
        .back-link:hover { color: var(--primary); }
        
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .header .subtitle {
            color: var(--text-secondary);
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            .order-summary {
                order: -1;
            }
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-header i { color: var(--primary); }
        
        .card-body { padding: 1.5rem; }
        
        /* Order Summary */
        .order-summary {
            position: sticky;
            top: 1rem;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-item:last-child { border-bottom: none; }
        
        .order-item .label { color: var(--text-secondary); }
        .order-item .value { font-weight: 500; text-align: right; }
        .order-item .value.price { color: var(--success); }
        
        .order-total {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .order-total .value { color: var(--success); }
        
        /* Payment Methods */
        .payment-section {
            margin-bottom: 1.5rem;
        }
        
        .payment-section-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .payment-methods {
            display: grid;
            gap: 0.75rem;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method:hover {
            border-color: var(--primary);
        }
        
        .payment-method.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
        }
        
        .payment-method.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .payment-method input {
            display: none;
        }
        
        .payment-icon {
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .payment-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .payment-icon-text {
            font-weight: 700;
            color: var(--bg-dark);
            font-size: 0.7rem;
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .payment-desc {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .payment-fee {
            text-align: right;
        }
        
        .payment-fee .fee {
            font-size: 0.8rem;
            color: var(--warning);
        }
        
        .payment-fee .total {
            font-weight: 600;
            color: var(--success);
        }
        
        .payment-check {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s;
        }
        
        .payment-method.selected .payment-check {
            opacity: 1;
            background: var(--success);
        }
        
        .payment-check i {
            color: white;
            font-size: 0.75rem;
        }
        
        /* OVO Phone Input */
        .ovo-phone-input {
            display: none;
            margin-top: 0.75rem;
            padding: 1rem;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 0.5rem;
            border: 1px solid var(--primary);
        }
        
        .ovo-phone-input.show {
            display: block;
        }
        
        .ovo-phone-input label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .ovo-phone-input input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .ovo-phone-input input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .ovo-phone-input small {
            display: block;
            margin-top: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.75rem;
        }
        
        /* Contact Form */
        .contact-form {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group .required { color: var(--danger); }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-info {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid var(--primary);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid var(--warning);
            color: var(--warning);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        /* Checkout Button */
        .btn-checkout {
            width: 100%;
            padding: 1rem;
            margin-top: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 0.75rem;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        
        .btn-checkout:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-checkout:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        
        .balance-amount {
            color: var(--success);
            font-weight: 600;
        }
        
        .no-methods {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .no-methods i {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="topup-game-detail.php?game=<?php echo $gameCode; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
            <h1><i class="fas fa-shopping-cart"></i> Checkout</h1>
            <p class="subtitle"><?php echo htmlspecialchars($game['game_name']); ?> - <?php echo htmlspecialchars($product['nominal_display'] ?? $product['product_name']); ?></p>
        </div>
        
        <form action="topup-game-process.php" method="POST" id="checkoutForm">
            <div class="checkout-grid">
                <!-- Payment Methods -->
                <div class="payment-methods-container">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-credit-card"></i>
                            Pilih Metode Pembayaran
                        </div>
                        <div class="card-body">
                            <?php if (empty($paymentMethods) && (!$isLoggedIn || !$canPayWithBalance)): ?>
                            <div class="no-methods">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Tidak ada metode pembayaran yang tersedia untuk nominal ini.</p>
                                <small>Harga produk: <?php echo formatRupiah($productPrice); ?></small>
                            </div>
                            <?php else: ?>
                            
                            <!-- User Balance -->
                            <?php if ($isLoggedIn): ?>
                            <div class="payment-section">
                                <div class="payment-section-title">
                                    <i class="fas fa-wallet"></i> Saldo Akun
                                </div>
                                <div class="payment-methods">
                                    <label class="payment-method <?php echo !$canPayWithBalance ? 'disabled' : ''; ?>">
                                        <input type="radio" name="payment_method" value="SALDO"
                                               <?php echo !$canPayWithBalance ? 'disabled' : ''; ?>
                                               data-fee="0" data-total="<?php echo $productPrice; ?>"
                                               data-requires-phone="false">
                                        <div class="payment-icon" style="background: linear-gradient(135deg, var(--success), #059669);">
                                            <i class="fas fa-wallet" style="color: white; font-size: 1.25rem;"></i>
                                        </div>
                                        <div class="payment-info">
                                            <div class="payment-name">Bayar dengan Saldo</div>
                                            <div class="payment-desc">
                                                Saldo: <span class="balance-amount"><?php echo formatRupiah($userBalance); ?></span>
                                                <?php if (!$canPayWithBalance): ?>
                                                <br><small style="color: var(--danger);">Saldo tidak mencukupi</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="payment-fee">
                                            <div class="fee">Tanpa biaya</div>
                                            <div class="total"><?php echo formatRupiah($productPrice); ?></div>
                                        </div>
                                        <div class="payment-check"><i class="fas fa-check"></i></div>
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- E-Wallet -->
                            <?php if (!empty($groupedMethods['ewallet'])): ?>
                            <div class="payment-section">
                                <div class="payment-section-title">
                                    <i class="fas fa-mobile-alt"></i> E-Wallet & QRIS
                                </div>
                                <div class="payment-methods">
                                    <?php foreach ($groupedMethods['ewallet'] as $method): ?>
                                    <div class="payment-wrapper">
                                        <label class="payment-method" data-method="<?php echo $method['code']; ?>">
                                            <input type="radio" name="payment_method" value="<?php echo $method['metode']; ?>"
                                                   data-fee="<?php echo $method['calculated_fee']; ?>"
                                                   data-total="<?php echo $method['total']; ?>"
                                                   data-requires-phone="<?php echo $method['requires_phone'] ? 'true' : 'false'; ?>">
                                            <div class="payment-icon">
                                                <?php if (!empty($method['img_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($method['img_url']); ?>" alt="<?php echo htmlspecialchars($method['name']); ?>">
                                                <?php else: ?>
                                                <span class="payment-icon-text"><?php echo strtoupper(substr($method['name'], 0, 4)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="payment-info">
                                                <div class="payment-name"><?php echo htmlspecialchars($method['name']); ?></div>
                                                <div class="payment-desc"><?php echo htmlspecialchars($method['description'] ?? ''); ?></div>
                                            </div>
                                            <div class="payment-fee">
                                                <?php if ($method['calculated_fee'] > 0): ?>
                                                <div class="fee">+<?php echo formatRupiah($method['calculated_fee']); ?></div>
                                                <?php else: ?>
                                                <div class="fee" style="color: var(--success);">Gratis</div>
                                                <?php endif; ?>
                                                <div class="total"><?php echo formatRupiah($method['total']); ?></div>
                                            </div>
                                            <div class="payment-check"><i class="fas fa-check"></i></div>
                                        </label>
                                        
                                        <?php if ($method['requires_phone']): ?>
                                        <div class="ovo-phone-input" id="ovoPhoneInput_<?php echo $method['code']; ?>">
                                            <label>Nomor HP OVO <span style="color: var(--danger);">*</span></label>
                                            <input type="tel" name="ovo_phone" placeholder="08xxxxxxxxxx" pattern="^08[0-9]{8,12}$">
                                            <small><i class="fas fa-info-circle"></i> Masukkan nomor HP yang terdaftar di OVO</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Bank / Virtual Account -->
                            <?php if (!empty($groupedMethods['bank'])): ?>
                            <div class="payment-section">
                                <div class="payment-section-title">
                                    <i class="fas fa-university"></i> Virtual Account / Bank Transfer
                                </div>
                                <div class="payment-methods">
                                    <?php foreach ($groupedMethods['bank'] as $method): ?>
                                    <label class="payment-method">
                                        <input type="radio" name="payment_method" value="<?php echo $method['metode']; ?>"
                                               data-fee="<?php echo $method['calculated_fee']; ?>"
                                               data-total="<?php echo $method['total']; ?>"
                                               data-requires-phone="false">
                                        <div class="payment-icon">
                                            <?php if (!empty($method['img_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($method['img_url']); ?>" alt="<?php echo htmlspecialchars($method['name']); ?>">
                                            <?php else: ?>
                                            <span class="payment-icon-text"><?php echo strtoupper(substr($method['code'], 0, 3)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="payment-info">
                                            <div class="payment-name"><?php echo htmlspecialchars($method['name']); ?></div>
                                            <div class="payment-desc"><?php echo htmlspecialchars($method['description'] ?? ''); ?></div>
                                        </div>
                                        <div class="payment-fee">
                                            <?php if ($method['calculated_fee'] > 0): ?>
                                            <div class="fee">+<?php echo formatRupiah($method['calculated_fee']); ?></div>
                                            <?php else: ?>
                                            <div class="fee" style="color: var(--success);">Gratis</div>
                                            <?php endif; ?>
                                            <div class="total"><?php echo formatRupiah($method['total']); ?></div>
                                        </div>
                                        <div class="payment-check"><i class="fas fa-check"></i></div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Contact Info for Guest -->
                            <?php if (!$isLoggedIn): ?>
                            <div class="contact-form">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Masukkan nomor WhatsApp untuk menerima notifikasi</span>
                                </div>
                                <div class="form-group">
                                    <label>Nomor WhatsApp <span class="required">*</span></label>
                                    <input type="tel" name="phone" class="form-control" 
                                           placeholder="08xxxxxxxxxx" required
                                           pattern="^08[0-9]{8,12}$">
                                </div>
                                <div class="form-group">
                                    <label>Email (opsional)</label>
                                    <input type="email" name="email" class="form-control" 
                                           placeholder="email@example.com">
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-receipt"></i>
                            Ringkasan Pesanan
                        </div>
                        <div class="card-body">
                            <div class="order-item">
                                <span class="label">Game</span>
                                <span class="value"><?php echo htmlspecialchars($game['game_name']); ?></span>
                            </div>
                            <div class="order-item">
                                <span class="label">Item</span>
                                <span class="value"><?php echo htmlspecialchars($product['nominal_display'] ?? $product['product_name']); ?></span>
                            </div>
                            <div class="order-item">
                                <span class="label"><?php echo htmlspecialchars($game['input1_label'] ?? 'User ID'); ?></span>
                                <span class="value"><?php echo htmlspecialchars($targetDisplay); ?></span>
                            </div>
                            <div class="order-item">
                                <span class="label">Harga</span>
                                <span class="value price"><?php echo formatRupiah($productPrice); ?></span>
                            </div>
                            <div class="order-item" id="feeRow" style="display: none;">
                                <span class="label">Biaya Layanan</span>
                                <span class="value" id="feeAmount">Rp 0</span>
                            </div>
                            
                            <div class="order-total">
                                <span>Total</span>
                                <span class="value" id="totalAmount"><?php echo formatRupiah($productPrice); ?></span>
                            </div>
                            
                            <input type="hidden" name="game_id" value="<?php echo $gameId; ?>">
                            <input type="hidden" name="game_code" value="<?php echo $gameCode; ?>">
                            <input type="hidden" name="product_code" value="<?php echo $productCode; ?>">
                            <input type="hidden" name="product_price" value="<?php echo $productPrice; ?>">
                            <input type="hidden" name="fee" id="feeInput" value="0">
                            <input type="hidden" name="total" id="totalInput" value="<?php echo $productPrice; ?>">
                            
                            <button type="submit" class="btn-checkout" id="btnCheckout" disabled>
                                <i class="fas fa-lock"></i>
                                <span>Pilih Metode Pembayaran</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning" style="margin-top: 1rem;">
                        <i class="fas fa-shield-alt"></i>
                        <small>Transaksi aman & terenkripsi. Item akan dikirim otomatis setelah pembayaran berhasil.</small>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        const productPrice = <?php echo $productPrice; ?>;
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const feeRow = document.getElementById('feeRow');
        const feeAmount = document.getElementById('feeAmount');
        const totalAmount = document.getElementById('totalAmount');
        const feeInput = document.getElementById('feeInput');
        const totalInput = document.getElementById('totalInput');
        const btnCheckout = document.getElementById('btnCheckout');
        
        // Hide all OVO phone inputs
        function hideAllOvoInputs() {
            document.querySelectorAll('.ovo-phone-input').forEach(el => {
                el.classList.remove('show');
            });
        }
        
        paymentMethods.forEach(radio => {
            radio.addEventListener('change', function() {
                // Update selected style
                document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
                this.closest('.payment-method').classList.add('selected');
                
                // Update amounts
                const fee = parseInt(this.dataset.fee) || 0;
                const total = parseInt(this.dataset.total) || productPrice;
                const requiresPhone = this.dataset.requiresPhone === 'true';
                
                if (fee > 0) {
                    feeRow.style.display = 'flex';
                    feeAmount.textContent = formatRupiah(fee);
                } else {
                    feeRow.style.display = 'none';
                }
                
                totalAmount.textContent = formatRupiah(total);
                feeInput.value = fee;
                totalInput.value = total;
                
                // Handle OVO phone input
                hideAllOvoInputs();
                if (requiresPhone) {
                    const methodCode = this.closest('.payment-method').dataset.method;
                    const ovoInput = document.getElementById('ovoPhoneInput_' + methodCode);
                    if (ovoInput) {
                        ovoInput.classList.add('show');
                        ovoInput.querySelector('input').required = true;
                    }
                } else {
                    // Remove required from all ovo inputs
                    document.querySelectorAll('.ovo-phone-input input').forEach(input => {
                        input.required = false;
                    });
                }
                
                // Enable button
                btnCheckout.disabled = false;
                btnCheckout.querySelector('span').textContent = 'Bayar ' + formatRupiah(total);
            });
        });
        
        function formatRupiah(num) {
            return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                e.preventDefault();
                alert('Pilih metode pembayaran');
                return;
            }
            
            // Check OVO phone if required
            if (selectedMethod.dataset.requiresPhone === 'true') {
                const ovoPhone = document.querySelector('input[name="ovo_phone"]');
                if (ovoPhone && (!ovoPhone.value || !ovoPhone.value.match(/^08[0-9]{8,12}$/))) {
                    e.preventDefault();
                    alert('Masukkan nomor HP OVO yang valid');
                    ovoPhone.focus();
                    return;
                }
            }
            
            <?php if (!$isLoggedIn): ?>
            const phone = document.querySelector('input[name="phone"]').value;
            if (!phone || !phone.match(/^08[0-9]{8,12}$/)) {
                e.preventDefault();
                alert('Masukkan nomor WhatsApp yang valid');
                return;
            }
            <?php endif; ?>
            
            btnCheckout.disabled = true;
            btnCheckout.querySelector('span').textContent = 'Memproses...';
        });
    </script>
</body>
</html>