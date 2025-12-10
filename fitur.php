<?php
require_once 'config.php';

$user = isLoggedIn() ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitur-Fitur Seru | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <section class="container" style="padding: 4rem 2rem; text-align: center;">
        <div style="font-size: 6rem; margin-bottom: 1rem;">✨</div>
        <h1 style="font-size: 3.5rem; margin-bottom: 1rem; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            Fitur-Fitur Seru
        </h1>
        <p style="font-size: 1.3rem; color: var(--text-muted); max-width: 800px; margin: 0 auto; line-height: 1.8;">
            Nikmati berbagai fitur canggih dan menarik yang kami tawarkan untuk kemudahan berbelanja Anda.
        </p>
    </section>

    <!-- Main Features Section -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Fitur Utama Kami</h2>
        <div class="grid grid-2" style="gap: 2rem;">
            <!-- Feature 1 -->
            <div class="card" style="padding: 2.5rem; overflow: hidden;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 3rem; min-width: 60px;">💳</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">Pembayaran Fleksibel</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Kami mendukung berbagai metode pembayaran mulai dari QRIS, transfer bank (BNI, BRI, Mandiri), e-wallet (GoPay, OVO, DANA, ShopeePay), hingga kartu kredit. Pilih yang paling memudahkan Anda!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Feature 2 -->
            <div class="card" style="padding: 2.5rem; overflow: hidden;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 3rem; min-width: 60px;">⚡</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">Pengiriman Instan</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Setelah pembayaran dikonfirmasi, produk digital Anda langsung dikirim ke email dalam hitungan menit. Tidak perlu menunggu lama!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Feature 3 -->
            <div class="card" style="padding: 2.5rem; overflow: hidden;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 3rem; min-width: 60px;">🔍</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">Pencarian Mudah</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Sistem pencarian yang canggih dan filter kategori membantu Anda menemukan produk yang diinginkan dengan cepat dan mudah.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Feature 4 -->
            <div class="card" style="padding: 2.5rem; overflow: hidden;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 3rem; min-width: 60px;">📱</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">Responsive Mobile</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Platform kami dioptimalkan untuk semua perangkat, dari smartphone hingga desktop. Belanja kapan saja, di mana saja!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Feature 5 -->
            <div class="card" style="padding: 2.5rem; overflow: hidden;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 3rem; min-width: 60px;">🛡️</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">Keamanan SSL</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Data pribadi dan transaksi Anda dilindungi dengan enkripsi SSL 256-bit. Belanja dengan aman dan tenang!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Feature 6 -->
            <div class="card" style="padding: 2.5rem; overflow: hidden;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 3rem; min-width: 60px;">📊</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">Riwayat Pembelian</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Kelola semua pembelian Anda di dashboard. Lihat status order, invoice, dan download produk kapan saja dengan mudah.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Advanced Features -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Fitur Canggih Lainnya</h2>
        <div class="grid grid-3">
            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    🎁
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Program Voucher</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Dapatkan kode voucher eksklusif dan diskon spesial untuk pembelian berikutnya. Semakin banyak belanja, semakin banyak untung!
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    👥
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Referral Program</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Ajak teman dan dapatkan komisi atau bonus credit. Semakin banyak referral, semakin besar penghasilan pasif Anda!
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f59e0b, #ef4444); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    ⭐
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Rating & Review</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Baca review dari pembeli lain dan beri rating untuk membantu customer lain membuat keputusan yang tepat.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #ec4899, #be185d); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    🔔
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Notifikasi Real-time</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Terima notifikasi otomatis untuk setiap update order, promo terbaru, dan produk baru yang diluncurkan.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    💬
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Live Chat Support</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Hubungi tim support kami melalui live chat untuk bantuan instant. Customer service responsif tersedia 24/7.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">
                    📲
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Wishlist & Favorit</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Simpan produk favorit Anda di wishlist untuk dibeli nanti. Dapatkan notifikasi jika ada potongan harga!
                </p>
            </div>
        </div>
    </section>

    <!-- Premium Features Comparison -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="padding: 3rem;">
            <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Keuntungan Menjadi Member</h2>
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: center; border-collapse: collapse;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));">
                            <th style="padding: 1rem; border-bottom: 2px solid #6366f1; text-align: left;">Fitur</th>
                            <th style="padding: 1rem; border-bottom: 2px solid #6366f1;">Gratis</th>
                            <th style="padding: 1rem; border-bottom: 2px solid #6366f1;">Member Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 1rem; text-align: left; color: var(--text-muted);">Akses Katalog Lengkap</td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 1rem; text-align: left; color: var(--text-muted);">Pengiriman Instan</td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 1rem; text-align: left; color: var(--text-muted);">Diskon Eksklusif</td>
                            <td style="padding: 1rem;"><i class="fas fa-times" style="color: #ef4444; font-size: 1.2rem;"></i></td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 1rem; text-align: left; color: var(--text-muted);">Poin Reward</td>
                            <td style="padding: 1rem;"><i class="fas fa-times" style="color: #ef4444; font-size: 1.2rem;"></i></td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 1rem; text-align: left; color: var(--text-muted);">Priority Support</td>
                            <td style="padding: 1rem;"><i class="fas fa-times" style="color: #ef4444; font-size: 1.2rem;"></i></td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 1rem; text-align: left; color: var(--text-muted);">Akses Early Access Produk Baru</td>
                            <td style="padding: 1rem;"><i class="fas fa-times" style="color: #ef4444; font-size: 1.2rem;"></i></td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; text-align: left; color: var(--text-muted);">Garansi 100%</td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                            <td style="padding: 1rem;"><i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="text-align: center; padding: 4rem; background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <div style="font-size: 5rem; margin-bottom: 1.5rem;">🚀</div>
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: white;">Mulai Nikmati Fitur-Fitur Seru!</h2>
            <p style="font-size: 1.2rem; color: rgba(255,255,255,0.9); margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                Jangan lewatkan pengalaman berbelanja yang lebih mudah, cepat, dan menguntungkan.
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
                <a href="fitur.php">Fitur</a>
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