<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu'
    ]);
    exit;
}

$user = getUserData();

// Get transaction ID
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$silent = isset($_GET['silent']) ? true : false;

if ($transaction_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID transaksi tidak valid'
    ]);
    exit;
}

try {
    // Get transaction details
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $user['id']]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        echo json_encode([
            'success' => false,
            'message' => 'Transaksi tidak ditemukan'
        ]);
        exit;
    }
    
    // If payment is already completed, return current status
    if (in_array($transaction['status'], ['ready', 'proses', 'selesai', 'gagal'])) {
        echo json_encode([
            'success' => true,
            'status' => $transaction['status'],
            'message' => 'Status: ' . ucfirst($transaction['status']),
            'already_processed' => true
        ]);
        exit;
    }
    
    // If using Pakasir payment, check with Pakasir API
    if (!empty($transaction['pakasir_order_id'])) {
        // Call Pakasir Transaction Detail API
        $pakasir_url = "https://app.pakasir.com/api/transactiondetail?" . http_build_query([
            'project' => PAKASIR_MERCHANT_CODE,
            'order_id' => $transaction['pakasir_order_id'],
            'amount' => $transaction['total_harga'],
            'api_key' => PAKASIR_API_KEY
        ]);
        
        $ch = curl_init($pakasir_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log API response
        if (!$silent) {
            file_put_contents('pakasir_check.log', date('Y-m-d H:i:s') . " - " . $response . "\n", FILE_APPEND);
        }
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            
            if (isset($result['transaction'])) {
                $payment_status = $result['transaction']['status'] ?? 'pending';
                
                // Update transaction status based on Pakasir response
                if ($payment_status === 'completed' || $payment_status === 'paid' || $payment_status === 'success') {
                    // Get product details to determine status
                    $stmt = $conn->prepare("SELECT tipe_produk FROM products WHERE id = ?");
                    $stmt->execute([$transaction['product_id']]);
                    $product = $stmt->fetch();
                    
                    $new_status = $product['tipe_produk'] == 'otomatis' ? 'ready' : 'proses';
                    
                    // Start database transaction
                    $conn->beginTransaction();
                    
                    // Update transaction status
                    $stmt = $conn->prepare("UPDATE transactions 
                                           SET status = ?, 
                                               payment_completed_at = NOW(),
                                               updated_at = NOW() 
                                           WHERE id = ?");
                    $stmt->execute([$new_status, $transaction_id]);
                    
                    // Update product stock
                    $stmt = $conn->prepare("UPDATE products SET stok = stok - ? WHERE id = ?");
                    $stmt->execute([$transaction['quantity'], $transaction['product_id']]);
                    
                    $conn->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'status' => $new_status,
                        'message' => 'Pembayaran berhasil! Pesanan sedang diproses.',
                        'payment_completed' => true
                    ]);
                } elseif ($payment_status === 'expired' || $payment_status === 'cancelled' || $payment_status === 'failed') {
                    // Update to failed
                    $stmt = $conn->prepare("UPDATE transactions SET status = 'gagal', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$transaction_id]);
                    
                    echo json_encode([
                        'success' => true,
                        'status' => 'gagal',
                        'message' => 'Pembayaran gagal atau kadaluarsa.'
                    ]);
                } else {
                    // Still pending
                    echo json_encode([
                        'success' => true,
                        'status' => 'pending',
                        'message' => 'Pembayaran belum diterima. Silakan selesaikan pembayaran terlebih dahulu.',
                        'still_pending' => true
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal mendapatkan status dari Pakasir'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menghubungi gateway pembayaran'
            ]);
        }
    } else {
        // Not a Pakasir payment, return current status
        echo json_encode([
            'success' => true,
            'status' => $transaction['status'],
            'message' => 'Status: ' . ucfirst($transaction['status'])
        ]);
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log error
    file_put_contents('check_status_error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>