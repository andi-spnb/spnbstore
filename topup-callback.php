<?php
require_once 'config.php';

// Get callback data from Pakasir
$callback_data = file_get_contents('php://input');
$data = json_decode($callback_data, true);

// Log callback for debugging
file_put_contents('pakasir_callback.log', date('Y-m-d H:i:s') . " - " . $callback_data . "\n", FILE_APPEND);

if (!isset($data['order_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$order_id = $data['order_id'];
$status = $data['status']; // paid, expired, cancelled
$amount = isset($data['amount']) ? floatval($data['amount']) : 0;

// Find topup record
$stmt = $conn->prepare("SELECT * FROM topup_history WHERE pakasir_order_id = ?");
$stmt->execute([$order_id]);
$topup = $stmt->fetch();

if (!$topup) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Process based on status
if ($status === 'paid' || $status === 'success') {
    // Update topup status
    $stmt = $conn->prepare("UPDATE topup_history SET status = 'success', updated_at = NOW() WHERE pakasir_order_id = ?");
    $stmt->execute([$order_id]);
    
    // Add balance to user
    $stmt = $conn->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?");
    $stmt->execute([$topup['jumlah'], $topup['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Payment processed']);
    
} elseif ($status === 'expired' || $status === 'cancelled') {
    // Update topup status
    $stmt = $conn->prepare("UPDATE topup_history SET status = 'failed', updated_at = NOW() WHERE pakasir_order_id = ?");
    $stmt->execute([$order_id]);
    
    echo json_encode(['success' => true, 'message' => 'Payment cancelled/expired']);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown status']);
}
?>