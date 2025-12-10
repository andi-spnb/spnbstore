<?php
/**
 * ATLANTIC WEBHOOK HANDLER
 * 
 * Handle webhook dari Atlantic H2H untuk:
 * 1. Deposit/Payment notification (deposit.success, deposit.expired)
 * 2. Transaction notification (transaction.success, transaction.failed)
 * 
 * Webhook URL: https://andispnb.shop/atlantic-webhook.php
 */

require_once 'config.php';

// Set timezone to WIB
date_default_timezone_set('Asia/Jakarta');

// Set header
header('Content-Type: application/json');

// Log function
function logWebhook($message, $data = null) {
    $logFile = __DIR__ . '/logs/atlantic_webhook_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $logMessage .= " | " . (is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $data);
    }
    
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
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

try {
    logWebhook("=== WEBHOOK RECEIVED ===");
    
    // Get raw input
    $rawInput = file_get_contents('php://input');
    logWebhook("Raw Input", ['body' => $rawInput]);
    
    // Log headers
    $headers = getallheaders();
    logWebhook("Headers", $headers);
    
    // Validate signature
    $signature = $headers['x-atl-signature'] ?? $headers['X-Atl-Signature'] ?? '';
    $expectedSignature = md5(ATLANTIC_USERNAME);
    
    if (!empty($signature) && hash_equals($expectedSignature, $signature)) {
        logWebhook("Signature validated");
    } else {
        logWebhook("Signature validation skipped or failed", [
            'received' => $signature,
            'expected' => $expectedSignature
        ]);
        // Continue anyway - some webhooks may not have signature
    }
    
    // Parse JSON data
    $webhookData = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }
    
    logWebhook("Webhook Data", $webhookData);
    
    // Extract data from NESTED structure
    // Atlantic sends: { "event": "...", "status": "...", "data": { actual data here } }
    $event = $webhookData['event'] ?? '';
    $nestedData = $webhookData['data'] ?? [];
    
    // Get important fields from NESTED data
    $atlanticTrxId = $nestedData['id'] ?? $webhookData['id'] ?? '';
    $reffId = $nestedData['reff_id'] ?? $webhookData['reff_id'] ?? '';
    $paymentStatus = strtolower($nestedData['status'] ?? $webhookData['status'] ?? '');
    $sn = $nestedData['sn'] ?? $webhookData['sn'] ?? null;
    
    logWebhook("Extracted Data", [
        'event' => $event,
        'atlantic_trx_id' => $atlanticTrxId,
        'reff_id' => $reffId,
        'payment_status' => $paymentStatus,
        'sn' => $sn
    ]);
    
    // Validate we have order reference
    if (empty($reffId) && empty($atlanticTrxId)) {
        logWebhook("MISSING REFF_ID AND TRX_ID");
        echo json_encode(['status' => true, 'message' => 'No order reference']);
        exit;
    }
    
    // ================================================
    // HANDLE TRANSACTION WEBHOOK (H2H result notification)
    // IMPORTANT: Check this FIRST before deposit webhook!
    // ================================================
    if ($event === 'transaksi' || strpos($event, 'transaction') !== false) {
        logWebhook("Processing as TRANSACTION webhook");
        
        // Find order by reff_id or h2h_trx_id
        $order = null;
        
        if (!empty($reffId)) {
            $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ?");
            $stmt->execute([$reffId]);
            $order = $stmt->fetch();
        }
        
        if (!$order && !empty($atlanticTrxId)) {
            $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE h2h_trx_id = ?");
            $stmt->execute([$atlanticTrxId]);
            $order = $stmt->fetch();
        }
        
        if (!$order) {
            logWebhook("Order not found for transaction webhook");
            echo json_encode(['status' => true, 'message' => 'Order not found']);
            exit;
        }
        
        logWebhook("Order found", [
            'order_id' => $order['order_id'],
            'current_status' => $order['status']
        ]);
        
        // Update based on transaction status
        $newStatus = $order['status']; // Keep current if unknown
        if (in_array($paymentStatus, ['success', 'sukses'])) {
            $newStatus = 'success';
        } elseif (in_array($paymentStatus, ['failed', 'gagal', 'error'])) {
            $newStatus = 'failed';
        } elseif ($paymentStatus === 'pending' || $paymentStatus === 'processing') {
            $newStatus = 'processing';
        }
        
        logWebhook("Updating order status", [
            'from' => $order['status'],
            'to' => $newStatus,
            'sn' => $sn
        ]);
        
        $stmt = $conn->prepare("UPDATE atlantic_orders SET 
            status = ?,
            h2h_trx_id = COALESCE(?, h2h_trx_id),
            sn_voucher = COALESCE(?, sn_voucher),
            h2h_response = ?,
            completed_at = ?,
            updated_at = NOW()
            WHERE id = ?");
        
        $stmt->execute([
            $newStatus,
            $atlanticTrxId,
            $sn,
            json_encode($webhookData),
            $newStatus === 'success' ? date('Y-m-d H:i:s') : null,
            $order['id']
        ]);
        
        logWebhook("Transaction status updated to: $newStatus");
        
        echo json_encode(['status' => true, 'message' => 'Transaction webhook processed']);
        logWebhook("=== WEBHOOK PROCESSED ===");
        exit;
    }
    
    // ================================================
    // HANDLE DEPOSIT WEBHOOK (Payment notification)
    // ================================================
    if (strpos($event, 'deposit') !== false || !empty($reffId)) {
        logWebhook("Processing as DEPOSIT webhook");
        
        // Find order by reff_id (order_id) or deposit_id
        $order = null;
        
        if (!empty($reffId)) {
            $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ?");
            $stmt->execute([$reffId]);
            $order = $stmt->fetch();
        }
        
        if (!$order && !empty($atlanticTrxId)) {
            $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE deposit_id = ?");
            $stmt->execute([$atlanticTrxId]);
            $order = $stmt->fetch();
        }
        
        if (!$order) {
            logWebhook("Order not found", ['reff_id' => $reffId, 'deposit_id' => $atlanticTrxId]);
            echo json_encode(['status' => true, 'message' => 'Order not found but acknowledged']);
            exit;
        }
        
        logWebhook("Order found", [
            'order_id' => $order['order_id'],
            'current_status' => $order['status']
        ]);
        
        // Only process if waiting_payment
        if ($order['status'] !== 'waiting_payment') {
            logWebhook("Order already processed, skipping");
            echo json_encode(['status' => true, 'message' => 'Already processed']);
            exit;
        }
        
        // Check payment status from NESTED data
        // Atlantic sends status in data.status, not root status
        // NOTE: "processing" means payment received but in settlement - should process H2H immediately
        if (in_array($paymentStatus, ['success', 'paid', 'sukses', 'processing'])) {
            logWebhook("Payment SUCCESS/PROCESSING - updating order (status: $paymentStatus)");
            
            // Update order status
            $stmt = $conn->prepare("UPDATE atlantic_orders SET 
                status = 'payment_success', 
                paid_at = NOW(),
                updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$order['id']]);
            
            // Process H2H to Atlantic
            logWebhook("Triggering H2H to Atlantic");
            
            $atlantic = new AtlanticH2H();
            $h2hResult = processH2H($conn, $atlantic, $order);
            
            logWebhook("H2H Result", $h2hResult);
            
        } elseif ($paymentStatus === 'expired' || $paymentStatus === 'failed' || $paymentStatus === 'cancel') {
            logWebhook("Payment EXPIRED/FAILED - updating order");
            
            $stmt = $conn->prepare("UPDATE atlantic_orders SET 
                status = 'expired',
                updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$order['id']]);
        } else {
            logWebhook("Unknown payment status: " . $paymentStatus);
        }
    }
    
    logWebhook("=== WEBHOOK PROCESSED ===");
    echo json_encode(['status' => true, 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    logWebhook("ERROR: " . $e->getMessage());
    
    // Always return 200 to prevent retry spam
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}

/**
 * Process H2H to Atlantic after payment success
 */
function processH2H($conn, $atlantic, $order) {
    $logFile = __DIR__ . '/logs/atlantic_webhook_' . date('Y-m-d') . '.log';
    
    try {
        // Update status to processing
        $stmt = $conn->prepare("UPDATE atlantic_orders SET 
            status = 'processing',
            processed_at = NOW()
            WHERE id = ?");
        $stmt->execute([$order['id']]);
        
        // Call Atlantic API
        $result = $atlantic->createTransaction(
            $order['product_code'],
            $order['target'],
            $order['order_id']
        );
        
        $logEntry = "[" . date('Y-m-d H:i:s') . "] H2H API Response | " . json_encode($result) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
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
                $order['id']
            ]);
            
            return ['success' => true, 'status' => $orderStatus, 'h2h_trx_id' => $h2hTrxId];
            
        } else {
            // API call failed
            $stmt = $conn->prepare("UPDATE atlantic_orders SET 
                h2h_response = ?,
                status = 'failed',
                status_message = ?
                WHERE id = ?");
            
            $stmt->execute([
                json_encode($result),
                $result['message'] ?? 'H2H API call failed',
                $order['id']
            ]);
            
            return ['success' => false, 'message' => $result['message'] ?? 'API failed'];
        }
        
    } catch (Exception $e) {
        // Update status to failed
        $stmt = $conn->prepare("UPDATE atlantic_orders SET 
            status = 'failed',
            status_message = ?
            WHERE id = ?");
        $stmt->execute([$e->getMessage(), $order['id']]);
        
        return ['success' => false, 'message' => $e->getMessage()];
    }
}