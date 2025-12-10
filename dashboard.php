<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_transaksi, COALESCE(SUM(total_harga), 0) as total_pengeluaran FROM transactions WHERE user_id = ?");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

// Get today's transactions
$stmt = $conn->prepare("SELECT COUNT(*) as transaksi_hari_ini, COALESCE(SUM(total_harga), 0) as pengeluaran_hari_ini FROM transactions WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$user['id']]);
$today_stats = $stmt->fetch();

// Get recent transactions
$stmt = $conn->prepare("SELECT t.*, p.nama as product_name FROM transactions t 
                        LEFT JOIN products p ON t.product_id = p.id 
                        WHERE t.user_id = ? 
                        ORDER BY t.created_at DESC LIMIT 6");
$stmt->execute([$user['id']]);
$recent_transactions = $stmt->fetchAll();

// Get cart count
$stmt = $conn->prepare("SELECT COUNT(*) as cart_count FROM cart WHERE user_id = ?");
$stmt->execute([$user['id']]);
$cart_count = $stmt->fetch()['cart_count'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* PERBAIKAN RESPONSIVITAS */
        
        /* 1. Grid Statistik Responsive */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr; /* Jadi 1 kolom di HP */
                gap: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            /* Header Responsive */
            .card-header-wrapper {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 1rem;
            }
            
            .card-header-actions {
                width: 100%;
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            
            .card-header-actions input,
            .card-header-actions select {
                flex: 1; /* Input memenuhi lebar container */
            }
        }

        /* 2. Tabel Responsive & Scrollable */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch; /* Smooth scroll di iOS */
            margin-bottom: 1rem;
            border-radius: 0.5rem;
        }
        
        .table {
            width: 100%;
            min-width: 800px; /* Memaksa tabel lebar agar tidak gepeng */
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th, .table td {
            white-space: nowrap; /* Mencegah teks turun baris */
            padding: 1rem;
            vertical-align: middle;
        }

        /* 3. Tombol Aksi Responsive */
        .btn-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            padding: 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    <div class="container">
        <!-- Welcome Section -->
        <div style="margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Hi, <?php echo htmlspecialchars($user['nama_lengkap'] ?: $user['username']); ?></h1>
            <p style="color: var(--text-muted);">Selamat datang kembali di dashboard Anda</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-2" style="margin-bottom: 3rem;">
            <!-- Total Saldo -->
            <div class="stat-card">
                <div class="stat-label">
                    Total Saldo <i class="fas fa-info-circle" style="font-size: 0.8rem; margin-left: 0.25rem;"></i>
                </div>
                <div class="stat-value"><?php echo formatRupiah($user['saldo']); ?></div>
                <div style="position: absolute; right: 1rem; bottom: 1rem;">
                    <svg width="100" height="100" viewBox="0 0 200 200" style="opacity: 0.5;">
                        <g transform="translate(100,100)">
                            <circle r="40" fill="none" stroke="currentColor" stroke-width="3" opacity="0.2"/>
                            <path d="M -20,-30 L 0,-10 L 20,-30" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                            <rect x="-25" y="-5" width="50" height="40" rx="5" fill="none" stroke="currentColor" stroke-width="3"/>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- Transaksi Harian -->
            <div class="stat-card">
                <div class="stat-label">Transaksi Harian</div>
                <div class="stat-value"><?php echo formatRupiah($today_stats['pengeluaran_hari_ini']); ?></div>
                <div style="position: absolute; right: 1rem; bottom: 1rem;">
                    <svg width="100" height="100" viewBox="0 0 200 200" style="opacity: 0.5;">
                        <g transform="translate(100,100)">
                            <circle r="50" fill="none" stroke="currentColor" stroke-width="3" opacity="0.2"/>
                            <path d="M 0,-50 L 0,20 M -15,5 L 0,20 L 15,5" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- Total Transaksi -->
            <div class="stat-card">
                <div class="stat-label">
                    Total Transaksi <a href="riwayat.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.85rem; margin-left: 0.5rem;"><i class="fas fa-external-link-alt"></i></a>
                </div>
                <div class="stat-value"><?php echo formatRupiah($stats['total_pengeluaran']); ?></div>
                <div style="position: absolute; right: 1rem; bottom: 1rem;">
                    <svg width="100" height="100" viewBox="0 0 200 200" style="opacity: 0.5;">
                        <g transform="translate(100,100)">
                            <circle r="45" fill="none" stroke="currentColor" stroke-width="3" opacity="0.2"/>
                            <path d="M -30,0 L 30,0 M 0,-30 L 0,30" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- Keranjang -->
            <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='keranjang.php'">
                <div class="stat-label">Keranjang</div>
                <div style="display: flex; align-items: center; gap: 1rem; margin-top: 1rem;">
                    <div style="font-size: 1.5rem; font-weight: 600;">
                        <i class="fas fa-plus-circle" style="color: var(--primary-color);"></i> Tambahkan
                    </div>
                </div>
                <div style="position: absolute; right: 1rem; bottom: 1rem;">
                    <svg width="100" height="100" viewBox="0 0 200 200" style="opacity: 0.5;">
                        <g transform="translate(100,100)">
                            <rect x="-30" y="-10" width="60" height="50" rx="5" fill="none" stroke="currentColor" stroke-width="3"/>
                            <circle cx="-15" cy="50" r="5" fill="currentColor"/>
                            <circle cx="15" cy="50" r="5" fill="currentColor"/>
                        </g>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 class="card-header" style="margin-bottom: 0;">Riwayat Transaksi</h2>
                <div class="card-header-actions" style="display: flex; gap: 1rem;">
                    <input type="text" placeholder="ID Faktur" class="form-control search-input" style="min-width: 150px;">
                    <select class="form-control" style="min-width: 100px;">
                        <option>All</option>
                        <option>Pending</option>
                        <option>Proses</option>
                        <option>Ready</option>
                        <option>Selesai</option>
                    </select>
                    <select class="form-control" style="min-width: 70px;">
                        <option>10</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                </div>
            </div>

            <?php if (count($recent_transactions) > 0): ?>
            <!-- Wrapper Responsive Table -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>AKTIFITAS</th>
                            <th>ID TRANSAKSI</th>
                            <th>JUMLAH</th>
                            <th>HARGA</th>
                            <th>TANGGAL</th>
                            <th>STATUS</th>
                            <th width="100">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $index => $trans): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <span class="badge badge-primary">ORDER</span>
                            </td>
                            <td>
                                <span style="background: var(--dark-bg); padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-family: monospace; font-size: 0.85rem;">
                                    <?php echo $trans['transaction_id']; ?>
                                </span>
                            </td>
                            <td><?php echo $trans['jumlah']; ?> Item</td>
                            <td style="font-weight: 600;"><?php echo formatRupiah($trans['total_harga']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></td>
                            <td>
                                <?php 
                                $status_badge = [
                                    'ready' => 'success',
                                    'proses' => 'warning',
                                    'pending' => 'warning',
                                    'selesai' => 'success',
                                    'gagal' => 'danger'
                                ];
                                $badge_class = $status_badge[$trans['status']] ?? 'primary';
                                ?>
                                <span class="badge badge-<?php echo $badge_class; ?>"><?php echo ucfirst($trans['status']); ?></span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-icon" onclick="window.location.href='transaction-detail.php?id=<?php echo $trans['id']; ?>'">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-illustration">
                    <svg viewBox="0 0 200 200">
                        <circle cx="100" cy="100" r="80" fill="none" stroke="currentColor" stroke-width="2" opacity="0.2"/>
                        <path d="M 60,100 L 80,120 L 140,60" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/>
                    </svg>
                </div>
                <h3 class="empty-title">Belum Ada Transaksi</h3>
                <p class="empty-description">Mulai berbelanja untuk melihat riwayat transaksi Anda</p>
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Mulai Belanja
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
    // Search functionality - redirect to search.php
    const searchInput = document.querySelector('.search-input');
    
    if (searchInput) {
        // Handle Enter key to search
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    window.location.href = 'riwayat.php?search=' + encodeURIComponent(query);
                }
            }
        });
    }
    </script>
</body>
</html>