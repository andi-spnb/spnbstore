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

// Initialize stats array with error handling
$stats = [
    'total_users' => 0,
    'total_products' => 0,
    'total_transactions' => 0,
    'total_revenue' => 0,
    'today_transactions' => 0,
    'today_revenue' => 0,
    'pending_transactions' => 0,
    'success_rate' => 0
];

try {
    // Total Users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Total Products
    $stmt = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
    $stats['total_products'] = $stmt->fetch()['count'];
    
    // Total Transactions and Revenue
    $stmt = $conn->query("SELECT 
        COUNT(*) as count, 
        COALESCE(SUM(total_harga), 0) as total,
        COUNT(CASE WHEN status = 'selesai' OR status = 'ready' THEN 1 END) as completed
        FROM transactions");
    $trans_stats = $stmt->fetch();
    $stats['total_transactions'] = $trans_stats['count'];
    $stats['total_revenue'] = $trans_stats['total'];
    
    // Success rate
    if ($stats['total_transactions'] > 0) {
        $stats['success_rate'] = round(($trans_stats['completed'] / $stats['total_transactions']) * 100, 1);
    }
    
    // Today's transactions
    $stmt = $conn->query("SELECT 
        COUNT(*) as count, 
        COALESCE(SUM(total_harga), 0) as total 
        FROM transactions 
        WHERE DATE(created_at) = CURDATE()");
    $today_stats = $stmt->fetch();
    $stats['today_transactions'] = $today_stats['count'];
    $stats['today_revenue'] = $today_stats['total'];
    
    // Pending transactions
    $stmt = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status IN ('pending', 'proses')");
    $stats['pending_transactions'] = $stmt->fetch()['count'];
    
    // Recent transactions
    $stmt = $conn->query("SELECT t.*, u.username, u.email, p.nama as product_name 
                          FROM transactions t 
                          LEFT JOIN users u ON t.user_id = u.id 
                          LEFT JOIN products p ON t.product_id = p.id 
                          ORDER BY t.created_at DESC LIMIT 10");
    $recent_transactions = $stmt->fetchAll();
    
    // Recent users
    $stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $recent_users = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Include Sidebar -->
    <?php require_once 'admin-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-content">
        <div class="admin-content-inner">
            <!-- Admin Header -->
            <div class="admin-header">
                <div class="admin-header-content">
                    <h1>
                        <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                    </h1>
                    <p>Selamat datang kembali, <?php echo htmlspecialchars($user['nama_lengkap'] ?: $user['username']); ?> • <?php echo date('l, d F Y H:i'); ?></p>
                </div>
                <div class="admin-header-actions">
                    <a href="admin-products.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Produk
                    </a>
                    <a href="admin-settings.php" class="btn btn-secondary">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-4 mb-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Registered accounts
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-label">Total Produk</div>
                    <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Active products
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-label">Total Transaksi</div>
                    <div class="stat-value"><?php echo number_format($stats['total_transactions']); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-chart-line"></i> All transactions
                    </div>
                </div>

                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatRupiah($stats['total_revenue']); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-trophy"></i> All time earnings
                    </div>
                </div>
            </div>

            <!-- Today's Stats & Quick Info -->
            <div class="grid grid-3 mb-4">
                <div class="card">
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                        <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-muted);">Transaksi Hari Ini</h3>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">
                            <?php echo number_format($stats['today_transactions']); ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--text-muted);">
                            <?php echo formatRupiah($stats['today_revenue']); ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">⏳</div>
                        <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-muted);">Pending Transaksi</h3>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color); margin-bottom: 0.5rem;">
                            <?php echo number_format($stats['pending_transactions']); ?>
                        </div>
                        <a href="admin-transactions.php?status=pending" style="font-size: 0.85rem; color: var(--primary-color); text-decoration: none;">
                            Lihat semua <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                        <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-muted);">Success Rate</h3>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success-color); margin-bottom: 0.5rem;">
                            <?php echo $stats['success_rate']; ?>%
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            Completion rate
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card mb-4">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 style="font-size: 1.5rem; margin: 0;">
                        <i class="fas fa-receipt"></i> Transaksi Terbaru
                    </h2>
                    <a href="admin-transactions.php" class="btn btn-secondary">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (count($recent_transactions) > 0): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID Transaksi</th>
                                <th>User</th>
                                <th>Produk</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $trans): 
                                $status_badge = [
                                    'ready' => 'success',
                                    'proses' => 'warning',
                                    'pending' => 'warning',
                                    'selesai' => 'success',
                                    'gagal' => 'danger'
                                ];
                                $badge_class = $status_badge[$trans['status']] ?? 'primary';
                            ?>
                            <tr>
                                <td><code style="font-size: 0.85rem;"><?php echo $trans['transaction_id']; ?></code></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($trans['username']); ?></div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($trans['email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($trans['product_name'] ?: 'N/A'); ?></td>
                                <td style="font-weight: 600;"><?php echo formatRupiah($trans['total_harga']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($trans['status']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.85rem;"><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></td>
                                <td>
                                    <a href="transaction-detail.php?id=<?php echo $trans['transaction_id']; ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3>Belum Ada Transaksi</h3>
                    <p>Transaksi akan muncul di sini setelah ada pesanan masuk</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Users -->
            <div class="card mb-4">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 style="font-size: 1.5rem; margin: 0;">
                        <i class="fas fa-users"></i> User Terbaru
                    </h2>
                    <a href="admin-users.php" class="btn btn-secondary">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (count($recent_users) > 0): ?>
                <div class="grid grid-5">
                    <?php foreach ($recent_users as $recent_user): ?>
                    <div class="card" style="padding: 1.25rem; text-align: center; background: var(--dark-bg); border: 1px solid var(--dark-border);">
                        <div class="avatar-btn" style="width: 60px; height: 60px; margin: 0 auto 0.75rem;">
                            <img src="assets/img/avatars/<?php echo $recent_user['avatar']; ?>.png" alt="Avatar">
                        </div>
                        <div style="font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">
                            <?php echo htmlspecialchars($recent_user['username']); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($recent_user['email']); ?>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--success-color); font-weight: 600;">
                            <?php echo formatRupiah($recent_user['saldo']); ?>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem;">
                            <i class="fas fa-clock"></i> <?php echo date('d M Y', strtotime($recent_user['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <h3>Belum Ada User</h3>
                    <p>User yang mendaftar akan muncul di sini</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h2>
                <div class="quick-actions">
                    <a href="admin-products.php?action=add" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>
                        <span>Tambah Produk</span>
                    </a>
                    <a href="admin-categories.php?action=add" class="quick-action-btn">
                        <i class="fas fa-tag"></i>
                        <span>Tambah Kategori</span>
                    </a>
                    <a href="admin-transactions.php?status=pending" class="quick-action-btn">
                        <i class="fas fa-clock"></i>
                        <span>Pending Order</span>
                    </a>
                    <a href="admin-users.php" class="quick-action-btn">
                        <i class="fas fa-users"></i>
                        <span>Kelola Users</span>
                    </a>
                    <a href="backup-database.php" class="quick-action-btn">
                        <i class="fas fa-database"></i>
                        <span>Backup Data</span>
                    </a>
                    <a href="admin-settings.php" class="quick-action-btn">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Auto refresh stats every 30 seconds
        setInterval(function() {
            // Optional: Add AJAX refresh for stats without page reload
            console.log('Stats refresh...');
        }, 30000);
    </script>
</body>
</html>