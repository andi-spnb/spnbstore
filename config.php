<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'DB USER');  // Ganti dengan username database Anda
define('DB_PASS', 'DB PASS');  // Ganti dengan password database Anda
define('DB_NAME', 'DB NAME);

// Konfigurasi Website
define('SITE_URL', 'https://andispnb.shop'); // Ganti dengan domain Anda
define('SITE_NAME', 'SPNB Store');

// Konfigurasi Pakasir.com API
define('PAKASIR_API_KEY', 'PAKASAIR_API_KEY');  // Ganti dengan API Key Anda
define('PAKASIR_MERCHANT_CODE', 'PAKASIR_KODE'); // Ganti dengan Merchant Code Anda
define('PAKASIR_API_URL', 'https://app.pakasir.com/api/');

// API Credentials - GANTI DENGAN CREDENTIALS ASLI ANDA!
define('ATLANTIC_API_KEY', 'YOUR_API_KEY_ATLANTIC');      // Dari dashboard Atlantic H2H
define('ATLANTIC_USERNAME', 'YOUR_USERNAME_ATLANTIC');    // Username Atlantic H2H Anda

// Atlantic H2H Settings
define('ATLANTIC_BASE_URL', 'https://atlantich2h.com/');
define('ATLANTIC_TIMEOUT', 30);
define('ATLANTIC_CALLBACK_URL', SITE_URL . '/atlantic-webhook.php');

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');  // Ganti dengan Client ID Anda
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');  // Ganti dengan Client Secret Anda
define('GOOGLE_REDIRECT_URI', SITE_URL . '/google-callback.php');


spl_autoload_register(function ($class) {
    if ($class === 'AtlanticH2H') {
        require_once __DIR__ . '/classes/AtlanticH2H.php';
    }
});

// Session Configuration
session_start();

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Koneksi Database
try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserData() {
    global $conn;
    if (!isLoggedIn()) return null;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function generateTransactionId() {
    return 'PREMISME' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
}
/**
 * Get produk Netflix dari Atlantic H2H
 */
function getAtlanticNetflixProducts() {
    require_once __DIR__ . '/classes/AtlanticH2H.php';
    
    $atlantic = new AtlanticH2H();
    $result = $atlantic->getPriceList('prabayar');
    
    if (!$result['success']) {
        return [];
    }
    
    // Filter produk Netflix
    $products = array_filter($result['data']['data'] ?? [], function($item) {
        return stripos($item['name'], 'netflix') !== false || 
               stripos($item['code'], 'ntf') !== false ||
               stripos($item['code'], 'nfx') !== false;
    });
    
    return array_values($products);
}

/**
 * Sync harga produk dari Atlantic H2H ke database
 */
function syncAtlanticPrices() {
    global $conn;
    require_once __DIR__ . '/classes/AtlanticH2H.php';
    
    $atlantic = new AtlanticH2H();
    $result = $atlantic->getPriceList('prabayar');
    
    if (!$result['success']) {
        return [
            'success' => false,
            'message' => 'Failed to get price list from Atlantic'
        ];
    }
    
    $updated = 0;
    $products = $result['data']['data'] ?? [];
    
    foreach ($products as $product) {
        $stmt = $conn->prepare("
            UPDATE products 
            SET harga = ?,
                stok = CASE WHEN ? = 'available' THEN 999 ELSE 0 END,
                updated_at = NOW()
            WHERE product_code = ?
        ");
        
        $stmt->execute([
            intval($product['price']),
            $product['status'],
            $product['code']
        ]);
        
        if ($stmt->rowCount() > 0) {
            $updated++;
        }
    }
    
    return [
        'success' => true,
        'message' => "Updated {$updated} products",
        'total_products' => count($products)
    ];
}

/**
 * Process Atlantic H2H transaction after payment
 */
function processAtlanticTransaction($orderId) {
    global $conn;
    require_once __DIR__ . '/classes/AtlanticH2H.php';
    
    try {
        // Get transaction details
        $stmt = $conn->prepare("
            SELECT t.*, p.product_code, p.nama as product_name
            FROM transactions t
            JOIN products p ON t.product_id = p.id
            WHERE t.order_id = ? AND p.tipe_produk = 'otomatis'
        ");
        $stmt->execute([$orderId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            return ['success' => false, 'message' => 'Transaction not found'];
        }
        
        // Get customer data
        $customerData = json_decode($transaction['customer_data'], true);
        $target = $customerData['device_info'] ?? $customerData['email'] ?? $customerData['phone'] ?? '';
        
        if (empty($target)) {
            return ['success' => false, 'message' => 'Customer target not found'];
        }
        
        // Check if already processed
        $stmt = $conn->prepare("
            SELECT * FROM h2h_transactions 
            WHERE order_id = ? AND status != 'pending'
        ");
        $stmt->execute([$orderId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Already processed'];
        }
        
        // Create Atlantic H2H transaction
        $atlantic = new AtlanticH2H();
        $result = $atlantic->createTransaction(
            $transaction['product_code'],
            $target,
            $orderId,
            intval($transaction['total'])
        );
        
        if ($result['success']) {
            $atlanticData = $result['data']['data'] ?? [];
            
            // Update H2H transaction
            $stmt = $conn->prepare("
                UPDATE h2h_transactions
                SET status = 'processing',
                    h2h_trx_id = ?,
                    h2h_response = ?,
                    updated_at = NOW()
                WHERE order_id = ?
            ");
            
            $stmt->execute([
                $atlanticData['id'] ?? '',
                json_encode($result['data']),
                $orderId
            ]);
            
            // Log success
            error_log("Atlantic H2H transaction created: {$orderId}");
            
            return [
                'success' => true,
                'message' => 'Atlantic transaction created',
                'atlantic_id' => $atlanticData['id'] ?? ''
            ];
        } else {
            // Update status to failed
            $stmt = $conn->prepare("
                UPDATE h2h_transactions
                SET status = 'failed',
                    h2h_response = ?
                WHERE order_id = ?
            ");
            
            $stmt->execute([
                json_encode($result['data']),
                $orderId
            ]);
            
            return [
                'success' => false,
                'message' => $result['data']['message'] ?? 'Transaction failed'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Atlantic transaction error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Check Atlantic transaction status
 */
function checkAtlanticStatus($orderId) {
    global $conn;
    require_once __DIR__ . '/classes/AtlanticH2H.php';
    
    $stmt = $conn->prepare("SELECT * FROM h2h_transactions WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $h2hTrx = $stmt->fetch();
    
    if (!$h2hTrx || empty($h2hTrx['h2h_trx_id'])) {
        return ['success' => false, 'message' => 'H2H transaction not found'];
    }
    
    $atlantic = new AtlanticH2H();
    $result = $atlantic->checkTransactionStatus($h2hTrx['h2h_trx_id'], 'prabayar');
    
    if ($result['success']) {
        $statusData = $result['data']['data'] ?? [];
        
        $status = 'pending';
        if ($statusData['status'] === 'success') {
            $status = 'success';
        } elseif ($statusData['status'] === 'failed') {
            $status = 'failed';
        }
        
        $stmt = $conn->prepare("
            UPDATE h2h_transactions
            SET status = ?,
                sn_voucher = ?,
                h2h_response = ?,
                updated_at = NOW()
            WHERE order_id = ?
        ");
        
        $stmt->execute([
            $status,
            $statusData['sn'] ?? null,
            json_encode($result['data']),
            $orderId
        ]);
        
        return [
            'success' => true,
            'status' => $status,
            'sn' => $statusData['sn'] ?? null
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to check status'];
}

/**
 * Validate webhook dari Atlantic H2H
 */
function validateAtlanticWebhook($headers) {
    $signature = '';
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'x-atl-signature') {
            $signature = $value;
            break;
        }
    }
    
    if (empty($signature)) {
        return false;
    }
    
    $expectedSignature = md5(ATLANTIC_USERNAME);
    return hash_equals($expectedSignature, $signature);
}

/**
 * Format customer data untuk Atlantic H2H
 * Untuk Netflix Sharing & Semi Private butuh format: DEVICE - LOKASI
 */
function formatAtlanticCustomerData($productCode, $customerInput) {
    // Untuk produk NTFS1 dan NTSM1 (Sharing & Semi Private)
    // Format harus: DEVICE - LOKASI
    // Contoh: Iphone 20 Pro - Bandung
    
    if (in_array($productCode, ['NTFS1', 'NTSM1'])) {
        // Validasi format
        if (strpos($customerInput, '-') === false) {
            return [
                'valid' => false,
                'message' => 'Format tidak valid. Gunakan: DEVICE - LOKASI (Contoh: Iphone 20 Pro - Bandung)'
            ];
        }
        
        return [
            'valid' => true,
            'target' => trim($customerInput)
        ];
    }
    
    // Untuk produk lain (NFX1PR - Private)
    // Bisa email atau phone
    return [
        'valid' => true,
        'target' => trim($customerInput)
    ];
}

// =============================================
// VALIDATION
// =============================================

/**
 * Validate Atlantic configuration
 */
function validateAtlanticConfig() {
    $errors = [];
    
    if (!defined('ATLANTIC_API_KEY') || 
        ATLANTIC_API_KEY === 'GANTI_DENGAN_API_KEY_ANDA' ||
        empty(ATLANTIC_API_KEY)) {
        $errors[] = 'ATLANTIC_API_KEY not configured';
    }
    
    if (!defined('ATLANTIC_USERNAME') || 
        ATLANTIC_USERNAME === 'GANTI_DENGAN_USERNAME_ANDA' ||
        empty(ATLANTIC_USERNAME)) {
        $errors[] = 'ATLANTIC_USERNAME not configured';
    }
    
    if (!empty($errors)) {
        error_log("Atlantic H2H Config Error: " . implode(', ', $errors));
        return false;
    }
    
    return true;
}

?>
