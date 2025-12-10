<?php
/**
 * Generate QR Code for Atlantic Orders
 * Simple endpoint untuk generate QR code dari qr_string
 */

require_once 'config.php';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include QRCodeHelper
if (!file_exists(__DIR__ . '/QRCodeHelper.php')) {
    echo json_encode(['success' => false, 'message' => 'QRCodeHelper not found']);
    exit;
}
require_once 'QRCodeHelper.php';

// Get parameters
$orderId = $_GET['order'] ?? '';
$phone = $_GET['phone'] ?? '';

if (empty($orderId)) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    // Get order
    $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Get QR string from deposit_response or qr_string column
    $qrString = '';
    
    if (!empty($order['deposit_response'])) {
        $depositData = json_decode($order['deposit_response'], true);
        $qrString = $depositData['data']['qr_string'] ?? $depositData['qr_string'] ?? '';
    }
    
    if (empty($qrString) && !empty($order['qr_string'])) {
        $qrString = $order['qr_string'];
    }
    
    if (empty($qrString)) {
        // Return Atlantic's QR image URL as fallback
        $qrImageUrl = '';
        if (!empty($order['deposit_response'])) {
            $depositData = json_decode($order['deposit_response'], true);
            $qrImageUrl = $depositData['data']['qr_image'] ?? $depositData['qr_image'] ?? '';
        }
        if (empty($qrImageUrl) && !empty($order['qr_image'])) {
            $qrImageUrl = $order['qr_image'];
        }
        
        if (!empty($qrImageUrl)) {
            echo json_encode([
                'success' => true,
                'qr_image' => $qrImageUrl,
                'source' => 'atlantic_url'
            ]);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'QR string not found']);
        exit;
    }
    
    // Generate QR Code using QRCodeHelper
    $qrImage = QRCodeHelper::generateBase64($qrString, 300);
    
    if (!$qrImage) {
        // Fallback to Atlantic URL
        $qrImageUrl = $order['qr_image'] ?? '';
        if (!empty($order['deposit_response'])) {
            $depositData = json_decode($order['deposit_response'], true);
            $qrImageUrl = $depositData['data']['qr_image'] ?? $qrImageUrl;
        }
        
        if (!empty($qrImageUrl)) {
            echo json_encode([
                'success' => true,
                'qr_image' => $qrImageUrl,
                'source' => 'atlantic_fallback'
            ]);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Failed to generate QR']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'qr_image' => $qrImage,
        'source' => 'local',
        'order_id' => $order['order_id']
    ]);
    
} catch (Exception $e) {
    error_log('Generate QR Atlantic Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}