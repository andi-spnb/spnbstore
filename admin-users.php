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
$active_page = 'users';
$page_title = 'Kelola Users';
$page_subtitle = 'Manage user accounts, roles, dan balance';
$page_icon = 'fas fa-users';

$message = '';
$message_type = 'success';

// Handle Update User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    
    // Don't allow removing admin from self
    if ($user_id == $user['id']) {
        $is_admin = 1;
    }
    
    $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, is_admin = ? WHERE id = ?");
    if ($stmt->execute([$nama_lengkap, $email, $no_hp, $is_admin, $user_id])) {
        $message = 'User berhasil diupdate!';
        $message_type = 'success';
    } else {
        $message = 'Gagal mengupdate user!';
        $message_type = 'error';
    }
}

// Handle Update Saldo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_saldo'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    $amount = intval($_POST['amount']);
    $keterangan = trim($_POST['keterangan']);
    
    if ($amount > 0) {
        if ($action == 'add') {
            $stmt = $conn->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);
            $message = 'Saldo berhasil ditambahkan!';
        } elseif ($action == 'subtract') {
            $stmt = $conn->prepare("UPDATE users SET saldo = GREATEST(0, saldo - ?) WHERE id = ?");
            $stmt->execute([$amount, $user_id]);
            $message = 'Saldo berhasil dikurangi!';
        } elseif ($action == 'set') {
            $stmt = $conn->prepare("UPDATE users SET saldo = ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);
            $message = 'Saldo berhasil diset!';
        }
        
        // Log balance change (optional)
        // logBalanceChange($user_id, $action, $amount, $keterangan);
        
        $message_type = 'success';
    }
}

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = trim($_POST['new_password']);
    
    if (strlen($new_password) >= 6) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed_password, $user_id])) {
            $message = 'Password berhasil direset!';
            $message_type = 'success';
        }
    } else {
        $message = 'Password minimal 6 karakter!';
        $message_type = 'error';
    }
}

// Handle Delete User
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $user_id = intval($_GET['delete']);
    // Don't delete current admin
    if ($user_id != $user['id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $message = 'User berhasil dihapus!';
            $message_type = 'success';
        }
    } else {
        $message = 'Tidak dapat menghapus akun sendiri!';
        $message_type = 'error';
    }
}

// Get filter parameters
$filter_role = isset($_GET['role']) ? trim($_GET['role']) : 'all';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

// Role filter
if ($filter_role == 'admin') {
    $query .= " AND is_admin = 1";
} elseif ($filter_role == 'user') {
    $query .= " AND is_admin = 0";
}

// Date filter
if ($filter_date !== 'all') {
    switch ($filter_date) {
        case 'today':
            $query .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
            break;
    }
}

// Search filter
if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR nama_lengkap LIKE ? OR no_hp LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Cast limit to integer for security
$limit = intval($limit);
$query .= " ORDER BY created_at DESC LIMIT " . $limit;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stats = [];

// Total statistics
$stmt = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN is_admin = 0 THEN 1 ELSE 0 END) as user_count,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count,
    COALESCE(SUM(saldo), 0) as total_balance,
    COALESCE(AVG(saldo), 0) as avg_balance
    FROM users");
$stats = $stmt->fetch();

// Get top users by balance
$stmt = $conn->query("SELECT id, username, nama_lengkap, saldo FROM users ORDER BY saldo DESC LIMIT 5");
$top_users = $stmt->fetchAll();
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
            max-width: 600px;
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
        
        /* User Detail */
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
        
        /* Avatar Display */
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--dark-border);
        }
        .user-avatar-large {
            width: 80px;
            height: 80px;
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
                <button onclick="exportUsers()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export
                </button>
                <button onclick="refreshData()" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Admin Navigation -->
        <div class="admin-nav">
            <a href="admin.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="admin-products.php">
                <i class="fas fa-box"></i> Produk
            </a>
            <a href="admin-transactions.php">
                <i class="fas fa-receipt"></i> Transaksi
            </a>
            <a href="admin-users.php" class="active">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="admin-categories.php">
                <i class="fas fa-tags"></i> Kategori
            </a>
            <a href="admin-h2h.php">
                <i class="fas fa-exchange-alt"></i> H2H
            </a>
            <a href="admin-settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
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
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-user-plus"></i> All registered users
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-label">Admin Users</div>
                <div class="stat-value"><?php echo number_format($stats['admin_count']); ?></div>
                <div class="stat-change">
                    <i class="fas fa-shield-alt"></i> Admin accounts
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="stat-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-label">Total Balance</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatRupiah($stats['total_balance']); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-chart-line"></i> Avg: <?php echo formatRupiah($stats['avg_balance']); ?>
                </div>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="stat-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-label">New Users</div>
                <div class="stat-value"><?php echo number_format($stats['today_count']); ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-calendar-day"></i> Today / <?php echo $stats['week_count']; ?> this week
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-3 mb-4">
            <a href="?role=admin" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👑</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Admin Accounts</div>
                <div style="font-size: 1.75rem; font-weight: 700; color: var(--primary-color);">
                    <?php echo $stats['admin_count']; ?>
                </div>
            </a>
            <a href="?role=user" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👤</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Regular Users</div>
                <div style="font-size: 1.75rem; font-weight: 700; color: var(--info-color);">
                    <?php echo $stats['user_count']; ?>
                </div>
            </a>
            <a href="?date=today" class="card" style="text-align: center; text-decoration: none; transition: transform 0.3s;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🆕</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">New Today</div>
                <div style="font-size: 1.75rem; font-weight: 700; color: var(--success-color);">
                    <?php echo $stats['today_count']; ?>
                </div>
            </a>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card mb-4">
            <form method="GET" class="filter-bar">
                <input type="text" name="search" class="form-control" placeholder="Cari username, email, nama..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="role" class="form-control">
                    <option value="all">Semua Role</option>
                    <option value="admin" <?php echo $filter_role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="user" <?php echo $filter_role == 'user' ? 'selected' : ''; ?>>User</option>
                </select>
                
                <select name="date" class="form-control">
                    <option value="all">Semua Waktu</option>
                    <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
                    <option value="week" <?php echo $filter_date == 'week' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
                    <option value="month" <?php echo $filter_date == 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
                </select>
                
                <select name="limit" class="form-control">
                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20 Item</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 Item</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 Item</option>
                </select>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <a href="admin-users.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h2 style="font-size: 1.5rem; margin: 0;">
                    <i class="fas fa-list"></i> Daftar Users
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">
                        (<?php echo count($users); ?> users)
                    </span>
                </h2>
            </div>

            <?php if (count($users) > 0): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email & HP</th>
                            <th>Saldo</th>
                            <th>Role</th>
                            <th>Bergabung</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $usr): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <img src="assets/img/avatars/<?php echo $usr['avatar']; ?>.png" alt="Avatar" class="user-avatar">
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($usr['username']); ?></div>
                                        <?php if (!empty($usr['nama_lengkap'])): ?>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?php echo htmlspecialchars($usr['nama_lengkap']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;"><?php echo htmlspecialchars($usr['email']); ?></div>
                                <?php if (!empty($usr['no_hp'])): ?>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($usr['no_hp']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 600; color: var(--success-color);">
                                <?php echo formatRupiah($usr['saldo']); ?>
                            </td>
                            <td>
                                <?php if ($usr['is_admin']): ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-crown"></i> Admin
                                </span>
                                <?php else: ?>
                                <span class="badge badge-primary">
                                    <i class="fas fa-user"></i> User
                                </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?php echo date('d/m/Y', strtotime($usr['created_at'])); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button onclick='viewDetail(<?php echo json_encode($usr); ?>)' class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick='editUser(<?php echo json_encode($usr); ?>)' class="btn btn-warning" style="padding: 0.5rem 1rem; font-size: 0.85rem;" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick='manageSaldo(<?php echo json_encode($usr); ?>)' class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.85rem;" title="Kelola Saldo">
                                        <i class="fas fa-wallet"></i>
                                    </button>
                                    <?php if ($usr['id'] != $user['id']): ?>
                                    <button onclick="confirmDelete(<?php echo $usr['id']; ?>, '<?php echo htmlspecialchars($usr['username']); ?>')" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.85rem;" title="Hapus User">
                                        <i class="fas fa-trash"></i>
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
                    <i class="fas fa-user-slash"></i>
                </div>
                <h3>Tidak Ada User</h3>
                <p>Belum ada user yang sesuai dengan filter Anda.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Users by Balance -->
        <?php if (count($top_users) > 0): ?>
        <div class="card mt-4">
            <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem;">
                <i class="fas fa-trophy"></i> Top Users by Balance
            </h2>
            <div class="grid grid-5">
                <?php foreach ($top_users as $idx => $top): ?>
                <div class="card" style="text-align: center; background: var(--dark-bg); border: 2px solid var(--dark-border);">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                        <?php echo ['🥇', '🥈', '🥉', '🏅', '🏅'][$idx]; ?>
                    </div>
                    <div style="font-weight: 600; margin-bottom: 0.25rem;">
                        <?php echo htmlspecialchars($top['username']); ?>
                    </div>
                    <?php if (!empty($top['nama_lengkap'])): ?>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($top['nama_lengkap']); ?>
                    </div>
                    <?php endif; ?>
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--success-color);">
                        <?php echo formatRupiah($top['saldo']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Detail User Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('detailModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-user-circle"></i> Detail User</h3>
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

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('editModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit User</h3>
                <button onclick="closeModal('editModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" id="edit_username" class="form-control" disabled>
                        <small style="color: var(--text-muted);">Username tidak dapat diubah</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">No. HP</label>
                        <input type="text" name="no_hp" id="edit_no_hp" class="form-control">
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                            <input type="checkbox" name="is_admin" id="edit_is_admin" value="1" style="width: 20px; height: 20px;">
                            <span style="font-weight: 600;">
                                <i class="fas fa-crown"></i> Admin Privileges
                            </span>
                        </label>
                        <small style="color: var(--text-muted);">Berikan akses admin panel</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="update_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Saldo Modal -->
    <div id="saldoModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('saldoModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-wallet"></i> Kelola Saldo</h3>
                <button onclick="closeModal('saldoModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="saldo_user_id">
                <div class="modal-body">
                    <div class="detail-row">
                        <div class="detail-label">Username:</div>
                        <div class="detail-value" id="saldo_username"></div>
                    </div>

                    <div class="detail-row" style="border-bottom: none; padding-bottom: 1rem;">
                        <div class="detail-label">Saldo Saat Ini:</div>
                        <div class="detail-value" id="saldo_current" style="font-size: 1.2rem; color: var(--success-color);"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Aksi <span style="color: var(--danger-color);">*</span></label>
                        <select name="action" class="form-control" required>
                            <option value="add">➕ Tambah Saldo</option>
                            <option value="subtract">➖ Kurangi Saldo</option>
                            <option value="set">⚙️ Set Saldo (Override)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Jumlah (Rp) <span style="color: var(--danger-color);">*</span></label>
                        <input type="number" name="amount" class="form-control" required min="0" placeholder="10000">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Alasan perubahan saldo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('saldoModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="update_saldo" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Saldo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('passwordModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Reset Password</h3>
                <button onclick="closeModal('passwordModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="password_user_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Peringatan!</strong> Password akan direset dan user harus login ulang.
                        </div>
                    </div>

                    <div class="detail-row" style="border-bottom: none; padding-bottom: 1rem;">
                        <div class="detail-label">Username:</div>
                        <div class="detail-value" id="password_username"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password Baru <span style="color: var(--danger-color);">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password <span style="color: var(--danger-color);">*</span></label>
                        <input type="password" id="confirm_password" class="form-control" required minlength="6" placeholder="Ketik ulang password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('passwordModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="reset_password" class="btn btn-danger" onclick="return validatePassword()">
                        <i class="fas fa-key"></i> Reset Password
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

        // View User Detail
        function viewDetail(user) {
            const content = `
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <img src="assets/img/avatars/${user.avatar}.png" alt="Avatar" class="user-avatar user-avatar-large">
                    <h3 style="margin-top: 1rem; margin-bottom: 0.5rem;">${user.username}</h3>
                    ${user.nama_lengkap ? `<p style="color: var(--text-muted); margin: 0;">${user.nama_lengkap}</p>` : ''}
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-info-circle"></i> Informasi Akun
                    </h4>
                    <div class="detail-row">
                        <div class="detail-label">User ID:</div>
                        <div class="detail-value">#${user.id}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Username:</div>
                        <div class="detail-value">${user.username}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value">${user.email}</div>
                    </div>
                    ${user.no_hp ? `
                    <div class="detail-row">
                        <div class="detail-label">No. HP:</div>
                        <div class="detail-value">${user.no_hp}</div>
                    </div>
                    ` : ''}
                    <div class="detail-row">
                        <div class="detail-label">Role:</div>
                        <div class="detail-value">
                            <span class="badge badge-${user.is_admin == 1 ? 'danger' : 'primary'}">
                                ${user.is_admin == 1 ? '👑 Admin' : '👤 User'}
                            </span>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-wallet"></i> Informasi Saldo
                    </h4>
                    <div class="detail-row">
                        <div class="detail-label">Saldo Saat Ini:</div>
                        <div class="detail-value" style="font-size: 1.3rem; color: var(--success-color);">
                            ${formatRupiah(user.saldo)}
                        </div>
                    </div>
                </div>

                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <i class="fas fa-clock"></i> Informasi Waktu
                    </h4>
                    <div class="detail-row">
                        <div class="detail-label">Bergabung:</div>
                        <div class="detail-value">${formatDate(user.created_at)}</div>
                    </div>
                    ${user.updated_at ? `
                    <div class="detail-row">
                        <div class="detail-label">Update Terakhir:</div>
                        <div class="detail-value">${formatDate(user.updated_at)}</div>
                    </div>
                    ` : ''}
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button onclick="closeModal('detailModal'); editUser(${JSON.stringify(user).replace(/"/g, '&quot;')})" class="btn btn-warning" style="flex: 1;">
                        <i class="fas fa-edit"></i> Edit User
                    </button>
                    <button onclick="closeModal('detailModal'); manageSaldo(${JSON.stringify(user).replace(/"/g, '&quot;')})" class="btn btn-success" style="flex: 1;">
                        <i class="fas fa-wallet"></i> Kelola Saldo
                    </button>
                    <button onclick="closeModal('detailModal'); resetPassword(${JSON.stringify(user).replace(/"/g, '&quot;')})" class="btn btn-danger" style="flex: 1;">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </div>
            `;
            
            document.getElementById('detailContent').innerHTML = content;
            openModal('detailModal');
        }

        // Edit User
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_nama_lengkap').value = user.nama_lengkap || '';
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_no_hp').value = user.no_hp || '';
            document.getElementById('edit_is_admin').checked = user.is_admin == 1;
            openModal('editModal');
        }

        // Manage Saldo
        function manageSaldo(user) {
            document.getElementById('saldo_user_id').value = user.id;
            document.getElementById('saldo_username').textContent = user.username;
            document.getElementById('saldo_current').textContent = formatRupiah(user.saldo);
            openModal('saldoModal');
        }

        // Reset Password
        function resetPassword(user) {
            document.getElementById('password_user_id').value = user.id;
            document.getElementById('password_username').textContent = user.username;
            openModal('passwordModal');
        }

        // Validate Password
        function validatePassword() {
            const password = document.querySelector('input[name="new_password"]').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                alert('Password dan konfirmasi password tidak cocok!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password minimal 6 karakter!');
                return false;
            }
            
            return confirm('Apakah Anda yakin ingin mereset password user ini?');
        }

        // Delete User
        function confirmDelete(id, username) {
            if (confirm('Apakah Anda yakin ingin menghapus user "' + username + '"?\n\nPeringatan: Tindakan ini tidak dapat dibatalkan!')) {
                window.location.href = 'admin-users.php?delete=' + id + '&confirm=1';
            }
        }

        // Helper Functions
        function formatRupiah(amount) {
            return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
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

        function exportUsers() {
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
    </script>
</body>
</html>