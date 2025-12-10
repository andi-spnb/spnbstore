<?php
require_once 'config.php';

// Define upload directory
$upload_dir = 'assets/img/products/';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    redirect('/');
}

// Get category
$stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    redirect('/');
}

// Get products in this category
$stmt = $conn->prepare("SELECT p.* FROM products p 
                        WHERE p.category_id = ? AND p.is_active = 1 
                        ORDER BY p.nama ASC");
$stmt->execute([$category['id']]);
$products = $stmt->fetchAll();

$user = isLoggedIn() ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['nama']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .product-image-box {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            overflow: hidden;
            position: relative;
        }
        
        .product-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-image-placeholder {
            font-size: 3rem;
            opacity: 0.5;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <!-- Category Header -->
    <section class="container" style="padding: 3rem 2rem 1rem;">
        <div style="text-align: center;">
            <div style="font-size: 5rem; margin-bottom: 1rem;"><?php echo $category['icon']; ?></div>
            <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($category['nama']); ?></h1>
            <p style="color: var(--text-muted); font-size: 1.1rem;"><?php echo count($products); ?> produk tersedia</p>
        </div>
    </section>

    <!-- Breadcrumb -->
    <div class="container">
        <div style="display: flex; gap: 0.5rem; align-items: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">
            <a href="/" style="color: var(--text-muted); text-decoration: none;">Home</a>
            <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
            <span style="color: var(--text-primary);"><?php echo htmlspecialchars($category['nama']); ?></span>
        </div>
    </div>

    <!-- Products Grid -->
    <section class="container">
        <?php if (count($products) > 0): ?>
        <div class="grid grid-4">
            <?php foreach ($products as $product): ?>
            <div class="card" style="padding: 1.5rem; transition: transform 0.3s; cursor: pointer;" onclick="window.location.href='produk.php?slug=<?php echo $product['slug']; ?>'">
                <!-- Product Image - BAGIAN YANG DIPERBAIKI -->
                <div class="product-image-box">
                    <?php if (!empty($product['gambar']) && file_exists($upload_dir . $product['gambar'])): ?>
                        <img src="<?php echo $upload_dir . $product['gambar']; ?>" alt="<?php echo htmlspecialchars($product['nama']); ?>">
                    <?php else: ?>
                        <div class="product-image-placeholder">
                            <?php 
                            // Use category icon or random emoji
                            if (!empty($category['icon'])) {
                                echo $category['icon'];
                            } else {
                                $icons = ['🎨', '🎵', '📊', '🎬', '✏️', '📝', '💎', '⚡', '🎮'];
                                echo $icons[array_rand($icons)];
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-primary); min-height: 2.5rem;">
                    <?php echo htmlspecialchars($product['nama']); ?>
                </h3>
                
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; min-height: 2.5rem;">
                    <?php 
                    $desc = $product['deskripsi'] ?: 'Produk digital berkualitas premium';
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
            <div style="font-size: 5rem; margin-bottom: 1rem;"><?php echo $category['icon']; ?></div>
            <h2 style="font-size: 1.75rem; margin-bottom: 0.75rem;">Belum Ada Produk</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">
                Produk dalam kategori ini akan segera hadir.<br>
                Silakan cek kategori lainnya.
            </p>
            <a href="/" class="btn btn-primary" style="text-decoration: none; padding: 1rem 2rem;">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
        </div>
        <?php endif; ?>
    </section>

    <!-- Other Categories -->
    <?php
    $stmt = $conn->query("SELECT * FROM categories WHERE id != {$category['id']} ORDER BY nama ASC");
    $other_categories = $stmt->fetchAll();
    
    if (count($other_categories) > 0):
    ?>
    <section class="container" style="margin-top: 4rem;">
        <h2 style="font-size: 1.75rem; margin-bottom: 2rem; text-align: center;">Kategori Lainnya</h2>
        <div class="grid grid-3">
            <?php foreach ($other_categories as $cat): ?>
            <a href="kategori.php?slug=<?php echo $cat['slug']; ?>" class="card" style="text-decoration: none; text-align: center; transition: transform 0.3s; cursor: pointer;">
                <div style="font-size: 3rem; margin-bottom: 1rem;"><?php echo $cat['icon']; ?></div>
                <h3 style="color: var(--text-primary); font-size: 1.25rem; margin-bottom: 0.5rem;"><?php echo $cat['nama']; ?></h3>
                <p style="color: var(--text-muted); font-size: 0.85rem;">
                    <?php
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ? AND is_active = 1");
                    $stmt->execute([$cat['id']]);
                    $count = $stmt->fetch()['count'];
                    echo $count . ' produk';
                    ?>
                </p>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3 style="background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.5rem; margin-bottom: 1rem;">SPNB STORE</h3>
                <p>Toko digital terpercaya untuk berbagai produk premium dengan harga terjangkau.</p>
            </div>
            
            <div class="footer-section">
                <h3>Kategori</h3>
                <?php 
                $stmt = $conn->query("SELECT * FROM categories ORDER BY nama ASC");
                $footer_categories = $stmt->fetchAll();
                foreach ($footer_categories as $cat): 
                ?>
                <a href="kategori.php?slug=<?php echo $cat['slug']; ?>"><?php echo $cat['nama']; ?></a>
                <?php endforeach; ?>
            </div>
            
            <div class="footer-section">
                <h3>Informasi</h3>
                <a href="about.php">Tentang Kami</a>
                <a href="faq.php">FAQ</a>
                <a href="contact.php">Hubungi Kami</a>
            </div>
            
            <div class="footer-section">
                <h3>Akun</h3>
                <?php if ($user): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="profil.php">Profil</a>
                <a href="riwayat.php">Riwayat</a>
                <a href="logout.php">Logout</a>
                <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>Made with 💜 SPNB Store</p>
        </div>
    </footer>

    <script>
        // Add to Cart Function
        function addToCart(productId) {
            // Check if user is logged in
            <?php if (!$user): ?>
            alert('Silakan login terlebih dahulu!');
            window.location.href = 'login.php';
            return;
            <?php endif; ?>
            
            const formData = new URLSearchParams();
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            
            fetch('cart-add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Berhasil ditambahkan ke keranjang!');
                    
                    // Optional: Update cart count in navbar if exists
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge && data.cart_count) {
                        cartBadge.textContent = data.cart_count;
                    }
                } else {
                    alert(data.message || 'Gagal menambahkan ke keranjang');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan sistem. Silakan coba lagi.');
            });
        }

        // Search functionality (if search input exists)
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const productCards = document.querySelectorAll('.grid > .card');
                
                productCards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
    </script>
</body>
</html>