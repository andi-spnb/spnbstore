<?php
/**
 * PAKASIR PAYMENT CALLBACK - INTEGRATED WITH ATLANTIC H2H
 * 
 * File ini handle callback dari Pakasir.com setelah payment success
 * Kemudian otomatis trigger transaksi ke Atlantic H2H
 * 
 * FLOW:
 * 1. Terima callback dari Pakasir
 * 2. Validate payment
 * 3. Update transaction status
 * 4. Trigger Atlantic H2H untuk produk otomatis
 * 5. Send notification
 */

require_once 'config.php';

// Set header
header('Content-Type: application/json');

// Logging
function logPaymentCallback($message, $data = []) {
    $logFile = __DIR__ . '/logs/payment_callback_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";
    
    if (!empty($data)) {
        $logMessage .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
}

try {
    logPaymentCallback("=== PAYMENT CALLBACK RECEIVED ===");
    
    // Get callback data
    $rawInput = file_get_contents('php://input');
    logPaymentCallback("Raw Input", ['raw' => $rawInput]);
    
    // Parse data
    $callbackData = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $callbackData = $_POST;
    }
    
    if (empty($callbackData) && !empty($_GET)) {
        $callbackData = $_GET;
    }
    
    logPaymentCallback("Parsed Data", $callbackData);
    
    // Validate required fields
    $requiredFields = ['order_id', 'status', 'amount'];
    foreach ($requiredFields as $field) {
        if (!isset($callbackData[$field])) {
            throw new Exception("Missing field: {$field}");
        }
    }
    
    // Extract data
    $orderId = trim($callbackData['order_id']);
    $paymentStatus = trim($callbackData['status']);
    $amount = intval($callbackData['amount']);
    $paymentMethod = $callbackData['payment_method'] ?? '';
    
    logPaymentCallback("Processing", [
        'order_id' => $orderId,
        'status' => $paymentStatus,
        'amount' => $amount
    ]);
    
    // Validate signature (jika Pakasir pakai signature)
    if (isset($callbackData['signature'])) {
        $expectedSignature = hash('sha256', 
            $orderId . $paymentStatus . $amount . PAKASIR_API_KEY
        );
        
        if (!hash_equals($expectedSignature, $callbackData['signature'])) {
            throw new Exception("Invalid signature");
        }
    }
    
    // Get transaction
    $stmt = $conn->prepare("
        SELECT t.*, p.product_code, p.tipe_produk, p.nama as product_name,
               u.email, u.nama as user_name
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        JOIN users u ON t.user_id = u.id
        WHERE t.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        throw new Exception("Transaction not found: {$orderId}");
    }
    
    logPaymentCallback("Transaction found", [
        'product' => $transaction['product_name'],
        'type' => $transaction['tipe_produk']
    ]);
    
    // Validate amount
    if ($amount != $transaction['total']) {
        throw new Exception("Amount mismatch");
    }
    
    // Map status
    $dbStatus = 'pending';
    switch (strtolower($paymentStatus)) {
        case 'success':
        case 'paid':
        case 'completed':
            $dbStatus = 'completed';
            break;
        case 'failed':
        case 'expired':
        case 'cancelled':
            $dbStatus = 'failed';
            break;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update main transaction
        $stmt = $conn->prepare("
            UPDATE transactions
            SET status = ?,
                payment_method = ?,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE order_id = ?
        ");
        $stmt->execute([$dbStatus, $paymentMethod, $orderId]);
        
        logPaymentCallback("Transaction updated", ['status' => $dbStatus]);
        
        // Process Atlantic H2H if payment success and product is automatic
        if ($dbStatus === 'completed' && 
            $transaction['tipe_produk'] === 'otomatis' && 
            !empty($transaction['product_code'])) {
            
            logPaymentCallback("Processing Atlantic H2H");
            
            // Call function dari config.php
            $atlanticResult = processAtlanticTransaction($orderId);
            
            logPaymentCallback("Atlantic Result", $atlanticResult);
            
            if (!$atlanticResult['success']) {
                // Log error but don't rollback payment
                logPaymentCallback("Atlantic H2H Failed", [
                    'error' => $atlanticResult['message']
                ]);
                
                // Send email to admin
                $adminEmail = 'admin@' . parse_url(SITE_URL, PHP_URL_HOST);
                mail(
                    $adminEmail,
                    "Atlantic H2H Failed - Order {$orderId}",
                    "Order ID: {$orderId}\nError: {$atlanticResult['message']}\n\nPlease process manually.",
                    "From: " . SITE_NAME . " <noreply@" . parse_url(SITE_URL, PHP_URL_HOST) . ">"
                );
            }
        }
        
        // Commit
        $conn->commit();
        logPaymentCallback("Database committed");
        
        // Send email notification
        if ($dbStatus === 'completed') {
            sendPaymentSuccessEmail(
                $transaction['email'],
                $transaction['user_name'],
                $transaction['product_name'],
                $orderId,
                $amount
            );
            
            logPaymentCallback("Email sent");
        }
        
        // Response
        $response = [
            'status' => 'success',
            'message' => 'Payment callback processed',
            'order_id' => $orderId
        ];
        
        logPaymentCallback("Callback processed successfully");
        echo json_encode($response);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    logPaymentCallback("ERROR: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    http_response_code(500);
    echo json_encode($response);
}

/**
 * Send payment success email
 */
function sendPaymentSuccessEmail($email, $name, $productName, $orderId, $amount) {
    $subject = "Pembayaran Berhasil - Order #{$orderId}";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 30px; 
                text-align: center;
                border-radius: 10px 10px 0 0;
            }
            .content { 
                background: #f9fafb; 
                padding: 30px; 
                border-radius: 0 0 10px 10px;
            }
            .badge {
                background: #10b981;
                color: white;
                padding: 8px 16px;
                border-radius: 20px;
                display: inline-block;
                font-size: 14px;
                margin-bottom: 15px;
            }
            .info-box {
                background: white;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .footer {
                text-align: center;
                padding: 20px;
                color: #6b7280;
                font-size: 13px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='badge'>✓ PEMBAYARAN BERHASIL</div>
                <h1 style='margin: 0;'>Terima Kasih!</h1>
                <p style='margin: 10px 0 0 0;'>Pesanan Anda sedang diproses</p>
            </div>
            
            <div class='content'>
                <p>Halo <strong>{$name}</strong>,</p>
                <p>Pembayaran untuk <strong>{$productName}</strong> telah berhasil dikonfirmasi.</p>
                
                <div class='info-box' style='background: #fef3c7; border-left: 4px solid #f59e0b;'>
                    <p style='margin: 0; color: #92400e;'>
                        ⏳ <strong>Voucher Anda sedang diproses</strong><br>
                        <span style='font-size: 14px;'>
                            Anda akan menerima kode voucher via email dalam beberapa menit.
                            Jika lebih dari 15 menit belum diterima, silakan hubungi customer service.
                        </span>
                    </p>
                </div>
                
                <div class='info-box'>
                    <p style='margin: 0 0 10px 0;'><strong>Detail Pesanan:</strong></p>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr style='border-bottom: 1px solid #e5e7eb;'>
                            <td style='padding: 10px 0; color: #6b7280;'>Order ID:</td>
                            <td style='padding: 10px 0; text-align: right;'><strong>{$orderId}</strong></td>
                        </tr>
                        <tr style='border-bottom: 1px solid #e5e7eb;'>
                            <td style='padding: 10px 0; color: #6b7280;'>Produk:</td>
                            <td style='padding: 10px 0; text-align: right;'>{$productName}</td>
                        </tr>
                        <tr style='border-bottom: 1px solid #e5e7eb;'>
                            <td style='padding: 10px 0; color: #6b7280;'>Total:</td>
                            <td style='padding: 10px 0; text-align: right;'><strong>" . formatRupiah($amount) . "</strong></td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280;'>Tanggal:</td>
                            <td style='padding: 10px 0; text-align: right;'>" . date('d F Y, H:i') . " WIB</td>
                        </tr>
                    </table>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . SITE_URL . "/transaction-detail.php?id={$orderId}' 
                       style='display: inline-block; background: #667eea; color: white; 
                              padding: 12px 30px; text-decoration: none; border-radius: 6px;'>
                        Lihat Detail Transaksi
                    </a>
                </div>
            </div>
            
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . SITE_NAME . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@" . parse_url(SITE_URL, PHP_URL_HOST) . ">\r\n";
    
    return mail($email, $subject, $message, $headers);
}
?>