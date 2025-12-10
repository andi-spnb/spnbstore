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
    $log_file = __DIR__ . '/error.log';
    
    if (file_exists($log_file)) {
        // Create backup before clearing
        $backup_file = __DIR__ . '/logs/error_backup_' . date('Y-m-d_H-i-s') . '.log';
        $backup_dir = dirname($backup_file);
        
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        copy($log_file, $backup_file);
        
        // Clear the log file
        file_put_contents($log_file, '');
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Error log berhasil dibersihkan! Backup disimpan.'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Tidak ada error log untuk dibersihkan.'
        ]);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>