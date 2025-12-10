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

// Database backup configuration
$backup_file = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
$backup_path = __DIR__ . '/backups/';

// Create backups directory if not exists
if (!file_exists($backup_path)) {
    mkdir($backup_path, 0755, true);
}

$full_backup_path = $backup_path . $backup_file;

try {
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // Open file for writing
    $handle = fopen($full_backup_path, 'w+');
    if (!$handle) {
        throw new Exception('Cannot create backup file');
    }
    
    // Write backup header
    $content = "-- Database Backup\n";
    $content .= "-- Database: " . DB_NAME . "\n";
    $content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $content .= "-- Host: " . DB_HOST . "\n\n";
    $content .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $content .= "SET time_zone = \"+00:00\";\n\n";
    
    fwrite($handle, $content);
    
    // Loop through each table
    foreach ($tables as $table) {
        // Get table structure
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch(PDO::FETCH_NUM);
        
        $content = "\n\n-- --------------------------------------------------------\n";
        $content .= "-- Table structure for table `$table`\n";
        $content .= "-- --------------------------------------------------------\n\n";
        $content .= "DROP TABLE IF EXISTS `$table`;\n";
        $content .= $row[1] . ";\n\n";
        
        fwrite($handle, $content);
        
        // Get table data
        $result = $conn->query("SELECT * FROM `$table`");
        $num_rows = $result->rowCount();
        
        if ($num_rows > 0) {
            $content = "-- Dumping data for table `$table`\n\n";
            fwrite($handle, $content);
            
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $content = "INSERT INTO `$table` VALUES(";
                $values = [];
                
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . addslashes($value) . "'";
                    }
                }
                
                $content .= implode(',', $values) . ");\n";
                fwrite($handle, $content);
            }
            
            fwrite($handle, "\n");
        }
    }
    
    $content = "\nSET FOREIGN_KEY_CHECKS=1;\n";
    fwrite($handle, $content);
    
    fclose($handle);
    
    // Force download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backup_file . '"');
    header('Content-Length: ' . filesize($full_backup_path));
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    readfile($full_backup_path);
    
    // Delete temporary backup file
    unlink($full_backup_path);
    
    exit;
    
} catch (Exception $e) {
    $_SESSION['message'] = 'Error creating backup: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    redirect('admin-settings.php');
}
?>