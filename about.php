<?php
require_once 'config.php';

$user = isLoggedIn() ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    <!-- Hero Section -->
    <section class="container" style="padding: 4rem 2rem; text-align: center;">
        <div style="font-size: 6rem; margin-bottom: 1rem;">🚀</div>
        <h1 style="font-size: 3.5rem; margin-bottom: 1rem; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            Tentang Premiumisme Store
        </h1>
        <p style="font-size: 1.3rem; color: var(--text-muted); max-width: 800px; margin: 0 auto; line-height: 1.8;">
            Platform terpercaya untuk mendapatkan produk digital premium dengan harga terjangkau dan layanan terbaik di Indonesia.
        </p>
    </section>

    <!-- Our Story -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="padding: 3rem;">
            <div class="grid grid-2" style="align-items: center; gap: 3rem;">
                <div>
                    <h2 style="font-size: 2.5rem; margin-bottom: 1.5rem;">
                        <span style="font-size: 3rem;">📖</span> Cerita Kami
                    </h2>
                    <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1rem;">
                        Premiumisme Store didirikan pada tahun 2024 dengan visi untuk membuat produk digital premium menjadi lebih accessible untuk semua orang di Indonesia.
                    </p>
                    <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1rem;">
                        Kami memahami bahwa tidak semua orang mampu membayar harga penuh untuk subscription premium seperti Canva Pro, Netflix, Spotify, dan lainnya. Oleh karena itu, kami hadir dengan solusi yang lebih hemat tanpa mengurangi kualitas.
                    </p>
                    <p style="color: var(--text-muted); line-height: 1.8;">
                        Hingga saat ini, kami telah melayani ribuan customer dengan tingkat kepuasan 99% dan terus berkembang menjadi platform terpercaya di Indonesia.
                    </p>
                </div>
                <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2)); border-radius: 1rem; padding: 3rem; text-align: center;">
                    <div style="font-size: 5rem; margin-bottom: 1rem;">🏆</div>
                    <div style="font-size: 3rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">99%</div>
                    <div style="font-size: 1.1rem; color: var(--text-muted);">Customer Satisfaction</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Values -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Nilai-Nilai Kami</h2>
        <div class="grid grid-3">
            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    ✨
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Kualitas Premium</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Kami hanya menjual produk original dan berkualitas tinggi dengan garansi 100% legal.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    💰
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Harga Terjangkau</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Dapatkan produk premium dengan harga jauh lebih murah dari harga normal tanpa mengurangi kualitas.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f59e0b, #ef4444); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    ⚡
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Proses Cepat</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Pengiriman otomatis dalam hitungan menit setelah pembayaran berhasil dikonfirmasi.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #ec4899, #be185d); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    🔒
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Keamanan Terjamin</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Data pribadi dan transaksi Anda dilindungi dengan sistem keamanan SSL encryption.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    💬
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Support 24/7</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Tim customer service kami siap membantu Anda kapan saja via WhatsApp dan email.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    🎯
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Garansi Produk</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Semua produk bergaransi. Jika ada masalah, kami siap refund atau replace 100%.
                </p>
            </div>
        </div>
    </section>

    <!-- Statistics -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="padding: 3rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));">
            <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Prestasi Kami</h2>
            <div class="grid grid-4">
                <div style="text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 0.5rem;">👥</div>
                    <div style="font-size: 3rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">5K+</div>
                    <div style="color: var(--text-muted);">Happy Customers</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 0.5rem;">📦</div>
                    <div style="font-size: 3rem; font-weight: 700; color: var(--success-color); margin-bottom: 0.5rem;">50+</div>
                    <div style="color: var(--text-muted);">Premium Products</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 0.5rem;">⚡</div>
                    <div style="font-size: 3rem; font-weight: 700; color: var(--warning-color); margin-bottom: 0.5rem;">10K+</div>
                    <div style="color: var(--text-muted);">Transactions</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 0.5rem;">⭐</div>
                    <div style="font-size: 3rem; font-weight: 700; color: var(--danger-color); margin-bottom: 0.5rem;">4.9/5</div>
                    <div style="color: var(--text-muted);">Average Rating</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Mengapa Memilih Kami?</h2>
        <div class="grid grid-2" style="gap: 2rem;">
            <div class="card" style="padding: 2rem;">
                <div style="display: flex; align-items: start; gap: 1.5rem;">
                    <div style="font-size: 3rem;">✅</div>
                    <div>
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.75rem;">100% Original & Legal</h3>
                        <p style="color: var(--text-muted); line-height: 1.6;">
                            Semua produk dijamin original dan legal. Kami tidak menjual produk bajakan atau ilegal.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 2rem;">
                <div style="display: flex; align-items: start; gap: 1.5rem;">
                    <div style="font-size: 3rem;">💎</div>
                    <div>
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.75rem;">Harga Terbaik</h3>
                        <p style="color: var(--text-muted); line-height: 1.6;">
                            Dapatkan harga termurah di pasaran dengan kualitas yang sama dengan subscription original.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 2rem;">
                <div style="display: flex; align-items: start; gap: 1.5rem;">
                    <div style="font-size: 3rem;">🚀</div>
                    <div>
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.75rem;">Instant Delivery</h3>
                        <p style="color: var(--text-muted); line-height: 1.6;">
                            Produk otomatis dikirim ke email dalam 1-5 menit setelah pembayaran dikonfirmasi.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 2rem;">
                <div style="display: flex; align-items: start; gap: 1.5rem;">
                    <div style="font-size: 3rem;">🛡️</div>
                    <div>
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.75rem;">Garansi Uang Kembali</h3>
                        <p style="color: var(--text-muted); line-height: 1.6;">
                            Jika produk bermasalah atau tidak sesuai, kami jamin refund 100% tanpa ribet.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="text-align: center; padding: 4rem; background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <div style="font-size: 5rem; margin-bottom: 1.5rem;">🎉</div>
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: white;">Siap Untuk Memulai?</h2>
            <p style="font-size: 1.2rem; color: rgba(255,255,255,0.9); margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                Bergabunglah dengan ribuan customer yang sudah merasakan manfaat produk premium dengan harga terjangkau
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <?php if ($user): ?>
                <a href="/" class="btn" style="background: white; color: var(--primary-color); padding: 1.25rem 2.5rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-shopping-bag"></i> Belanja Sekarang
                </a>
                <?php else: ?>
                <a href="register.php" class="btn" style="background: white; color: var(--primary-color); padding: 1.25rem 2.5rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-user-plus"></i> Daftar Gratis
                </a>
                <?php endif; ?>
                <a href="contact.php" class="btn btn-secondary" style="padding: 1.25rem 2.5rem; text-decoration: none; border: 2px solid white; background: transparent; color: white;">
                    <i class="fas fa-envelope"></i> Hubungi Kami
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3 style="background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.5rem; margin-bottom: 1rem;">premiumisme.store</h3>
                <p>Platform terpercaya untuk produk digital premium dengan harga terjangkau.</p>
            </div>
            
            <div class="footer-section">
                <h3>Informasi</h3>
                <a href="about.php">Tentang Kami</a>
                <a href="faq.php">FAQ</a>
                <a href="contact.php">Hubungi Kami</a>
            </div>
            
            <div class="footer-section">
                <h3>Kategori</h3>
                <?php
                $stmt = $conn->query("SELECT * FROM categories ORDER BY nama ASC LIMIT 5");
                while ($cat = $stmt->fetch()):
                ?>
                <a href="kategori.php?slug=<?php echo $cat['slug']; ?>"><?php echo $cat['nama']; ?></a>
                <?php endwhile; ?>
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
            <p>Made with 💜 Premiumisme</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>