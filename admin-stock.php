<?php
require_once 'config.php';

// Cek Admin
if (!isLoggedIn()) { redirect('login.php'); }
$user = getUserData();
if ($user['is_admin'] != 1) { redirect('dashboard.php'); }

$message = '';

// Handle Tambah Stok
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $product_id = intval($_POST['product_id']);
    $raw_data = trim($_POST['account_data']);
    
    if ($product_id > 0 && !empty($raw_data)) {
        // Pecah berdasarkan baris (enter)
        $accounts = explode("\n", $raw_data);
        $count = 0;
        
        $stmt = $conn->prepare("INSERT INTO product_accounts (product_id, account_data, status) VALUES (?, ?, 'available')");
        
        foreach ($accounts as $acc) {
            $acc = trim($acc);
            if (!empty($acc)) {
                $stmt->execute([$product_id, $acc]);
                $count++;
            }
        }
        
        // Update stok di tabel products
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM product_accounts WHERE product_id = ? AND status = 'available'");
        $stmt_count->execute([$product_id]);
        $real_stock = $stmt_count->fetchColumn();
        
        $update_prod = $conn->prepare("UPDATE products SET stok = ? WHERE id = ?");
        $update_prod->execute([$real_stock, $product_id]);
        
        $message = "<div class='alert alert-success'>Berhasil menambahkan $count akun baru!</div>";
    }
}

// Handle Hapus Stok
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM product_accounts WHERE id = ? AND status = 'available'");
    $stmt->execute([$id]);
    redirect('admin-stock.php');
}

// Ambil Produk
$products = $conn->query("SELECT id, nama FROM products ORDER BY nama ASC")->fetchAll();

// Ambil Data Stok (Filter)
$filter_pid = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$query = "SELECT pa.*, p.nama as product_name, t.transaction_id as trx_code 
          FROM product_accounts pa 
          JOIN products p ON pa.product_id = p.id 
          LEFT JOIN transactions t ON pa.transaction_id = t.id 
          WHERE 1=1";
$params = [];

if ($filter_pid > 0) {
    $query .= " AND pa.product_id = ?";
    $params[] = $filter_pid;
}

$query .= " ORDER BY pa.status ASC, pa.created_at DESC LIMIT 50";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$stocks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Stok Akun - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Responsive CSS Fixes */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            min-width: 600px; /* Ensure table doesn't squish */
        }

        @media (max-width: 900px) {
            .grid-2 {
                grid-template-columns: 1fr; /* Stack columns on tablet/mobile */
            }
            
            .admin-content {
                padding: 1rem;
            }
            
            .card {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'admin-sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-content-inner">
            <div class="admin-header">
                <h1><i class="fas fa-database"></i> Kelola Stok Produk Manual</h1>
            </div>
            
            <?php echo $message; ?>

            <div class="grid grid-2">
                <!-- Form Tambah Stok -->
                <div class="card">
                    <h3><i class="fas fa-plus-circle"></i> Tambah Stok Massal</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Pilih Produk</label>
                            <select name="product_id" class="form-control" required>
                                <option value="">-- Pilih Produk --</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $filter_pid == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nama']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Data Akun / Voucher</label>
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 5px;">
                                Masukkan data per baris. Gunakan tanda <code>|</code> sebagai pemisah jika format Email|Pass.<br>
                                Contoh:<br>
                                <code>email1@gmail.com|pass123</code><br>
                                <code>email2@gmail.com|pass456</code>
                            </div>
                            <textarea name="account_data" class="form-control" rows="10" placeholder="Format: email|password" required></textarea>
                        </div>
                        <button type="submit" name="add_stock" class="btn btn-primary" style="width: 100%;">Simpan Stok</button>
                    </form>
                </div>

                <!-- List Stok -->
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap: 0.5rem;">
                        <h3>Riwayat Stok</h3>
                        <form method="GET" style="flex:1; max-width: 200px;">
                            <select name="product_id" class="form-control" onchange="this.form.submit()" style="padding:5px;">
                                <option value="0">Semua Produk</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $filter_pid == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nama']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stocks as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                                    <td>
                                        <code style="font-size:0.8rem; background: #f3f4f6; padding: 2px 4px; border-radius: 4px;">
                                            <?php echo htmlspecialchars(substr($s['account_data'], 0, 20)) . (strlen($s['account_data'])>20?'...':''); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if($s['status'] == 'available'): ?>
                                            <span class="badge badge-success">Tersedia</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Terjual</span>
                                            <?php if(!empty($s['trx_code'])): ?>
                                                <div style="font-size:0.7rem; margin-top:2px;"><a href="transaction-detail.php?id=<?php echo $s['transaction_id']; ?>">View TRX</a></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($s['status'] == 'available'): ?>
                                        <a href="?delete=<?php echo $s['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus stok ini?')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>