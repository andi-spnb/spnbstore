<?php
/**
 * MANUAL TRIGGER H2H TRANSACTION
 * Untuk retry transaksi yang pending/failed
 * 
 * Usage: manual-trigger.php?order_id=ORDER123456
 * ADMIN ONLY!
 */

require_once 'config.php';

$user = getUserData();

if ($user['is_admin'] != 1) {
    redirect('dashboard.php');
}

$orderId = $_GET['order_id'] ?? '';
$action = $_GET['action'] ?? 'process';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Trigger H2H</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-bottom: 1rem; }
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        pre {
            background: #f4f4f4;
            padding: 1rem;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #38ef7d; color: white; }
        .btn-danger { background: #ff6a00; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .info-table th, .info-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .info-table th {
            background: #f9f9f9;
            font-weight: 600;
            width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Manual Trigger H2H Transaction</h1>
        
        <?php if (empty($orderId)): ?>
            
            <!-- Form input Order ID -->
            <div class="alert alert-info">
                <strong>ℹ️ Cara Penggunaan:</strong><br>
                Masukkan Order ID untuk retry transaksi H2H yang pending atau failed.
            </div>
            
            <form method="GET">
                <div style="margin: 1rem 0;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        Order ID:
                    </label>
                    <input type="text" 
                           name="order_id" 
                           placeholder="ORDER123456"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                           required>
                </div>
                
                <div style="margin: 1rem 0;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        Action:
                    </label>
                    <select name="action" 
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="process">Process Transaction (Create ke Atlantic)</option>
                        <option value="check">Check Status (Get dari Atlantic)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">🚀 Execute</button>
                <a href="monitor-h2h.php" class="btn btn-secondary">← Back to Monitor</a>
            </form>
            
        <?php else: ?>
            
            <!-- Show transaction info -->
            <div class="alert alert-info">
                <strong>Order ID:</strong> <?php echo htmlspecialchars($orderId); ?><br>
                <strong>Action:</strong> <?php echo htmlspecialchars($action); ?>
            </div>
            
            <?php
            // Get transaction info
            $stmt = $conn->prepare("
                SELECT 
                    t.*,
                    p.nama as product_name,
                    p.product_code,
                    u.email as customer_email,
                    u.nama as customer_name
                FROM transactions t
                LEFT JOIN products p ON t.product_id = p.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                echo "<div class='alert alert-error'>❌ Transaction not found!</div>";
                echo "<a href='manual-trigger.php' class='btn btn-secondary'>← Back</a>";
                exit;
            }
            
            // Get H2H transaction info
            $stmt = $conn->prepare("
                SELECT * FROM h2h_transactions 
                WHERE order_id = ?
            ");
            $stmt->execute([$orderId]);
            $h2hTrx = $stmt->fetch();
            
            // Display transaction info
            echo "<h2>📋 Transaction Info</h2>";
            echo "<table class='info-table'>";
            echo "<tr><th>Order ID</th><td>{$transaction['order_id']}</td></tr>";
            echo "<tr><th>Product</th><td>{$transaction['product_name']} ({$transaction['product_code']})</td></tr>";
            echo "<tr><th>Customer</th><td>{$transaction['customer_name']} ({$transaction['customer_email']})</td></tr>";
            echo "<tr><th>Amount</th><td>" . formatRupiah($transaction['total']) . "</td></tr>";
            echo "<tr><th>Payment Status</th><td>{$transaction['status']}</td></tr>";
            
            if ($h2hTrx) {
                echo "<tr><th>H2H Status</th><td>{$h2hTrx['status']}</td></tr>";
                echo "<tr><th>H2H Trx ID</th><td>" . ($h2hTrx['h2h_trx_id'] ?: '-') . "</td></tr>";
                if ($h2hTrx['sn_voucher']) {
                    echo "<tr><th>Voucher</th><td><code>{$h2hTrx['sn_voucher']}</code></td></tr>";
                }
            } else {
                echo "<tr><th>H2H Status</th><td><span style='color: red;'>Not created yet</span></td></tr>";
            }
            
            echo "</table>";
            
            // Execute action
            echo "<h2>🚀 Execution Result</h2>";
            
            try {
                if ($action === 'process') {
                    // Process Atlantic transaction
                    echo "<div class='alert alert-info'>Processing transaction to Atlantic H2H...</div>";
                    
                    $result = processAtlanticTransaction($orderId);
                    
                    if ($result['success']) {
                        echo "<div class='alert alert-success'>";
                        echo "<strong>✅ Success!</strong><br>";
                        echo "Message: " . ($result['message'] ?? 'Transaction processed successfully') . "<br>";
                        if (isset($result['atlantic_id'])) {
                            echo "Atlantic Transaction ID: {$result['atlantic_id']}";
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='alert alert-error'>";
                        echo "<strong>❌ Failed!</strong><br>";
                        echo "Message: " . ($result['message'] ?? 'Unknown error');
                        echo "</div>";
                    }
                    
                    echo "<h3>Full Response:</h3>";
                    echo "<pre>" . print_r($result, true) . "</pre>";
                    
                } elseif ($action === 'check') {
                    // Check status from Atlantic
                    echo "<div class='alert alert-info'>Checking status from Atlantic H2H...</div>";
                    
                    $result = checkAtlanticStatus($orderId);
                    
                    if ($result['success']) {
                        echo "<div class='alert alert-success'>";
                        echo "<strong>✅ Status Retrieved!</strong><br>";
                        echo "Status: " . ($result['status'] ?? 'unknown') . "<br>";
                        if (isset($result['sn'])) {
                            echo "Voucher: <code>{$result['sn']}</code>";
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='alert alert-error'>";
                        echo "<strong>❌ Failed to retrieve status!</strong><br>";
                        echo "Message: " . ($result['message'] ?? 'Unknown error');
                        echo "</div>";
                    }
                    
                    echo "<h3>Full Response:</h3>";
                    echo "<pre>" . print_r($result, true) . "</pre>";
                }
                
            } catch (Exception $e) {
                echo "<div class='alert alert-error'>";
                echo "<strong>❌ Exception!</strong><br>";
                echo "Error: " . htmlspecialchars($e->getMessage());
                echo "</div>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            }
            
            // Action buttons
            echo "<hr>";
            echo "<h3>Actions:</h3>";
            echo "<a href='?order_id={$orderId}&action=process' class='btn btn-primary'>🔄 Process Again</a>";
            echo "<a href='?order_id={$orderId}&action=check' class='btn btn-success'>🔍 Check Status</a>";
            echo "<a href='monitor-h2h.php' class='btn btn-secondary'>← Back to Monitor</a>";
            echo "<a href='manual-trigger.php' class='btn btn-secondary'>New Order ID</a>";
            
            // Show recent H2H logs for this order
            echo "<hr>";
            echo "<h3>📜 Recent Logs for this Order:</h3>";
            
            $stmt = $conn->prepare("
                SELECT * FROM h2h_logs 
                WHERE order_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$orderId]);
            
            if ($stmt->rowCount() > 0) {
                echo "<table class='info-table'>";
                echo "<tr><th>Time</th><th>Action</th><th>Status</th><th>Message</th></tr>";
                
                while ($log = $stmt->fetch()) {
                    $response = json_decode($log['response_data'], true);
                    $message = $response['message'] ?? $log['error_message'] ?? '-';
                    
                    echo "<tr>";
                    echo "<td>" . date('H:i:s', strtotime($log['created_at'])) . "</td>";
                    echo "<td>{$log['action']}</td>";
                    echo "<td>{$log['status_code']}</td>";
                    echo "<td>" . htmlspecialchars(substr($message, 0, 100)) . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<div class='alert alert-warning'>No logs found for this order.</div>";
            }
            ?>
            
        <?php endif; ?>
        
        <hr style="margin: 2rem 0;">
        <div style="color: #666; font-size: 0.9rem;">
            <strong>💡 Tips:</strong>
            <ul style="margin-left: 20px;">
                <li><strong>Process:</strong> Membuat transaksi baru ke Atlantic H2H</li>
                <li><strong>Check:</strong> Mengecek status transaksi yang sudah dibuat</li>
                <li>Jika status "failed", coba process ulang</li>
                <li>Jika status "processing", tunggu 3-5 menit atau check status</li>
                <li>Check logs untuk detail error</li>
            </ul>
        </div>
    </div>
</body>
</html>