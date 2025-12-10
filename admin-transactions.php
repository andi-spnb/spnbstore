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

// Page Configuration
$active_page = 'transactions';
$page_title = 'Kelola Transaksi';
$page_subtitle = 'Monitor dan kelola semua transaksi pelanggan';
$page_icon = 'fas fa-receipt';

$message = '';
$message_type = 'success';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $transaction_id = intval($_POST['transaction_id']);
    $new_status = trim($_POST['new_status']);
    $keterangan = trim($_POST['keterangan']);
    
    $valid_statuses = ['pending', 'proses', 'ready', 'selesai', 'gagal'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $conn->prepare("UPDATE transactions SET status = ?, keterangan = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$new_status, $keterangan, $transaction_id])) {
            $message = 'Status transaksi berhasil diupdate!';
            $message_type = 'success';
            
            // Send notification to user (optional)
            // sendTransactionNotification($transaction_id, $new_status);
        }
    }
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

// Build query
$query = "SELECT t.*, u.username, u.email, u.nama_lengkap, p.nama as product_name 
          FROM transactions t 
          LEFT JOIN users u ON t.user_id = u.id 
          LEFT JOIN products p ON t.product_id = p.id 
          WHERE 1=1";
$params = [];

// Status filter
if ($filter_status !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $filter_status;
}

// Date filter
if ($filter_date !== 'all') {
    switch ($filter_date) {
        case 'today':
            $query .= " AND DATE(t.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $query .= " AND DATE(t.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $query .= " AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND MONTH(t.created_at) = MONTH(NOW()) AND YEAR(t.created_at) = YEAR(NOW())";
            break;
    }
}

// Search filter
if (!empty($search)) {
    $query .= " AND (t.transaction_id LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR p.nama LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Cast limit to integer for security
$limit = intval($limit);
$query .= " ORDER BY t.created_at DESC LIMIT " . $limit;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get statistics
$stats = [];

// Status counts
$stmt = $conn->query("SELECT status, COUNT(*) as count FROM transactions GROUP BY status");
$status_counts = [];
while ($row = $stmt->fetch()) {
    $status_counts[$row['status']] = $row['count'];
}

// Total statistics
$stmt = $conn->query("SELECT 
    COUNT(*) as total_transactions,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'selesai' OR status = 'ready' THEN 1 ELSE 0 END) as success_count,
    SUM(CASE WHEN status = 'gagal' THEN 1 ELSE 0 END) as failed_count,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count,
    COALESCE(SUM(total_harga), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_harga ELSE 0 END), 0) as today_revenue
    FROM transactions");
$stats = $stmt->fetch();

// Success rate
$stats['success_rate'] = $stats['total_transactions'] > 0 
    ? round(($stats['success_count'] / $stats['total_transactions']) * 100, 1) 
    : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        .modal.active {
            display: block;
        }
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }
        .modal-container {
            position: relative;
            background: var(--dark-card);
            max-width: 700px;
            width: 90%;
            margin: 2rem auto;
            border-radius: 1rem;
            z-index: 10000;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--dark-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--dark-card);
            z-index: 10;
        }
        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.5rem;
            padding: 0.25rem;
            transition: color 0.3s;
        }
        .modal-close:hover {
            color: var(--danger-color);
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--dark-border);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            position: sticky;
            bottom: 0;
            background: var(--dark-card);
        }
        
        /* Transaction Detail */
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--dark-border);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .detail-value {
            font-weight: 600;
            text-align: right;
        }
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar > * {
            flex: 1;
            min-width: 150px;
        }
        .filter-bar .btn {
            flex: 0;
            white-space: nowrap;
        }
        
        /* Status Timeline */
        .status-timeline {
            position: relative;
            padding: 1rem 0;
        }
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            position: relative;
        }
        .timeline-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--text-muted);
            position: relative;
            z-index: 1;
        }
        .timeline-dot.active {
            width: 16px;
            height: 16px;
            background: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
        }
        
        @media (max-width: 768px) {
            .modal-container {
                width: 95%;
                margin: 1rem auto;
            }
            .modal-footer {
                flex-direction: column;
            }
            .modal-footer .btn {
                width: 100%;
            }
            .detail-row {
                flex-direction: column;
                gap: 0.25rem;
            }
            .detail-value {
                text-align: left;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
<?php require_once 'admin-sidebar.php'; ?>
            <div class="admin-content">
        <div class="admin-content-inner">
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="admin-header-content">
                <h1>
                    <i class="<?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>
                </h1>
                <p><?php echo $page_subtitle; ?></p>
            </div>
            <div class="admin-header-actions">
                <button onclick="exportTransactions()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export
                </button>
                <button onclick="refreshData()" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <div><?php echo htmlspecialchars($message); ?></div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-4 mb-4">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-label">Total Transaksi</div>
                <div class="stat-value"><?php echo number_format($stats['total_transactions']); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-chart-line"></i> All time
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo number_format($stats['pending_count']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-hourglass-half"></i> Menunggu pembayaran
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-label">Berhasil</div>
                <div class="stat-value"><?php echo number_format($stats['success_count']); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-trophy"></i> Success rate: <?php echo $stats['success_rate']; ?>%
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatRupiah($stats['total_revenue']); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-calendar-day"></i> Hari ini: <?php echo formatRupiah($stats['today_revenue']); ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-5 mb-4">
            <a href="?status=pending" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">⏳</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Pending</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning-color);">
                    <?php echo $status_counts['pending'] ?? 0; ?>
                </div>
            </a>
            <a href="?status=proses" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔄</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Proses</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--info-color);">
                    <?php echo $status_counts['proses'] ?? 0; ?>
                </div>
            </a>
            <a href="?status=ready" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Ready</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color);">
                    <?php echo $status_counts['ready'] ?? 0; ?>
                </div>
            </a>
            <a href="?status=selesai" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">🎉</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Selesai</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color);">
                    <?php echo $status_counts['selesai'] ?? 0; ?>
                </div>
            </a>
            <a href="?status=gagal" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">❌</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Gagal</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger-color);">
                    <?php echo $status_counts['gagal'] ?? 0; ?>
                </div>
            </a>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card mb-4">
            <form method="GET" class="filter-bar">
                <input type="text" name="search" class="form-control" placeholder="Cari ID, user, email..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="status" class="form-control">
                    <option value="all">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="proses" <?php echo $filter_status == 'proses' ? 'selected' : ''; ?>>Proses</option>
                    <option value="ready" <?php echo $filter_status == 'ready' ? 'selected' : ''; ?>>Ready</option>
                    <option value="selesai" <?php echo $filter_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                    <option value="gagal" <?php echo $filter_status == 'gagal' ? 'selected' : ''; ?>>Gagal</option>
                </select>
                
                <select name="date" class="form-control">
                    <option value="all">Semua Waktu</option>
                    <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="yesterday" <?php echo $filter_date == 'yesterday' ? 'selected' : ''; ?>>Kemarin</option>
                    <option value="week" <?php echo $filter_date == 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                    <option value="month" <?php echo $filter_date == 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                </select>
                
                <select name="limit" class="form-control">
                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20 Item</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 Item</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 Item</option>
                    <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200 Item</option>
                </select>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <a href="admin-transactions.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h2 style="font-size: 1.5rem; margin: 0;">
                    <i class="fas fa-list"></i> Daftar Transaksi
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">
                        (<?php echo count($transactions); ?> transaksi)
                    </span>
                </h2>
            </div>

            <?php if (count($transactions) > 0): ?>
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
                        <?php foreach ($transactions as $trans): 
                            $status_badge = [
                                'pending' => 'warning',
                                'proses' => 'info',
                                'ready' => 'success',
                                'selesai' => 'success',
                                'gagal' => 'danger'
                            ];
                            $badge_class = $status_badge[$trans['status']] ?? 'primary';
                            
                            $status_icon = [
                                'pending' => 'clock',
                                'proses' => 'spinner',
                                'ready' => 'check',
                                'selesai' => 'check-circle',
                                'gagal' => 'times-circle'
                            ];
                            $icon_class = $status_icon[$trans['status']] ?? 'info-circle';
                        ?>
                        <tr>
                            <td>
                                <code style="font-size: 0.85rem;"><?php echo $trans['transaction_id']; ?></code>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($trans['username']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($trans['email']); ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($trans['product_name'] ?: 'N/A'); ?></div>
                                <?php if (!empty($trans['data_akun'])): ?>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars(substr($trans['data_akun'], 0, 20)); ?>...
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 600; color: var(--success-color);">
                                <?php echo formatRupiah($trans['total_harga']); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $badge_class; ?>">
                                    <i class="fas fa-<?php echo $icon_class; ?>"></i>
                                    <?php echo ucfirst($trans['status']); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button onclick='viewDetail(<?php echo json_encode($trans); ?>)' class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick='updateStatus(<?php echo json_encode($trans); ?>)' class="btn btn-warning" style="padding: 0.5rem 1rem; font-size: 0.85rem;" title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
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
                <h3>Tidak Ada Transaksi</h3>
                <p>Belum ada transaksi yang sesuai dengan filter Anda.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail Transaction Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('detailModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-file-invoice"></i> Detail Transaksi</h3>
                <button onclick="closeModal('detailModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('detailModal')" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Tutup
                </button>
                <button onclick="printDetail()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('statusModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-sync-alt"></i> Update Status Transaksi</h3>
                <button onclick="closeModal('statusModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="transaction_id" id="status_transaction_id">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Info:</strong> Update status transaksi dengan hati-hati. 
                            User akan menerima notifikasi otomatis.
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">ID Transaksi:</div>
                        <div class="detail-value" id="status_display_id"></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">User:</div>
                        <div class="detail-value" id="status_display_user"></div>
                    </div>

                    <div class="detail-row" style="border-bottom: none; padding-bottom: 1rem;">
                        <div class="detail-label">Status Saat Ini:</div>
                        <div class="detail-value" id="status_display_current"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status Baru <span style="color: var(--danger-color);">*</span></label>
                        <select name="new_status" class="form-control" required>
                            <option value="pending">⏳ Pending - Menunggu Pembayaran</option>
                            <option value="proses">🔄 Proses - Sedang Diproses</option>
                            <option value="ready">✅ Ready - Siap Digunakan</option>
                            <option value="selesai">🎉 Selesai - Transaksi Selesai</option>
                            <option value="gagal">❌ Gagal - Transaksi Gagal</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Keterangan (Opsional)</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Tambahkan catatan atau keterangan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('statusModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
    <script src="assets/js/main.js"></script>
    <script>
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }

        // View Transaction Detail
        function viewDetail(transaction) {
            const content = `
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-info-circle"></i> Informasi Transaksi
                    </h4>
                    <div class="detail-row">
                        <div class="detail-label">ID Transaksi:</div>
                        <div class="detail-value"><code>${transaction.transaction_id}</code></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <span class="badge badge-${getStatusBadge(transaction.status)}">
                                ${transaction.status.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Tanggal:</div>
                        <div class="detail-value">${formatDate(transaction.created_at)}</div>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-user"></i> Informasi User
                    </h4>
                    <div class="detail-row">
                        <div class="detail-label">Username:</div>
                        <div class="detail-value">${transaction.username}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value">${transaction.email}</div>
                    </div>
                    ${transaction.nama_lengkap ? `
                    <div class="detail-row">
                        <div class="detail-label">Nama Lengkap:</div>
                        <div class="detail-value">${transaction.nama_lengkap}</div>
                    </div>
                    ` : ''}
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-box"></i> Informasi Produk
                    </h4>
                    <div class="detail-row">
                        <div class="detail-label">Produk:</div>
                        <div class="detail-value">${transaction.product_name || 'N/A'}</div>
                    </div>
                    ${transaction.data_akun ? `
                    <div class="detail-row">
                        <div class="detail-label">Data Akun:</div>
                        <div class="detail-value"><code>${transaction.data_akun}</code></div>
                    </div>
                    ` : ''}
                    ${transaction.jumlah ? `
                    <div class="detail-row">
                        <div class="detail-label">Jumlah:</div>
                        <div class="detail-value">${transaction.jumlah} unit</div>
                    </div>
                    ` : ''}
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-money-bill-wave"></i> Informasi Pembayaran
                    </h4>
                    <div class="detail-row">
                        <div class="detail-label">Total Harga:</div>
                        <div class="detail-value" style="font-size: 1.2rem; color: var(--success-color);">
                            ${formatRupiah(transaction.total_harga)}
                        </div>
                    </div>
                    ${transaction.payment_method ? `
                    <div class="detail-row">
                        <div class="detail-label">Metode Pembayaran:</div>
                        <div class="detail-value">${transaction.payment_method}</div>
                    </div>
                    ` : ''}
                </div>

                ${transaction.keterangan ? `
                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-sticky-note"></i> Keterangan
                    </h4>
                    <div style="background: var(--dark-bg); padding: 1rem; border-radius: 0.5rem; border-left: 4px solid var(--primary-color);">
                        ${transaction.keterangan}
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('detailContent').innerHTML = content;
            openModal('detailModal');
        }

        // Update Status
        function updateStatus(transaction) {
            document.getElementById('status_transaction_id').value = transaction.id;
            document.getElementById('status_display_id').textContent = transaction.transaction_id;
            document.getElementById('status_display_user').textContent = transaction.username;
            document.getElementById('status_display_current').innerHTML = `
                <span class="badge badge-${getStatusBadge(transaction.status)}">
                    ${transaction.status.toUpperCase()}
                </span>
            `;
            openModal('statusModal');
        }

        // Helper Functions
        function getStatusBadge(status) {
            const badges = {
                'pending': 'warning',
                'proses': 'info',
                'ready': 'success',
                'selesai': 'success',
                'gagal': 'danger'
            };
            return badges[status] || 'primary';
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit' 
            };
            return date.toLocaleDateString('id-ID', options);
        }

        function formatRupiah(amount) {
            return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
        }

        function printDetail() {
            window.print();
        }

        function exportTransactions() {
            alert('Fitur export sedang dalam pengembangan.\n\nAnda dapat menggunakan fitur print browser atau copy data dari tabel.');
        }

        function refreshData() {
            location.reload();
        }

        // Quick Search
        document.getElementById('quickSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });

        // Auto refresh every 60 seconds (optional)
        // setInterval(refreshData, 60000);
    </script>
</body>
</html>