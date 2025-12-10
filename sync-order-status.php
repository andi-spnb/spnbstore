<?php
/**
 * SYNC ORDER STATUS
 * 
 * Script untuk sync status order yang sudah sukses di Atlantic
 * tapi masih "processing" di database lokal
 * 
 * URL: https://andispnb.shop/sync-order-status.php
 */

require_once 'config.php';
require_once 'AtlanticH2H.php';

date_default_timezone_set('Asia/Jakarta');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Sync Order Status</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { color: blue; background: #cce5ff; padding: 15px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f5f5f5; }
        pre { background: #f5f5f5; padding: 15px; overflow-x: auto; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<h1>🔄 Sync Order Status</h1>";

$action = $_GET['action'] ?? '';
$orderId = $_GET['order_id'] ?? '';

// Get orders that are stuck in processing
$stmt = $conn->query("
    SELECT * FROM atlantic_orders 
    WHERE status = 'processing' 
    AND h2h_trx_id IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 20
");
$processingOrders = $stmt->fetchAll();

if ($action === 'sync' && !empty($orderId)) {
    echo "<h2>Syncing Order: $orderId</h2>";
    
    // Get order
    $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo "<div class='error'>Order not found!</div>";
    } else {
        echo "<pre>";
        echo "Order ID: {$order['order_id']}\n";
        echo "Product: {$order['product_code']}\n";
        echo "Target: {$order['target']}\n";
        echo "Current Status: {$order['status']}\n";
        echo "H2H Trx ID: " . ($order['h2h_trx_id'] ?? 'N/A') . "\n";
        echo "</pre>";
        
        // Check status from Atlantic
        $atlantic = new AtlanticH2H();
        
        // Try checking by h2h_trx_id first
        if (!empty($order['h2h_trx_id'])) {
            echo "<h3>Checking status from Atlantic API...</h3>";
            $result = $atlantic->checkTransactionStatus($order['h2h_trx_id']);
            
            echo "<pre>";
            echo "API Response:\n";
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "</pre>";
            
            if ($result['success']) {
                $data = $result['data']['data'] ?? $result['data'] ?? [];
                $atlanticStatus = strtolower($data['status'] ?? '');
                $sn = $data['sn'] ?? null;
                
                echo "<div class='info'>";
                echo "Atlantic Status: <strong>$atlanticStatus</strong><br>";
                if ($sn) echo "SN: <code>$sn</code>";
                echo "</div>";
                
                // Update local status
                $newStatus = 'processing';
                if (in_array($atlanticStatus, ['success', 'sukses', 'berhasil'])) {
                    $newStatus = 'success';
                } elseif (in_array($atlanticStatus, ['failed', 'gagal', 'error'])) {
                    $newStatus = 'failed';
                }
                
                $stmt = $conn->prepare("UPDATE atlantic_orders SET 
                    status = ?,
                    sn_voucher = COALESCE(?, sn_voucher),
                    h2h_response = ?,
                    completed_at = ?,
                    updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([
                    $newStatus,
                    $sn,
                    json_encode($result['data']),
                    $newStatus === 'success' ? date('Y-m-d H:i:s') : null,
                    $order['id']
                ]);
                
                echo "<div class='success'>✅ Order status updated to: <strong>$newStatus</strong></div>";
                
            } else {
                echo "<div class='error'>Failed to get status from Atlantic: " . ($result['message'] ?? 'Unknown') . "</div>";
            }
        }
    }
    
    echo "<br><a href='?' class='btn btn-primary'>← Back</a>";
    
} elseif ($action === 'manual_update' && !empty($orderId)) {
    $newStatus = $_GET['new_status'] ?? 'success';
    $sn = $_GET['sn'] ?? '';
    
    $stmt = $conn->prepare("UPDATE atlantic_orders SET 
        status = ?,
        sn_voucher = COALESCE(NULLIF(?, ''), sn_voucher),
        completed_at = ?,
        updated_at = NOW()
        WHERE order_id = ?");
    $stmt->execute([
        $newStatus,
        $sn,
        $newStatus === 'success' ? date('Y-m-d H:i:s') : null,
        $orderId
    ]);
    
    echo "<div class='success'>✅ Order $orderId updated to: $newStatus</div>";
    echo "<br><a href='?' class='btn btn-primary'>← Back</a>";
    
} elseif ($action === 'sync_all') {
    echo "<h2>Syncing All Processing Orders...</h2>";
    
    $atlantic = new AtlanticH2H();
    $successCount = 0;
    $failCount = 0;
    
    foreach ($processingOrders as $order) {
        if (empty($order['h2h_trx_id'])) continue;
        
        $result = $atlantic->checkTransactionStatus($order['h2h_trx_id']);
        
        if ($result['success']) {
            $data = $result['data']['data'] ?? $result['data'] ?? [];
            $atlanticStatus = strtolower($data['status'] ?? '');
            $sn = $data['sn'] ?? null;
            
            $newStatus = 'processing';
            if (in_array($atlanticStatus, ['success', 'sukses', 'berhasil'])) {
                $newStatus = 'success';
            } elseif (in_array($atlanticStatus, ['failed', 'gagal', 'error'])) {
                $newStatus = 'failed';
            }
            
            if ($newStatus !== 'processing') {
                $stmt = $conn->prepare("UPDATE atlantic_orders SET 
                    status = ?, sn_voucher = COALESCE(?, sn_voucher), completed_at = ?, updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([
                    $newStatus,
                    $sn,
                    $newStatus === 'success' ? date('Y-m-d H:i:s') : null,
                    $order['id']
                ]);
                
                echo "<div class='success'>✅ {$order['order_id']} → $newStatus</div>";
                $successCount++;
            } else {
                echo "<div class='info'>⏳ {$order['order_id']} still processing</div>";
            }
        } else {
            echo "<div class='error'>❌ {$order['order_id']} - " . ($result['message'] ?? 'API error') . "</div>";
            $failCount++;
        }
        
        usleep(300000); // 0.3s delay
    }
    
    echo "<h3>Summary: $successCount updated, $failCount failed</h3>";
    echo "<br><a href='?' class='btn btn-primary'>← Back</a>";
    
} else {
    // Show processing orders
    echo "<div class='info'>
        <strong>ℹ️ Orders yang status-nya 'processing' tapi mungkin sudah sukses di Atlantic</strong>
    </div>";
    
    if (count($processingOrders) === 0) {
        echo "<div class='success'>✅ Tidak ada order dalam status processing!</div>";
    } else {
        echo "<table>
            <tr>
                <th>Order ID</th>
                <th>Product</th>
                <th>Target</th>
                <th>H2H Trx ID</th>
                <th>Created</th>
                <th>Action</th>
            </tr>";
        
        foreach ($processingOrders as $order) {
            echo "<tr>";
            echo "<td><code>{$order['order_id']}</code></td>";
            echo "<td>{$order['product_code']}</td>";
            echo "<td>{$order['target']}</td>";
            echo "<td><code>" . ($order['h2h_trx_id'] ?? 'N/A') . "</code></td>";
            echo "<td>" . date('d/m H:i', strtotime($order['created_at'])) . "</td>";
            echo "<td>
                <a href='?action=sync&order_id={$order['order_id']}' class='btn btn-primary'>Sync</a>
            </td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<br><a href='?action=sync_all' class='btn btn-success'>🔄 Sync All Orders</a>";
    }
    
    // Manual update form
    echo "<h2>Manual Update Order</h2>";
    echo "<p>Jika sudah konfirmasi sukses di Atlantic tapi API check gagal:</p>";
    echo "<form method='GET' style='background:#f5f5f5;padding:20px;border-radius:8px;'>";
    echo "<input type='hidden' name='action' value='manual_update'>";
    echo "<label>Order ID: <input type='text' name='order_id' placeholder='TG25112902809C21' required></label><br><br>";
    echo "<label>SN/Voucher: <input type='text' name='sn' placeholder='Optional - dari Atlantic'></label><br><br>";
    echo "<label>New Status: 
        <select name='new_status'>
            <option value='success'>Success</option>
            <option value='failed'>Failed</option>
        </select>
    </label><br><br>";
    echo "<button type='submit' class='btn btn-success'>Update Order</button>";
    echo "</form>";
}

echo "</body></html>";
?>