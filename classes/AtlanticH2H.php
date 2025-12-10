<?php
/**
 * Atlantic H2H API Class - COMPLETE VERSION
 * 
 * Endpoints:
 * - /layanan/price_list - Daftar produk/layanan
 * - /transaksi/create - Order produk H2H
 * - /transaksi/status - Cek status transaksi
 * - /deposit/create - Buat pembayaran/deposit
 * - /deposit/status - Cek status deposit
 * - /deposit/methods - Daftar metode pembayaran
 * - /balance - Cek saldo
 * 
 * Content-Type: application/x-www-form-urlencoded
 * 
 * FIXES:
 * - OVO memerlukan nomor telepon (parameter phone)
 * 
 * @version 4.1
 */

class AtlanticH2H {
    private $apiUrl;
    private $apiKey;
    private $debug = [];
    private $timeout = 30;
    
    /**
     * Constructor
     */
    public function __construct($apiKey = null, $apiUrl = null) {
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
        
        // Load from database if not provided
        if (empty($this->apiKey) || empty($this->apiUrl)) {
            $this->loadConfigFromDatabase();
        }
        
        // Fallback to constants
        if (empty($this->apiKey) && defined('ATLANTIC_API_KEY')) {
            $this->apiKey = ATLANTIC_API_KEY;
        }
        if (empty($this->apiUrl)) {
            if (defined('ATLANTIC_API_URL')) {
                $this->apiUrl = ATLANTIC_API_URL;
            } elseif (defined('ATLANTIC_BASE_URL')) {
                $this->apiUrl = ATLANTIC_BASE_URL;
            }
        }
        
        // Default URL
        if (empty($this->apiUrl)) {
            $this->apiUrl = 'https://atlantich2h.com';
        }
        
        // Remove trailing slash
        $this->apiUrl = rtrim($this->apiUrl, '/');
    }
    
    /**
     * Load config from database settings
     */
    private function loadConfigFromDatabase() {
        global $conn;
        
        if (!isset($conn)) return;
        
        try {
            $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('atlantic_api_key', 'atlantic_api_url')");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['setting_key'] === 'atlantic_api_key' && empty($this->apiKey)) {
                    $this->apiKey = $row['setting_value'];
                }
                if ($row['setting_key'] === 'atlantic_api_url' && empty($this->apiUrl)) {
                    $this->apiUrl = $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            $this->addDebug('Config load error', $e->getMessage());
        }
    }
    
    /**
     * Add debug log
     */
    private function addDebug($message, $data = null) {
        $this->debug[] = [
            'time' => date('Y-m-d H:i:s'),
            'message' => $message,
            'data' => $data
        ];
    }
    
    /**
     * Get debug log
     */
    public function getDebug() {
        return $this->debug;
    }
    
    /**
     * Clear debug log
     */
    public function clearDebug() {
        $this->debug = [];
    }
    
    /**
     * Make API request
     */
    private function request($endpoint, $params = []) {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        // Always include API key
        $params['api_key'] = $this->apiKey;
        
        $this->addDebug("API Request: POST $endpoint", [
            'url' => $url,
            'params' => array_merge($params, ['api_key' => '***' . substr($this->apiKey, -4)])
        ]);
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: SPNB-Store/1.0'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        $this->addDebug("API Response (HTTP $httpCode)", [
            'raw_response' => substr($response, 0, 1000),
            'curl_error' => $curlError
        ]);
        
        // Log to file
        $logData = date('Y-m-d H:i:s') . " | $endpoint | HTTP $httpCode | " . substr($response, 0, 500) . "\n";
        @file_put_contents(__DIR__ . '/atlantic_api.log', $logData, FILE_APPEND);
        
        // Handle curl error
        if ($curlErrno) {
            return [
                'success' => false,
                'message' => "cURL Error ($curlErrno): $curlError",
                'data' => null,
                'debug' => $this->debug
            ];
        }
        
        // Handle empty response
        if (empty($response)) {
            return [
                'success' => false,
                'message' => "Empty response from API (HTTP $httpCode)",
                'data' => null,
                'debug' => $this->debug
            ];
        }
        
        // Parse JSON response
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'JSON decode error: ' . json_last_error_msg(),
                'data' => null,
                'raw' => $response,
                'debug' => $this->debug
            ];
        }
        
        // Check API status
        $apiStatus = $data['status'] ?? false;
        
        return [
            'success' => $apiStatus === true || $apiStatus === 'true' || $apiStatus === 1,
            'message' => $data['message'] ?? ($apiStatus ? 'Success' : 'API returned status false'),
            'data' => $data,
            'debug' => $this->debug
        ];
    }
    
    // =========================================================================
    // STATIC HELPER METHODS
    // =========================================================================
    
    /**
     * Generate unique order ID
     */
    public static function generateOrderId($prefix = 'ATL') {
        return $prefix . date('ymd') . strtoupper(substr(uniqid(), -6)) . rand(10, 99);
    }
    
    /**
     * Format phone number to 08xx format
     */
    public static function formatPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 2) === '62') {
            $phone = '0' . substr($phone, 2);
        }
        if (substr($phone, 0, 1) !== '0') {
            $phone = '0' . $phone;
        }
        return $phone;
    }
    
    // =========================================================================
    // LAYANAN / PRICE LIST
    // =========================================================================
    
    /**
     * Get Price List / Daftar Layanan
     * ENDPOINT: /layanan/price_list
     */
    public function getPriceList($type = 'prabayar', $code = null) {
        $this->addDebug('Getting price list', ['type' => $type, 'code' => $code]);
        
        $params = ['type' => $type];
        
        if (!empty($code)) {
            $params['code'] = $code;
        }
        
        return $this->request('/layanan/price_list', $params);
    }
    
    /**
     * Alias untuk getPriceList
     */
    public function getServices($type = 'prabayar') {
        return $this->getPriceList($type);
    }
    
    // =========================================================================
    // TRANSAKSI H2H
    // =========================================================================
    
    /**
     * Create Transaction / Order Produk H2H
     * ENDPOINT: /transaksi/create
     * 
     * @param string $code Kode produk
     * @param string $target Tujuan (HP, ID game, dll)
     * @param string $refId Reference ID unik
     */
    public function createTransaction($code, $target, $refId = null) {
        if (empty($refId)) {
            $refId = self::generateOrderId('TRX');
        }
        
        $this->addDebug('Creating transaction', [
            'code' => $code,
            'target' => $target,
            'ref_id' => $refId
        ]);
        
        $params = [
            'code' => $code,
            'target' => $target,
            'reff_id' => $refId
        ];
        
        return $this->request('/transaksi/create', $params);
    }
    
    /**
     * Alias untuk createTransaction
     */
    public function order($code, $target, $refId = null) {
        return $this->createTransaction($code, $target, $refId);
    }
    
    /**
     * Check Transaction Status
     * ENDPOINT: /transaksi/status
     */
    public function checkTransactionStatus($trxId, $type = 'prabayar') {
        $this->addDebug('Checking transaction status', [
            'trx_id' => $trxId,
            'type' => $type
        ]);
        
        $params = [
            'trx_id' => $trxId,
            'type' => $type
        ];
        
        return $this->request('/transaksi/status', $params);
    }
    
    /**
     * Alias untuk checkTransactionStatus
     */
    public function status($trxId, $type = 'prabayar') {
        return $this->checkTransactionStatus($trxId, $type);
    }
    
    // =========================================================================
    // DEPOSIT / PAYMENT GATEWAY
    // =========================================================================
    
    /**
     * Get Payment Methods / Metode Pembayaran
     * ENDPOINT: /deposit/methods
     * 
     * @param string $type Jenis: va, ewallet, bank (optional, untuk filter)
     */
    public function getPaymentMethods($type = null) {
        $this->addDebug('Getting payment methods', ['type' => $type]);
        
        $params = [];
        if (!empty($type)) {
            $params['type'] = $type;
        }
        
        return $this->request('/deposit/methods', $params);
    }
    
    /**
     * Create Deposit / Payment
     * ENDPOINT: /deposit/create
     * 
     * Parameters dari dokumentasi:
     * - api_key (string, wajib) - Kunci API
     * - reff_id (string, wajib) - ID Unik dari sistem Anda
     * - nominal (integer, wajib) - Jumlah Deposit
     * - type (string, wajib) - Jenis deposit: va, ewallet, bank
     * - metode (string, wajib) - Metode pembayaran yang dipilih
     * - phone (string, opsional) - Nomor telepon untuk OVO
     * 
     * @param string $reffId Reference ID unik
     * @param int $nominal Jumlah deposit
     * @param string $type Jenis: va, ewallet, bank
     * @param string $metode Metode pembayaran (QRIS, OVO, DANA, BNI, BRI, dll)
     * @param string $phone Nomor telepon untuk OVO (format 08xxx)
     */
    public function createDeposit($reffId, $nominal, $type, $metode, $phone = null) {
        $this->addDebug('Creating deposit/payment', [
            'reff_id' => $reffId,
            'nominal' => $nominal,
            'type' => $type,
            'metode' => $metode,
            'phone' => $phone ? '***' . substr($phone, -4) : null
        ]);
        
        $params = [
            'reff_id' => $reffId,
            'nominal' => intval($nominal),
            'type' => $type,
            'metode' => $metode
        ];
        
        // OVO memerlukan nomor telepon
        if (strtoupper($metode) === 'OVO' && !empty($phone)) {
            $params['phone'] = self::formatPhone($phone);
        }
        
        return $this->request('/deposit/create', $params);
    }
    
    /**
     * Alias - Create Payment dengan format berbeda
     */
    public function createPayment($orderId, $amount, $paymentType, $paymentMethod, $phone = null) {
        return $this->createDeposit($orderId, $amount, $paymentType, $paymentMethod, $phone);
    }
    
    /**
     * Check Deposit/Payment Status
     * ENDPOINT: /deposit/status
     * 
     * @param string $reffId Reference ID yang digunakan saat create
     */
    public function checkDepositStatus($reffId) {
        $this->addDebug('Checking deposit status', ['reff_id' => $reffId]);
        
        $params = [
            'reff_id' => $reffId
        ];
        
        return $this->request('/deposit/status', $params);
    }
    
    /**
     * Alias untuk checkDepositStatus
     */
    public function checkPaymentStatus($reffId) {
        return $this->checkDepositStatus($reffId);
    }
    
    // =========================================================================
    // BALANCE / SALDO
    // =========================================================================
    
    /**
     * Check Balance / Saldo
     * ENDPOINT: /balance atau /saldo
     */
    public function getBalance() {
        $this->addDebug('Checking balance');
        
        $result = $this->request('/balance', []);
        
        if (!$result['success']) {
            $this->addDebug('Trying alternative endpoint /saldo');
            $result = $this->request('/saldo', []);
        }
        
        return $result;
    }
    
    // =========================================================================
    // HELPER METHODS
    // =========================================================================
    
    /**
     * Get product by code
     */
    public function getProduct($code) {
        $result = $this->getPriceList('prabayar', $code);
        
        if ($result['success'] && !empty($result['data']['data'])) {
            $products = $result['data']['data'];
            foreach ($products as $product) {
                if ($product['code'] === $code) {
                    return $product;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Search products by provider/category
     */
    public function searchProducts($type, $search) {
        $result = $this->getPriceList($type);
        
        if (!$result['success']) {
            return [];
        }
        
        $products = $result['data']['data'] ?? [];
        $search = strtolower($search);
        
        return array_filter($products, function($p) use ($search) {
            return stripos($p['provider'] ?? '', $search) !== false ||
                   stripos($p['category'] ?? '', $search) !== false ||
                   stripos($p['type'] ?? '', $search) !== false ||
                   stripos($p['name'] ?? '', $search) !== false;
        });
    }
    
    /**
     * Get unique providers from price list
     */
    public function getProviders($type = 'prabayar') {
        $result = $this->getPriceList($type);
        
        if (!$result['success']) {
            return [];
        }
        
        $products = $result['data']['data'] ?? [];
        $providers = [];
        
        foreach ($products as $p) {
            $provider = $p['provider'] ?? 'Unknown';
            if (!isset($providers[$provider])) {
                $providers[$provider] = 0;
            }
            $providers[$provider]++;
        }
        
        arsort($providers);
        return $providers;
    }
    
    /**
     * Get unique categories from price list
     */
    public function getCategories($type = 'prabayar') {
        $result = $this->getPriceList($type);
        
        if (!$result['success']) {
            return [];
        }
        
        $products = $result['data']['data'] ?? [];
        $categories = [];
        
        foreach ($products as $p) {
            $category = $p['category'] ?? 'Unknown';
            if (!isset($categories[$category])) {
                $categories[$category] = 0;
            }
            $categories[$category]++;
        }
        
        arsort($categories);
        return $categories;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        $result = $this->getPriceList('prabayar');
        
        return [
            'connected' => $result['success'],
            'message' => $result['message'],
            'product_count' => count($result['data']['data'] ?? []),
            'api_url' => $this->apiUrl,
            'api_key_set' => !empty($this->apiKey),
            'debug' => $this->debug
        ];
    }
    
    /**
     * Get API URL (for debugging)
     */
    public function getApiUrl() {
        return $this->apiUrl;
    }
    
    /**
     * Check if API key is set
     */
    public function hasApiKey() {
        return !empty($this->apiKey);
    }
}