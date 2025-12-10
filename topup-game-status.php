<?php
/**
 * TOP UP GAME - Status
 * Halaman untuk cek status pesanan
 */

// Enable error reporting for debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Get parameters
$orderId = $_GET['order'] ?? '';
$phone = $_GET['phone'] ?? '';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getUserData() : null;

$order = null;
$error = '';
$needsVerification = false;

if (!empty($orderId)) {
    // Get order
    $stmt = $conn->prepare("SELECT o.*, g.game_name, g.game_code 
                            FROM atlantic_orders o 
                            LEFT JOIN atlantic_games g ON o.game_id = g.id 
                            WHERE o.order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $error = 'Order tidak ditemukan';
    } else {
        // Verify access
        if ($order['user_id']) {
            // User order
            if (!$isLoggedIn || $user['id'] != $order['user_id']) {
                $error = 'Akses ditolak. Silakan login dengan akun yang benar.';
                $order = null;
            }
        } else {
            // Guest order - need phone verification
            if (empty($phone)) {
                $needsVerification = true;
                $order = null;
            } elseif (AtlanticH2H::formatPhone($phone) !== AtlanticH2H::formatPhone($order['guest_phone'])) {
                $error = 'Nomor WhatsApp tidak sesuai dengan pesanan ini';
                $order = null;
                $needsVerification = true;
            }
        }
    }
}

// Status mapping
$statusConfig = [
    'waiting_payment' => [
        'label' => 'Menunggu Pembayaran',
        'icon' => 'clock',
        'color' => '#f59e0b',
        'description' => 'Silakan selesaikan pembayaran Anda'
    ],
    'payment_success' => [
        'label' => 'Pembayaran Berhasil',
        'icon' => 'check-circle',
        'color' => '#10b981',
        'description' => 'Pembayaran diterima, pesanan sedang diproses'
    ],
    'processing' => [
        'label' => 'Sedang Diproses',
        'icon' => 'cog fa-spin',
        'color' => '#3b82f6',
        'description' => 'Pesanan Anda sedang dalam proses'
    ],
    'success' => [
        'label' => 'Berhasil',
        'icon' => 'check-double',
        'color' => '#10b981',
        'description' => 'Pesanan berhasil diproses'
    ],
    'failed' => [
        'label' => 'Gagal',
        'icon' => 'times-circle',
        'color' => '#ef4444',
        'description' => 'Pesanan gagal diproses'
    ],
    'expired' => [
        'label' => 'Kedaluwarsa',
        'icon' => 'clock',
        'color' => '#6b7280',
        'description' => 'Pembayaran tidak diterima dalam batas waktu'
    ],
    'cancelled' => [
        'label' => 'Dibatalkan',
        'icon' => 'ban',
        'color' => '#6b7280',
        'description' => 'Pesanan dibatalkan'
    ],
    'refunded' => [
        'label' => 'Dikembalikan',
        'icon' => 'undo',
        'color' => '#8b5cf6',
        'description' => 'Dana telah dikembalikan'
    ]
];

$pageTitle = "Status Pesanan" . ($order ? " - " . $order['order_id'] : "");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link rel="stylesheet" href="assets/css/style.css">
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
        
        /* Header */
        .topup-header {
            background: var(--bg-card);
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .btn-back {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--bg-dark);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: var(--primary);
        }
        
        .header-title h1 {
            font-size: 1.25rem;
        }
        
        /* Main */
        .main-content {
            max-width: 600px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
        }
        
        /* Search Form */
        .search-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .search-card i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .search-card h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .search-card p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }
        
        .search-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .search-form .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }
        
        .search-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .search-form input {
            width: 100%;
            padding: 1rem;
            border-radius: 0.75rem;
            border: 2px solid var(--border-color);
            background: var(--bg-dark);
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .search-form input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .search-form button {
            width: 100%;
            padding: 1rem;
            border-radius: 0.75rem;
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        
        .search-form button:hover {
            opacity: 0.9;
        }
        
        /* Error */
        .error-card {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .error-card i {
            font-size: 2rem;
            color: var(--danger);
            margin-bottom: 0.5rem;
        }
        
        .error-card p {
            color: var(--danger);
        }
        
        /* Status Card */
        .status-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }
        
        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
        }
        
        .status-label {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .status-description {
            color: var(--text-secondary);
        }
        
        /* Order Details */
        .details-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }
        
        .details-title {
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .details-title i {
            color: var(--primary);
        }
        
        .details-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }
        
        .details-item-label {
            color: var(--text-secondary);
        }
        
        .details-item-value {
            font-weight: 500;
            text-align: right;
        }
        
        /* Credential Card */
        .credential-card {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            border: 2px solid var(--success);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .credential-title {
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--success);
        }
        
        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--bg-dark);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .credential-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .credential-value {
            font-weight: 600;
            font-family: monospace;
        }
        
        .btn-copy {
            background: var(--primary);
            border: none;
            color: white;
            padding: 0.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
        }
        
        .btn-copy:hover {
            background: var(--primary-dark);
        }
        
        .btn-copy.copied {
            background: var(--success);
        }
        
        /* Processing Note */
        .processing-note {
            background: rgba(59, 130, 246, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }
        
        .processing-note i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            flex: 1;
            min-width: 150px;
            padding: 1rem;
            border-radius: 0.75rem;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-action.primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .btn-action.secondary {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        /* Auto refresh */
        .auto-refresh {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 1rem;
        }
        
        .auto-refresh i {
            animation: spin 2s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="topup-header">
        <div class="header-container">
            <a href="topup-game.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="header-title">
                <h1>Status Pesanan</h1>
            </div>
        </div>
    </header>
    
    <div class="main-content">
        <?php if (!empty($error)): ?>
        <div class="error-card">
            <i class="fas fa-exclamation-circle"></i>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($needsVerification || (empty($orderId) && !$order)): ?>
        <!-- Search Form -->
        <div class="search-card">
            <i class="fas fa-search"></i>
            <h2>Cek Status Pesanan</h2>
            <p>Masukkan ID Order dan nomor WhatsApp yang digunakan saat pemesanan</p>
            
            <form class="search-form" method="GET">
                <div class="form-group">
                    <label for="order">ID Order</label>
                    <input type="text" id="order" name="order" placeholder="TG-XXXXXXXX-XXXXXXXX" 
                           value="<?php echo htmlspecialchars($orderId); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Nomor WhatsApp (PIN)</label>
                    <input type="tel" id="phone" name="phone" placeholder="08123456789" required>
                </div>
                <button type="submit">
                    <i class="fas fa-search"></i> Cek Status
                </button>
            </form>
        </div>
        
        <?php elseif ($order): ?>
        
        <?php 
        $status = $order['status'];
        $config = $statusConfig[$status] ?? $statusConfig['processing'];
        ?>
        
        <!-- Status Card -->
        <div class="status-card">
            <div class="status-icon" style="background: <?php echo $config['color']; ?>20; color: <?php echo $config['color']; ?>">
                <i class="fas fa-<?php echo $config['icon']; ?>"></i>
            </div>
            <div class="status-label" style="color: <?php echo $config['color']; ?>">
                <?php echo $config['label']; ?>
            </div>
            <div class="status-description">
                <?php echo $config['description']; ?>
            </div>
        </div>
        
        <?php if (in_array($status, ['processing', 'payment_success'])): ?>
        <div class="processing-note">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Pesanan sedang diproses. Halaman ini akan otomatis diperbarui.</span>
        </div>
        <?php endif; ?>
        
        <?php if ($status === 'success' && !empty($order['sn'])): ?>
        <!-- Credential -->
        <div class="credential-card">
            <div class="credential-title">
                <i class="fas fa-key"></i>
                Voucher / Serial Number
            </div>
            <?php 
            $atlantic = new AtlanticH2H();
            $credentials = $atlantic->parseCredential($order['sn']);
            if ($credentials):
                foreach ($credentials as $label => $value):
            ?>
            <div class="credential-item">
                <div>
                    <div class="credential-label"><?php echo is_numeric($label) ? 'Value' : htmlspecialchars($label); ?></div>
                    <div class="credential-value"><?php echo htmlspecialchars($value); ?></div>
                </div>
                <button class="btn-copy" onclick="copyToClipboard('<?php echo addslashes($value); ?>', this)">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <?php endforeach; else: ?>
            <div class="credential-item">
                <div class="credential-value"><?php echo htmlspecialchars($order['sn_voucher']); ?></div>
                <button class="btn-copy" onclick="copyToClipboard('<?php echo addslashes($order['sn_voucher']); ?>', this)">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Order Details -->
        <div class="details-card">
            <div class="details-title">
                <i class="fas fa-receipt"></i>
                Detail Pesanan
            </div>
            <div class="details-item">
                <span class="details-item-label">Order ID</span>
                <span class="details-item-value"><?php echo htmlspecialchars($order['order_id']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-item-label">Game</span>
                <span class="details-item-value"><?php echo htmlspecialchars($order['game_name']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-item-label">ID Game</span>
                <span class="details-item-value"><?php echo htmlspecialchars($order['target_display']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-item-label">Username</span>
                <span class="details-item-value"><?php echo htmlspecialchars($order['sn_voucher']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-item-label">Produk</span>
                <span class="details-item-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-item-label">Total</span>
                <span class="details-item-value">Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></span>
            </div>
            <div class="details-item">
                <span class="details-item-label">Waktu</span>
                <span class="details-item-value"><?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></span>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if ($status === 'waiting_payment'): ?>
            <a href="topup-game-payment.php?order=<?php echo $orderId; ?><?php echo $phone ? '&phone='.$phone : ''; ?>" class="btn-action primary">
                <i class="fas fa-credit-card"></i>
                Bayar Sekarang
            </a>
            <?php endif; ?>
            
            <a href="topup-game.php" class="btn-action secondary">
                <i class="fas fa-gamepad"></i>
                Top Up Lagi
            </a>
        </div>
        
        <?php if (in_array($status, ['processing', 'payment_success', 'waiting_payment'])): ?>
        <div class="auto-refresh">
            <i class="fas fa-sync-alt"></i>
            Memperbarui otomatis...
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <script>
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="fas fa-copy"></i>';
                }, 2000);
            });
        }
        
        <?php if ($order && in_array($order['status'], ['processing', 'payment_success', 'waiting_payment'])): ?>
        // Auto refresh every 10 seconds
        setTimeout(() => {
            location.reload();
        }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>
