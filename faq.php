<?php
require_once 'config.php';

$user = isLoggedIn() ? getUserData() : null;

// FAQ Data
$faqs = [
    [
        'category' => 'Umum',
        'icon' => '❓',
        'questions' => [
            [
                'q' => 'Apa itu Premiumisme Store?',
                'a' => 'Premiumisme Store adalah toko digital yang menyediakan berbagai produk premium seperti akun Canva Pro, Netflix, Spotify, dan masih banyak lagi dengan harga terjangkau.'
            ],
            [
                'q' => 'Apakah produk di sini legal dan aman?',
                'a' => 'Ya, semua produk kami dijamin 100% legal dan aman. Kami hanya menjual produk original dengan garansi resmi.'
            ],
            [
                'q' => 'Bagaimana cara berbelanja di sini?',
                'a' => 'Sangat mudah! Cukup daftar akun, top-up saldo, pilih produk, dan checkout. Produk otomatis akan dikirim ke email Anda.'
            ]
        ]
    ],
    [
        'category' => 'Pembayaran',
        'icon' => '💳',
        'questions' => [
            [
                'q' => 'Metode pembayaran apa saja yang tersedia?',
                'a' => 'Kami menerima pembayaran via QRIS, Transfer Bank (BCA, BRI, Mandiri, BNI), E-Wallet (GoPay, OVO, Dana, ShopeePay), dan Minimarket (Alfamart, Indomaret).'
            ],
            [
                'q' => 'Berapa minimal top-up saldo?',
                'a' => 'Minimal top-up adalah Rp 10.000. Tidak ada maksimal, Anda bisa top-up sesuai kebutuhan.'
            ],
            [
                'q' => 'Apakah ada biaya admin?',
                'a' => 'Biaya admin tergantung metode pembayaran yang Anda pilih. Biasanya berkisar Rp 0 - Rp 5.000.'
            ],
            [
                'q' => 'Berapa lama proses pembayaran dikonfirmasi?',
                'a' => 'Pembayaran QRIS dan E-Wallet biasanya otomatis dalam 1-5 menit. Transfer bank maksimal 15 menit.'
            ]
        ]
    ],
    [
        'category' => 'Produk & Pengiriman',
        'icon' => '📦',
        'questions' => [
            [
                'q' => 'Berapa lama produk dikirim setelah pembayaran?',
                'a' => 'Produk otomatis langsung dikirim ke email dalam 1-5 menit. Produk manual maksimal 2 jam kerja.'
            ],
            [
                'q' => 'Apakah produk bisa dikembalikan?',
                'a' => 'Produk digital tidak bisa dikembalikan kecuali terdapat error atau tidak sesuai deskripsi. Hubungi CS kami untuk klaim.'
            ],
            [
                'q' => 'Bagaimana jika produk tidak diterima?',
                'a' => 'Cek folder spam/junk di email Anda. Jika masih belum terima, hubungi customer service dengan menyertakan bukti transaksi.'
            ],
            [
                'q' => 'Apakah ada garansi produk?',
                'a' => 'Ya! Semua produk bergaransi 7-30 hari tergantung jenis produk. Jika ada masalah, kami akan refund atau replace.'
            ]
        ]
    ],
    [
        'category' => 'Akun & Keamanan',
        'icon' => '🔐',
        'questions' => [
            [
                'q' => 'Bagaimana cara mengubah password?',
                'a' => 'Login ke akun Anda, masuk ke menu Profil, lalu klik "Ubah Password". Masukkan password lama dan password baru.'
            ],
            [
                'q' => 'Apakah data pribadi saya aman?',
                'a' => 'Sangat aman! Kami menggunakan enkripsi SSL dan tidak pernah membagikan data pribadi Anda kepada pihak ketiga.'
            ],
            [
                'q' => 'Lupa password, bagaimana cara reset?',
                'a' => 'Klik "Lupa Password" di halaman login, masukkan email Anda, dan kami akan kirimkan link reset password.'
            ]
        ]
    ],
    [
        'category' => 'Lainnya',
        'icon' => '💡',
        'questions' => [
            [
                'q' => 'Bagaimana cara menjadi reseller?',
                'a' => 'Hubungi customer service kami via WhatsApp untuk informasi program reseller. Dapatkan harga khusus dan komisi menarik!'
            ],
            [
                'q' => 'Apakah ada diskon atau promo?',
                'a' => 'Follow social media kami untuk info diskon dan promo terbaru! Kami sering adakan flash sale dan giveaway.'
            ],
            [
                'q' => 'Bagaimana cara menghubungi customer service?',
                'a' => 'Hubungi kami via WhatsApp di nomor yang tertera di footer. CS kami aktif setiap hari pukul 08.00 - 22.00 WIB.'
            ]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Pertanyaan Umum | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .faq-item {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s;
        }
        .faq-item:hover {
            border-color: var(--primary-color);
        }
        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }
        .faq-question:hover {
            background: rgba(99, 102, 241, 0.05);
        }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            padding: 0 1.5rem;
        }
        .faq-answer.active {
            max-height: 500px;
            padding: 0 1.5rem 1.5rem;
        }
        .faq-icon {
            transition: transform 0.3s;
        }
        .faq-icon.active {
            transform: rotate(180deg);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    <!-- Header -->
    <section class="container" style="padding: 4rem 2rem 2rem; text-align: center;">
        <div style="font-size: 6rem; margin-bottom: 1rem;">❓</div>
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">Frequently Asked Questions</h1>
        <p style="font-size: 1.2rem; color: var(--text-muted); max-width: 600px; margin: 0 auto;">
            Temukan jawaban untuk pertanyaan yang sering diajukan tentang layanan kami
        </p>
    </section>

    <!-- Quick Links -->
    <div class="container" style="margin-bottom: 3rem;">
        <div class="grid grid-5">
            <?php foreach ($faqs as $category): ?>
            <a href="#<?php echo strtolower(str_replace(' ', '-', $category['category'])); ?>" class="card" style="text-decoration: none; text-align: center; padding: 1.5rem; transition: transform 0.3s;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;"><?php echo $category['icon']; ?></div>
                <div style="font-weight: 600; color: var(--text-primary);"><?php echo $category['category']; ?></div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
                    <?php echo count($category['questions']); ?> pertanyaan
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FAQ Content -->
    <div class="container">
        <?php foreach ($faqs as $category): ?>
        <div id="<?php echo strtolower(str_replace(' ', '-', $category['category'])); ?>" style="margin-bottom: 4rem;">
            <h2 style="font-size: 2rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                <span style="font-size: 2.5rem;"><?php echo $category['icon']; ?></span>
                <?php echo $category['category']; ?>
            </h2>
            
            <?php foreach ($category['questions'] as $index => $faq): ?>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(<?php echo $index; ?>, '<?php echo strtolower(str_replace(' ', '-', $category['category'])); ?>')">
                    <div style="font-weight: 600; color: var(--text-primary); flex: 1;">
                        <?php echo $faq['q']; ?>
                    </div>
                    <i class="fas fa-chevron-down faq-icon" id="icon-<?php echo strtolower(str_replace(' ', '-', $category['category'])); ?>-<?php echo $index; ?>"></i>
                </div>
                <div class="faq-answer" id="answer-<?php echo strtolower(str_replace(' ', '-', $category['category'])); ?>-<?php echo $index; ?>">
                    <p style="color: var(--text-muted); line-height: 1.8;"><?php echo $faq['a']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Still Have Questions -->
    <div class="container" style="margin-top: 4rem; margin-bottom: 4rem;">
        <div class="card" style="text-align: center; padding: 3rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));">
            <div style="font-size: 4rem; margin-bottom: 1rem;">💬</div>
            <h2 style="font-size: 2rem; margin-bottom: 1rem;">Masih Ada Pertanyaan?</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 1.1rem;">
                Tim customer service kami siap membantu Anda 24/7
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="contact.php" class="btn btn-primary" style="padding: 1rem 2rem; text-decoration: none;">
                    <i class="fas fa-envelope"></i> Hubungi Kami
                </a>
                <a href="https://wa.me/6281234567890" target="_blank" class="btn btn-success" style="padding: 1rem 2rem; text-decoration: none; background: #25D366;">
                    <i class="fab fa-whatsapp"></i> Chat WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3 style="background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.5rem; margin-bottom: 1rem;">premiumisme.store</h3>
                <p>Toko digital terpercaya dengan produk premium berkualitas.</p>
            </div>
            
            <div class="footer-section">
                <h3>Informasi</h3>
                <a href="about.php">Tentang Kami</a>
                <a href="faq.php">FAQ</a>
                <a href="contact.php">Hubungi Kami</a>
            </div>
            
            <div class="footer-section">
                <h3>Bantuan</h3>
                <a href="#">Cara Pembelian</a>
                <a href="#">Syarat & Ketentuan</a>
                <a href="#">Kebijakan Privasi</a>
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
    <script>
        function toggleFaq(index, category) {
            const answer = document.getElementById(`answer-${category}-${index}`);
            const icon = document.getElementById(`icon-${category}-${index}`);
            
            answer.classList.toggle('active');
            icon.classList.toggle('active');
        }

        // Search functionality
        document.getElementById('faqSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>