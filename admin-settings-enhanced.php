<?php
require_once 'config.php';

// Admin check
if (!isLoggedIn()) {
    redirect('login.php');
}
$user = getUserData();

if ($user['is_admin'] != 1) {
    redirect('dashboard.php');
}

$message = '';
$message_type = 'success';

// Handle Update Settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $settings = [
        // General Settings
        'site_name' => trim($_POST['site_name']),
        'site_description' => trim($_POST['site_description']),
        'site_keywords' => trim($_POST['site_keywords']),
        'admin_email' => trim($_POST['admin_email']),
        'admin_whatsapp' => trim($_POST['admin_whatsapp']),
        
        // Pakasir Settings
        'pakasir_api_key' => trim($_POST['pakasir_api_key']),
        'pakasir_merchant_code' => trim($_POST['pakasir_merchant_code']),
        'pakasir_callback_url' => trim($_POST['pakasir_callback_url']),
        'pakasir_expired_time' => intval($_POST['pakasir_expired_time']),
        
        // Payment Methods
        'enable_qris' => isset($_POST['enable_qris']) ? 1 : 0,
        'enable_bni_va' => isset($_POST['enable_bni_va']) ? 1 : 0,
        'enable_bri_va' => isset($_POST['enable_bri_va']) ? 1 : 0,
        'enable_bca_va' => isset($_POST['enable_bca_va']) ? 1 : 0,
        'enable_mandiri_va' => isset($_POST['enable_mandiri_va']) ? 1 : 0,
        'enable_cimb_va' => isset($_POST['enable_cimb_va']) ? 1 : 0,
        'enable_permata_va' => isset($_POST['enable_permata_va']) ? 1 : 0,
        
        // Commission & Fees
        'admin_fee_percentage' => floatval($_POST['admin_fee_percentage']),
        'admin_fee_fixed' => floatval($_POST['admin_fee_fixed']),
        'min_topup_amount' => floatval($_POST['min_topup_amount']),
        'max_topup_amount' => floatval($_POST['max_topup_amount']),
        
        // Notification Settings
        'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 1 : 0,
        'enable_whatsapp_notifications' => isset($_POST['enable_whatsapp_notifications']) ? 1 : 0,
        'smtp_host' => trim($_POST['smtp_host']),
        'smtp_port' => intval($_POST['smtp_port']),
        'smtp_username' => trim($_POST['smtp_username']),
        'smtp_password' => trim($_POST['smtp_password']),
        'smtp_encryption' => trim($_POST['smtp_encryption']),
        
        // System Settings
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
        'maintenance_message' => trim($_POST['maintenance_message']),
        'auto_process_transactions' => isset($_POST['auto_process_transactions']) ? 1 : 0,
        'session_lifetime' => intval($_POST['session_lifetime']),
        
        // Social Media
        'facebook_url' => trim($_POST['facebook_url']),
        'instagram_url' => trim($_POST['instagram_url']),
        'twitter_url' => trim($_POST['twitter_url']),
        'telegram_url' => trim($_POST['telegram_url']),
        
        // Display Settings
        'items_per_page' => intval($_POST['items_per_page']),
        'currency_symbol' => trim($_POST['currency_symbol']),
        'date_format' => trim($_POST['date_format']),
        'timezone' => trim($_POST['timezone'])
    ];
    
    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    
    $message = 'Pengaturan berhasil disimpan!';
    $message_type = 'success';
}

// Handle Test API
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_api'])) {
    // Test Pakasir API Connection
    $test_result = testPakasirAPI();
    $message = $test_result['message'];
    $message_type = $test_result['status'];
}

// Get current settings with defaults
function getSetting($key, $default = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

// Test Pakasir API function
function testPakasirAPI() {
    $api_key = getSetting('pakasir_api_key', PAKASIR_API_KEY);
    
    if (empty($api_key)) {
        return ['status' => 'danger', 'message' => 'API Key belum diatur!'];
    }
    
    // Try to ping Pakasir API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://app.pakasir.com/api/status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        return ['status' => 'success', 'message' => '✅ Koneksi API berhasil! API Key valid.'];
    } else {
        return ['status' => 'danger', 'message' => '❌ Koneksi API gagal! Periksa API Key Anda.'];
    }
}

// Get website statistics
$stmt = $conn->query("SELECT COUNT(*) as count FROM users");
$total_users = $stmt->fetch()['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
$total_products = $stmt->fetch()['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM transactions");
$total_transactions = $stmt->fetch()['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM categories");
$total_categories = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Website - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS untuk mendukung Sidebar Responsif */
        :root {
            --primary-color: #6366f1; /* Sesuaikan dengan style.css jika berbeda */
        }
        
        .main-content {
            transition: all 0.3s ease;
            padding: 2rem;
            min-height: 100vh;
            background: #0f172a; /* Warna background dark */
            color: #f1f5f9;
        }

        @media (min-width: 992px) {
            .main-content {
                margin-left: 280px; /* Space untuk sidebar */
            }
            .mobile-header {
                display: none !important; /* Sembunyikan header mobile di desktop */
            }
        }

        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
                padding-top: 80px; /* Space untuk header mobile */
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* End of Sidebar CSS Support */

        .admin-nav {
            background: rgba(99, 102, 241, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .admin-nav a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
        }
        .admin-nav a:hover, .admin-nav a.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--dark-border);
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            -webkit-overflow-scrolling: touch;
        }
        .tab-btn {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .tab-btn:hover {
            color: var(--primary-color);
        }
        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Settings Section */
        .settings-section {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .settings-section h3 {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--dark-border);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--dark-border);
            transition: 0.3s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: var(--primary-color);
        }
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        /* Setting Item */
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--dark-bg);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap; /* Agar responsif di HP */
            gap: 1rem;
        }
        .setting-info {
            flex: 1;
            min-width: 200px;
        }
        .setting-info h4 {
            margin: 0 0 0.25rem 0;
            font-size: 0.95rem;
        }
        .setting-info p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        /* Alert Box */
        .alert-box {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            color: #3b82f6;
        }
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
            color: #f59e0b;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10b981;
            color: #10b981;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
            color: #ef4444;
        }
        
        /* Responsive Grid fixes */
        .grid-4 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .settings-section { padding: 1rem; }
            .tab-btn { padding: 0.75rem 1rem; font-size: 0.85rem; }
            .sticky-footer {
                flex-direction: column;
                margin: 0 -1rem -1rem -1rem !important;
                border-radius: 0 0 1rem 1rem;
            }
            .sticky-footer button, .sticky-footer a {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar / Sidebar -->
    <?php require_once 'admin-sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <main class="main-content">
        <div class="container">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.8rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-cog" style="color: var(--primary-color);"></i> Pengaturan Website
                </h1>
            </div>

            <?php if ($message): ?>
            <div class="alert-box alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <div><?php echo $message; ?></div>
            </div>
            <?php endif; ?>

            <!-- Website Statistics -->
            <div class="card" style="margin-bottom: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">
                    <i class="fas fa-chart-bar"></i> Statistik Website
                </h2>
                <div class="grid-4">
                    <div style="text-align: center; padding: 1.5rem; background: var(--dark-bg); border-radius: 0.75rem;">
                        <div style="font-size: 3rem; margin-bottom: 0.5rem;">👥</div>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?php echo $total_users; ?></div>
                        <div style="color: var(--text-muted); margin-top: 0.5rem;">Total Users</div>
                    </div>
                    <div style="text-align: center; padding: 1.5rem; background: var(--dark-bg); border-radius: 0.75rem;">
                        <div style="font-size: 3rem; margin-bottom: 0.5rem;">📦</div>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?php echo $total_products; ?></div>
                        <div style="color: var(--text-muted); margin-top: 0.5rem;">Produk Aktif</div>
                    </div>
                    <div style="text-align: center; padding: 1.5rem; background: var(--dark-bg); border-radius: 0.75rem;">
                        <div style="font-size: 3rem; margin-bottom: 0.5rem;">🧾</div>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);"><?php echo $total_transactions; ?></div>
                        <div style="color: var(--text-muted); margin-top: 0.5rem;">Total Transaksi</div>
                    </div>
                    <div style="text-align: center; padding: 1.5rem; background: var(--dark-bg); border-radius: 0.75rem;">
                        <div style="font-size: 3rem; margin-bottom: 0.5rem;">🏷️</div>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--danger-color);"><?php echo $total_categories; ?></div>
                        <div style="color: var(--text-muted); margin-top: 0.5rem;">Kategori</div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="card">
                <div class="tab-navigation">
                    <button type="button" class="tab-btn active" onclick="openTab(event, 'general')">
                        <i class="fas fa-globe"></i> Umum
                    </button>
                    <button type="button" class="tab-btn" onclick="openTab(event, 'payment')">
                        <i class="fas fa-credit-card"></i> Payment Gateway
                    </button>
                    <button type="button" class="tab-btn" onclick="openTab(event, 'payment-methods')">
                        <i class="fas fa-money-bill-wave"></i> Metode Pembayaran
                    </button>
                    <button type="button" class="tab-btn" onclick="openTab(event, 'fees')">
                        <i class="fas fa-percent"></i> Biaya & Komisi
                    </button>
                    <button type="button" class="tab-btn" onclick="openTab(event, 'notifications')">
                        <i class="fas fa-bell"></i> Notifikasi
                    </button>
                    <button type="button" class="tab-btn" onclick="openTab(event, 'system')">
                        <i class="fas fa-server"></i> Sistem
                    </button>
                    <button type="button" class="tab-btn" onclick="openTab(event, 'social')">
                        <i class="fas fa-share-alt"></i> Sosial Media
                    </button>
                    <button type="button" class="tab-btn" onclick="openTab(event, 'advanced')">
                        <i class="fas fa-tools"></i> Advanced
                    </button>
                </div>

                <!-- Settings Form -->
                <form method="POST">
                    
                    <!-- General Settings Tab -->
                    <div id="general" class="tab-content active">
                        <div class="settings-section">
                            <h3><i class="fas fa-info-circle"></i> Informasi Website</h3>
                            
                            <div class="form-group">
                                <label class="form-label">Nama Website</label>
                                <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars(getSetting('site_name', SITE_NAME)); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Deskripsi Website</label>
                                <textarea name="site_description" class="form-control" rows="3"><?php echo htmlspecialchars(getSetting('site_description', '')); ?></textarea>
                                <small style="color: var(--text-muted); font-size: 0.85rem;">Deskripsi singkat tentang website Anda (untuk SEO)</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Keywords (SEO)</label>
                                <input type="text" name="site_keywords" class="form-control" value="<?php echo htmlspecialchars(getSetting('site_keywords', '')); ?>" placeholder="digital product, game, voucher">
                                <small style="color: var(--text-muted); font-size: 0.85rem;">Pisahkan dengan koma</small>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-envelope"></i> Kontak Admin</h3>
                            
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Email Admin</label>
                                    <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars(getSetting('admin_email', $user['email'])); ?>" placeholder="admin@example.com">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">WhatsApp Admin</label>
                                    <input type="text" name="admin_whatsapp" class="form-control" value="<?php echo htmlspecialchars(getSetting('admin_whatsapp', '')); ?>" placeholder="628xxxxxxxxxx">
                                    <small style="color: var(--text-muted); font-size: 0.85rem;">Format: 628xxxxxxxxxx (tanpa +)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Gateway Tab -->
                    <div id="payment" class="tab-content">
                        <div class="alert-box alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Informasi Pakasir.com</strong><br>
                                Daftar di <a href="https://pakasir.com" target="_blank" style="color: inherit; text-decoration: underline;">pakasir.com</a> untuk mendapatkan API Key dan Merchant Code Anda.
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-key"></i> API Configuration</h3>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    API Key 
                                    <a href="https://pakasir.com/dashboard/settings/api" target="_blank" style="color: var(--primary-color); text-decoration: none; font-size: 0.85rem;">
                                        <i class="fas fa-external-link-alt"></i> Dapatkan API Key
                                    </a>
                                </label>
                                <input type="text" name="pakasir_api_key" class="form-control" value="<?php echo htmlspecialchars(getSetting('pakasir_api_key', PAKASIR_API_KEY)); ?>" placeholder="pak_live_xxxxxxxxxxxxxxxx">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Merchant Code</label>
                                <input type="text" name="pakasir_merchant_code" class="form-control" value="<?php echo htmlspecialchars(getSetting('pakasir_merchant_code', PAKASIR_MERCHANT_CODE)); ?>" placeholder="MERCHANT123">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Callback URL</label>
                                <input type="url" name="pakasir_callback_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('pakasir_callback_url', SITE_URL . '/payment-callback.php')); ?>" readonly>
                                <small style="color: var(--text-muted); font-size: 0.85rem;">Salin URL ini ke dashboard Pakasir Anda</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Waktu Expired Pembayaran (menit)</label>
                                <input type="number" name="pakasir_expired_time" class="form-control" value="<?php echo htmlspecialchars(getSetting('pakasir_expired_time', 60)); ?>" min="10" max="1440">
                                <small style="color: var(--text-muted); font-size: 0.85rem;">Rentang: 10 - 1440 menit (1 hari)</small>
                            </div>
                            
                            <div style="margin-top: 1.5rem;">
                                <button type="submit" name="test_api" class="btn btn-secondary">
                                    <i class="fas fa-flask"></i> Test Koneksi API
                                </button>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-book"></i> Panduan Setup</h3>
                            <ol style="color: var(--text-muted); line-height: 1.8;">
                                <li>Daftar akun di <a href="https://pakasir.com" target="_blank" style="color: var(--primary-color);">pakasir.com</a></li>
                                <li>Login ke dashboard Pakasir</li>
                                <li>Buka menu <strong>Settings → API Keys</strong></li>
                                <li>Copy <strong>API Key</strong> dan <strong>Merchant Code</strong></li>
                                <li>Paste di form di atas</li>
                                <li>Copy <strong>Callback URL</strong> dan tambahkan ke dashboard Pakasir</li>
                                <li>Klik <strong>Test Koneksi API</strong> untuk memastikan konfigurasi benar</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Payment Methods Tab -->
                    <div id="payment-methods" class="tab-content">
                        <div class="alert-box alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Aktifkan Metode Pembayaran</strong><br>
                                Pilih metode pembayaran yang tersedia untuk pelanggan Anda.
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-qrcode"></i> QRIS</h3>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>QRIS (Quick Response Code Indonesian Standard)</h4>
                                    <p>Pembayaran menggunakan QR Code yang bisa di-scan dari aplikasi e-wallet manapun</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_qris" value="1" <?php echo getSetting('enable_qris', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-university"></i> Virtual Account</h3>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>BNI Virtual Account</h4>
                                    <p>Nomor rekening virtual BNI untuk transfer bank</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_bni_va" value="1" <?php echo getSetting('enable_bni_va', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>BRI Virtual Account</h4>
                                    <p>Nomor rekening virtual BRI untuk transfer bank</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_bri_va" value="1" <?php echo getSetting('enable_bri_va', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>BCA Virtual Account</h4>
                                    <p>Nomor rekening virtual BCA untuk transfer bank</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_bca_va" value="1" <?php echo getSetting('enable_bca_va', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Mandiri Virtual Account</h4>
                                    <p>Nomor rekening virtual Mandiri untuk transfer bank</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_mandiri_va" value="1" <?php echo getSetting('enable_mandiri_va', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>CIMB Niaga Virtual Account</h4>
                                    <p>Nomor rekening virtual CIMB Niaga untuk transfer bank</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_cimb_va" value="1" <?php echo getSetting('enable_cimb_va', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Permata Virtual Account</h4>
                                    <p>Nomor rekening virtual Permata untuk transfer bank</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_permata_va" value="1" <?php echo getSetting('enable_permata_va', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Fees & Commission Tab -->
                    <div id="fees" class="tab-content">
                        <div class="alert-box alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Perhatian!</strong><br>
                                Biaya admin akan ditambahkan ke total pembayaran pelanggan.
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-calculator"></i> Biaya Admin</h3>
                            
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Biaya Persentase (%)</label>
                                    <input type="number" name="admin_fee_percentage" class="form-control" value="<?php echo htmlspecialchars(getSetting('admin_fee_percentage', 0)); ?>" min="0" max="100" step="0.1">
                                    <small style="color: var(--text-muted); font-size: 0.85rem;">Contoh: 2.5 untuk 2.5%</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Biaya Tetap (Rp)</label>
                                    <input type="number" name="admin_fee_fixed" class="form-control" value="<?php echo htmlspecialchars(getSetting('admin_fee_fixed', 0)); ?>" min="0" step="100">
                                    <small style="color: var(--text-muted); font-size: 0.85rem;">Contoh: 1000 untuk Rp 1.000</small>
                                </div>
                            </div>

                            <div class="alert-box alert-info" style="margin-top: 1rem;">
                                <i class="fas fa-calculator"></i>
                                <div>
                                    <strong>Contoh Perhitungan:</strong><br>
                                    Harga Produk: Rp 100.000<br>
                                    Biaya Persentase (2.5%): Rp 2.500<br>
                                    Biaya Tetap: Rp 1.000<br>
                                    <strong>Total Dibayar: Rp 103.500</strong>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-money-bill-wave"></i> Limit Top Up</h3>
                            
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Minimum Top Up (Rp)</label>
                                    <input type="number" name="min_topup_amount" class="form-control" value="<?php echo htmlspecialchars(getSetting('min_topup_amount', 10000)); ?>" min="0" step="1000">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Maximum Top Up (Rp)</label>
                                    <input type="number" name="max_topup_amount" class="form-control" value="<?php echo htmlspecialchars(getSetting('max_topup_amount', 10000000)); ?>" min="0" step="1000">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications Tab -->
                    <div id="notifications" class="tab-content">
                        <div class="settings-section">
                            <h3><i class="fas fa-bell"></i> Pengaturan Notifikasi</h3>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Email Notifications</h4>
                                    <p>Kirim notifikasi via email untuk transaksi dan aktivitas penting</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_email_notifications" value="1" <?php echo getSetting('enable_email_notifications', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>WhatsApp Notifications</h4>
                                    <p>Kirim notifikasi via WhatsApp untuk update transaksi</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_whatsapp_notifications" value="1" <?php echo getSetting('enable_whatsapp_notifications', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-envelope"></i> SMTP Configuration</h3>
                            
                            <div class="alert-box alert-info">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    Konfigurasi SMTP untuk mengirim email notifikasi. Gunakan Gmail, SendGrid, atau SMTP provider lainnya.
                                </div>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_host', '')); ?>" placeholder="smtp.gmail.com">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_port', 587)); ?>" placeholder="587">
                                </div>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_username', '')); ?>" placeholder="your-email@gmail.com">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_password', '')); ?>" placeholder="••••••••">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Encryption</label>
                                <select name="smtp_encryption" class="form-control">
                                    <option value="tls" <?php echo getSetting('smtp_encryption', 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo getSetting('smtp_encryption', 'tls') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- System Tab -->
                    <div id="system" class="tab-content">
                        <div class="settings-section">
                            <h3><i class="fas fa-shield-alt"></i> Pengaturan Sistem</h3>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Maintenance Mode</h4>
                                    <p>Aktifkan untuk menutup sementara akses website ke publik</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maintenance_mode" value="1" <?php echo getSetting('maintenance_mode', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Pesan Maintenance</label>
                                <textarea name="maintenance_message" class="form-control" rows="3"><?php echo htmlspecialchars(getSetting('maintenance_message', 'Website sedang dalam perbaikan. Silakan kembali lagi nanti.')); ?></textarea>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Auto Process Transactions</h4>
                                    <p>Otomatis proses transaksi yang sudah dibayar tanpa verifikasi manual</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="auto_process_transactions" value="1" <?php echo getSetting('auto_process_transactions', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Session Lifetime (menit)</label>
                                <input type="number" name="session_lifetime" class="form-control" value="<?php echo htmlspecialchars(getSetting('session_lifetime', 1440)); ?>" min="30" max="10080">
                                <small style="color: var(--text-muted); font-size: 0.85rem;">Berapa lama user tetap login (default: 1440 menit = 1 hari)</small>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-server"></i> Informasi Server</h3>
                            
                            <div class="grid-2">
                                <div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">PHP Version</div>
                                    <div style="font-weight: 600; font-size: 1.1rem;"><?php echo phpversion(); ?></div>
                                </div>
                                
                                <div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Database</div>
                                    <div style="font-weight: 600; font-size: 1.1rem;"><?php echo DB_NAME; ?></div>
                                </div>
                                
                                <div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Server Software</div>
                                    <div style="font-weight: 600; font-size: 1.1rem;"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                                </div>
                                
                                <div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Max Upload Size</div>
                                    <div style="font-weight: 600; font-size: 1.1rem;"><?php echo ini_get('upload_max_filesize'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media Tab -->
                    <div id="social" class="tab-content">
                        <div class="settings-section">
                            <h3><i class="fas fa-share-alt"></i> Link Sosial Media</h3>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook
                                </label>
                                <input type="url" name="facebook_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('facebook_url', '')); ?>" placeholder="https://facebook.com/username">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fab fa-instagram" style="color: #e4405f;"></i> Instagram
                                </label>
                                <input type="url" name="instagram_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('instagram_url', '')); ?>" placeholder="https://instagram.com/username">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fab fa-twitter" style="color: #1da1f2;"></i> Twitter / X
                                </label>
                                <input type="url" name="twitter_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('twitter_url', '')); ?>" placeholder="https://twitter.com/username">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fab fa-telegram" style="color: #0088cc;"></i> Telegram
                                </label>
                                <input type="url" name="telegram_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('telegram_url', '')); ?>" placeholder="https://t.me/username">
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Tab -->
                    <div id="advanced" class="tab-content">
                        <div class="settings-section">
                            <h3><i class="fas fa-paint-brush"></i> Tampilan</h3>
                            
                            <div class="grid-3">
                                <div class="form-group">
                                    <label class="form-label">Items Per Page</label>
                                    <input type="number" name="items_per_page" class="form-control" value="<?php echo htmlspecialchars(getSetting('items_per_page', 12)); ?>" min="6" max="100">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars(getSetting('currency_symbol', 'Rp')); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Date Format</label>
                                    <select name="date_format" class="form-control">
                                        <option value="d/m/Y" <?php echo getSetting('date_format', 'd/m/Y') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="m/d/Y" <?php echo getSetting('date_format', 'd/m/Y') == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="Y-m-d" <?php echo getSetting('date_format', 'd/m/Y') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Timezone</label>
                                <select name="timezone" class="form-control">
                                    <option value="Asia/Jakarta" <?php echo getSetting('timezone', 'Asia/Makassar') == 'Asia/Jakarta' ? 'selected' : ''; ?>>Asia/Jakarta (WIB)</option>
                                    <option value="Asia/Makassar" <?php echo getSetting('timezone', 'Asia/Makassar') == 'Asia/Makassar' ? 'selected' : ''; ?>>Asia/Makassar (WITA)</option>
                                    <option value="Asia/Jayapura" <?php echo getSetting('timezone', 'Asia/Makassar') == 'Asia/Jayapura' ? 'selected' : ''; ?>>Asia/Jayapura (WIT)</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-database"></i> Database & Backup</h3>
                            
                            <div class="alert-box alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>Penting!</strong><br>
                                    <ul style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                                        <li>Backup database secara rutin (minimal 1x seminggu)</li>
                                        <li>Backup via phpMyAdmin → Export</li>
                                        <li>Simpan file backup di tempat aman</li>
                                        <li>Test restore backup secara berkala</li>
                                    </ul>
                                </div>
                            </div>

                            <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                                <a href="backup-database.php" class="btn btn-secondary">
                                    <i class="fas fa-download"></i> Download Backup
                                </a>
                                <button type="button" class="btn btn-secondary" onclick="clearCache()">
                                    <i class="fas fa-trash"></i> Clear Cache
                                </button>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3><i class="fas fa-bug"></i> Debug Mode</h3>
                            
                            <div class="alert-box alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>Peringatan!</strong><br>
                                    Jangan aktifkan debug mode di production. Hanya gunakan untuk development.
                                </div>
                            </div>

                            <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                                <a href="error-log.php" class="btn btn-secondary">
                                    <i class="fas fa-file-alt"></i> View Error Log
                                </a>
                                <button type="button" class="btn btn-secondary" onclick="clearErrorLog()">
                                    <i class="fas fa-eraser"></i> Clear Error Log
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button (Fixed at bottom of form card) -->
                    <div class="sticky-footer" style="display: flex; gap: 1rem; padding-top: 2rem; border-top: 1px solid var(--dark-border); margin-top: 2rem; background: var(--dark-card); padding: 1.5rem; border-radius: 0 0 1rem 1rem; margin-left: -2rem; margin-right: -2rem; margin-bottom: -2rem;">
                        <button type="submit" name="update_settings" class="btn btn-primary" style="padding: 1rem 2rem;">
                            <i class="fas fa-save"></i> Simpan Semua Pengaturan
                        </button>
                        <a href="admin.php" class="btn btn-secondary" style="padding: 1rem 2rem; text-decoration: none;">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Links -->
            <div class="card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem;">
                    <i class="fas fa-link"></i> Quick Links
                </h2>
                <div class="grid-4">
                    <a href="admin-products.php" class="btn btn-secondary" style="padding: 1.5rem; text-decoration: none; text-align: center;">
                        <i class="fas fa-box" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                        <span>Kelola Produk</span>
                    </a>
                    <a href="admin-categories.php" class="btn btn-secondary" style="padding: 1.5rem; text-decoration: none; text-align: center;">
                        <i class="fas fa-tags" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                        <span>Kelola Kategori</span>
                    </a>
                    <a href="admin-users.php" class="btn btn-secondary" style="padding: 1.5rem; text-decoration: none; text-align: center;">
                        <i class="fas fa-users" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                        <span>Kelola Users</span>
                    </a>
                    <a href="admin-transactions.php" class="btn btn-secondary" style="padding: 1.5rem; text-decoration: none; text-align: center;">
                        <i class="fas fa-receipt" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                        <span>Lihat Transaksi</span>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/main.js"></script>
    <script>
        // Tab Navigation
        function openTab(evt, tabName) {
            const tabContent = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContent.length; i++) {
                tabContent[i].classList.remove("active");
            }
            
            const tabBtn = document.getElementsByClassName("tab-btn");
            for (let i = 0; i < tabBtn.length; i++) {
                tabBtn[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
            
            // Save active tab to localStorage
            localStorage.setItem('activeSettingsTab', tabName);
        }

        // Restore active tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = localStorage.getItem('activeSettingsTab');
            if (activeTab) {
                const tabBtn = Array.from(document.getElementsByClassName('tab-btn')).find(
                    btn => btn.textContent.toLowerCase().includes(activeTab.toLowerCase()) || 
                           btn.onclick.toString().includes(activeTab)
                );
                if (tabBtn) {
                    tabBtn.click();
                }
            }
        });

        // Clear Cache
        function clearCache() {
            if (confirm('Apakah Anda yakin ingin clear cache?')) {
                fetch('clear-cache.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                    })
                    .catch(error => {
                        alert('Error: ' + error);
                    });
            }
        }

        // Clear Error Log
        function clearErrorLog() {
            if (confirm('Apakah Anda yakin ingin clear error log?')) {
                fetch('clear-error-log.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                    })
                    .catch(error => {
                        alert('Error: ' + error);
                    });
            }
        }

        // Auto-save form on change (optional)
        const formElements = document.querySelectorAll('input, select, textarea');
        formElements.forEach(element => {
            element.addEventListener('change', function() {
                // Add visual indicator that settings have changed
                const saveBtn = document.querySelector('button[name="update_settings"]');
                saveBtn.style.animation = 'pulse 0.5s';
                setTimeout(() => {
                    saveBtn.style.animation = '';
                }, 500);
            });
        });
    </script>
</body>
</html>