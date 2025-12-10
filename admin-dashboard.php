<?php
/**
 * ADMIN DASHBOARD - SIMPLIFIED VERSION
 * Version with better error handling
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Admin check
if (!isLoggedIn()) {
    redirect('login.php');
    exit;
}

$user = getUserData();

if (!isset($user['is_admin']) || $user['is_admin'] != 1) {
    redirect('dashboard.php');
    exit;
}

// Page info for layout
$page_title = 'Dashboard';
$page_subtitle = 'Overview & Statistics';

// Initialize stats array
$stats = [
    'total_users' => 0,
    'total_products' => 0,
    'total_transactions' => 0,
    'total_revenue' => 0,
    'today_transactions' => 0,
    'today_revenue' => 0,
    'pending_transactions' => 0
];

$h2h_stats = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'processing' => 0,
    'pending' => 0
];

$recent_transactions = [];
$recent_h2h = [];

try {
    // Total Users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch()['count'];

    // Total Products
    $stmt = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
    $stats['total_products'] = $stmt->fetch()['count'];

    // Check which column name is used for total
    $stmt = $conn->query("SHOW COLUMNS FROM transactions LIKE '%total%'");
    $total_column = 'total'; // default
    while ($col = $stmt->fetch()) {
        if ($col['Field'] === 'total_harga') {
            $total_column = 'total_harga';
            break;
        }
    }

    // Total Transactions
    $stmt = $conn->query("SELECT COUNT(*) as count, SUM({$total_column}) as total FROM transactions");
    $trans_stats = $stmt->fetch();
    $stats['total_transactions'] = $trans_stats['count'] ?? 0;
    $stats['total_revenue'] = $trans_stats['total'] ?? 0;

    // Today's transactions
    $stmt = $conn->query("SELECT COUNT(*) as count, SUM({$total_column}) as total FROM transactions WHERE DATE(created_at) = CURDATE()");
    $today_stats = $stmt->fetch();
    $stats['today_transactions'] = $today_stats['count'] ?? 0;
    $stats['today_revenue'] = $today_stats['total'] ?? 0;

    // Pending transactions
    $stmt = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status IN ('pending', 'proses')");
    $stats['pending_transactions'] = $stmt->fetch()['count'] ?? 0;

    // H2H Statistics (only if table exists)
    $stmt = $conn->query("SHOW TABLES LIKE 'h2h_transactions'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
            FROM h2h_transactions
            WHERE DATE(created_at) = CURDATE()
        ");
        $h2h_stats = $stmt->fetch();
        
        // Recent H2H transactions
        $stmt = $conn->query("
            SELECT h.*, p.nama as product_name, u.username
            FROM h2h_transactions h
            LEFT JOIN products p ON h.product_id = p.id
            LEFT JOIN users u ON h.user_id = u.id
            WHERE DATE(h.created_at) = CURDATE()
            ORDER BY h.created_at DESC
            LIMIT 5
        ");
        $recent_h2h = $stmt->fetchAll();
    }

    // Recent transactions
    $stmt = $conn->query("
        SELECT t.*, u.username, u.email, p.nama as product_name 
        FROM transactions t 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN products p ON t.product_id = p.id 
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    $recent_transactions = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    // Continue with default values
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f23;
            color: #e2e8f0;
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 2rem;
            color: white;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
        }

        .nav-links a {
            padding: 0.75rem 1.5rem;
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 0.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: rgba(102, 126, 234, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: white;
        }

        .card {
            background: rgba(26, 26, 46, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card h2 {
            margin-bottom: 1rem;
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
                <p style="color: rgba(255,255,255,0.6);">Welcome, <?php echo htmlspecialchars($user['username']); ?></p>
            </div>
            <div class="nav-links">
                <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="admin-h2h.php"><i class="fas fa-sync-alt"></i> H2H Monitor</a>
                <a href="admin-products.php"><i class="fas fa-box"></i> Products</a>
                <a href="admin-transactions.php"><i class="fas fa-receipt"></i> Transactions</a>
                <a href="admin-users.php"><i class="fas fa-users"></i> Users</a>
                <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, rgba(240, 147, 251, 0.2), rgba(245, 87, 108, 0.2));">
                <div class="stat-label">Total Products</div>
                <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.2), rgba(0, 242, 254, 0.2));">
                <div class="stat-label">Today Transactions</div>
                <div class="stat-value"><?php echo number_format($stats['today_transactions']); ?></div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, rgba(67, 233, 123, 0.2), rgba(56, 249, 215, 0.2));">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatRupiah($stats['total_revenue']); ?></div>
            </div>
        </div>

        <!-- H2H Statistics -->
        <?php if (count($recent_h2h) > 0 || $h2h_stats['total'] > 0): ?>
        <div class="card">
            <h2><i class="fas fa-sync-alt"></i> H2H Transactions Today</h2>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem;">
                <div style="text-align: center; padding: 1rem; background: rgba(102, 126, 234, 0.1); border-radius: 0.5rem;">
                    <div style="font-size: 1.5rem; font-weight: 700;"><?php echo $h2h_stats['total']; ?></div>
                    <div style="font-size: 0.75rem; color: rgba(255,255,255,0.6);">Total</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 0.5rem;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $h2h_stats['success']; ?></div>
                    <div style="font-size: 0.75rem; color: rgba(255,255,255,0.6);">Success</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: rgba(245, 158, 11, 0.1); border-radius: 0.5rem;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $h2h_stats['processing']; ?></div>
                    <div style="font-size: 0.75rem; color: rgba(255,255,255,0.6);">Processing</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: 0.5rem;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444;"><?php echo $h2h_stats['failed']; ?></div>
                    <div style="font-size: 0.75rem; color: rgba(255,255,255,0.6);">Failed</div>
                </div>
            </div>

            <?php if (count($recent_h2h) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_h2h as $h2h): 
                        $badge_class = [
                            'success' => 'badge-success',
                            'failed' => 'badge-danger',
                            'processing' => 'badge-warning',
                            'pending' => 'badge-warning'
                        ];
                        $status_badge = $badge_class[$h2h['status']] ?? 'badge-warning';
                    ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($h2h['order_id']); ?></code></td>
                        <td><?php echo htmlspecialchars($h2h['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($h2h['username']); ?></td>
                        <td><span class="badge <?php echo $status_badge; ?>"><?php echo strtoupper($h2h['status']); ?></span></td>
                        <td><?php echo date('H:i:s', strtotime($h2h['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Transactions -->
        <div class="card">
            <h2><i class="fas fa-receipt"></i> Recent Transactions</h2>

            <?php if (count($recent_transactions) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Product</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $trans): 
                        $status_badge = [
                            'completed' => 'badge-success',
                            'selesai' => 'badge-success',
                            'pending' => 'badge-warning',
                            'proses' => 'badge-warning',
                            'failed' => 'badge-danger',
                            'gagal' => 'badge-danger'
                        ];
                        $badge_class = $status_badge[$trans['status']] ?? 'badge-warning';
                        $amount = isset($trans['total']) ? $trans['total'] : ($trans['total_harga'] ?? 0);
                    ?>
                    <tr>
                        <td><code><?php echo $trans['order_id'] ?? $trans['id']; ?></code></td>
                        <td><?php echo htmlspecialchars($trans['username']); ?></td>
                        <td><?php echo htmlspecialchars($trans['product_name'] ?: 'N/A'); ?></td>
                        <td><?php echo formatRupiah($amount); ?></td>
                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($trans['status']); ?></span></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                <p>No transactions yet</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>