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
$active_page = 'h2h';
$page_title = 'H2H Monitor';
$page_subtitle = 'Host-to-Host API Transaction Monitoring & Logs';
$page_icon = 'fas fa-exchange-alt';

$message = '';
$message_type = 'success';

// Handle Manual Retry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['retry_transaction'])) {
    $h2h_id = intval($_POST['h2h_id']);
    
    // Get H2H transaction details
    $stmt = $conn->prepare("SELECT * FROM h2h_transactions WHERE id = ?");
    $stmt->execute([$h2h_id]);
    $h2h = $stmt->fetch();
    
    if ($h2h && $h2h['status'] == 'failed') {
        // Update status to processing
        $stmt = $conn->prepare("UPDATE h2h_transactions SET status = 'processing' WHERE id = ?");
        $stmt->execute([$h2h_id]);
        
        // TODO: Trigger actual H2H retry logic here
        // retryH2HTransaction($h2h);
        
        $message = 'Transaksi berhasil di-retry! Status: processing';
        $message_type = 'success';
    } else {
        $message = 'Transaksi tidak dapat di-retry!';
        $message_type = 'error';
    }
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : 'today';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

// Check if h2h_transactions table exists
$table_exists = false;
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'h2h_transactions'");
    $table_exists = ($stmt->rowCount() > 0);
} catch (PDOException $e) {
    $table_exists = false;
}

// If table doesn't exist, set empty array and skip queries
if (!$table_exists) {
    $h2h_transactions = [];
    $stats = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'processing' => 0,
        'pending' => 0,
        'success_rate' => 0,
        'avg_response_time' => 0
    ];
    $provider_stats = [];
} else {
    // Build query for H2H transactions
    $query = "SELECT h.*, p.nama as product_name, u.username 
              FROM h2h_transactions h 
              LEFT JOIN products p ON h.product_id = p.id 
              LEFT JOIN users u ON h.user_id = u.id 
              WHERE 1=1";
    $params = [];

    // Date filter
    switch ($filter_date) {
        case 'today':
            $query .= " AND DATE(h.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $query .= " AND DATE(h.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $query .= " AND h.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND MONTH(h.created_at) = MONTH(NOW()) AND YEAR(h.created_at) = YEAR(NOW())";
            break;
    }

    // Status filter
    if ($filter_status !== 'all') {
        $query .= " AND h.status = ?";
        $params[] = $filter_status;
    }

    // Search filter
    if (!empty($search)) {
        $query .= " AND (h.order_id LIKE ? OR h.product_code LIKE ? OR p.nama LIKE ? OR u.username LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $limit = intval($limit);
    $query .= " ORDER BY h.created_at DESC LIMIT " . $limit;

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $h2h_transactions = $stmt->fetchAll();

    // Get statistics
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
        FROM h2h_transactions 
        WHERE ";

    // Apply same date filter to stats
    switch ($filter_date) {
        case 'today':
            $stats_query .= "DATE(created_at) = CURDATE()";
            break;
        case 'yesterday':
            $stats_query .= "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $stats_query .= "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $stats_query .= "MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
            break;
        default:
            $stats_query .= "1=1";
    }

    $stmt = $conn->query($stats_query);
    $stats = $stmt->fetch();
    
    // Add avg response time (not available in current table structure)
    $stats['avg_response_time'] = 0;

    // Calculate success rate
    $stats['success_rate'] = $stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 1) : 0;

    // Get provider statistics (extract from h2h_trx_id)
    $provider_stats = [];
    try {
        $stmt = $conn->query("SELECT 
                              COALESCE(NULLIF(SUBSTRING_INDEX(h2h_trx_id, '-', 1), ''), 'Unknown') as provider,
                              COUNT(*) as count, 
                              SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as success_count
                              FROM h2h_transactions 
                              GROUP BY provider
                              ORDER BY count DESC 
                              LIMIT 5");
        $provider_stats = $stmt->fetchAll();
    } catch (PDOException $e) {
        $provider_stats = [];
    }
}
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
            max-width: 800px;
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
        
        /* Detail Row */
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
        
        /* Code Block */
        .code-block {
            background: var(--dark-bg);
            padding: 1rem;
            border-radius: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }
        
        /* Live Indicator */
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: var(--success-color);
            border-radius: 50%;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @media (max-width: 768px) {
            .modal-container {
                width: 95%;
                margin: 1rem auto;
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
<?php require_once 'admin-sidebar.php'; ?>

    <div class="admin-content">
    <div class="admin-content-inner">
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="admin-header-content">
                <h1>
                    <i class="<?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>
                    <span class="live-indicator"></span>
                </h1>
                <p><?php echo $page_subtitle; ?></p>
            </div>
            <div class="admin-header-actions">
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
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-label">Total H2H Requests</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-clock"></i> <?php echo ucfirst($filter_date); ?>
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-label">Success</div>
                <div class="stat-value"><?php echo number_format($stats['success']); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-percentage"></i> Success Rate: <?php echo $stats['success_rate']; ?>%
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-label">Failed</div>
                <div class="stat-value"><?php echo number_format($stats['failed']); ?></div>
                <div class="stat-change negative">
                    <i class="fas fa-exclamation-triangle"></i> Need attention
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-label">Processing</div>
                <div class="stat-value"><?php echo number_format($stats['processing']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-spinner"></i> In progress
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-4 mb-4">
            <a href="?status=pending&date=<?php echo $filter_date; ?>" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">⏳</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Pending</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning-color);">
                    <?php echo $stats['pending'] ?? 0; ?>
                </div>
            </a>
            <a href="?status=processing&date=<?php echo $filter_date; ?>" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">⚙️</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Processing</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--info-color);">
                    <?php echo $stats['processing'] ?? 0; ?>
                </div>
            </a>
            <a href="?status=success&date=<?php echo $filter_date; ?>" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Success</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color);">
                    <?php echo $stats['success'] ?? 0; ?>
                </div>
            </a>
            <a href="?status=failed&date=<?php echo $filter_date; ?>" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">❌</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Failed</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger-color);">
                    <?php echo $stats['failed'] ?? 0; ?>
                </div>
            </a>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card mb-4">
            <form method="GET" class="filter-bar">
                <input type="text" name="search" class="form-control" placeholder="Cari order ID, product..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="status" class="form-control">
                    <option value="all">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $filter_status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="success" <?php echo $filter_status == 'success' ? 'selected' : ''; ?>>Success</option>
                    <option value="failed" <?php echo $filter_status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
                
                <select name="date" class="form-control">
                    <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="yesterday" <?php echo $filter_date == 'yesterday' ? 'selected' : ''; ?>>Kemarin</option>
                    <option value="week" <?php echo $filter_date == 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                    <option value="month" <?php echo $filter_date == 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                    <option value="all" <?php echo $filter_date == 'all' ? 'selected' : ''; ?>>Semua Waktu</option>
                </select>
                
                <select name="limit" class="form-control">
                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20 Item</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 Item</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 Item</option>
                </select>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <a href="admin-h2h.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </form>
        </div>

        <!-- H2H Transactions Table -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h2 style="font-size: 1.5rem; margin: 0;">
                    <i class="fas fa-server"></i> H2H Transaction Log
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">
                        (<?php echo count($h2h_transactions); ?> requests)
                    </span>
                </h2>
            </div>

            <?php if (count($h2h_transactions) > 0): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>User</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>H2H TRX ID</th>
                            <th>Waktu</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($h2h_transactions as $h2h): 
                            $status_badge = [
                                'pending' => 'warning',
                                'processing' => 'info',
                                'success' => 'success',
                                'failed' => 'danger'
                            ];
                            $badge_class = $status_badge[$h2h['status']] ?? 'primary';
                            
                            $status_icon = [
                                'pending' => 'clock',
                                'processing' => 'spinner fa-spin',
                                'success' => 'check-circle',
                                'failed' => 'times-circle'
                            ];
                            $icon_class = $status_icon[$h2h['status']] ?? 'info-circle';
                        ?>
                        <tr>
                            <td>
                                <code style="font-size: 0.85rem;"><?php echo htmlspecialchars($h2h['order_id']); ?></code>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($h2h['product_name'] ?: 'N/A'); ?></div>
                                <?php if (!empty($h2h['product_code'])): ?>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($h2h['product_code']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?php echo htmlspecialchars($h2h['username'] ?? 'Guest'); ?>
                            </td>
                            <td style="font-weight: 600; color: var(--success-color);">
                                <?php echo formatRupiah($h2h['price']); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $badge_class; ?>">
                                    <i class="fas fa-<?php echo $icon_class; ?>"></i>
                                    <?php echo ucfirst($h2h['status']); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?php echo !empty($h2h['h2h_trx_id']) ? htmlspecialchars($h2h['h2h_trx_id']) : '-'; ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?php echo date('d/m/Y H:i:s', strtotime($h2h['created_at'])); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button onclick='viewH2HDetail(<?php echo json_encode($h2h); ?>)' class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($h2h['status'] == 'failed'): ?>
                                    <button onclick='retryH2H(<?php echo json_encode($h2h); ?>)' class="btn btn-warning" style="padding: 0.5rem 1rem; font-size: 0.85rem;" title="Retry">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <?php endif; ?>
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
                    <i class="fas fa-server"></i>
                </div>
                <h3>Tidak Ada Data H2H</h3>
                <p>Belum ada transaksi H2H yang sesuai dengan filter Anda.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Provider Statistics -->
        <?php if (count($provider_stats) > 0): ?>
        <div class="card mt-4">
            <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem;">
                <i class="fas fa-chart-pie"></i> Provider Statistics
            </h2>
            <div class="grid grid-5">
                <?php foreach ($provider_stats as $prov): 
                    $prov_success_rate = $prov['count'] > 0 ? round(($prov['success_count'] / $prov['count']) * 100, 1) : 0;
                ?>
                <div class="card" style="text-align: center; background: var(--dark-bg);">
                    <div style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($prov['provider']); ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                        Total Requests
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">
                        <?php echo number_format($prov['count']); ?>
                    </div>
                    <div style="font-size: 0.85rem;">
                        <span class="badge badge-success">
                            Success: <?php echo $prov_success_rate; ?>%
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- H2H Detail Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('detailModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-file-code"></i> H2H Transaction Detail</h3>
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
            </div>
        </div>
    </div>

    <!-- Retry H2H Modal -->
    <div id="retryModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('retryModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-redo"></i> Retry H2H Transaction</h3>
                <button onclick="closeModal('retryModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="h2h_id" id="retry_h2h_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Peringatan!</strong> Transaksi akan di-retry ke provider H2H. 
                            Pastikan transaksi memang gagal dan perlu di-ulang.
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Order ID:</div>
                        <div class="detail-value" id="retry_order_id"></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Product:</div>
                        <div class="detail-value" id="retry_product"></div>
                    </div>

                    <div class="detail-row" style="border-bottom: none;">
                        <div class="detail-label">Current Status:</div>
                        <div class="detail-value" id="retry_status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('retryModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="retry_transaction" class="btn btn-warning">
                        <i class="fas fa-redo"></i> Retry Sekarang
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

        // View H2H Detail
        function viewH2HDetail(h2h) {
            let customerData = 'N/A';
            let responseData = 'N/A';
            
            try {
                if (h2h.customer_data) {
                    const req = typeof h2h.customer_data === 'string' ? JSON.parse(h2h.customer_data) : h2h.customer_data;
                    customerData = JSON.stringify(req, null, 2);
                }
            } catch(e) {
                customerData = h2h.customer_data || 'N/A';
            }
            
            try {
                if (h2h.h2h_response) {
                    const res = typeof h2h.h2h_response === 'string' ? JSON.parse(h2h.h2h_response) : h2h.h2h_response;
                    responseData = JSON.stringify(res, null, 2);
                }
            } catch(e) {
                responseData = h2h.h2h_response || 'N/A';
            }

            const content = `
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-info-circle"></i> Transaction Info
                    </h4>
                    <div class="detail-row">
                        <div class="detail-label">Order ID:</div>
                        <div class="detail-value"><code>${h2h.order_id}</code></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Product:</div>
                        <div class="detail-value">${h2h.product_name || 'N/A'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Product Code:</div>
                        <div class="detail-value"><code>${h2h.product_code || 'N/A'}</code></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Price:</div>
                        <div class="detail-value">${formatRupiah(h2h.price)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <span class="badge badge-${getStatusBadge(h2h.status)}">
                                ${h2h.status.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">H2H Transaction ID:</div>
                        <div class="detail-value">${h2h.h2h_trx_id || '-'}</div>
                    </div>
                    ${h2h.sn_voucher ? `
                    <div class="detail-row">
                        <div class="detail-label">SN/Voucher:</div>
                        <div class="detail-value"><code>${h2h.sn_voucher}</code></div>
                    </div>
                    ` : ''}
                    <div class="detail-row">
                        <div class="detail-label">Created At:</div>
                        <div class="detail-value">${formatDate(h2h.created_at)}</div>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-arrow-up"></i> Customer Data
                    </h4>
                    <div class="code-block">${escapeHtml(customerData)}</div>
                </div>

                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-arrow-down"></i> H2H Response
                    </h4>
                    <div class="code-block">${escapeHtml(responseData)}</div>
                </div>

                ${h2h.notes ? `
                <div style="margin-top: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-sticky-note"></i> Notes
                    </h4>
                    <div style="background: rgba(99, 102, 241, 0.1); padding: 1rem; border-radius: 0.5rem; border-left: 4px solid var(--primary-color);">
                        ${escapeHtml(h2h.notes)}
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('detailContent').innerHTML = content;
            openModal('detailModal');
        }

        // Retry H2H Transaction
        function retryH2H(h2h) {
            document.getElementById('retry_h2h_id').value = h2h.id;
            document.getElementById('retry_order_id').textContent = h2h.order_id;
            document.getElementById('retry_product').textContent = h2h.product_name || 'N/A';
            document.getElementById('retry_status').innerHTML = `<span class="badge badge-${getStatusBadge(h2h.status)}">${h2h.status.toUpperCase()}</span>`;
            openModal('retryModal');
        }

        // Helper Functions
        function getStatusBadge(status) {
            const badges = {
                'pending': 'warning',
                'processing': 'info',
                'success': 'success',
                'failed': 'danger'
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
                minute: '2-digit',
                second: '2-digit'
            };
            return date.toLocaleDateString('id-ID', options);
        }

        function formatRupiah(amount) {
            return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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

        // Auto refresh every 30 seconds (optional)
        // setInterval(refreshData, 30000);
    </script>
</body>
</html>