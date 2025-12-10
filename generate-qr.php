<?php
/**
 * Generate QR Code API Endpoint
 * Endpoint untuk generate QR code image dari transaction data
 * 
 * Usage: generate-qr.php?transaction_id=123&type=qris
 */

require_once 'config.php';
require_once 'QRCodeHelper.php';

// Set proper headers
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login first.'
    ]);
    exit;
}

$user = getUserData();

// Get transaction ID
$transaction_id = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'qris';
$format = isset($_GET['format']) ? $_GET['format'] : 'base64'; // base64 or url

if ($transaction_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid transaction ID'
    ]);
    exit;
}

try {
    // Get transaction details
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $user['id']]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found'
        ]);
        exit;
    }
    
    // Get payment number
    $payment_number = $transaction['pakasir_payment_number'] ?? '';
    
    if (empty($payment_number)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Payment number not found in transaction'
        ]);
        exit;
    }
    
    // Validate QRIS format
    if ($type === 'qris' && !QRCodeHelper::validateQRIS($payment_number)) {
        // Log for debugging
        error_log("Invalid QRIS format for transaction {$transaction_id}: " . $payment_number);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid QRIS format',
            'debug' => [
                'payment_number_length' => strlen($payment_number),
                'starts_with_0002' => substr($payment_number, 0, 4) === '0002',
                'contains_5802ID' => strpos($payment_number, '5802ID') !== false
            ]
        ]);
        exit;
    }
    
    // Generate QR Code
    if ($format === 'base64') {
        // Generate as base64 data URL
        $qrImage = QRCodeHelper::generateBase64($payment_number, 300);
        
        if (!$qrImage) {
            throw new Exception('Failed to generate QR code');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'QR code generated successfully',
            'data' => [
                'qr_image' => $qrImage,
                'payment_number' => $payment_number,
                'transaction_id' => $transaction['transaction_id'],
                'total_payment' => $transaction['pakasir_total_payment'],
                'expired_at' => $transaction['pakasir_expired_at']
            ]
        ]);
        
    } elseif ($format === 'url') {
        // Generate file and return URL
        $filename = 'qr_' . $transaction['transaction_id'] . '_' . time() . '.png';
        $filepath = __DIR__ . '/uploads/qr/' . $filename;
        
        // Ensure directory exists
        $dir = __DIR__ . '/uploads/qr';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $success = QRCodeHelper::generateFile($payment_number, $filepath, 300);
        
        if (!$success) {
            throw new Exception('Failed to generate QR code file');
        }
        
        // Get base URL
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                    . "://" . $_SERVER['HTTP_HOST'] 
                    . dirname($_SERVER['REQUEST_URI']);
        
        $qr_url = $base_url . '/uploads/qr/' . $filename;
        
        echo json_encode([
            'success' => true,
            'message' => 'QR code generated successfully',
            'data' => [
                'qr_url' => $qr_url,
                'qr_filename' => $filename,
                'payment_number' => $payment_number,
                'transaction_id' => $transaction['transaction_id'],
                'total_payment' => $transaction['pakasir_total_payment'],
                'expired_at' => $transaction['pakasir_expired_at']
            ]
        ]);
        
    } elseif ($format === 'image') {
        // Return direct image output
        $qrImage = QRCodeHelper::generateBase64($payment_number, 300);
        
        if (!$qrImage) {
            throw new Exception('Failed to generate QR code');
        }
        
        // Extract base64 data
        $imageData = explode(',', $qrImage)[1];
        $imageData = base64_decode($imageData);
        
        // Set proper image headers
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: max-age=3600'); // Cache for 1 hour
        
        echo $imageData;
        exit;
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid format. Use: base64, url, or image'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Generate QR Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate QR code',
        'error' => $e->getMessage()
    ]);
}
?>