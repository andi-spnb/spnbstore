<?php
require_once 'config.php';

// Admin check
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
$user = getUserData();

if ($user['is_admin'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // Clear PHP session cache
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    
    // Clear OPcache if available
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Clear file-based cache (if you have one)
    $cache_dir = __DIR__ . '/cache/';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // Clear temporary files
    $temp_dir = __DIR__ . '/temp/';
    if (is_dir($temp_dir)) {
        $files = glob($temp_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Cache berhasil dibersihkan!'
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>