<?php
/**
 * HELPER FUNCTIONS - Top Up Game System
 * Upload file ini ke server bersama file topup-game lainnya
 * 
 * File ini menyediakan fungsi yang tidak ada di config.php:
 * - getSetting()
 * - isAdmin()
 * - getSiteName()
 * - tableExists()
 */

// Helper function: getSetting - ambil setting dari database
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        global $conn;
        try {
            // Cek apakah tabel settings ada
            $check = $conn->query("SHOW TABLES LIKE 'settings'");
            if ($check->rowCount() === 0) {
                return $default;
            }
            
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// Helper function: isAdmin - cek apakah user adalah admin
if (!function_exists('isAdmin')) {
    function isAdmin() {
        if (!function_exists('isLoggedIn') || !isLoggedIn()) {
            return false;
        }
        
        $user = function_exists('getUserData') ? getUserData() : null;
        if (!$user) return false;
        
        // Cek berbagai kemungkinan field untuk admin
        if (isset($user['role']) && $user['role'] === 'admin') return true;
        if (isset($user['is_admin']) && $user['is_admin'] == 1) return true;
        if (isset($user['level']) && $user['level'] === 'admin') return true;
        if (isset($user['user_type']) && $user['user_type'] === 'admin') return true;
        
        return false;
    }
}

// Helper function: getSiteName - ambil nama website
if (!function_exists('getSiteName')) {
    function getSiteName() {
        // Prioritas: constant SITE_NAME > setting > default
        if (defined('SITE_NAME')) {
            return SITE_NAME;
        }
        return getSetting('site_name', 'SPNB Store');
    }
}

// Helper function: tableExists - cek apakah tabel ada
if (!function_exists('tableExists')) {
    function tableExists($tableName) {
        global $conn;
        try {
            $result = $conn->query("SHOW TABLES LIKE '$tableName'");
            return $result->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Helper function: formatPhone - format nomor telepon
if (!function_exists('formatPhone')) {
    function formatPhone($phone) {
        // Remove all non-numeric
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert +62 or 62 to 0
        if (substr($phone, 0, 2) === '62') {
            $phone = '0' . substr($phone, 2);
        }
        
        return $phone;
    }
}
