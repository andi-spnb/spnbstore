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

// Page Configuration
$active_page = 'settings';
$page_title = 'Pengaturan Website';
$page_subtitle = 'Kelola konfigurasi dan pengaturan sistem';
$page_icon = 'fas fa-cog';

$message = '';
$message_type = 'success';

// Helper function to get setting
function getSetting($key, $default = '') {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Helper function to update setting
function updateSetting($key, $value) {
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $tab = $_POST['tab'] ?? 'general';
    
    try {
        switch ($tab) {
            case 'general':
                updateSetting('site_name', trim($_POST['site_name']));
                updateSetting('site_description', trim($_POST['site_description']));
                updateSetting('site_keywords', trim($_POST['site_keywords']));
                updateSetting('admin_email', trim($_POST['admin_email']));
                updateSetting('admin_whatsapp', trim($_POST['admin_whatsapp']));
                updateSetting('timezone', $_POST['timezone']);
                updateSetting('date_format', $_POST['date_format']);
                updateSetting('currency_symbol', trim($_POST['currency_symbol']));
                updateSetting('items_per_page', intval($_POST['items_per_page']));
                break;
                
            case 'payment':
                updateSetting('pakasir_api_key', trim($_POST['pakasir_api_key']));
                updateSetting('pakasir_merchant_code', trim($_POST['pakasir_merchant_code']));
                updateSetting('pakasir_callback_url', trim($_POST['pakasir_callback_url']));
                updateSetting('pakasir_expired_time', intval($_POST['pakasir_expired_time']));
                
                // Payment methods
                updateSetting('enable_qris', isset($_POST['enable_qris']) ? '1' : '0');
                updateSetting('enable_bni_va', isset($_POST['enable_bni_va']) ? '1' : '0');
                updateSetting('enable_bri_va', isset($_POST['enable_bri_va']) ? '1' : '0');
                updateSetting('enable_bca_va', isset($_POST['enable_bca_va']) ? '1' : '0');
                updateSetting('enable_mandiri_va', isset($_POST['enable_mandiri_va']) ? '1' : '0');
                updateSetting('enable_cimb_va', isset($_POST['enable_cimb_va']) ? '1' : '0');
                updateSetting('enable_permata_va', isset($_POST['enable_permata_va']) ? '1' : '0');
                
                // Fees
                updateSetting('admin_fee_percentage', floatval($_POST['admin_fee_percentage']));
                updateSetting('admin_fee_fixed', intval($_POST['admin_fee_fixed']));
                updateSetting('min_topup_amount', intval($_POST['min_topup_amount']));
                updateSetting('max_topup_amount', intval($_POST['max_topup_amount']));
                break;
                
            case 'email':
                updateSetting('enable_email_notifications', isset($_POST['enable_email_notifications']) ? '1' : '0');
                updateSetting('smtp_host', trim($_POST['smtp_host']));
                updateSetting('smtp_port', intval($_POST['smtp_port']));
                updateSetting('smtp_username', trim($_POST['smtp_username']));
                if (!empty($_POST['smtp_password'])) {
                    updateSetting('smtp_password', trim($_POST['smtp_password']));
                }
                updateSetting('smtp_encryption', $_POST['smtp_encryption']);
                break;
                
            case 'notifications':
                updateSetting('enable_whatsapp_notifications', isset($_POST['enable_whatsapp_notifications']) ? '1' : '0');
                break;
                
            case 'maintenance':
                updateSetting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
                updateSetting('maintenance_message', trim($_POST['maintenance_message']));
                break;
                
            case 'social':
                updateSetting('facebook_url', trim($_POST['facebook_url']));
                updateSetting('instagram_url', trim($_POST['instagram_url']));
                updateSetting('twitter_url', trim($_POST['twitter_url']));
                updateSetting('telegram_url', trim($_POST['telegram_url']));
                break;
                
            case 'advanced':
                updateSetting('auto_process_transactions', isset($_POST['auto_process_transactions']) ? '1' : '0');
                updateSetting('session_lifetime', intval($_POST['session_lifetime']));
                break;
        }
        
        $message = 'Pengaturan berhasil disimpan!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get current tab
$current_tab = $_GET['tab'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            border-bottom: 2px solid var(--dark-border);
            padding-bottom: 0;
        }
        .settings-tabs a {
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: var(--text-muted);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .settings-tabs a:hover {
            color: var(--text-primary);
            background: rgba(99, 102, 241, 0.1);
        }
        .settings-tabs a.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }
        
        /* Settings Section */
        .settings-section {
            display: none;
        }
        .settings-section.active {
            display: block;
        }
        
        /* Form Groups */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
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
            transition: 0.4s;
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
            transition: 0.4s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: var(--primary-color);
        }
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        /* Payment Method Card */
        .payment-method-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--dark-bg);
            border: 1px solid var(--dark-border);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .settings-tabs {
                flex-wrap: nowrap;
                overflow-x: scroll;
            }
        }
    </style>
</head>
<body>
<?php require_once 'admin-sidebar.php'; ?>
<div class="admin-content">
        <div class="admin-content-inner">
        <div class="admin-header">
            <div class="admin-header-content">
                <h1>
                    <i class="<?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>
                </h1>
                <p><?php echo $page_subtitle; ?></p>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <div><?php echo htmlspecialchars($message); ?></div>
        </div>
        <?php endif; ?>

        <!-- Settings Tabs -->
        <div class="settings-tabs">
            <a href="?tab=general" class="<?php echo $current_tab == 'general' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Umum
            </a>
            <a href="?tab=payment" class="<?php echo $current_tab == 'payment' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Pembayaran
            </a>
            <a href="?tab=email" class="<?php echo $current_tab == 'email' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Email
            </a>
            <a href="?tab=notifications" class="<?php echo $current_tab == 'notifications' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i> Notifikasi
            </a>
            <a href="?tab=maintenance" class="<?php echo $current_tab == 'maintenance' ? 'active' : ''; ?>">
                <i class="fas fa-tools"></i> Maintenance
            </a>
            <a href="?tab=social" class="<?php echo $current_tab == 'social' ? 'active' : ''; ?>">
                <i class="fas fa-share-alt"></i> Social Media
            </a>
            <a href="?tab=advanced" class="<?php echo $current_tab == 'advanced' ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i> Advanced
            </a>
        </div>

        <!-- General Settings -->
        <div class="settings-section <?php echo $current_tab == 'general' ? 'active' : ''; ?>">
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-home"></i> Pengaturan Umum</h2>
                <form method="POST">
                    <input type="hidden" name="tab" value="general">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nama Website <span style="color: var(--danger-color);">*</span></label>
                            <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars(getSetting('site_name', 'Digital Store')); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Admin <span style="color: var(--danger-color);">*</span></label>
                            <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars(getSetting('admin_email')); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi Website</label>
                        <textarea name="site_description" class="form-control" rows="3"><?php echo htmlspecialchars(getSetting('site_description')); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Keywords (SEO)</label>
                        <input type="text" name="site_keywords" class="form-control" value="<?php echo htmlspecialchars(getSetting('site_keywords')); ?>" placeholder="keyword1, keyword2, keyword3">
                        <small style="color: var(--text-muted);">Pisahkan dengan koma</small>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">WhatsApp Admin</label>
                            <input type="text" name="admin_whatsapp" class="form-control" value="<?php echo htmlspecialchars(getSetting('admin_whatsapp')); ?>" placeholder="628123456789">
                            <small style="color: var(--text-muted);">Format: 628xxxxxxxxx</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <select name="timezone" class="form-control">
                                <option value="Asia/Jakarta" <?php echo getSetting('timezone') == 'Asia/Jakarta' ? 'selected' : ''; ?>>WIB (Jakarta)</option>
                                <option value="Asia/Makassar" <?php echo getSetting('timezone') == 'Asia/Makassar' ? 'selected' : ''; ?>>WITA (Makassar)</option>
                                <option value="Asia/Jayapura" <?php echo getSetting('timezone') == 'Asia/Jayapura' ? 'selected' : ''; ?>>WIT (Jayapura)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Format Tanggal</label>
                            <select name="date_format" class="form-control">
                                <option value="d/m/Y" <?php echo getSetting('date_format') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="Y-m-d" <?php echo getSetting('date_format') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                <option value="m/d/Y" <?php echo getSetting('date_format') == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Simbol Mata Uang</label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars(getSetting('currency_symbol', 'Rp')); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Item Per Halaman</label>
                        <input type="number" name="items_per_page" class="form-control" value="<?php echo getSetting('items_per_page', '12'); ?>" min="6" max="50">
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment Settings -->
        <div class="settings-section <?php echo $current_tab == 'payment' ? 'active' : ''; ?>">
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-credit-card"></i> Pengaturan Pembayaran</h2>
                <form method="POST">
                    <input type="hidden" name="tab" value="payment">
                    
                    <h3 style="margin-bottom: 1rem;">Pakasir.com API</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">API Key</label>
                            <input type="text" name="pakasir_api_key" class="form-control" value="<?php echo htmlspecialchars(getSetting('pakasir_api_key')); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Merchant Code</label>
                            <input type="text" name="pakasir_merchant_code" class="form-control" value="<?php echo htmlspecialchars(getSetting('pakasir_merchant_code')); ?>">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Callback URL</label>
                            <input type="url" name="pakasir_callback_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('pakasir_callback_url')); ?>" placeholder="https://yourdomain.com/payment-callback.php">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Expired Time (menit)</label>
                            <input type="number" name="pakasir_expired_time" class="form-control" value="<?php echo getSetting('pakasir_expired_time', '60'); ?>" min="10" max="1440">
                        </div>
                    </div>

                    <hr style="margin: 2rem 0; border-color: var(--dark-border);">

                    <h3 style="margin-bottom: 1rem;">Metode Pembayaran</h3>
                    
                    <div class="payment-method-card">
                        <div>
                            <strong>QRIS</strong>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Scan QR Code</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_qris" <?php echo getSetting('enable_qris') == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="payment-method-card">
                        <div>
                            <strong>BNI Virtual Account</strong>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Transfer via BNI</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_bni_va" <?php echo getSetting('enable_bni_va') == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="payment-method-card">
                        <div>
                            <strong>BRI Virtual Account</strong>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Transfer via BRI</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_bri_va" <?php echo getSetting('enable_bri_va') == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="payment-method-card">
                        <div>
                            <strong>BCA Virtual Account</strong>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Transfer via BCA</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_bca_va" <?php echo getSetting('enable_bca_va') == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="payment-method-card">
                        <div>
                            <strong>Mandiri Virtual Account</strong>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Transfer via Mandiri</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_mandiri_va" <?php echo getSetting('enable_mandiri_va') == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="payment-method-card">
                        <div>
                            <strong>CIMB Niaga Virtual Account</strong>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Transfer via CIMB</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_cimb_va" <?php echo getSetting('enable_cimb_va') == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="payment-method-card">
                        <div>
                            <strong>Permata Virtual Account</strong>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">Transfer via Permata</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_permata_va" <?php echo getSetting('enable_permata_va') == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <hr style="margin: 2rem 0; border-color: var(--dark-border);">

                    <h3 style="margin-bottom: 1rem;">Biaya & Limit</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Admin Fee (Persentase %)</label>
                            <input type="number" name="admin_fee_percentage" class="form-control" value="<?php echo getSetting('admin_fee_percentage', '0'); ?>" min="0" max="100" step="0.1">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Admin Fee (Fixed)</label>
                            <input type="number" name="admin_fee_fixed" class="form-control" value="<?php echo getSetting('admin_fee_fixed', '0'); ?>" min="0">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Min Top Up Amount</label>
                            <input type="number" name="min_topup_amount" class="form-control" value="<?php echo getSetting('min_topup_amount', '10000'); ?>" min="1000">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Max Top Up Amount</label>
                            <input type="number" name="max_topup_amount" class="form-control" value="<?php echo getSetting('max_topup_amount', '10000000'); ?>" min="10000">
                        </div>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Email Settings -->
        <div class="settings-section <?php echo $current_tab == 'email' ? 'active' : ''; ?>">
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-envelope"></i> Pengaturan Email</h2>
                <form method="POST">
                    <input type="hidden" name="tab" value="email">
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                            <label class="toggle-switch">
                                <input type="checkbox" name="enable_email_notifications" <?php echo getSetting('enable_email_notifications') == '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="font-weight: 600;">
                                <i class="fas fa-bell"></i> Aktifkan Email Notifications
                            </span>
                        </label>
                    </div>

                    <hr style="margin: 2rem 0; border-color: var(--dark-border);">

                    <h3 style="margin-bottom: 1rem;">SMTP Configuration</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_host', 'smtp.gmail.com')); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?php echo getSetting('smtp_port', '587'); ?>">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars(getSetting('smtp_username')); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" name="smtp_password" class="form-control" placeholder="••••••••">
                            <small style="color: var(--text-muted);">Kosongkan jika tidak ingin mengubah</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Encryption</label>
                        <select name="smtp_encryption" class="form-control">
                            <option value="tls" <?php echo getSetting('smtp_encryption') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo getSetting('smtp_encryption') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo getSetting('smtp_encryption') == 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                        <button type="button" onclick="testEmail()" class="btn btn-secondary">
                            <i class="fas fa-paper-plane"></i> Test Email
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notifications Settings -->
        <div class="settings-section <?php echo $current_tab == 'notifications' ? 'active' : ''; ?>">
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-bell"></i> Pengaturan Notifikasi</h2>
                <form method="POST">
                    <input type="hidden" name="tab" value="notifications">
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                            <label class="toggle-switch">
                                <input type="checkbox" name="enable_whatsapp_notifications" <?php echo getSetting('enable_whatsapp_notifications') == '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="font-weight: 600;">
                                <i class="fab fa-whatsapp"></i> Aktifkan WhatsApp Notifications
                            </span>
                        </label>
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem; margin-left: 66px;">
                            Kirim notifikasi otomatis ke WhatsApp customer saat transaksi berhasil
                        </small>
                    </div>

                    <div class="alert alert-info" style="margin-top: 1.5rem;">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Info:</strong> WhatsApp notification memerlukan integrasi dengan WhatsApp Business API atau layanan pihak ketiga.
                        </div>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Maintenance Settings -->
        <div class="settings-section <?php echo $current_tab == 'maintenance' ? 'active' : ''; ?>">
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-tools"></i> Mode Maintenance</h2>
                <form method="POST">
                    <input type="hidden" name="tab" value="maintenance">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Peringatan!</strong> Saat mode maintenance aktif, hanya admin yang bisa mengakses website. User biasa akan melihat halaman maintenance.
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                            <label class="toggle-switch">
                                <input type="checkbox" name="maintenance_mode" <?php echo getSetting('maintenance_mode') == '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="font-weight: 600;">
                                <i class="fas fa-power-off"></i> Aktifkan Mode Maintenance
                            </span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pesan Maintenance</label>
                        <textarea name="maintenance_message" class="form-control" rows="4"><?php echo htmlspecialchars(getSetting('maintenance_message', 'Website sedang dalam perbaikan. Silakan kembali lagi nanti.')); ?></textarea>
                        <small style="color: var(--text-muted);">Pesan yang akan ditampilkan kepada user</small>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Social Media Settings -->
        <div class="settings-section <?php echo $current_tab == 'social' ? 'active' : ''; ?>">
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-share-alt"></i> Social Media Links</h2>
                <form method="POST">
                    <input type="hidden" name="tab" value="social">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook URL
                        </label>
                        <input type="url" name="facebook_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('facebook_url')); ?>" placeholder="https://facebook.com/yourpage">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fab fa-instagram" style="color: #e4405f;"></i> Instagram URL
                        </label>
                        <input type="url" name="instagram_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('instagram_url')); ?>" placeholder="https://instagram.com/yourpage">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fab fa-twitter" style="color: #1da1f2;"></i> Twitter URL
                        </label>
                        <input type="url" name="twitter_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('twitter_url')); ?>" placeholder="https://twitter.com/yourpage">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fab fa-telegram" style="color: #0088cc;"></i> Telegram URL
                        </label>
                        <input type="url" name="telegram_url" class="form-control" value="<?php echo htmlspecialchars(getSetting('telegram_url')); ?>" placeholder="https://t.me/yourgroup">
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Advanced Settings -->
        <div class="settings-section <?php echo $current_tab == 'advanced' ? 'active' : ''; ?>">
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-sliders-h"></i> Advanced Settings</h2>
                <form method="POST">
                    <input type="hidden" name="tab" value="advanced">
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                            <label class="toggle-switch">
                                <input type="checkbox" name="auto_process_transactions" <?php echo getSetting('auto_process_transactions') == '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="font-weight: 600;">
                                <i class="fas fa-robot"></i> Auto Process Transactions
                            </span>
                        </label>
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem; margin-left: 66px;">
                            Proses transaksi otomatis setelah payment confirmed
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Session Lifetime (minutes)</label>
                        <input type="number" name="session_lifetime" class="form-control" value="<?php echo getSetting('session_lifetime', '1440'); ?>" min="60" max="43200">
                        <small style="color: var(--text-muted);">Durasi session login user (default: 1440 = 24 jam)</small>
                    </div>

                    <div class="alert alert-info" style="margin-top: 1.5rem;">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Hati-hati!</strong> Pengaturan advanced dapat mempengaruhi performa dan keamanan website. Ubah hanya jika Anda tahu apa yang Anda lakukan.
                        </div>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
    <script src="assets/js/main.js"></script>
    <script>
        function testEmail() {
            alert('Fitur test email sedang dalam pengembangan.\n\nPastikan konfigurasi SMTP sudah benar sebelum mengaktifkan email notifications.');
        }

        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>