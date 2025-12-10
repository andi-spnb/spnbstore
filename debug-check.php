<?php
/**
 * DEBUG FILE - Cek apa yang error
 * Upload file ini sebagai: debug-check.php
 * Akses: https://andispnb.shop/debug-check.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Check</h1>";
echo "<hr>";

// 1. Check PHP Version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "✅ PHP OK<br><br>";

// 2. Check Files Exist
echo "<h2>2. Check Files</h2>";

$files = [
    'config.php',
    'classes/AtlanticH2H.php',
    'atlantic-webhook.php',
    'test-atlantic.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ {$file} EXISTS<br>";
    } else {
        echo "❌ {$file} NOT FOUND!<br>";
    }
}
echo "<br>";

// 3. Check config.php
echo "<h2>3. Check config.php</h2>";
try {
    require_once 'config.php';
    echo "✅ config.php loaded<br>";
    
    // Check constants
    if (defined('ATLANTIC_API_KEY')) {
        $key = ATLANTIC_API_KEY;
        if ($key === 'GANTI_DENGAN_API_KEY_ANDA' || empty($key)) {
            echo "⚠️ ATLANTIC_API_KEY not set (still default)<br>";
        } else {
            echo "✅ ATLANTIC_API_KEY: " . substr($key, 0, 10) . "...<br>";
        }
    } else {
        echo "❌ ATLANTIC_API_KEY not defined<br>";
    }
    
    if (defined('ATLANTIC_USERNAME')) {
        $username = ATLANTIC_USERNAME;
        if ($username === 'GANTI_DENGAN_USERNAME_ANDA' || empty($username)) {
            echo "⚠️ ATLANTIC_USERNAME not set (still default)<br>";
        } else {
            echo "✅ ATLANTIC_USERNAME: {$username}<br>";
        }
    } else {
        echo "❌ ATLANTIC_USERNAME not defined<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error loading config.php: " . $e->getMessage() . "<br>";
}
echo "<br>";

// 4. Check AtlanticH2H class
echo "<h2>4. Check AtlanticH2H Class</h2>";
try {
    if (file_exists('classes/AtlanticH2H.php')) {
        require_once 'classes/AtlanticH2H.php';
        echo "✅ AtlanticH2H.php loaded<br>";
        
        if (class_exists('AtlanticH2H')) {
            echo "✅ Class AtlanticH2H exists<br>";
            
            // Try to instantiate
            $atlantic = new AtlanticH2H();
            echo "✅ Class instantiated successfully<br>";
        } else {
            echo "❌ Class AtlanticH2H not found in file<br>";
        }
    } else {
        echo "❌ File classes/AtlanticH2H.php not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
echo "<br>";

// 5. Check Database Connection
echo "<h2>5. Check Database</h2>";
try {
    if (isset($conn) && $conn) {
        echo "✅ Database connected<br>";
        
        // Check tables
        $tables = ['products', 'categories', 'h2h_transactions'];
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '{$table}'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Table {$table} exists<br>";
            } else {
                echo "❌ Table {$table} not found<br>";
            }
        }
    } else {
        echo "❌ Database not connected<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// 6. Check Permissions
echo "<h2>6. Check Permissions</h2>";
$dirs = ['logs', 'classes'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ {$dir}/ is writable<br>";
        } else {
            echo "⚠️ {$dir}/ exists but not writable<br>";
        }
    } else {
        echo "❌ {$dir}/ not found<br>";
    }
}
echo "<br>";

// 7. Test Simple API Call
echo "<h2>7. Test API Call (if config OK)</h2>";
if (defined('ATLANTIC_API_KEY') && 
    ATLANTIC_API_KEY !== 'GANTI_DENGAN_API_KEY_ANDA' && 
    !empty(ATLANTIC_API_KEY) &&
    class_exists('AtlanticH2H')) {
    
    try {
        $atlantic = new AtlanticH2H();
        echo "Attempting API call...<br>";
        
        $result = $atlantic->getPriceList('prabayar');
        
        if ($result['success']) {
            echo "✅ API Connection OK!<br>";
            echo "Total products: " . count($result['data']['data'] ?? []) . "<br>";
        } else {
            echo "❌ API call failed<br>";
            echo "<pre>" . print_r($result, true) . "</pre>";
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ Skipped (config not complete)<br>";
}

echo "<hr>";
echo "<h2>📊 Summary</h2>";
echo "<p>Check hasil di atas untuk melihat masalahnya.</p>";
echo "<p><strong>HAPUS FILE INI setelah debugging selesai!</strong></p>";
?>