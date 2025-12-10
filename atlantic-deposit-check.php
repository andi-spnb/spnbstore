<?php
/**
 * AJAX Endpoint - Check Deposit Status
 * Dipanggil dari halaman payment untuk cek status pembayaran
 */

require_once 'config.php';

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

header('Content-Type: application/json');

// Get parameters
$orderId = $_GET['order'] ?? '';
$phone = $_GET['phone'] ?? '';

if (empty($orderId)) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

// Get order
$stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Verify access for guest orders
if (!$order['user_id'] && !empty($phone)) {
    if (AtlanticH2H::formatPhone($phone) !== AtlanticH2H::formatPhone($order['guest_phone'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

// If still waiting payment, check with Atlantic
if ($order['status'] === 'waiting_payment' && !empty($order['deposit_id'])) {
    $atlantic = new AtlanticH2H();
    $result = $atlantic->checkDepositStatus($order['deposit_id']);
    
    if ($result['success']) {
        $depositData = $result['data']['data'] ?? [];
        $depositStatus = strtolower($depositData['status'] ?? '');
        
        // If payment successful
        if ($depositStatus === 'success' || $depositStatus === 'paid') {
            // Update order status
            $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'payment_success', paid_at = NOW() WHERE id = ?");
            $stmt->execute([$order['id']]);
            
            $order['status'] = 'payment_success';
            
            // Process to Atlantic H2H
            processAtlanticOrder($conn, $atlantic, $order);
        }
        // If expired
        elseif ($depositStatus === 'expired' || $depositStatus === 'cancel') {
            $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'expired' WHERE id = ?");
            $stmt->execute([$order['id']]);
            
            $order['status'] = 'expired';
        }
    }
}

// Return current status
echo json_encode([
    'success' => true,
    'status' => $order['status'],
    'order_id' => $order['order_id'],
    'sn' => $order['sn_voucher'] ?? null
]);

/**
 * Process order to Atlantic H2H
 */
function processAtlanticOrder($conn, $atlantic, $order) {
    try {
        // Update status to processing
        $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'processing' WHERE id = ?");
        $stmt->execute([$order['id']]);
        
        // Call Atlantic API
        $result = $atlantic->createTransaction(
            $order['product_code'], 
            $order['target'], 
            $order['order_id'], 
            $order['price_atlantic']
        );
        
        if ($result['success']) {
            $data = $result['data']['data'] ?? [];
            
            $status = strtolower($data['status'] ?? 'pending');
            $h2hTrxId = $data['id'] ?? null;
            $sn = $data['sn'] ?? null;
            
            // Map status
            $orderStatus = 'processing';
            if ($status === 'success') {
                $orderStatus = 'success';
            } elseif (in_array($status, ['failed', 'gagal', 'error'])) {
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
            
        } else {
            // API call failed - keep as processing for retry
            $stmt = $conn->prepare("UPDATE atlantic_orders SET 
                h2h_response = ?,
                status_message = ?
                WHERE id = ?");
            
            $stmt->execute([
                json_encode($result),
                $result['message'] ?? 'API call failed',
                $order['id']
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Process Atlantic Order Error: " . $e->getMessage());
    }
}
