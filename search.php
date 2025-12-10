<?php
require_once 'config.php';

$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$sort_by = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

// Build search query
$sql = "SELECT p.*, c.nama as category_name, c.icon as category_icon 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (p.nama LIKE ? OR p.deskripsi LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_filter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

// Sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY p.harga ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.harga DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.nama ASC";
        break;
    default: // newest
        $sql .= " ORDER BY p.created_at DESC";
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get all categories for filter
$stmt = $conn->query("SELECT * FROM categories ORDER BY nama ASC");
$categories = $stmt->fetchAll();

$user = isLoggedIn() ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencarian<?php echo !empty($search_query) ? ' - ' . htmlspecialchars($search_query) : ''; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    <div class="container">
        <!-- Search Header -->
        <div style="margin: 2rem 0;">
            <?php if (!empty($search_query)): ?>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">
                Hasil pencarian untuk "<span style="color: var(--primary-color);"><?php echo htmlspecialchars($search_query); ?></span>"
            </h1>
            <p style="color: var(--text-muted);">Ditemukan <?php echo count($products); ?> produk</p>
            <?php else: ?>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Semua Produk</h1>
            <p style="color: var(--text-muted);">Total <?php echo count($products); ?> produk tersedia</p>
            <?php endif; ?>
        </div>

        <!-- Filters & Sort -->
        <div class="card" style="margin-bottom: 2rem;">
            <form method="GET" action="search.php" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                
                <!-- Category Filter -->
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-filter"></i> Kategori
                    </label>
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="0">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo $cat['icon'] . ' ' . $cat['nama']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sort By -->
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-sort"></i> Urutkan
                    </label>
                    <select name="sort" class="form-control" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Nama (A-Z)</option>
                        <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Harga Terendah</option>
                        <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                    </select>
                </div>
                
                <!-- Reset -->
                <?php if (!empty($search_query) || $category_filter > 0 || $sort_by != 'newest'): ?>
                <div style="padding-top: 1.5rem;">
                    <a href="search.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filter
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (count($products) > 0): ?>
        <div class="grid grid-4">
            <?php foreach ($products as $product): ?>
            <div class="card" style="padding: 1.5rem; transition: transform 0.3s; cursor: pointer;" onclick="window.location.href='produk.php?slug=<?php echo $product['slug']; ?>'">
                <!-- Category Badge -->
                <div style="position: absolute; top: 1rem; right: 1rem; z-index: 1;">
                    <span class="badge badge-primary" style="font-size: 0.7rem;">
                        <?php echo $product['category_icon'] . ' ' . $product['category_name']; ?>
                    </span>
                </div>
                
                <div style="width: 100%; height: 150px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); border-radius: 0.75rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                    <?php 
                    $icons = ['🎨', '🎵', '📊', '🎬', '✏️', '📝', '💎', '⚡', '🎮'];
                    echo $icons[array_rand($icons)]; 
                    ?>
                </div>
                
                <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-primary); min-height: 2.5rem;">
                    <?php echo htmlspecialchars($product['nama']); ?>
                </h3>
                
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; min-height: 2.5rem;">
                    <?php 
                    $desc = $product['deskripsi'] ?: 'Produk digital berkualitas';
                    echo htmlspecialchars(substr($desc, 0, 80) . (strlen($desc) > 80 ? '...' : '')); 
                    ?>
                </p>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary-color);">
                            <?php echo formatRupiah($product['harga']); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            <i class="fas fa-box"></i> Stok: <?php echo $product['stok']; ?>
                        </div>
                    </div>
                    <span class="badge badge-<?php echo $product['tipe_produk'] == 'otomatis' ? 'success' : 'warning'; ?>" style="font-size: 0.7rem;">
                        <?php echo ucfirst($product['tipe_produk']); ?>
                    </span>
                </div>
                
                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>)" class="btn btn-secondary" style="flex: 1; justify-content: center; padding: 0.75rem; font-size: 0.85rem;">
                        <i class="fas fa-cart-plus"></i>
                    </button>
                    <a href="produk.php?slug=<?php echo $product['slug']; ?>" class="btn btn-primary" style="flex: 2; justify-content: center; text-decoration: none; padding: 0.75rem; font-size: 0.85rem;">
                        <i class="fas fa-shopping-bag"></i> Beli
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state" style="padding: 5rem 2rem;">
            <div style="font-size: 6rem; margin-bottom: 1rem;">🔍</div>
            <h2 style="font-size: 1.75rem; margin-bottom: 0.75rem;">Produk Tidak Ditemukan</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">
                Maaf, kami tidak menemukan produk yang sesuai dengan pencarian Anda.<br>
                Coba kata kunci lain atau cek kategori produk kami.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="search.php" class="btn btn-secondary" style="text-decoration: none; padding: 1rem 1.5rem;">
                    <i class="fas fa-redo"></i> Reset Pencarian
                </a>
                <a href="/" class="btn btn-primary" style="text-decoration: none; padding: 1rem 1.5rem;">
                    <i class="fas fa-home"></i> Ke Beranda
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3 style="background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.5rem; margin-bottom: 1rem;">andispnb.shop</h3>
                <p>Toko digital terpercaya dengan produk premium berkualitas.</p>
            </div>
            
            <div class="footer-section">
                <h3>Kategori</h3>
                <?php foreach ($categories as $cat): ?>
                <a href="kategori.php?slug=<?php echo $cat['slug']; ?>"><?php echo $cat['icon'] . ' ' . $cat['nama']; ?></a>
                <?php endforeach; ?>
            </div>
            
            <div class="footer-section">
                <h3>Bantuan</h3>
                <a href="#">FAQ</a>
                <a href="#">Cara Pembelian</a>
                <a href="#">Hubungi Kami</a>
            </div>
            
            <div class="footer-section">
                <h3>Akun</h3>
                <?php if ($user): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
                <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>Made with 💜 AndiSpnb</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script>
        // Search functionality for search.php
        const searchInput = document.querySelector('.search-input');
        
        if (searchInput) {
            // Handle Enter key to search
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const query = this.value.trim();
                    if (query) {
                        // Get current filters
                        const urlParams = new URLSearchParams(window.location.search);
                        const category = urlParams.get('category') || '';
                        const sort = urlParams.get('sort') || '';
                        
                        // Build new URL with filters
                        let newUrl = 'search.php?q=' + encodeURIComponent(query);
                        if (category) newUrl += '&category=' + category;
                        if (sort) newUrl += '&sort=' + sort;
                        
                        window.location.href = newUrl;
                    }
                }
            });
            
            // Handle Ctrl+K shortcut to focus search
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select(); // Select all text for easy replacement
                }
            });
        }
    </script>

</body>
</html>
