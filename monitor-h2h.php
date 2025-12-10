<?php
/**
 * MONITOR H2H TRANSACTIONS
 * Upload file ini untuk monitor transaksi Atlantic H2H
 * Akses: https://andispnb.shop/monitor-h2h.php
 * 
 * ADMIN ONLY!
 */

require_once 'config.php';

$user = getUserData();

if ($user['is_admin'] != 1) {
    redirect('dashboard.php');
}

$user = getUserData();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor H2H Transactions</title>
    <meta http-equiv="refresh" content="30"> <!-- Auto refresh 30 detik -->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.failed { background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%); }
        .stat-card.processing { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.pending { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            background: white;
        }
        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.failed { background: #f8d7da; color: #721c24; }
        .badge.processing { background: #fff3cd; color: #856404; }
        .badge.pending { background: #d1ecf1; color: #0c5460; }
        .logs {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1rem;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: scroll;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #38ef7d; color: white; }
        .btn-danger { background: #ff6a00; color: white; }
        .actions {
            margin-bottom: 2rem;
            display: flex;
            gap: 10px;
        }
        .voucher-code {
            font-family: 'Courier New', monospace;
            background: #f4f4f4;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.9rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Monitor H2H Transactions</h1>
        <p class="subtitle">
            Auto refresh setiap 30 detik | 
            Last update: <?php echo date('d M Y H:i:s'); ?> | 
            Admin: <?php echo htmlspecialchars($user['nama']); ?>
        </p>
        
        <div class="actions">
            <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
            <a href="?refresh=1" class="btn btn-success">🔄 Refresh Now</a>
        </div>
        
        <!-- Statistics -->
        <div class="stats">
            <?php
            $stats = $conn->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
                FROM h2h_transactions
                WHERE DATE(created_at) = CURDATE()
            ")->fetch();
            ?>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Today</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-number"><?php echo $stats['success']; ?></div>
                <div class="stat-label">Success</div>
            </div>
            
            <div class="stat-card failed">
                <div class="stat-number"><?php echo $stats['failed']; ?></div>
                <div class="stat-label">Failed</div>
            </div>
            
            <div class="stat-card processing">
                <div class="stat-number"><?php echo $stats['processing']; ?></div>
                <div class="stat-label">Processing</div>
            </div>
        </div>
        
        <!-- Transactions Table -->
        <h2>📋 Transaksi Hari Ini</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th>Customer Info</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Voucher</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->query("
                        SELECT 
                            h.id,
                            h.order_id,
                            h.product_code,
                            h.customer_data,
                            h.price,
                            h.status,
                            h.sn_voucher,
                            h.created_at,
                            h.updated_at,
                            p.nama as product_name
                        FROM h2h_transactions h
                        LEFT JOIN products p ON h.product_id = p.id
                        WHERE DATE(h.created_at) = CURDATE()
                        ORDER BY h.created_at DESC
                    ");
                    
                    if ($stmt->rowCount() > 0) {
                        while ($row = $stmt->fetch()) {
                            $customer = json_decode($row['customer_data'], true);
                            $customerInfo = $customer['device_info'] ?? $customer['email'] ?? $customer['name'] ?? '-';
                            
                            echo "<tr>";
                            echo "<td><strong>{$row['order_id']}</strong></td>";
                            echo "<td>{$row['product_code']}<br><small>{$row['product_name']}</small></td>";
                            echo "<td>{$customerInfo}</td>";
                            echo "<td>" . formatRupiah($row['price']) . "</td>";
                            echo "<td><span class='badge {$row['status']}'>{$row['status']}</span></td>";
                            
                            if (!empty($row['sn_voucher'])) {
                                $voucher = strlen($row['sn_voucher']) > 30 
                                    ? substr($row['sn_voucher'], 0, 30) . '...' 
                                    : $row['sn_voucher'];
                                echo "<td><span class='voucher-code'>{$voucher}</span></td>";
                            } else {
                                echo "<td>-</td>";
                            }
                            
                            $timeAgo = time() - strtotime($row['created_at']);
                            $timeStr = $timeAgo < 60 
                                ? $timeAgo . 's ago' 
                                : ($timeAgo < 3600 ? floor($timeAgo/60) . 'm ago' : floor($timeAgo/3600) . 'h ago');
                            
                            echo "<td>" . date('H:i:s', strtotime($row['created_at'])) . "<br><small>{$timeStr}</small></td>";
                            
                            // Action buttons
                            echo "<td>";
                            if ($row['status'] === 'pending' || $row['status'] === 'processing') {
                                echo "<a href='manual-trigger.php?order_id={$row['order_id']}' class='btn btn-primary' style='font-size: 0.8rem;'>Retry</a>";
                            }
                            echo "</td>";
                            
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='empty-state'>Belum ada transaksi hari ini</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Recent Logs -->
        <h2>📜 Recent Logs</h2>
        <div class="logs">
<?php
$logFiles = [
    'logs/atlantic_h2h_' . date('Y-m-d') . '.log',
    'logs/atlantic_webhook_' . date('Y-m-d') . '.log'
];

$hasLogs = false;
foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        $hasLogs = true;
        echo "=== " . basename($logFile) . " ===\n";
        $logs = file($logFile);
        $recentLogs = array_slice($logs, -15); // Last 15 lines
        echo htmlspecialchars(implode('', $recentLogs));
        echo "\n\n";
    }
}

if (!$hasLogs) {
    echo "No logs available today\n";
    echo "Logs will appear here after first transaction.";
}
?>
        </div>
        
        <div style="margin-top: 2rem; padding: 1rem; background: #f9f9f9; border-radius: 5px;">
            <strong>💡 Tips:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>Halaman auto-refresh setiap 30 detik</li>
                <li>Status "pending" → tunggu 1-2 menit, atau klik "Retry"</li>
                <li>Status "processing" → Atlantic sedang proses, tunggu 3-5 menit</li>
                <li>Status "success" → Voucher sudah diterima customer</li>
                <li>Status "failed" → Check logs untuk detail error</li>
            </ul>
        </div>
    </div>
</body>
</html>