<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validate and sanitize limit
$limit = max(1, min(100, $limit));

// Build query
$query = "SELECT t.*, p.nama as product_name FROM transactions t
          LEFT JOIN products p ON t.product_id = p.id
          WHERE t.user_id = ?";
$params = [$user['id']];

if ($filter_status !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $query .= " AND t.transaction_id LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY t.created_at DESC LIMIT " . intval($limit);

$stmt = $conn->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* RESPONSIVE STYLES */
        
        /* 1. Header & Filter Layout */
        .header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Di Mobile, form filter stack ke bawah */
        @media (max-width: 768px) {
            .header-wrapper {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-form {
                width: 100%;
                flex-direction: column;
                gap: 0.75rem;
            }

            .filter-form input,
            .filter-form select {
                width: 100% !important; /* Force full width on mobile */
                height: 45px; /* Tinggi tombol agar mudah di-tap */
            }
            
            .card {
                padding: 1rem;
            }
        }

        /* 2. Tabel Responsive */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--border-light, rgba(255,255,255,0.1));
            border-radius: 0.5rem;
        }

        .table {
            width: 100%;
            min-width: 900px; /* Pastikan tabel cukup lebar untuk konten */
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th, .table td {
            white-space: nowrap; /* Mencegah wrap */
            padding: 1rem;
            vertical-align: middle;
        }

        /* 3. Tombol Aksi */
        .btn-action {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            padding: 0;
            transition: transform 0.2s;
        }
        
        .btn-action:active {
            transform: scale(0.95);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="card">
            <!-- Header & Filters -->
            <div class="header-wrapper">
                <h2 class="card-header" style="margin-bottom: 0;">Riwayat Transaksi</h2>
                
                <!-- Filters Form -->
                <form method="GET" class="filter-form">
                    <input type="text" 
                           name="search" 
                           placeholder="Cari ID Faktur..." 
                           class="form-control" 
                           style="width: 200px;"
                           value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="status" class="form-control" style="width: 120px;" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="proses" <?php echo $filter_status === 'proses' ? 'selected' : ''; ?>>Proses</option>
                        <option value="ready" <?php echo $filter_status === 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="selesai" <?php echo $filter_status === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                    </select>
                    
                    <select name="limit" class="form-control" style="width: 80px;" onchange="this.form.submit()">
                        <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10 Baris</option>
                        <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25 Baris</option>
                        <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50 Baris</option>
                        <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100 Baris</option>
                    </select>
                </form>
            </div>

            <?php if (count($transactions) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>ID TRANSAKSI</th>
                            <th>PRODUK</th>
                            <th>JUMLAH</th>
                            <th>TOTAL HARGA</th>
                            <th>TANGGAL</th>
                            <th>STATUS</th>
                            <th width="100">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $index => $trans): 
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
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <code style="background: var(--dark-bg); padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.85rem; color: var(--primary-color);">
                                    <?php echo $trans['transaction_id']; ?>
                                </code>
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($trans['product_name'] ?? 'Produk Terhapus'); ?>
                            </td>
                            <td><?php echo $trans['jumlah']; ?> x</td>
                            <td style="font-weight: 600;"><?php echo formatRupiah($trans['total_harga']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $badge_class; ?>">
                                    <?php echo ucfirst($trans['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-action" 
                                        onclick="viewTransaction('<?php echo $trans['transaction_id']; ?>')" 
                                        title="Lihat Detail">
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
                <div style="width: 120px; height: 120px; margin: 0 auto 1.5rem; opacity: 0.5;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </div>
                <h3 style="margin-bottom: 0.5rem;">Tidak Ada Transaksi</h3>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                    <?php if (!empty($search) || $filter_status !== 'all'): ?>
                        Tidak ditemukan data yang cocok dengan filter.
                    <?php else: ?>
                        Anda belum melakukan transaksi apapun.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                <a href="riwayat.php" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Reset Filter
                </a>
                <?php else: ?>
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Belanja Sekarang
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaction Detail Modal -->
    <div id="transactionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
        <div class="card" style="max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; margin: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light, #eee); padding-bottom: 1rem;">
                <h3 style="margin:0;">Detail Transaksi</h3>
                <button onclick="closeModal()" class="btn btn-secondary btn-action">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="transactionDetail">
                <!-- Content loaded via JS -->
                <div style="text-align:center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewTransaction(transactionId) {
            const modal = document.getElementById('transactionModal');
            const content = document.getElementById('transactionDetail');
            
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Memuat...</div>';
            
            // Ganti URL ini sesuai dengan endpoint API Anda
            // Jika file detail ada di transaction-detail.php?id=...
            // Kita bisa redirect atau fetch partial content.
            // Di sini saya asumsikan fetch JSON seperti kode asli Anda.
            
            // Untuk demo fleksibel, jika API belum ada, kita arahkan ke halaman detail
            // window.location.href = 'transaction-detail.php?id=' + transactionId;
            
            // Tapi mengikuti kode asli Anda (fetch API):
            fetch('get-transaction.php?tid=' + transactionId) // Sesuaikan endpoint
                .then(res => res.text()) // Ubah ke text dulu untuk debug
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        // Render logic...
                        // (Sederhana: redirect saja jika API kompleks)
                         window.location.href = 'payment-instruction.php?id=' + data.id;
                    } catch (e) {
                        // Fallback jika JSON gagal (biasanya karena endpoint belum ada)
                        // Kita redirect manual ke halaman detail/instruksi
                        // Kita butuh ID database, bukan ID Transaksi string (TRX-...)
                        // Karena table view pakai transaction_id string, kita perlu cari ID int nya atau redirect via query
                        // Solusi cepat: Reload ke halaman detail dengan parameter search
                        content.innerHTML = `
                            <div style="text-align:center;">
                                <p>Mengarahkan ke halaman detail...</p>
                                <a href="transaction-detail.php?trx_id=${transactionId}" class="btn btn-primary">Buka Halaman Detail</a>
                            </div>
                        `;
                    }
                });
        }
        
        function closeModal() {
            document.getElementById('transactionModal').style.display = 'none';
        }
        
        // Close modal on outside click
        document.getElementById('transactionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>