<?php
require_once 'config.php';

$user = isLoggedIn() ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tips Hemat | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <section class="container" style="padding: 4rem 2rem; text-align: center;">
        <div style="font-size: 6rem; margin-bottom: 1rem;">💰</div>
        <h1 style="font-size: 3.5rem; margin-bottom: 1rem; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            Tips Hemat Berbelanja
        </h1>
        <p style="font-size: 1.3rem; color: var(--text-muted); max-width: 800px; margin: 0 auto; line-height: 1.8;">
            Pelajari cara pintar berbelanja dan maksimalkan budget Anda untuk mendapatkan produk premium dengan harga terbaik.
        </p>
    </section>

    <!-- Essential Tips -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Tips Hemat Dasar</h2>
        <div class="grid grid-2" style="gap: 2rem;">
            <!-- Tip 1 -->
            <div class="card" style="padding: 2.5rem; border-left: 4px solid #6366f1;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 2.5rem; min-width: 60px;">🎯</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">1. Tentukan Prioritas Kebutuhan</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Sebelum berbelanja, buat daftar produk yang benar-benar Anda butuhkan. Hindari pembelian impulsif dengan fokus pada kebutuhan utama terlebih dahulu.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tip 2 -->
            <div class="card" style="padding: 2.5rem; border-left: 4px solid #10b981;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 2.5rem; min-width: 60px;">📱</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">2. Bandingkan Harga</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Gunakan fitur pencarian dan filter kami untuk membandingkan harga berbagai produk. Platform kami menawarkan harga kompetitif dengan kualitas terjamin.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tip 3 -->
            <div class="card" style="padding: 2.5rem; border-left: 4px solid #f59e0b;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 2.5rem; min-width: 60px;">🎁</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">3. Manfaatkan Promo & Voucher</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Selalu cek halaman promo kami untuk mendapatkan voucher eksklusif dan diskon spesial. Subscribe newsletter untuk penawaran terbaru!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tip 4 -->
            <div class="card" style="padding: 2.5rem; border-left: 4px solid #ec4899;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 2.5rem; min-width: 60px;">👥</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">4. Ajak Teman Lewat Referral</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Program referral kami memberikan komisi atau bonus untuk setiap teman yang Anda ajak. Ajak lebih banyak teman, dapatkan lebih banyak bonus!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tip 5 -->
            <div class="card" style="padding: 2.5rem; border-left: 4px solid #3b82f6;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 2.5rem; min-width: 60px;">⭐</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">5. Baca Review Pembeli</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Sebelum membeli, selalu baca review dan rating dari pembeli lain. Ini membantu Anda memilih produk terbaik dan menghindari pembelian yang merugikan.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tip 6 -->
            <div class="card" style="padding: 2.5rem; border-left: 4px solid #8b5cf6;">
                <div style="display: flex; align-items: flex-start; gap: 1.5rem;">
                    <div style="font-size: 2.5rem; min-width: 60px;">📅</div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem;">6. Tunggu Flash Sale</h3>
                        <p style="color: var(--text-muted); line-height: 1.8;">
                            Kami secara berkala mengadakan flash sale dengan diskon besar-besaran. Aktifkan notifikasi untuk tidak ketinggalan penawaran terbaik!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Product-Specific Tips -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Tips Hemat Per Kategori Produk</h2>
        <div class="grid grid-3">
            <!-- Netflix Tips -->
            <div class="card" style="padding: 2rem; background: linear-gradient(135deg, rgba(237, 100, 166, 0.1), rgba(190, 24, 93, 0.1));">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎬</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Netflix</h3>
                </div>
                <ul style="color: var(--text-muted); line-height: 2; list-style: none; padding: 0;">
                    <li>✓ Pilih paket Standard, hemat hingga 30%</li>
                    <li>✓ Bagikan akun dengan keluarga untuk efisiensi</li>
                    <li>✓ Manfaatkan trial gratis sebelum berlangganan</li>
                    <li>✓ Pantau penawaran spesial kami untuk diskon bulk</li>
                </ul>
            </div>

            <!-- Canva Tips -->
            <div class="card" style="padding: 2rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎨</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Canva Pro</h3>
                </div>
                <ul style="color: var(--text-muted); line-height: 2; list-style: none; padding: 0;">
                    <li>✓ Hemat 50-70% dari harga original</li>
                    <li>✓ Unlimited design credits untuk proyek panjang</li>
                    <li>✓ Miliki akses ke premium template dan assets</li>
                    <li>✓ Gunakan untuk kebutuhan bisnis dan personal</li>
                </ul>
            </div>

            <!-- Spotify Tips -->
            <div class="card" style="padding: 2rem; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎵</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Spotify Premium</h3>
                </div>
                <ul style="color: var(--text-muted); line-height: 2; list-style: none; padding: 0;">
                    <li>✓ Dengarkan lagu tanpa iklan dan offline</li>
                    <li>✓ Hemat biaya langganan bulanan hingga 40%</li>
                    <li>✓ Akses high-quality audio streaming</li>
                    <li>✓ Bagikan keluarga plan dengan teman</li>
                </ul>
            </div>

            <!-- Microsoft Tips -->
            <div class="card" style="padding: 2rem; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(29, 78, 216, 0.1));">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">💼</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Microsoft 365</h3>
                </div>
                <ul style="color: var(--text-muted); line-height: 2; list-style: none; padding: 0;">
                    <li>✓ Akses Word, Excel, PowerPoint di semua device</li>
                    <li>✓ 1TB OneDrive storage untuk backup aman</li>
                    <li>✓ Support prioritas untuk pekerjaan profesional</li>
                    <li>✓ Hemat 45% dari harga resmi Microsoft</li>
                </ul>
            </div>

            <!-- VPN Tips -->
            <div class="card" style="padding: 2rem; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(239, 68, 68, 0.1));">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🔒</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">VPN Premium</h3>
                </div>
                <ul style="color: var(--text-muted); line-height: 2; list-style: none; padding: 0;">
                    <li>✓ Browsing aman dengan enkripsi end-to-end</li>
                    <li>✓ Akses konten dari berbagai negara</li>
                    <li>✓ Hemat biaya dibanding langganan langsung</li>
                    <li>✓ Support multiple devices sekaligus</li>
                </ul>
            </div>

            <!-- Gaming Tips -->
            <div class="card" style="padding: 2rem; background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(99, 102, 241, 0.1));">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎮</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Gaming Pass</h3>
                </div>
                <ul style="color: var(--text-muted); line-height: 2; list-style: none; padding: 0;">
                    <li>✓ Akses ribuan game premium sekaligus</li>
                    <li>✓ Hemat hingga 60% dari membeli per game</li>
                    <li>✓ New games ditambahkan setiap bulan</li>
                    <li>✓ Mainkan di console dan PC</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Budget Planning -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="padding: 3rem;">
            <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Strategi Budget Cerdas</h2>
            <div class="grid grid-2" style="gap: 2rem;">
                <div>
                    <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: var(--primary-color);">📊 Contoh Budget Bulanan</h3>
                    <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05)); padding: 1.5rem; border-radius: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                            <span style="color: var(--text-muted);">Netflix Premium</span>
                            <span style="font-weight: 600; color: var(--primary-color);">Rp 45.000</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                            <span style="color: var(--text-muted);">Canva Pro</span>
                            <span style="font-weight: 600; color: var(--primary-color);">Rp 35.000</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                            <span style="color: var(--text-muted);">Spotify Premium</span>
                            <span style="font-weight: 600; color: var(--primary-color);">Rp 35.000</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                            <span style="color: var(--text-muted);">Microsoft 365</span>
                            <span style="font-weight: 600; color: var(--primary-color);">Rp 40.000</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-top: 1rem; border-top: 2px solid var(--primary-color);">
                            <span style="font-weight: 600; font-size: 1.1rem;">Total</span>
                            <span style="font-weight: 700; font-size: 1.1rem; color: var(--primary-color);">Rp 155.000</span>
                        </div>
                    </div>
                    <p style="color: var(--text-muted); margin-top: 1rem; font-size: 0.95rem;">
                        💡 <strong>Saving Tip:</strong> Paket tahunan biasanya lebih murah dari bulanan. Pertimbangkan untuk membayar setahun sekali!
                    </p>
                </div>

                <div>
                    <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: var(--primary-color);">💡 Trik Hemat Ekstrem</h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="padding: 1rem; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1)); border-radius: 0.5rem; border-left: 3px solid #10b981;">
                            <p style="margin: 0; color: var(--text-muted);">
                                <strong style="color: var(--primary-color);">Paket Bundling:</strong> Beli beberapa produk sekaligus untuk diskon tambahan hingga 15%.
                            </p>
                        </div>
                        <div style="padding: 1rem; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(239, 68, 68, 0.1)); border-radius: 0.5rem; border-left: 3px solid #f59e0b;">
                            <p style="margin: 0; color: var(--text-muted);">
                                <strong style="color: var(--primary-color);">Akumulasi Poin:</strong> Setiap pembelian mengumpulkan poin yang bisa ditukar diskon berikutnya.
                            </p>
                        </div>
                        <div style="padding: 1rem; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(29, 78, 216, 0.1)); border-radius: 0.5rem; border-left: 3px solid #3b82f6;">
                            <p style="margin: 0; color: var(--text-muted);">
                                <strong style="color: var(--primary-color);">Member Eksklusif:</strong> Daftar membership untuk akses early bird sale dan diskon member khusus.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Common Mistakes -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Kesalahan Umum yang Harus Dihindari</h2>
        <div class="grid grid-2" style="gap: 2rem;">
            <div class="card" style="padding: 2rem; border-left: 4px solid #ef4444;">
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: #ef4444;">❌ Membeli Impulsif</h3>
                <p style="color: var(--text-muted); line-height: 1.8;">
                    Hindari membeli tanpa perencanaan. Buat wishlist terlebih dahulu dan tunggu waktu yang tepat untuk membeli dengan harga terbaik.
                </p>
            </div>

            <div class="card" style="padding: 2rem; border-left: 4px solid #ef4444;">
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: #ef4444;">❌ Abaikan Review</h3>
                <p style="color: var(--text-muted); line-height: 1.8;">
                    Selalu baca review pembeli sebelum membeli. Review membantu mengidentifikasi produk berkualitas dan penjual terpercaya.
                </p>
            </div>

            <div class="card" style="padding: 2rem; border-left: 4px solid #ef4444;">
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: #ef4444;">❌ Lupakan Voucher</h3>
                <p style="color: var(--text-muted); line-height: 1.8;">
                    Cek selalu halaman promo sebelum checkout. Voucher dan kode diskon bisa menghemat hingga 20-30% dari harga asli.
                </p>
            </div>

            <div class="card" style="padding: 2rem; border-left: 4px solid #ef4444;">
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: #ef4444;">❌ Membeli Satu-Satu</h3>
                <p style="color: var(--text-muted); line-height: 1.8;">
                    Pertimbangkan membeli paket bundling atau produk multiple sekaligus. Sering ada diskon khusus untuk pembelian dalam jumlah banyak.
                </p>
            </div>
        </div>
    </section>

    <!-- FAQ Tips -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Pertanyaan Umum Tentang Berhemat</h2>
        <div style="max-width: 800px; margin: 0 auto;">
            <div class="card" style="padding: 2rem; margin-bottom: 1rem;">
                <div style="display: flex; align-items: flex-start; gap: 1rem;">
                    <span style="font-size: 1.5rem;">❓</span>
                    <div style="flex: 1;">
                        <h4 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Apakah harga kami lebih murah dari kompetitor?</h4>
                        <p style="color: var(--text-muted); margin: 0;">
                            Ya, kami menawarkan harga termurah di pasaran dengan kualitas sama. Bandingkan sendiri dengan platform lain!
                        </p>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 2rem; margin-bottom: 1rem;">
                <div style="display: flex; align-items: flex-start; gap: 1rem;">
                    <span style="font-size: 1.5rem;">❓</span>
                    <div style="flex: 1;">
                        <h4 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Bagaimana cara maksimalkan poin reward?</h4>
                        <p style="color: var(--text-muted); margin: 0;">
                            Setiap pembelian menghasilkan poin. Kumpulkan dan tukarkan poin untuk diskon pada pembelian berikutnya atau produk gratis.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 2rem; margin-bottom: 1rem;">
                <div style="display: flex; align-items: flex-start; gap: 1rem;">
                    <span style="font-size: 1.5rem;">❓</span>
                    <div style="flex: 1;">
                        <h4 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Apakah ada biaya hidden?</h4>
                        <p style="color: var(--text-muted); margin: 0;">
                            Tidak, harga yang tertera adalah harga final. Tidak ada biaya tambahan atau biaya tersembunyi dalam transaksi Anda.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 2rem;">
                <div style="display: flex; align-items: flex-start; gap: 1rem;">
                    <span style="font-size: 1.5rem;">❓</span>
                    <div style="flex: 1;">
                        <h4 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Kapan waktu terbaik berbelanja?</h4>
                        <p style="color: var(--text-muted); margin: 0;">
                            Flash sale biasanya diadakan akhir bulan, hari libur nasional, dan saat launching produk baru. Subscribe newsletter untuk info terbaru!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="text-align: center; padding: 4rem; background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <div style="font-size: 5rem; margin-bottom: 1.5rem;">🎊</div>
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: white;">Mulai Belanja Hemat Sekarang!</h2>
            <p style="font-size: 1.2rem; color: rgba(255,255,255,0.9); margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                Terapkan tips-tips kami dan dapatkan produk premium dengan harga yang lebih terjangkau. Mulai belanja sekarang juga!
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <?php if ($user): ?>
                <a href="/" class="btn" style="background: white; color: var(--primary-color); padding: 1.25rem 2.5rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-shopping-bag"></i> Jelajahi Produk
                </a>
                <?php else: ?>
                <a href="register.php" class="btn" style="background: white; color: var(--primary-color); padding: 1.25rem 2.5rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-user-plus"></i> Daftar Gratis
                </a>
                <?php endif; ?>
                <a href="about.php" class="btn btn-secondary" style="padding: 1.25rem 2.5rem; text-decoration: none; border: 2px solid white; background: transparent; color: white;">
                    <i class="fas fa-info-circle"></i> Tentang Kami
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
                <a href="fitur.php">Fitur</a>
                <a href="tips.php">Tips Hemat</a>
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