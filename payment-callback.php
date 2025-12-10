<?php
/**
 * Pakasir Payment Callback/Webhook Handler - Fixed Version
 * Handles webhook from Pakasir with Atlantic H2H trigger
 */

require_once 'config.php';
require_once 'classes/Pakasir.php';

// Log incoming webhook
error_log("Pakasir Webhook Received: " . date('Y-m-d H:i:s'));

// Get headers and body
$headers = getallheaders();
$input = file_get_contents('php://input');

error_log("Headers: " . json_encode($headers));
error_log("Body: " . $input);

// Initialize Pakasir
$pakasir = new Pakasir($conn);

// Handle webhook - this will automatically validate and process
$result = $pakasir->handleWebhook();

// The handleWebhook() method already:
// 1. Validates the webhook signature
// 2. Verifies with getTransactionDetail API
// 3. Updates transaction status in database
// 4. Triggers Atlantic H2H if needed
// 5. Sends appropriate response

// If you need custom handling after webhook processing:
if ($result['success']) {
    $webhookData = $result['data'] ?? [];
    $orderId = $webhookData['order_id'] ?? '';
    $status = $webhookData['status'] ?? '';
    
    // Additional custom processing if needed
    if ($status === 'paid' || $status === 'success') {
        // Payment successful - Atlantic H2H already triggered by Pakasir class
        
        // Optional: Send additional notifications
        sendPaymentSuccessNotification($orderId);
        
    } elseif ($status === 'expired' || $status === 'failed') {
        // Payment failed
        
        // Optional: Send failure notification
        sendPaymentFailureNotification($orderId, $status);
    }
}

// Response already sent by handleWebhook()
exit();

/**
 * Send payment success notification
 */
function sendPaymentSuccessNotification($orderId) {
    global $conn;
    
    try {
        // Get transaction details
        $stmt = $conn->prepare("
            SELECT t.*, u.email, u.whatsapp, u.nama_lengkap, p.nama as product_name
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            JOIN products p ON t.product_id = p.id
            WHERE t.order_id = ? OR t.pakasir_order_id = ?
        ");
        $stmt->execute([$orderId, $orderId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) return;
        
        // Create notification in database
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0) {
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, data, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $title = "Pembayaran Berhasil";
            $message = "Pembayaran untuk {$transaction['product_name']} telah berhasil. ";
            
            // Check if it's an API product
            $stmt2 = $conn->prepare("SELECT * FROM h2h_transactions WHERE order_id = ?");
            $stmt2->execute([$orderId]);
            $h2hTrx = $stmt2->fetch();
            
            if ($h2hTrx) {
                $message .= "Pesanan Anda sedang diproses secara otomatis.";
            } else {
                $message .= "Pesanan Anda sedang diproses oleh admin.";
            }
            
            $data = json_encode([
                'order_id' => $orderId,
                'transaction_id' => $transaction['transaction_id'],
                'amount' => $transaction['total_harga']
            ]);
            
            $stmt->execute([
                $transaction['user_id'],
                'payment_success',
                $title,
                $message,
                $data
            ]);
        }
        
        // Send email notification (optional)
        if (!empty($transaction['email'])) {
            // sendEmailNotification($transaction['email'], $title, $message);
        }
        
        // Send WhatsApp notification (optional)
        if (!empty($transaction['whatsapp'])) {
            // sendWhatsAppNotification($transaction['whatsapp'], $message);
        }
        
    } catch (Exception $e) {
        error_log("Failed to send success notification: " . $e->getMessage());
    }
}

/**
 * Send payment failure notification
 */
function sendPaymentFailureNotification($orderId, $reason) {
    global $conn;
    
    try {
        // Get transaction details
        $stmt = $conn->prepare("
            SELECT t.*, u.email, u.whatsapp, u.nama_lengkap, p.nama as product_name
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            JOIN products p ON t.product_id = p.id
            WHERE t.order_id = ? OR t.pakasir_order_id = ?
        ");
        $stmt->execute([$orderId, $orderId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) return;
        
        // Create notification
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0) {
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, data, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $title = "Pembayaran Gagal";
            $message = "Pembayaran untuk {$transaction['product_name']} ";
            
            if ($reason === 'expired') {
                $message .= "telah kadaluarsa. Silakan buat pesanan baru.";
            } else {
                $message .= "gagal diproses. Silakan coba lagi.";
            }
            
            $data = json_encode([
                'order_id' => $orderId,
                'transaction_id' => $transaction['transaction_id'],
                'reason' => $reason
            ]);
            
            $stmt->execute([
                $transaction['user_id'],
                'payment_failed',
                $title,
                $message,
                $data
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Failed to send failure notification: " . $e->getMessage());
    }
}

/**
 * Alternative webhook handler if not using Pakasir class
 * (Backup method - not recommended)
 */
function manualWebhookHandler() {
    global $conn;
    
    try {
        // Parse input
        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);
        
        if (!$payload) {
            throw new Exception("Invalid payload");
        }
        
        // Extract data
        $order_id = $payload['order_id'] ?? '';
        $status = $payload['status'] ?? '';
        $amount = $payload['amount'] ?? 0;
        
        // Validate with Pakasir API
        $pakasir_url = "https://app.pakasir.com/api/transactiondetail?" . http_build_query([
            'project' => PAKASIR_MERCHANT_CODE,
            'order_id' => $order_id,
            'amount' => $amount,
            'api_key' => PAKASIR_API_KEY
        ]);
        
        $ch = curl_init($pakasir_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code != 200) {
            throw new Exception("Validation failed");
        }
        
        $validation = json_decode($response, true);
        $validated_status = $validation['transaction']['status'] ?? '';
        
        // Process based on validated status
        if ($validated_status === 'completed' || $validated_status === 'paid') {
            // Update transaction
            $stmt = $conn->prepare("
                UPDATE transactions 
                SET status = 'ready',
                    payment_completed_at = NOW()
                WHERE pakasir_order_id = ?
            ");
            $stmt->execute([$order_id]);
            
            // Trigger Atlantic H2H if needed
            triggerAtlanticIfNeeded($order_id);
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        
    } catch (Exception $e) {
        error_log("Manual webhook error: " . $e->getMessage());
        http_response_code(200);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * Trigger Atlantic H2H if product is API-based
 */
function triggerAtlanticIfNeeded($orderId) {
    global $conn;
    
    try {
        // Check if transaction has H2H record
        $stmt = $conn->prepare("
            SELECT h.*, p.product_code
            FROM h2h_transactions h
            JOIN products p ON h.product_id = p.id
            WHERE h.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $h2hTrx = $stmt->fetch();
        
        if ($h2hTrx && $h2hTrx['status'] === 'pending') {
            // Call Atlantic H2H processing
            if (function_exists('processAtlanticTransaction')) {
                $result = processAtlanticTransaction($orderId);
                error_log("Atlantic H2H triggered for order {$orderId}: " . json_encode($result));
            }
        }
        
    } catch (Exception $e) {
        error_log("Failed to trigger Atlantic: " . $e->getMessage());
    }
}