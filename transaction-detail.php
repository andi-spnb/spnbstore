<?php
/**
 * Transaction Detail Page - FIXED VERSION
 * 
 * Fixes:
 * 1. Status "processing" sekarang menampilkan "Sedang Diproses" (bukan Gagal)
 * 2. Parse SN format Atlantic: "email password profil pin" (dipisah spasi)
 */

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($transaction_id <= 0) {
    redirect('riwayat.php');
}

// Get transaction details
$stmt = $conn->prepare("
    SELECT t.*, 
           p.nama as product_name, 
           p.product_code,
           p.gambar as product_image,
           p.deskripsi as product_description
    FROM transactions t
    LEFT JOIN products p ON t.product_id = p.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$transaction_id, $user['id']]);
$transaction = $stmt->fetch();

if (!$transaction) {
    redirect('riwayat.php');
}

// Check if Atlantic product
$isAtlanticProduct = !empty($transaction['product_code']);

// Get H2H transaction details
$h2h_data = null;
if ($isAtlanticProduct) {
    // Try to get h2h_transactions if table exists
    try {
        $stmt = $conn->prepare("SELECT * FROM h2h_transactions WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$transaction['transaction_id']]);
        $h2h_data = $stmt->fetch();
    } catch (Exception $e) {
        // Table doesn't exist
        $h2h_data = null;
    }
}

// Parse customer data
$customer_data = [];
if (!empty($transaction['customer_data'])) {
    $customer_data = json_decode($transaction['customer_data'], true) ?: [];
}

/**
 * Parse SN/Credential dari Atlantic
 * 
 * Supports multiple formats:
 * 1. Format lama: "Email: xxx | Password: xxx | Profile: xxx"
 * 2. Format baru: "email password profile pin" (dipisah spasi)
 * 
 * Contoh format baru: "zionrr@freenet.de lolipop24575 D 1114"
 */
function parseAtlanticSN($sn) {
    if (empty($sn)) {
        return null;
    }
    
    $credentials = [];
    
    // Check if old format (contains : or |)
    if (strpos($sn, ':') !== false || strpos($sn, '|') !== false) {
        // Old format: "Email: xxx | Password: xxx | Profile: xxx"
        $parts = preg_split('/[\|\n]/', $sn);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            if (strpos($part, ':') !== false) {
                list($key, $value) = explode(':', $part, 2);
                $key = strtolower(trim($key));
                $value = trim($value);
                
                // Normalize keys
                if (in_array($key, ['email', 'e-mail', 'akun', 'account'])) {
                    $credentials['Email'] = $value;
                } elseif (in_array($key, ['password', 'pass', 'pwd', 'sandi'])) {
                    $credentials['Password'] = $value;
                } elseif (in_array($key, ['profile', 'profil'])) {
                    $credentials['Profile'] = $value;
                } elseif (in_array($key, ['pin', 'kode'])) {
                    $credentials['PIN'] = $value;
                } else {
                    $credentials[ucfirst($key)] = $value;
                }
            }
        }
    } else {
        // New format: "email password profile pin" (space separated)
        // Example: "zionrr@freenet.de lolipop24575 D 1114"
        $parts = preg_split('/\s+/', trim($sn));
        
        if (count($parts) >= 2) {
            // First part is email (contains @)
            if (filter_var($parts[0], FILTER_VALIDATE_EMAIL) || strpos($parts[0], '@') !== false) {
                $credentials['Email'] = $parts[0];
                
                if (isset($parts[1])) {
                    $credentials['Password'] = $parts[1];
                }
                if (isset($parts[2])) {
                    $credentials['Profile'] = $parts[2];
                }
                if (isset($parts[3])) {
                    $credentials['PIN'] = $parts[3];
                }
            } else {
                // Fallback: treat as raw data
                $credentials['Akses'] = $sn;
            }
        } else {
            // Single value - treat as raw
            $credentials['Akses'] = $sn;
        }
    }
    
    return $credentials;
}

// Handle AJAX status check
if (isset($_GET['check_status']) && $_GET['check_status'] == '1') {
    header('Content-Type: application/json');
    
    $response = [
        'success' => true,
        'status' => $transaction['status'],
        'has_credential' => false,
        'credential' => null,
        'credential_parsed' => null
    ];
    
    if ($isAtlanticProduct && $h2h_data) {
        // Try to check Atlantic status
        $atlanticClassPath = __DIR__ . '/classes/AtlanticH2H.php';
        if (!file_exists($atlanticClassPath)) {
            $atlanticClassPath = __DIR__ . '/AtlanticH2H.php';
        }
        
        if (file_exists($atlanticClassPath) && !empty($h2h_data['h2h_trx_id'])) {
            require_once $atlanticClassPath;
            $atlantic = new AtlanticH2H();
            
            $statusResult = $atlantic->checkStatus($h2h_data['h2h_trx_id']);
            
            if ($statusResult['success']) {
                $statusData = $statusResult['data']['data'] ?? [];
                $newStatus = $statusData['status'] ?? $h2h_data['status'];
                $sn = $statusData['sn'] ?? $h2h_data['sn_voucher'];
                
                // Map status
                $dbStatus = 'processing';
                $trxStatus = 'processing';
                
                if (in_array($newStatus, ['success', 'sukses', 'berhasil'])) {
                    $dbStatus = 'success';
                    $trxStatus = 'selesai';
                } elseif (in_array($newStatus, ['failed', 'gagal', 'error'])) {
                    $dbStatus = 'failed';
                    $trxStatus = 'gagal';
                }
                
                // Update database
                try {
                    $stmt = $conn->prepare("UPDATE h2h_transactions SET status = ?, sn_voucher = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$dbStatus, $sn, $h2h_data['id']]);
                    
                    $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ?");
                    $stmt->execute([$trxStatus, $transaction_id]);
                } catch (Exception $e) {
                    // Ignore DB errors
                }
                
                $response['status'] = $trxStatus;
                $response['h2h_status'] = $dbStatus;
                
                if (!empty($sn)) {
                    $response['has_credential'] = true;
                    $response['credential'] = $sn;
                    $response['credential_parsed'] = parseAtlanticSN($sn);
                }
            }
        }
        
        // If still have sn_voucher in h2h_data
        if (!$response['has_credential'] && !empty($h2h_data['sn_voucher'])) {
            $response['has_credential'] = true;
            $response['credential'] = $h2h_data['sn_voucher'];
            $response['credential_parsed'] = parseAtlanticSN($h2h_data['sn_voucher']);
        }
    }
    
    echo json_encode($response);
    exit;
}

// Status badge helper
function getStatusBadge($status) {
    $badges = [
        'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Menunggu Pembayaran'],
        'processing' => ['class' => 'info', 'icon' => 'spinner fa-spin', 'text' => 'Sedang Diproses'],
        'proses' => ['class' => 'info', 'icon' => 'cog fa-spin', 'text' => 'Proses Manual'],
        'ready' => ['class' => 'success', 'icon' => 'check', 'text' => 'Siap'],
        'selesai' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Selesai'],
        'gagal' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Gagal'],
        'failed' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Gagal'],
        'expired' => ['class' => 'secondary', 'icon' => 'clock', 'text' => 'Expired'],
        'pending_manual' => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'text' => 'Perlu Review'],
        'success' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Sukses'],
    ];
    
    $badge = $badges[strtolower($status)] ?? ['class' => 'secondary', 'icon' => 'question', 'text' => ucfirst($status)];
    return $badge;
}

// Check if transaction has credential
$hasCredential = false;
$sn_voucher = null;
$parsedCredentials = null;
$credentialSource = null; // 'atlantic' or 'local'

// Check Atlantic credential first (from h2h_data)
if (!empty($h2h_data['sn_voucher'])) {
    $hasCredential = true;
    $sn_voucher = $h2h_data['sn_voucher'];
    $parsedCredentials = parseAtlanticSN($sn_voucher);
    $credentialSource = 'atlantic';
}

// Check local credential (from account_info in transactions table)
if (!$hasCredential && !empty($transaction['account_info'])) {
    $hasCredential = true;
    $sn_voucher = $transaction['account_info'];
    $parsedCredentials = parseLocalAccountInfo($transaction['account_info']);
    $credentialSource = 'local';
}

/**
 * Parse local account info
 * 
 * Supports formats:
 * 1. "email|password" 
 * 2. "email|password|profile"
 * 3. Plain text
 */
function parseLocalAccountInfo($data) {
    if (empty($data)) {
        return null;
    }
    
    $credentials = [];
    
    // Handle multiple accounts (separated by newline)
    $accounts = explode("\n", trim($data));
    
    foreach ($accounts as $index => $account) {
        $account = trim($account);
        if (empty($account)) continue;
        
        // Check if pipe-separated format
        if (strpos($account, '|') !== false) {
            $parts = explode('|', $account);
            
            $prefix = count($accounts) > 1 ? 'Akun ' . ($index + 1) . ' - ' : '';
            
            if (isset($parts[0])) {
                $credentials[$prefix . 'Email'] = trim($parts[0]);
            }
            if (isset($parts[1])) {
                $credentials[$prefix . 'Password'] = trim($parts[1]);
            }
            if (isset($parts[2])) {
                $credentials[$prefix . 'Profile/PIN'] = trim($parts[2]);
            }
        } else {
            // Plain text - could be space separated like Atlantic
            // Try to parse as "email password profile pin"
            $parts = preg_split('/\s+/', $account);
            
            if (count($parts) >= 2 && (filter_var($parts[0], FILTER_VALIDATE_EMAIL) || strpos($parts[0], '@') !== false)) {
                $prefix = count($accounts) > 1 ? 'Akun ' . ($index + 1) . ' - ' : '';
                
                $credentials[$prefix . 'Email'] = $parts[0];
                if (isset($parts[1])) $credentials[$prefix . 'Password'] = $parts[1];
                if (isset($parts[2])) $credentials[$prefix . 'Profile'] = $parts[2];
                if (isset($parts[3])) $credentials[$prefix . 'PIN'] = $parts[3];
            } else {
                // Just store as-is
                $label = count($accounts) > 1 ? 'Akun ' . ($index + 1) : 'Akses';
                $credentials[$label] = $account;
            }
        }
    }
    
    return $credentials;
}

// Determine display status
$displayStatus = strtolower($transaction['status']);
$statusBadge = getStatusBadge($displayStatus);

// Check if should show processing view
$isProcessing = in_array($displayStatus, ['processing', 'proses', 'pending']);
$isSuccess = in_array($displayStatus, ['selesai', 'success', 'ready']);
$isFailed = in_array($displayStatus, ['gagal', 'failed']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #475569;
            --primary-color: #8b5cf6;
            --primary-hover: #7c3aed;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }
        
        .page-title {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-badge.success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }
        
        .status-badge.warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }
        
        .status-badge.danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
        }
        
        .status-badge.info {
            background: rgba(59, 130, 246, 0.2);
            color: var(--info-color);
        }
        
        .status-badge.secondary {
            background: rgba(100, 116, 139, 0.2);
            color: var(--text-muted);
        }
        
        .transaction-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        @media (max-width: 600px) {
            .transaction-info {
                grid-template-columns: 1fr;
            }
        }
        
        .info-item {
            padding: 0.75rem;
            background: var(--bg-tertiary);
            border-radius: 0.5rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .info-value.price {
            color: var(--success-color);
            font-size: 1.1rem;
        }
        
        /* Product Info */
        .product-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            background: var(--bg-tertiary);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-details h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1.1rem;
        }
        
        .product-code {
            font-size: 0.8rem;
            color: var(--primary-color);
            font-family: monospace;
        }
        
        /* Credential Box */
        .credential-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            border: 2px solid var(--success-color);
            border-radius: 1rem;
            padding: 1.5rem;
        }
        
        .credential-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: 1.5rem;
        }
        
        .credential-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .credential-label {
            min-width: 80px;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .credential-value {
            flex: 1;
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 600;
            word-break: break-all;
        }
        
        .btn-copy {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: var(--primary-color);
            border: none;
            border-radius: 0.5rem;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        
        .btn-copy:hover {
            background: var(--primary-hover);
            transform: scale(1.05);
        }
        
        .btn-copy.copied {
            background: var(--success-color);
        }
        
        /* Processing Box */
        .processing-box {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05));
            border: 2px solid var(--info-color);
            border-radius: 1rem;
        }
        
        .processing-box.error {
            border-color: var(--danger-color);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
        }
        
        .processing-icon {
            font-size: 3rem;
            color: var(--info-color);
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        
        .processing-box.error .processing-icon {
            color: var(--danger-color);
            animation: none;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .processing-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .processing-text {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }
        
        /* Buttons */
        .btn-refresh {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-refresh:hover {
            background: var(--primary-hover);
        }
        
        .btn-refresh:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            transform: translateX(150%);
            transition: transform 0.3s;
            z-index: 9999;
            border-left: 4px solid var(--success-color);
        }
        
        .toast.error {
            border-left-color: var(--danger-color);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        /* Debug Console */
        .debug-console {
            background: #1a1a2e;
            border: 1px solid #444;
            border-radius: 0.5rem;
            margin-top: 1.5rem;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.75rem;
        }
        
        .debug-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 1rem;
            background: #0f0f1a;
            border-bottom: 1px solid #444;
            cursor: pointer;
        }
        
        .debug-content {
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            display: none;
        }
        
        .debug-content.show {
            display: block;
        }
        
        .debug-log {
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: rgba(255,255,255,0.05);
            border-radius: 0.25rem;
        }
        
        .debug-log .time { color: #888; }
        .debug-log .message { color: #4ade80; }
        .debug-log .data { color: #fbbf24; margin-top: 0.25rem; white-space: pre-wrap; word-break: break-all; }
        
        /* Target Info */
        .target-info {
            margin-top: 1rem;
            padding: 0.75rem;
            background: var(--bg-tertiary);
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <a href="riwayat.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="page-title">Detail Transaksi</h1>
        </div>
        
        <!-- Status Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Status Transaksi</h3>
                <span class="status-badge <?php echo $statusBadge['class']; ?>" id="statusBadge">
                    <i class="fas fa-<?php echo $statusBadge['icon']; ?>"></i>
                    <span id="statusText"><?php echo $statusBadge['text']; ?></span>
                </span>
            </div>
            <div class="card-body">
                <div class="transaction-info">
                    <div class="info-item">
                        <div class="info-label">ID Transaksi</div>
                        <div class="info-value"><?php echo htmlspecialchars($transaction['transaction_id']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tanggal</div>
                        <div class="info-value"><?php echo date('d M Y, H:i', strtotime($transaction['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Metode Pembayaran</div>
                        <div class="info-value"><?php echo strtoupper($transaction['payment_method'] ?: 'SALDO'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Pembayaran</div>
                        <div class="info-value price"><?php echo formatRupiah($transaction['total_harga']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-box"></i> Produk</h3>
                <?php if ($isAtlanticProduct): ?>
                    <span class="status-badge info">
                        <i class="fas fa-cloud"></i> API Product
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="product-info">
                    <div class="product-image">
                        <?php if (!empty($transaction['product_image']) && file_exists('assets/img/products/' . $transaction['product_image'])): ?>
                            <img src="assets/img/products/<?php echo $transaction['product_image']; ?>" alt="Product">
                        <?php else: ?>
                            🎬
                        <?php endif; ?>
                    </div>
                    <div class="product-details">
                        <h4><?php echo htmlspecialchars($transaction['product_name']); ?></h4>
                        <?php if ($isAtlanticProduct): ?>
                            <div class="product-code">Code: <?php echo htmlspecialchars($transaction['product_code']); ?></div>
                        <?php endif; ?>
                        <div style="margin-top: 0.5rem; color: var(--text-secondary);">
                            Qty: <?php echo $transaction['quantity']; ?>x @ <?php echo formatRupiah($transaction['total_harga'] / $transaction['quantity']); ?>
                        </div>
                    </div>
                </div>
                
                <?php 
                // Show target info from h2h_data or customer_data
                $targetInfo = $h2h_data['target'] ?? $customer_data['target'] ?? $customer_data['device_info'] ?? null;
                if (!empty($targetInfo)): 
                ?>
                <div class="target-info">
                    <div class="info-label"><i class="fas fa-mobile-alt"></i> Target/Device Info</div>
                    <div class="info-value"><?php echo htmlspecialchars($targetInfo); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Credential Section (for Atlantic products AND Local products with credential) -->
        <?php if ($isAtlanticProduct || $hasCredential || $isProcessing): ?>
        <div class="card" id="credentialCard">
            <div class="card-header">
                <h3><i class="fas fa-key"></i> Akses Akun</h3>
                <?php if ($isAtlanticProduct): ?>
                <button class="btn-refresh" onclick="checkStatus()" id="btnRefresh">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($hasCredential && !empty($parsedCredentials)): ?>
                    <!-- SUCCESS - Show Credentials -->
                    <div class="credential-box" id="credentialBox">
                        <div class="credential-title">
                            <i class="fas fa-check-circle"></i>
                            Akun Anda Siap!
                            <?php if ($credentialSource === 'local'): ?>
                                <span style="font-size: 0.7em; background: var(--bg-tertiary); padding: 2px 8px; border-radius: 4px; margin-left: 8px;">Produk Lokal</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php foreach ($parsedCredentials as $label => $value): ?>
                        <div class="credential-item">
                            <span class="credential-label"><?php echo htmlspecialchars($label); ?></span>
                            <span class="credential-value"><?php echo htmlspecialchars($value); ?></span>
                            <button class="btn-copy" onclick="copyToClipboard('<?php echo addslashes($value); ?>', this)" title="Salin">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(16, 185, 129, 0.1); border-radius: 0.5rem; font-size: 0.85rem;">
                            <i class="fas fa-info-circle"></i>
                            Simpan informasi akun ini dengan baik. Jika ada masalah, hubungi customer service kami.
                        </div>
                    </div>
                    
                <?php elseif ($isProcessing): ?>
                    <!-- PROCESSING - Show Loading -->
                    <div class="processing-box" id="processingBox">
                        <div class="processing-icon">
                            <i class="fas fa-cog fa-spin"></i>
                        </div>
                        <div class="processing-title">Pesanan Sedang Diproses</div>
                        <div class="processing-text">
                            Sistem kami sedang memproses pesanan Anda. 
                            Credential akun akan muncul otomatis setelah proses selesai.
                        </div>
                        <button class="btn-refresh" onclick="checkStatus()">
                            <i class="fas fa-sync-alt"></i> Cek Status Sekarang
                        </button>
                        <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);" id="autoRefreshText">
                            Auto-refresh dalam <span id="countdown">10</span> detik...
                        </div>
                    </div>
                    
                <?php elseif ($isFailed): ?>
                    <!-- FAILED - Show Error -->
                    <div class="processing-box error" id="failedBox">
                        <div class="processing-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="processing-title">Transaksi Gagal</div>
                        <div class="processing-text">
                            Maaf, pesanan Anda tidak dapat diproses. 
                            Silakan hubungi customer service untuk bantuan atau refund.
                        </div>
                        <a href="contact.php" class="btn btn-primary">
                            <i class="fas fa-headset"></i> Hubungi CS
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- OTHER STATUS -->
                    <div class="processing-box">
                        <div class="processing-icon" style="animation: none;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="processing-title">Status: <?php echo htmlspecialchars($transaction['status']); ?></div>
                        <div class="processing-text">
                            Silakan tunggu atau refresh untuk memperbarui status.
                        </div>
                        <button class="btn-refresh" onclick="checkStatus()">
                            <i class="fas fa-sync-alt"></i> Refresh Status
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- H2H Debug Info -->
        <?php if ($isAtlanticProduct && $h2h_data): ?>
        <div class="debug-console">
            <div class="debug-header" onclick="toggleDebug()">
                <span><i class="fas fa-bug"></i> Debug Info (H2H Transaction)</span>
                <i class="fas fa-chevron-down" id="debugIcon"></i>
            </div>
            <div class="debug-content" id="debugContent">
                <div class="debug-log">
                    <span class="time">H2H ID:</span>
                    <span class="message"><?php echo $h2h_data['id']; ?></span>
                </div>
                <div class="debug-log">
                    <span class="time">Atlantic TRX ID:</span>
                    <span class="message"><?php echo $h2h_data['h2h_trx_id'] ?? 'N/A'; ?></span>
                </div>
                <div class="debug-log">
                    <span class="time">H2H Status:</span>
                    <span class="message"><?php echo $h2h_data['status']; ?></span>
                </div>
                <div class="debug-log">
                    <span class="time">Product Code:</span>
                    <span class="message"><?php echo $h2h_data['product_code']; ?></span>
                </div>
                <div class="debug-log">
                    <span class="time">Target:</span>
                    <span class="message"><?php echo $h2h_data['target']; ?></span>
                </div>
                <div class="debug-log">
                    <span class="time">SN/Voucher:</span>
                    <span class="message"><?php echo $h2h_data['sn_voucher'] ?? 'Belum tersedia'; ?></span>
                </div>
                <?php if (!empty($h2h_data['h2h_response'])): ?>
                <div class="debug-log">
                    <span class="time">Raw Response:</span>
                    <div class="data"><?php echo htmlspecialchars(substr($h2h_data['h2h_response'], 0, 500)); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="riwayat.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> Riwayat Transaksi
            </a>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag"></i> Belanja Lagi
            </a>
        </div>
    </div>
    
    <!-- Toast -->
    <div class="toast" id="toast"></div>
    
    <script>
    // Copy to clipboard
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.classList.add('copied');
            
            showToast('Berhasil disalin!');
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('copied');
            }, 2000);
        }).catch(err => {
            // Fallback
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            showToast('Berhasil disalin!');
        });
    }
    
    // Toast notification
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
    
    // Toggle debug console
    function toggleDebug() {
        const content = document.getElementById('debugContent');
        const icon = document.getElementById('debugIcon');
        
        content.classList.toggle('show');
        icon.style.transform = content.classList.contains('show') ? 'rotate(180deg)' : '';
    }
    
    // Check status via AJAX
    let isChecking = false;
    
    function checkStatus() {
        if (isChecking) return;
        
        isChecking = true;
        const btn = document.getElementById('btnRefresh');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
        }
        
        fetch('?id=<?php echo $transaction_id; ?>&check_status=1')
            .then(response => response.json())
            .then(data => {
                console.log('Status check response:', data);
                
                if (data.has_credential && data.credential_parsed) {
                    // Reload page to show credentials
                    location.reload();
                } else if (data.status !== '<?php echo $transaction['status']; ?>') {
                    // Status changed, reload
                    location.reload();
                } else {
                    showToast('Status: ' + data.status);
                }
            })
            .catch(err => {
                console.error('Error checking status:', err);
                showToast('Gagal memeriksa status', true);
            })
            .finally(() => {
                isChecking = false;
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                }
            });
    }
    
    // Auto-refresh for processing status
    <?php if ($isProcessing): ?>
    let countdown = 10;
    const countdownEl = document.getElementById('countdown');
    
    const countdownInterval = setInterval(() => {
        countdown--;
        if (countdownEl) countdownEl.textContent = countdown;
        
        if (countdown <= 0) {
            countdown = 10;
            checkStatus();
        }
    }, 1000);
    <?php endif; ?>
    </script>
</body>
</html>
