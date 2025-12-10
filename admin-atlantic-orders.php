<?php
/**
 * ADMIN - Atlantic Orders
 * Kelola orders top-up game dari Atlantic H2H
 * 
 * Standalone version - compatible dengan SPNB Store
 */

require_once 'config.php';

// Check login dan admin
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUserData();
if ($user['is_admin'] != 1) {
    header('Location: dashboard.php');
    exit;
}

// Include Atlantic class
if (!class_exists('AtlanticH2H')) {
    $paths = [
        __DIR__ . '/AtlanticH2H.php',
        __DIR__ . '/classes/AtlanticH2H.php'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = $_POST['order_id'] ?? '';
    
    // Retry order - trigger H2H lagi
    if ($action === 'retry' && $orderId) {
        $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order && in_array($order['status'], ['failed', 'payment_success'])) {
            if (class_exists('AtlanticH2H')) {
                try {
                    $atlantic = new AtlanticH2H();
                    $result = $atlantic->createTransaction(
                        $order['product_code'],
                        $order['target'],
                        $order['order_id']
                    );
                    
                    if ($result['success']) {
                        $trxId = $result['data']['data']['id'] ?? '';
                        $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'processing', h2h_trx_id = ?, h2h_response = ?, updated_at = NOW() WHERE order_id = ?");
                        $stmt->execute([$trxId, json_encode($result['data']), $orderId]);
                        $message = "Order berhasil di-retry. H2H ID: $trxId";
                        $messageType = 'success';
                    } else {
                        $stmt = $conn->prepare("UPDATE atlantic_orders SET h2h_response = ?, updated_at = NOW() WHERE order_id = ?");
                        $stmt->execute([json_encode($result), $orderId]);
                        $message = 'Retry gagal: ' . ($result['message'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
    
    // Check status H2H
    if ($action === 'check_status' && $orderId) {
        $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order && $order['h2h_trx_id'] && class_exists('AtlanticH2H')) {
            try {
                $atlantic = new AtlanticH2H();
                $result = $atlantic->checkTransactionStatus($order['h2h_trx_id'], 'prabayar');
                
                if ($result['success']) {
                    $data = $result['data']['data'] ?? [];
                    $status = $data['status'] ?? 'processing';
                    $sn = $data['sn'] ?? null;
                    
                    $newStatus = 'processing';
                    if ($status === 'success') $newStatus = 'success';
                    elseif ($status === 'failed') $newStatus = 'failed';
                    
                    $stmt = $conn->prepare("UPDATE atlantic_orders SET status = ?, sn_voucher = ?, h2h_response = ?, updated_at = NOW() WHERE order_id = ?");
                    $stmt->execute([$newStatus, $sn, json_encode($result['data']), $orderId]);
                    
                    $message = "Status updated: $newStatus" . ($sn ? " | SN: $sn" : "");
                    $messageType = $newStatus === 'success' ? 'success' : ($newStatus === 'failed' ? 'danger' : 'warning');
                } else {
                    $message = 'Check status gagal: ' . ($result['message'] ?? 'Unknown error');
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
    
    // Refund to user balance
    if ($action === 'refund' && $orderId) {
        $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order && $order['user_id'] && in_array($order['status'], ['failed', 'expired'])) {
            $refundAmount = $order['total'];
            
            // Add back to user balance
            $stmt = $conn->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?");
            $stmt->execute([$refundAmount, $order['user_id']]);
            
            // Update order status
            $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'refunded', updated_at = NOW() WHERE order_id = ?");
            $stmt->execute([$orderId]);
            
            $message = 'Refund berhasil: ' . formatRupiah($refundAmount);
            $messageType = 'success';
        } else {
            $message = 'Refund tidak dapat dilakukan';
            $messageType = 'danger';
        }
    }
    
    // Cancel order
    if ($action === 'cancel' && $orderId) {
        $stmt = $conn->prepare("SELECT * FROM atlantic_orders WHERE order_id = ? AND status = 'waiting_payment'");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if ($order) {
            $stmt = $conn->prepare("UPDATE atlantic_orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $message = 'Order berhasil dibatalkan';
            $messageType = 'success';
        }
    }
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$filterDateTo = $_GET['date_to'] ?? date('Y-m-d');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Build query
$where = ["DATE(created_at) BETWEEN ? AND ?"];
$params = [$filterDateFrom, $filterDateTo];

if ($filterStatus) {
    $where[] = "status = ?";
    $params[] = $filterStatus;
}

if ($filterSearch) {
    $where[] = "(order_id LIKE ? OR guest_phone LIKE ? OR target LIKE ? OR product_name LIKE ?)";
    $searchTerm = "%$filterSearch%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = implode(' AND ', $where);

// Get total count
$stmt = $conn->prepare("SELECT COUNT(*) FROM atlantic_orders WHERE $whereClause");
$stmt->execute($params);
$totalOrders = $stmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Get orders
$offset = ($page - 1) * $perPage;
$stmt = $conn->prepare("
    SELECT o.*, g.game_name, u.username 
    FROM atlantic_orders o 
    LEFT JOIN atlantic_games g ON o.game_id = g.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE $whereClause 
    ORDER BY o.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Stats for period
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'waiting_payment' THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'success' THEN total ELSE 0 END) as revenue,
        SUM(CASE WHEN status = 'success' THEN (price_sell - price_atlantic) ELSE 0 END) as profit
    FROM atlantic_orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$filterDateFrom, $filterDateTo]);
$stats = $stmt->fetch();

// Status colors
$statusColors = [
    'waiting_payment' => ['bg' => 'rgba(245, 158, 11, 0.2)', 'color' => '#f59e0b', 'label' => 'Menunggu Bayar'],
    'payment_success' => ['bg' => 'rgba(59, 130, 246, 0.2)', 'color' => '#3b82f6', 'label' => 'Bayar OK'],
    'processing' => ['bg' => 'rgba(99, 102, 241, 0.2)', 'color' => '#6366f1', 'label' => 'Proses'],
    'success' => ['bg' => 'rgba(16, 185, 129, 0.2)', 'color' => '#10b981', 'label' => 'Sukses'],
    'failed' => ['bg' => 'rgba(239, 68, 68, 0.2)', 'color' => '#ef4444', 'label' => 'Gagal'],
    'expired' => ['bg' => 'rgba(107, 114, 128, 0.2)', 'color' => '#6b7280', 'label' => 'Expired'],
    'cancelled' => ['bg' => 'rgba(107, 114, 128, 0.2)', 'color' => '#6b7280', 'label' => 'Batal'],
    'refunded' => ['bg' => 'rgba(139, 92, 246, 0.2)', 'color' => '#8b5cf6', 'label' => 'Refund']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Orders - Admin <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #0f172a;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-dark);
            min-height: 100vh;
            color: var(--text-primary);
        }
        
        .admin-header {
            background: var(--bg-card);
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .admin-header h1 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .admin-header h1 i { color: var(--primary); }
        
        .admin-nav { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        
        .admin-nav a {
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .admin-nav a:hover, .admin-nav a.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); }
        .alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid var(--warning); color: var(--warning); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.25rem;
            text-align: center;
        }
        
        .stat-card .value { font-size: 1.5rem; font-weight: 700; }
        .stat-card .label { color: var(--text-secondary); font-size: 0.8rem; margin-top: 0.25rem; }
        
        .filters-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .filters-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
        
        .filter-input {
            width: 100%;
            padding: 0.625rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            background: var(--bg-dark);
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .filter-input:focus { outline: none; border-color: var(--primary); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-sm { padding: 0.375rem 0.625rem; font-size: 0.8rem; }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .table-container { overflow-x: auto; }
        
        .orders-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        
        .orders-table th, .orders-table td {
            padding: 0.875rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .orders-table th {
            background: rgba(255,255,255,0.05);
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        
        .orders-table tr:hover { background: rgba(255,255,255,0.02); }
        
        .order-id { font-family: monospace; font-size: 0.85rem; color: var(--primary); }
        .order-game { font-weight: 500; }
        .order-target { font-family: monospace; font-size: 0.85rem; background: var(--bg-dark); padding: 0.25rem 0.5rem; border-radius: 0.25rem; }
        .order-user { font-size: 0.9rem; }
        .order-user .guest { color: var(--text-secondary); font-style: italic; }
        .order-amount { font-weight: 600; }
        .order-profit { font-size: 0.85rem; }
        .order-profit.positive { color: var(--success); }
        
        .order-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .order-time { font-size: 0.85rem; color: var(--text-secondary); }
        
        .order-actions { display: flex; gap: 0.375rem; flex-wrap: wrap; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 1.5rem;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 0.875rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .pagination a {
            background: rgba(255,255,255,0.05);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .pagination a:hover { background: var(--primary); border-color: var(--primary); }
        .pagination span { background: var(--primary); color: white; }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state i { font-size: 3rem; opacity: 0.5; margin-bottom: 1rem; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 1rem;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-close { background: none; border: none; color: var(--text-secondary); font-size: 1.25rem; cursor: pointer; }
        
        .modal-body { padding: 1.25rem; }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-secondary); }
        .detail-value { font-weight: 500; text-align: right; }
        
        .sn-box {
            background: var(--bg-dark);
            border: 1px solid var(--success);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .sn-box h4 { color: var(--success); margin-bottom: 0.5rem; font-size: 0.9rem; }
        .sn-value { font-family: monospace; font-size: 0.95rem; word-break: break-all; }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .admin-header { padding: 1rem; }
            .filters-form { flex-direction: column; }
            .filter-group { width: 100%; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <h1><i class="fas fa-receipt"></i> Kelola Orders Top Up</h1>
        <nav class="admin-nav">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="admin-atlantic-games.php"><i class="fas fa-gamepad"></i> Games</a>
            <a href="admin-atlantic-orders.php" class="active"><i class="fas fa-receipt"></i> Orders</a>
            <a href="admin-transactions.php"><i class="fas fa-exchange-alt"></i> Transaksi</a>
        </nav>
    </header>
    
    <div class="container">
        <!-- Alert -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--success);"><?php echo number_format($stats['success']); ?></div>
                <div class="label">Sukses</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--primary);"><?php echo number_format($stats['processing']); ?></div>
                <div class="label">Processing</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--warning);"><?php echo number_format($stats['waiting']); ?></div>
                <div class="label">Menunggu</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--danger);"><?php echo number_format($stats['failed']); ?></div>
                <div class="label">Gagal</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--success);"><?php echo formatRupiah($stats['revenue']); ?></div>
                <div class="label">Revenue</div>
            </div>
            <div class="stat-card">
                <div class="value" style="color: var(--success);"><?php echo formatRupiah($stats['profit']); ?></div>
                <div class="label">Profit</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="filter-input">
                        <option value="">Semua Status</option>
                        <?php foreach ($statusColors as $key => $val): ?>
                        <option value="<?php echo $key; ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="date_from" class="filter-input" value="<?php echo $filterDateFrom; ?>">
                </div>
                <div class="filter-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="date_to" class="filter-input" value="<?php echo $filterDateTo; ?>">
                </div>
                <div class="filter-group" style="flex: 2;">
                    <label>Cari</label>
                    <input type="text" name="search" class="filter-input" placeholder="Order ID, Phone, Target..." value="<?php echo htmlspecialchars($filterSearch); ?>">
                </div>
                <div class="filter-group" style="flex: 0;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                </div>
            </form>
        </div>
        
        <!-- Orders Table -->
        <div class="card">
            <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Tidak Ada Order</h3>
                <p>Tidak ada order yang sesuai dengan filter</p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Game / Produk</th>
                            <th>Target</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Waktu</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): 
                            $statusStyle = $statusColors[$order['status']] ?? $statusColors['processing'];
                            $profit = $order['price_sell'] - $order['price_atlantic'];
                        ?>
                        <tr>
                            <td>
                                <span class="order-id"><?php echo htmlspecialchars($order['order_id']); ?></span>
                            </td>
                            <td>
                                <div class="order-game"><?php echo htmlspecialchars($order['game_name'] ?? 'N/A'); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($order['product_name']); ?></div>
                            </td>
                            <td>
                                <span class="order-target"><?php echo htmlspecialchars($order['target']); ?></span>
                            </td>
                            <td class="order-user">
                                <?php if ($order['user_id']): ?>
                                    <?php echo htmlspecialchars($order['username']); ?>
                                <?php else: ?>
                                    <span class="guest"><?php echo htmlspecialchars($order['guest_phone']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="order-amount"><?php echo formatRupiah($order['total']); ?></div>
                                <div class="order-profit positive">+<?php echo formatRupiah($profit); ?></div>
                            </td>
                            <td>
                                <span class="order-status" style="background: <?php echo $statusStyle['bg']; ?>; color: <?php echo $statusStyle['color']; ?>;">
                                    <?php echo $statusStyle['label']; ?>
                                </span>
                            </td>
                            <td class="order-time">
                                <?php echo date('d/m/Y', strtotime($order['created_at'])); ?><br>
                                <?php echo date('H:i', strtotime($order['created_at'])); ?>
                            </td>
                            <td>
                                <div class="order-actions">
                                    <button class="btn btn-secondary btn-sm" onclick="viewDetail(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if (in_array($order['status'], ['failed', 'payment_success'])): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="retry">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <button type="submit" class="btn btn-warning btn-sm" title="Retry H2H">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['status'] === 'processing' && $order['h2h_trx_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="check_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" title="Check Status">
                                            <i class="fas fa-sync"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['user_id'] && in_array($order['status'], ['failed', 'expired'])): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Refund <?php echo formatRupiah($order['total']); ?> ke user?')">
                                        <input type="hidden" name="action" value="refund">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm" title="Refund">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    <i class="fas fa-chevron-left"></i> Prev
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                    <span><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Detail Modal -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-receipt"></i> Detail Order</h3>
                <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        function viewDetail(order) {
            const statusColors = <?php echo json_encode($statusColors); ?>;
            const status = statusColors[order.status] || statusColors['processing'];
            
            let html = `
                <div class="detail-row">
                    <span class="detail-label">Order ID</span>
                    <span class="detail-value" style="font-family: monospace;">${order.order_id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="order-status" style="background: ${status.bg}; color: ${status.color};">${status.label}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Game</span>
                    <span class="detail-value">${order.game_name || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Produk</span>
                    <span class="detail-value">${order.product_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Target ID</span>
                    <span class="detail-value" style="font-family: monospace;">${order.target}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Customer</span>
                    <span class="detail-value">${order.username || order.guest_phone}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Harga Modal</span>
                    <span class="detail-value">Rp ${parseInt(order.price_atlantic).toLocaleString('id')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Harga Jual</span>
                    <span class="detail-value">Rp ${parseInt(order.price_sell).toLocaleString('id')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Fee</span>
                    <span class="detail-value">Rp ${parseInt(order.fee || 0).toLocaleString('id')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total</span>
                    <span class="detail-value" style="font-weight: 700; color: var(--success);">Rp ${parseInt(order.total).toLocaleString('id')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Profit</span>
                    <span class="detail-value" style="color: var(--success);">+Rp ${(parseInt(order.price_sell) - parseInt(order.price_atlantic)).toLocaleString('id')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method</span>
                    <span class="detail-value">${order.payment_method || '-'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">H2H Trx ID</span>
                    <span class="detail-value" style="font-family: monospace;">${order.h2h_trx_id || '-'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Waktu Order</span>
                    <span class="detail-value">${order.created_at}</span>
                </div>
            `;
            
            if (order.sn_voucher) {
                html += `
                    <div class="sn-box">
                        <h4><i class="fas fa-key"></i> SN / Voucher</h4>
                        <div class="sn-value">${order.sn_voucher}</div>
                    </div>
                `;
            }
            
            document.getElementById('detailContent').innerHTML = html;
            document.getElementById('detailModal').classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
            }
        });
        
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', e => {
                if (e.target === modal) modal.classList.remove('active');
            });
        });
    </script>
</body>
</html>
