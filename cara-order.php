<?php
require_once 'config.php';

$user = isLoggedIn() ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cara Order | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .step-counter {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .step-item {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            margin-bottom: 2rem;
        }

        .step-content {
            flex: 1;
        }

        .step-content h3 {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            color: var(--text-color);
        }

        .step-content p {
            color: var(--text-muted);
            line-height: 1.8;
            margin-bottom: 1rem;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .payment-item {
            text-align: center;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
            border-radius: 0.75rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .payment-item:hover {
            border-color: #6366f1;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
        }

        .payment-item i {
            font-size: 2rem;
            color: #6366f1;
            margin-bottom: 0.5rem;
            display: block;
        }

        .payment-item small {
            display: block;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .faq-accordion {
            max-width: 900px;
            margin: 0 auto;
        }

        .faq-item {
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: #6366f1;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
        }

        .faq-question {
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .faq-item.active .faq-question {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
        }

        .faq-question:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
        }

        .faq-question strong {
            color: var(--text-color);
        }

        .faq-question i {
            color: #6366f1;
            transition: transform 0.3s ease;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }

        .faq-answer.active {
            padding: 1.5rem;
            max-height: 500px;
        }

        .faq-answer p {
            color: var(--text-muted);
            line-height: 1.8;
            margin: 0;
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            padding: 2rem 0;
        }

        .timeline-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            width: 2px;
            height: 4rem;
            background: linear-gradient(180deg, #6366f1, transparent);
            transform: translateX(-50%);
        }

        .feature-badge {
            display: inline-block;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
        }

        .support-card {
            text-align: center;
            padding: 2rem;
        }

        .support-card i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .support-card h4 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .support-card p {
            color: var(--text-muted);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .support-card .btn {
            text-decoration: none;
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .support-card .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <section class="container" style="padding: 4rem 2rem; text-align: center;">
        <div style="font-size: 6rem; margin-bottom: 1rem;">📋</div>
        <h1 style="font-size: 3.5rem; margin-bottom: 1rem; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            Cara Order
        </h1>
        <p style="font-size: 1.3rem; color: var(--text-muted); max-width: 800px; margin: 0 auto; line-height: 1.8;">
            Panduan lengkap cara berbelanja di SPNB Store. Proses yang mudah, cepat, dan aman dalam beberapa langkah sederhana.
        </p>
    </section>

    <!-- Step by Step Guide -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Langkah-Langkah Berbelanja</h2>
        
        <div style="max-width: 900px; margin: 0 auto;">
            <!-- Step 1 -->
            <div class="card" style="padding: 2.5rem; margin-bottom: 2rem;">
                <div class="step-item">
                    <div class="step-counter">1</div>
                    <div class="step-content">
                        <h3>🔍 Cari Produk yang Anda Inginkan</h3>
                        <p>
                            Telusuri katalog produk kami yang lengkap. Gunakan fitur pencarian atau filter kategori untuk menemukan produk yang Anda cari dengan mudah.
                        </p>
                        <ul style="list-style: none; padding: 0; color: var(--text-muted);">
                            <li style="margin-bottom: 0.5rem;">✓ Gunakan kolom pencarian untuk produk spesifik</li>
                            <li style="margin-bottom: 0.5rem;">✓ Filter berdasarkan kategori atau harga</li>
                            <li>✓ Baca deskripsi produk dan spesifikasi dengan cermat</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="card" style="padding: 2.5rem; margin-bottom: 2rem;">
                <div class="step-item">
                    <div class="step-counter">2</div>
                    <div class="step-content">
                        <h3>👁️ Lihat Detail Produk</h3>
                        <p>
                            Klik produk untuk melihat detail lengkapnya termasuk harga, deskripsi, waktu garansi, dan review dari pembeli lain.
                        </p>
                        <ul style="list-style: none; padding: 0; color: var(--text-muted);">
                            <li style="margin-bottom: 0.5rem;">✓ Perhatikan harga dan promo yang berlaku</li>
                            <li style="margin-bottom: 0.5rem;">✓ Cek durasi garansi dan ketentuan produk</li>
                            <li>✓ Baca review pembeli sebelumnya untuk referensi</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="card" style="padding: 2.5rem; margin-bottom: 2rem;">
                <div class="step-item">
                    <div class="step-counter">3</div>
                    <div class="step-content">
                        <h3>🛒 Tambahkan ke Keranjang</h3>
                        <p>
                            Setelah menemukan produk yang sesuai, klik tombol "Beli Sekarang" atau "Tambah ke Keranjang" untuk menambahkan ke shopping cart Anda.
                        </p>
                        <ul style="list-style: none; padding: 0; color: var(--text-muted);">
                            <li style="margin-bottom: 0.5rem;">✓ Pilih jumlah produk yang diinginkan</li>
                            <li style="margin-bottom: 0.5rem;">✓ Terapkan kode voucher jika memiliki diskon</li>
                            <li>✓ Lanjut ke keranjang atau terus belanja</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="card" style="padding: 2.5rem; margin-bottom: 2rem;">
                <div class="step-item">
                    <div class="step-counter">4</div>
                    <div class="step-content">
                        <h3>📦 Review Pesanan</h3>
                        <p>
                            Masuk ke halaman keranjang untuk melihat ringkasan pesanan Anda sebelum melanjutkan ke pembayaran.
                        </p>
                        <ul style="list-style: none; padding: 0; color: var(--text-muted);">
                            <li style="margin-bottom: 0.5rem;">✓ Verifikasi produk dan jumlah yang benar</li>
                            <li style="margin-bottom: 0.5rem;">✓ Cek total harga dengan diskon yang diterapkan</li>
                            <li>✓ Ubah atau hapus produk jika diperlukan</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 5 -->
            <div class="card" style="padding: 2.5rem; margin-bottom: 2rem;">
                <div class="step-item">
                    <div class="step-counter">5</div>
                    <div class="step-content">
                        <h3>💳 Pilih Metode Pembayaran</h3>
                        <p>
                            Kami menyediakan berbagai metode pembayaran yang aman dan terpercaya. Pilih yang paling memudahkan Anda.
                        </p>
                        <div class="payment-grid">
                            <div class="payment-item">
                                <i class="fas fa-qrcode"></i>
                                <small>QRIS</small>
                            </div>
                            <div class="payment-item">
                                <i class="fas fa-university"></i>
                                <small>Transfer Bank</small>
                            </div>
                            <div class="payment-item">
                                <i class="fas fa-mobile-alt"></i>
                                <small>E-Wallet</small>
                            </div>
                            <div class="payment-item">
                                <i class="fas fa-credit-card"></i>
                                <small>Kartu Kredit</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 6 -->
            <div class="card" style="padding: 2.5rem; margin-bottom: 2rem;">
                <div class="step-item">
                    <div class="step-counter">6</div>
                    <div class="step-content">
                        <h3>✅ Selesaikan Pembayaran</h3>
                        <p>
                            Lakukan pembayaran sesuai instruksi yang diberikan. Proses pembayaran dilindungi oleh enkripsi SSL 256-bit untuk keamanan maksimal.
                        </p>
                        <ul style="list-style: none; padding: 0; color: var(--text-muted);">
                            <li style="margin-bottom: 0.5rem;">✓ Ikuti instruksi pembayaran dengan benar</li>
                            <li style="margin-bottom: 0.5rem;">✓ Perhatikan waktu batas pembayaran (tidak boleh expired)</li>
                            <li>✓ Simpan bukti pembayaran jika diperlukan</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 7 -->
            <div class="card" style="padding: 2.5rem;">
                <div class="step-item">
                    <div class="step-counter">7</div>
                    <div class="step-content">
                        <h3>🎉 Terima Produk</h3>
                        <p>
                            Pembayaran Anda akan diverifikasi secara otomatis. Produk akan langsung dikirim ke email atau dashboard Anda dalam hitungan menit.
                        </p>
                        <ul style="list-style: none; padding: 0; color: var(--text-muted);">
                            <li style="margin-bottom: 0.5rem;">✓ Verifikasi otomatis dalam 1-15 menit</li>
                            <li style="margin-bottom: 0.5rem;">✓ Akses produk melalui email atau dashboard</li>
                            <li>✓ Nikmati produk premium dengan garansi sesuai durasi</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Payment Methods Detail -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="padding: 3rem;">
            <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Metode Pembayaran yang Tersedia</h2>
            <div class="grid grid-2" style="gap: 2rem;">
                <!-- QRIS -->
                <div style="padding: 2rem; border-left: 4px solid #6366f1;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <i class="fas fa-qrcode" style="font-size: 2.5rem; color: #6366f1;"></i>
                        <h3 style="margin: 0; font-size: 1.25rem;">QRIS</h3>
                    </div>
                    <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1rem;">
                        Pembayaran dengan scanning QR code menggunakan aplikasi e-wallet favorit Anda. Proses instan tanpa perlu input nomor rekening.
                    </p>
                    <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
                        <li style="margin-bottom: 0.5rem;">✓ Verifikasi: Otomatis 1-5 menit</li>
                        <li style="margin-bottom: 0.5rem;">✓ Biaya: Gratis atau sesuai aplikasi</li>
                        <li>✓ Keamanan: Sangat aman dengan enkripsi</li>
                    </ul>
                </div>

                <!-- Virtual Account -->
                <div style="padding: 2rem; border-left: 4px solid #10b981;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <i class="fas fa-university" style="font-size: 2.5rem; color: #10b981;"></i>
                        <h3 style="margin: 0; font-size: 1.25rem;">Virtual Account</h3>
                    </div>
                    <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1rem;">
                        Transfer langsung ke nomor rekening virtual dari bank mitra kami (BNI, BRI, Mandiri, CIMB). Cocok untuk transfer dari ATM atau mobile banking.
                    </p>
                    <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
                        <li style="margin-bottom: 0.5rem;">✓ Verifikasi: 5-15 menit setelah transfer</li>
                        <li style="margin-bottom: 0.5rem;">✓ Biaya: Gratis (biaya bank ditanggung)</li>
                        <li>✓ Keamanan: Nomor unik untuk setiap transaksi</li>
                    </ul>
                </div>

                <!-- E-Wallet -->
                <div style="padding: 2rem; border-left: 4px solid #f59e0b;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <i class="fas fa-mobile-alt" style="font-size: 2.5rem; color: #f59e0b;"></i>
                        <h3 style="margin: 0; font-size: 1.25rem;">E-Wallet</h3>
                    </div>
                    <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1rem;">
                        Pembayaran melalui dompet digital seperti GoPay, OVO, DANA, dan ShopeePay. Proses paling cepat dan mudah dengan cashback bonus.
                    </p>
                    <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
                        <li style="margin-bottom: 0.5rem;">✓ Verifikasi: Otomatis 1-5 menit</li>
                        <li style="margin-bottom: 0.5rem;">✓ Biaya: Gratis (sering ada cashback)</li>
                        <li>✓ Keamanan: Perlindungan dari aplikasi e-wallet</li>
                    </ul>
                </div>

                <!-- Credit Card -->
                <div style="padding: 2rem; border-left: 4px solid #ec4899;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <i class="fas fa-credit-card" style="font-size: 2.5rem; color: #ec4899;"></i>
                        <h3 style="margin: 0; font-size: 1.25rem;">Kartu Kredit</h3>
                    </div>
                    <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1rem;">
                        Bayar dengan kartu kredit dari berbagai bank. Opsi cicilan tersedia untuk transaksi tertentu dengan bunga 0%.
                    </p>
                    <ul style="list-style: none; padding: 0; color: var(--text-muted); font-size: 0.95rem;">
                        <li style="margin-bottom: 0.5rem;">✓ Verifikasi: Otomatis 1-5 menit</li>
                        <li style="margin-bottom: 0.5rem;">✓ Biaya: Sesuai bank masing-masing</li>
                        <li>✓ Keamanan: Perlindungan kartu bank</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Tips for Smooth Shopping -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Tips Berbelanja Lancar</h2>
        <div class="grid grid-3">
            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">⏰</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Perhatikan Waktu Pembayaran</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Pembayaran harus diselesaikan sebelum batas waktu yang ditentukan. Jika lewat, pesanan akan otomatis dibatalkan.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✉️</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Cek Email Secara Berkala</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Notifikasi pembayaran dan produk akan dikirim via email. Pastikan email Anda aktif dan cek folder spam jika tidak menerima.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Simpan Bukti Pembayaran</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Screenshot atau catat nomor transaksi Anda. Gunakan untuk verifikasi jika ada kendala dengan pembayaran.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔐</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Gunakan Password Kuat</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Pastikan akun Anda aman dengan password yang kuat. Jangan bagikan data login dengan siapa pun.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📱</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Pastikan Koneksi Internet</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Gunakan koneksi internet yang stabil saat melakukan pembayaran untuk menghindari kesalahan transaksi.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔔</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Aktifkan Notifikasi</h3>
                <p style="color: var(--text-muted); line-height: 1.6;">
                    Aktifkan notifikasi browser atau email untuk mendapat update terbaru tentang status pesanan Anda.
                </p>
            </div>
        </div>
    </section>

    <!-- Support Section -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Butuh Bantuan?</h2>
        <div class="grid grid-3">
            <div class="card support-card">
                <i class="fab fa-whatsapp" style="color: #25D366;"></i>
                <h4>WhatsApp</h4>
                <p style="margin-bottom: 1.5rem;">Hubungi kami melalui WhatsApp untuk bantuan cepat dan responsif.</p>
                <a href="https://wa.me/62895386763040" target="_blank" class="btn">
                    <i class="fab fa-whatsapp"></i> Chat WhatsApp
                </a>
            </div>

            <div class="card support-card">
                <i class="fas fa-envelope" style="color: #3b82f6;"></i>
                <h4>Email</h4>
                <p style="margin-bottom: 1.5rem;">Kirim pertanyaan Anda via email dan kami akan meresponnya dalam 24 jam.</p>
                <a href="mailto:support@andispnb.shop" class="btn">
                    <i class="fas fa-envelope"></i> Kirim Email
                </a>
            </div>

            <div class="card support-card">
                <i class="fas fa-headset" style="color: #8b5cf6;"></i>
                <h4>Live Chat</h4>
                <p style="margin-bottom: 1.5rem;">Chat dengan customer service kami untuk bantuan real-time dan instant.</p>
                <a href="contact.php" class="btn">
                    <i class="fas fa-comments"></i> Mulai Chat
                </a>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="container" style="margin-bottom: 4rem;">
        <h2 style="font-size: 2.5rem; text-align: center; margin-bottom: 3rem;">Pertanyaan yang Sering Diajukan</h2>
        <div class="faq-accordion">
            <div class="faq-item">
                <div class="faq-question">
                    <strong>Berapa lama proses verifikasi pembayaran?</strong>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>
                        Untuk pembayaran via QRIS dan e-wallet, verifikasi berlangsung otomatis dalam 1-5 menit. Untuk Virtual Account, maksimal 15 menit setelah transfer berhasil. Jika lebih dari waktu yang ditentukan, hubungi customer service kami.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    <strong>Apakah produk langsung aktif setelah pembayaran?</strong>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>
                        Ya, untuk produk digital seperti Netflix dan subscription lainnya, akses akan langsung dikirim melalui email setelah pembayaran terverifikasi. Anda juga bisa melihat detail akses di dashboard member Anda. Produk lokal akan diproses sesuai dengan kebijakan yang berlaku.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    <strong>Apa yang terjadi jika pembayaran gagal atau expired?</strong>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>
                        Jika pembayaran gagal atau melewati batas waktu (expired), pesanan akan otomatis dibatalkan dan stok produk akan dikembalikan. Anda bisa membuat pesanan baru dengan mengulang proses dari awal tanpa ada biaya tambahan.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    <strong>Apakah ada garansi untuk produk yang dibeli?</strong>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>
                        Ya, semua produk memiliki garansi sesuai durasi yang tertera di halaman produk. Untuk Netflix sharing 1 bulan = garansi 1 bulan penuh akses. Jika ada masalah dalam masa garansi, kami akan melakukan replace atau refund 100% tanpa ribet.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    <strong>Bisakah membatalkan pesanan setelah membayar?</strong>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>
                        Pesanan yang belum dibayar akan otomatis dibatalkan setelah 1 jam. Untuk pesanan yang sudah dibayar, pembatalan hanya bisa dilakukan jika produk belum dikirim dengan menghubungi customer service kami melalui WhatsApp atau email.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    <strong>Bagaimana jika saya lupa password atau akun?</strong>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>
                        Gunakan fitur "Lupa Password" di halaman login untuk mereset password Anda. Kami akan mengirim link reset ke email Anda. Jika mengalami kesulitan, hubungi customer service kami untuk bantuan lebih lanjut.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    <strong>Apakah data pribadi saya aman?</strong>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>
                        Tentu saja! Semua data pribadi dan transaksi Anda dilindungi dengan enkripsi SSL 256-bit dan keamanan tingkat bank. Kami tidak akan pernah membagikan data Anda kepada pihak ketiga tanpa persetujuan Anda.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="container" style="margin-bottom: 4rem;">
        <div class="card" style="text-align: center; padding: 4rem; background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <div style="font-size: 5rem; margin-bottom: 1.5rem;">🛍️</div>
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: white;">Siap untuk Memulai?</h2>
            <p style="font-size: 1.2rem; color: rgba(255,255,255,0.9); margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                Ikuti langkah-langkah sederhana di atas dan nikmati pengalaman berbelanja yang mudah, aman, dan menyenangkan.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <?php if ($user): ?>
                <a href="/" class="btn" style="background: white; color: var(--primary-color); padding: 1.25rem 2.5rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-shopping-bag"></i> Mulai Belanja
                </a>
                <?php else: ?>
                <a href="register.php" class="btn" style="background: white; color: var(--primary-color); padding: 1.25rem 2.5rem; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-user-plus"></i> Daftar Sekarang
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
                <a href="cara-order.php">Cara Order</a>
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

    <script>
        // FAQ Toggle
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', function() {
                const faqItem = this.parentElement;
                const answer = this.nextElementSibling;
                
                // Tutup FAQ lain
                document.querySelectorAll('.faq-item').forEach(item => {
                    if (item !== faqItem) {
                        item.classList.remove('active');
                        item.querySelector('.faq-answer').classList.remove('active');
                    }
                });
                
                // Toggle FAQ saat ini
                faqItem.classList.toggle('active');
                answer.classList.toggle('active');
            });
        });
    </script>
</body>
</html>