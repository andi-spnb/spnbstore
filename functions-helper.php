<?php
/**
 * Helper Functions for Admin Settings
 * Tambahkan functions ini ke file functions.php atau include file ini di config.php
 */

/**
 * Get setting value from database
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getSetting($key, $default = '') {
    global $conn;
    
    // Try to get from cache first (optional)
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        $value = $result ? $result['setting_value'] : $default;
        $cache[$key] = $value;
        
        return $value;
    } catch (Exception $e) {
        error_log("Error getting setting '$key': " . $e->getMessage());
        return $default;
    }
}

/**
 * Update or create setting value
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success status
 */
function updateSetting($key, $value) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        error_log("Error updating setting '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Get multiple settings at once
 * 
 * @param array $keys Array of setting keys
 * @return array Associative array of settings
 */
function getSettings($keys) {
    global $conn;
    
    $settings = [];
    $placeholders = str_repeat('?,', count($keys) - 1) . '?';
    
    try {
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
        $stmt->execute($keys);
        
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Error getting multiple settings: " . $e->getMessage());
    }
    
    return $settings;
}

/**
 * Check if maintenance mode is active
 * Admin users can bypass maintenance mode
 * 
 * @return bool True if maintenance mode is active
 */
function isMaintenanceMode() {
    // Admin can always access
    if (isset($_SESSION['user_id'])) {
        global $conn;
        try {
            $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user && $user['is_admin'] == 1) {
                return false;
            }
        } catch (Exception $e) {
            // Continue checking maintenance mode
        }
    }
    
    return getSetting('maintenance_mode', 0) == 1;
}

/**
 * Get all enabled payment methods
 * 
 * @return array Array of enabled payment methods
 */
function getEnabledPaymentMethods() {
    $methods = [];
    
    if (getSetting('enable_qris', 1)) {
        $methods[] = [
            'code' => 'qris',
            'name' => 'QRIS',
            'description' => 'Bayar dengan scan QR Code',
            'icon' => 'fa-qrcode',
            'type' => 'qris'
        ];
    }
    
    if (getSetting('enable_bni_va', 1)) {
        $methods[] = [
            'code' => 'bni_va',
            'name' => 'BNI Virtual Account',
            'description' => 'Transfer ke Virtual Account BNI',
            'icon' => 'fa-university',
            'type' => 'va'
        ];
    }
    
    if (getSetting('enable_bri_va', 1)) {
        $methods[] = [
            'code' => 'bri_va',
            'name' => 'BRI Virtual Account',
            'description' => 'Transfer ke Virtual Account BRI',
            'icon' => 'fa-university',
            'type' => 'va'
        ];
    }
    
    if (getSetting('enable_bca_va', 0)) {
        $methods[] = [
            'code' => 'bca_va',
            'name' => 'BCA Virtual Account',
            'description' => 'Transfer ke Virtual Account BCA',
            'icon' => 'fa-university',
            'type' => 'va'
        ];
    }
    
    if (getSetting('enable_mandiri_va', 0)) {
        $methods[] = [
            'code' => 'mandiri_va',
            'name' => 'Mandiri Virtual Account',
            'description' => 'Transfer ke Virtual Account Mandiri',
            'icon' => 'fa-university',
            'type' => 'va'
        ];
    }
    
    if (getSetting('enable_cimb_va', 0)) {
        $methods[] = [
            'code' => 'cimb_niaga_va',
            'name' => 'CIMB Niaga Virtual Account',
            'description' => 'Transfer ke Virtual Account CIMB Niaga',
            'icon' => 'fa-university',
            'type' => 'va'
        ];
    }
    
    if (getSetting('enable_permata_va', 0)) {
        $methods[] = [
            'code' => 'permata_va',
            'name' => 'Permata Virtual Account',
            'description' => 'Transfer ke Virtual Account Permata',
            'icon' => 'fa-university',
            'type' => 'va'
        ];
    }
    
    return $methods;
}

/**
 * Calculate total payment with admin fees
 * 
 * @param float $amount Original amount
 * @return array Array containing breakdown of payment
 */
function calculateTotalPayment($amount) {
    $fee_percentage = floatval(getSetting('admin_fee_percentage', 0));
    $fee_fixed = floatval(getSetting('admin_fee_fixed', 0));
    
    $fee_from_percentage = $amount * ($fee_percentage / 100);
    $total_fee = $fee_from_percentage + $fee_fixed;
    $total = $amount + $total_fee;
    
    return [
        'original_amount' => $amount,
        'fee_percentage_value' => $fee_percentage,
        'fee_percentage' => $fee_from_percentage,
        'fee_fixed' => $fee_fixed,
        'total_fee' => $total_fee,
        'total_payment' => round($total, 0) // Round to nearest integer
    ];
}

/**
 * Validate top-up amount
 * 
 * @param float $amount Amount to validate
 * @return array Array with 'valid' boolean and 'message' string
 */
function validateTopUpAmount($amount) {
    $min = floatval(getSetting('min_topup_amount', 10000));
    $max = floatval(getSetting('max_topup_amount', 10000000));
    
    if ($amount < $min) {
        return [
            'valid' => false,
            'message' => "Minimum top-up adalah " . formatRupiah($min)
        ];
    }
    
    if ($amount > $max) {
        return [
            'valid' => false,
            'message' => "Maximum top-up adalah " . formatRupiah($max)
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'Valid'
    ];
}

/**
 * Send email notification
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @return bool Success status
 */
function sendEmailNotification($to, $subject, $message) {
    // Check if email notifications are enabled
    if (!getSetting('enable_email_notifications', 0)) {
        return false;
    }
    
    // Get SMTP settings
    $smtp_host = getSetting('smtp_host', '');
    $smtp_port = getSetting('smtp_port', 587);
    $smtp_username = getSetting('smtp_username', '');
    $smtp_password = getSetting('smtp_password', '');
    $smtp_encryption = getSetting('smtp_encryption', 'tls');
    $admin_email = getSetting('admin_email', '');
    
    if (empty($smtp_host) || empty($smtp_username)) {
        return false;
    }
    
    // Use PHPMailer or similar library
    // This is a basic example - you should use a proper email library
    
    try {
        $headers = [
            'From: ' . $admin_email,
            'Reply-To: ' . $admin_email,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    } catch (Exception $e) {
        error_log("Error sending email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send WhatsApp notification
 * 
 * @param string $phone Phone number
 * @param string $message Message text
 * @return bool Success status
 */
function sendWhatsAppNotification($phone, $message) {
    // Check if WhatsApp notifications are enabled
    if (!getSetting('enable_whatsapp_notifications', 0)) {
        return false;
    }
    
    // Implement WhatsApp API integration here
    // This is a placeholder - you need to integrate with a WhatsApp API service
    
    return false;
}

/**
 * Log error to file
 * 
 * @param string $message Error message
 * @param string $type Error type (error, warning, info)
 * @return void
 */
function logError($message, $type = 'error') {
    $log_file = __DIR__ . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$type}] {$message}\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Get site information
 * 
 * @return array Array of site information
 */
function getSiteInfo() {
    return [
        'name' => getSetting('site_name', SITE_NAME),
        'description' => getSetting('site_description', ''),
        'keywords' => getSetting('site_keywords', ''),
        'url' => SITE_URL,
        'admin_email' => getSetting('admin_email', ''),
        'admin_whatsapp' => getSetting('admin_whatsapp', ''),
        'timezone' => getSetting('timezone', 'Asia/Makassar'),
        'currency_symbol' => getSetting('currency_symbol', 'Rp'),
        'date_format' => getSetting('date_format', 'd/m/Y')
    ];
}

/**
 * Get social media links
 * 
 * @return array Array of social media links
 */
function getSocialMediaLinks() {
    return [
        'facebook' => getSetting('facebook_url', ''),
        'instagram' => getSetting('instagram_url', ''),
        'twitter' => getSetting('twitter_url', ''),
        'telegram' => getSetting('telegram_url', '')
    ];
}

/**
 * Format date according to settings
 * 
 * @param string $date Date string
 * @return string Formatted date
 */
function formatDate($date) {
    $format = getSetting('date_format', 'd/m/Y');
    return date($format, strtotime($date));
}

/**
 * Format datetime according to settings
 * 
 * @param string $datetime Datetime string
 * @return string Formatted datetime
 */
function formatDateTime($datetime) {
    $format = getSetting('date_format', 'd/m/Y') . ' H:i:s';
    return date($format, strtotime($datetime));
}

/**
 * Check if a payment method is enabled
 * 
 * @param string $method Payment method code
 * @return bool True if enabled
 */
function isPaymentMethodEnabled($method) {
    $setting_key = 'enable_' . $method;
    return getSetting($setting_key, 0) == 1;
}

/**
 * Get Pakasir API credentials
 * 
 * @return array Array of API credentials
 */
function getPakasirCredentials() {
    return [
        'api_key' => getSetting('pakasir_api_key', PAKASIR_API_KEY),
        'merchant_code' => getSetting('pakasir_merchant_code', PAKASIR_MERCHANT_CODE),
        'callback_url' => getSetting('pakasir_callback_url', SITE_URL . '/payment-callback.php'),
        'expired_time' => intval(getSetting('pakasir_expired_time', 60))
    ];
}

/**
 * Auto-process transaction if enabled
 * 
 * @param int $transaction_id Transaction ID
 * @return bool Success status
 */
function autoProcessTransaction($transaction_id) {
    if (!getSetting('auto_process_transactions', 1)) {
        return false;
    }
    
    global $conn;
    
    try {
        // Get transaction details
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch();
        
        if (!$transaction || $transaction['status'] !== 'pending') {
            return false;
        }
        
        // Update transaction status to 'ready'
        $stmt = $conn->prepare("UPDATE transactions SET status = 'ready', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$transaction_id]);
        
        // Send notification
        if (getSetting('enable_email_notifications', 0)) {
            // Send email to customer
            $user_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $user_stmt->execute([$transaction['user_id']]);
            $user = $user_stmt->fetch();
            
            if ($user) {
                sendEmailNotification(
                    $user['email'],
                    'Transaksi Berhasil',
                    'Transaksi Anda dengan ID ' . $transaction['transaction_id'] . ' telah berhasil diproses.'
                );
            }
        }
        
        return true;
    } catch (Exception $e) {
        logError("Error auto-processing transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Check system health
 * 
 * @return array Array of health checks
 */
function checkSystemHealth() {
    global $conn;
    
    $health = [
        'database' => false,
        'api' => false,
        'writable' => false,
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'errors' => []
    ];
    
    // Check database connection
    try {
        $conn->query("SELECT 1");
        $health['database'] = true;
    } catch (Exception $e) {
        $health['errors'][] = 'Database connection failed';
    }
    
    // Check writable directories
    $dirs = ['uploads', 'backups', 'cache', 'logs'];
    $all_writable = true;
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_writable($dir)) {
            $all_writable = false;
            $health['errors'][] = "Directory '$dir' is not writable";
        }
    }
    $health['writable'] = $all_writable;
    
    // Check API connection (basic check)
    $api_key = getSetting('pakasir_api_key', '');
    $health['api'] = !empty($api_key);
    
    return $health;
}
?>