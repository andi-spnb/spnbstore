<?php
require_once 'config.php';

// Ambil produk aktif dari database lokal
$query = "SELECT p.*, c.nama as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.is_active = 1 
          ORDER BY c.nama ASC, p.harga ASC";
$stmt = $conn->query($query);
$products = $stmt->fetchAll();

// Grouping produk berdasarkan kategori
$grouped = [];
foreach ($products as $p) {
    $cat = $p['category_name'] ?: 'Lainnya';
    $grouped[$cat][] = $p;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Harga - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container" style="padding-top: 2rem;">
        <div class="card mb-4" style="text-align:center; background: linear-gradient(135deg, var(--primary-color), #8b5cf6); color:white;">
            <h1 style="margin-bottom:0.5rem;">Daftar Harga Layanan</h1>
            <p>Update Realtime Termurah & Terlengkap</p>
        </div>

        <!-- Search Box -->
        <div class="card mb-4">
            <input type="text" id="searchPrice" class="form-control" placeholder="Cari layanan (misal: Netflix, Spotify, Mobile Legends)..." style="font-size:1.1rem;">
        </div>

        <div id="priceListArea">
            <?php foreach ($grouped as $category => $items): ?>
            <div class="card mb-4 price-category-section">
                <h3 style="border-bottom:1px solid #eee; padding-bottom:1rem; margin-bottom:1rem; color:var(--primary-color);">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($category); ?>
                </h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Layanan</th>
                                <th>Kode</th>
                                <th>Harga</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr class="price-item">
                                <td class="service-name" style="font-weight:600;"><?php echo htmlspecialchars($item['nama']); ?></td>
                                <td><code style="background:var(--dark-bg); padding:2px 5px; border-radius:3px; font-size:0.85rem;"><?php echo htmlspecialchars($item['product_code']); ?></code></td>
                                <td style="color:var(--primary-color); font-weight:bold;">
                                    <?php echo formatRupiah($item['harga']); ?>
                                </td>
                                <td>
                                    <?php if($item['stok'] > 0): ?>
                                        <span class="badge badge-success">Ready</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Gangguan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="produk.php?slug=<?php echo $item['slug']; ?>" class="btn btn-sm btn-primary">Beli</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Simple Live Search
        document.getElementById('searchPrice').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let items = document.querySelectorAll('.price-item');
            let sections = document.querySelectorAll('.price-category-section');
            
            items.forEach(row => {
                let text = row.querySelector('.service-name').textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });

            // Sembunyikan kategori jika semua item di dalamnya hidden
            sections.forEach(section => {
                let visibleItems = section.querySelectorAll('.price-item:not([style*="display: none"])');
                section.style.display = visibleItems.length > 0 ? '' : 'none';
            });
        });
    </script>
</body>
</html>