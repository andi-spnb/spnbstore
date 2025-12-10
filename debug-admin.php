<?php
/**
 * DEBUG ADMIN DASHBOARD
 * Upload sebagai: debug-admin.php
 * Akses: https://andispnb.shop/debug-admin.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Admin Dashboard</h1><hr>";

// 1. Check config.php
echo "<h2>1. Check config.php</h2>";
if (file_exists('config.php')) {
    echo "✅ config.php exists<br>";
    try {
        require_once 'config.php';
        echo "✅ config.php loaded<br>";
    } catch (Exception $e) {
        echo "❌ Error loading config.php: " . $e->getMessage() . "<br>";
        exit;
    }
} else {
    echo "❌ config.php NOT FOUND<br>";
    exit;
}

// 2. Check user session
echo "<h2>2. Check Session</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    echo "✅ User logged in: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "⚠️ No user logged in<br>";
}

// 3. Check functions
echo "<h2>3. Check Functions</h2>";
$required_functions = [
    'isLoggedIn',
    'getUserData',
    'formatRupiah',
    'redirect'
];

foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "✅ {$func}() exists<br>";
    } else {
        echo "❌ {$func}() NOT FOUND<br>";
    }
}

// 4. Check database
echo "<h2>4. Check Database</h2>";
if (isset($conn) && $conn) {
    echo "✅ Database connected<br>";
    
    // Check tables
    $tables = ['users', 'products', 'transactions', 'h2h_transactions'];
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch()['count'];
            echo "✅ Table {$table}: {$count} rows<br>";
        } catch (Exception $e) {
            echo "❌ Table {$table}: " . $e->getMessage() . "<br>";
        }
    }
} else {
    echo "❌ Database NOT connected<br>";
}

// 5. Check admin-layout.php
echo "<h2>5. Check admin-layout.php</h2>";
if (file_exists('admin-layout.php')) {
    echo "✅ admin-layout.php exists<br>";
    $size = filesize('admin-layout.php');
    echo "File size: " . number_format($size) . " bytes<br>";
} else {
    echo "❌ admin-layout.php NOT FOUND<br>";
}

// 6. Test getUserData if logged in
echo "<h2>6. Test getUserData</h2>";
if (function_exists('isLoggedIn') && isLoggedIn()) {
    try {
        $user = getUserData();
        echo "✅ User data retrieved<br>";
        echo "<pre>";
        print_r([
            'id' => $user['id'] ?? 'N/A',
            'username' => $user['username'] ?? 'N/A',
            'is_admin' => $user['is_admin'] ?? 'N/A'
        ]);
        echo "</pre>";
    } catch (Exception $e) {
        echo "❌ Error getting user data: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ User not logged in, cannot test getUserData<br>";
}

// 7. Test simple query
echo "<h2>7. Test Statistics Query</h2>";
try {
    // Total Users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $total_users = $stmt->fetch()['count'];
    echo "✅ Total users: {$total_users}<br>";
    
    // Total Products
    $stmt = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
    $total_products = $stmt->fetch()['count'];
    echo "✅ Total products: {$total_products}<br>";
    
    // H2H Stats
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
            SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed
        FROM h2h_transactions
        WHERE DATE(created_at) = CURDATE()
    ");
    $h2h_stats = $stmt->fetch();
    echo "✅ H2H stats today: {$h2h_stats['total']} total, {$h2h_stats['success']} success<br>";
    
} catch (Exception $e) {
    echo "❌ Query error: " . $e->getMessage() . "<br>";
}

// 8. Test output buffering
echo "<h2>8. Test Output Buffering</h2>";
ob_start();
echo "Test content";
$content = ob_get_clean();
if ($content === "Test content") {
    echo "✅ Output buffering works<br>";
} else {
    echo "❌ Output buffering issue<br>";
}

// 9. PHP Info
echo "<h2>9. PHP Configuration</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Display Errors: " . ini_get('display_errors') . "<br>";
echo "Error Reporting: " . error_reporting() . "<br>";

echo "<hr>";
echo "<h2>✅ Debug Complete</h2>";
echo "<p>If all checks passed, try accessing admin-dashboard.php again.</p>";
echo "<p>If still error, check PHP error log for details.</p>";
?>