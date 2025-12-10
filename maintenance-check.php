<?php
/**
 * Maintenance Mode Middleware
 * Include file ini di awal setiap halaman publik untuk check maintenance mode
 * 
 * Usage:
 * require_once 'maintenance-check.php';
 */

// Only check if not already in maintenance page
if (basename($_SERVER['PHP_SELF']) !== 'maintenance.php') {
    
    // Check if maintenance mode is active
    if (function_exists('isMaintenanceMode') && isMaintenanceMode()) {
        // Redirect to maintenance page
        header('Location: maintenance.php');
        exit;
    } else if (!function_exists('isMaintenanceMode')) {
        // Fallback if function not loaded
        require_once __DIR__ . '/config.php';
        
        $maintenance_mode = 0;
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
            $stmt->execute();
            $result = $stmt->fetch();
            $maintenance_mode = $result ? intval($result['setting_value']) : 0;
        } catch (Exception $e) {
            // Ignore errors
        }
        
        // Check if user is admin
        $is_admin = false;
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                $is_admin = $user && $user['is_admin'] == 1;
            } catch (Exception $e) {
                // Ignore errors
            }
        }
        
        // Redirect if maintenance mode is active and user is not admin
        if ($maintenance_mode && !$is_admin) {
            header('Location: maintenance.php');
            exit;
        }
    }
}
?>