<?php
require_once 'config.php';

$user = isLoggedIn() ? getUserData() : null;

$message = '';
$message_type = 'success';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $pesan = trim($_POST['pesan']);
    
    if (!empty($nama) && !empty($email) && !empty($subject) && !empty($pesan)) {
        $message = 'Pesan Anda berhasil dikirim! Kami akan membalas dalam 1x24 jam.';
        $message_type = 'success';
    } else {
        $message = 'Mohon lengkapi semua field!';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hubungi Kami | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <section class="container" style="padding: 4rem 2rem 2rem; text-align: center;">
        <div style="font-size: 6rem; margin-bottom: 1rem;">📞</div>
        <h1 style="font-size: 3.5rem; margin-bottom: 1rem;">Hubungi Kami</h1>
        <p style="font-size: 1.2rem; color: var(--text-muted); max-width: 700px; margin: 0 auto;">
            Punya pertanyaan atau butuh bantuan? Tim kami siap membantu Anda 24/7
        </p>
    </section>

    <div class="container">
        <?php if ($message): ?>
        <div style="background: <?php echo $message_type == 'success' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; border: 1px solid <?php echo $message_type == 'success' ? 'var(--success-color)' : 'var(--danger-color)'; ?>; border-radius: 0.5rem; padding: 1rem; margin-bottom: 2rem; color: <?php echo $message_type == 'success' ? 'var(--success-color)' : 'var(--danger-color)'; ?>; text-align: center;">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-3" style="margin-bottom: 4rem;">
            <a href="https://wa.me/6281234567890" target="_blank" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #25D366, #128C7E); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem; color: white;">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem; color: var(--text-primary);">WhatsApp</h3>
                <p style="color: var(--text-muted); margin-bottom: 1rem;">Chat langsung dengan CS kami</p>
                <div style="color: #25D366; font-weight: 600;"><i class="fab fa-whatsapp"></i> 0812-3456-7890</div>
            </a>

            <a href="mailto:support@premiumisme.store" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem; color: white;">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem; color: var(--text-primary);">Email</h3>
                <p style="color: var(--text-muted); margin-bottom: 1rem;">Kirim email untuk pertanyaan detail</p>
                <div style="color: var(--primary-color); font-weight: 600;"><i class="fas fa-envelope"></i> support@premiumisme.store</div>
            </a>

            <div class="card" style="text-align: center; padding: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f59e0b, #ef4444); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem; color: white;">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem; color: var(--text-primary);">Jam Operasional</h3>
                <p style="color: var(--text-muted); margin-bottom: 1rem;">Kami melayani setiap hari</p>
                <div style="color: var(--warning-color); font-weight: 600;"><i class="fas fa-clock"></i> 08:00 - 22:00 WIB</div>
            </div>
        </div>

        <div class="grid grid-2" style="gap: 2rem; margin-bottom: 4rem;">
            <div class="card">
                <h2 style="font-size: 2rem; margin-bottom: 1.5rem;"><i class="fas fa-paper-plane"></i> Kirim Pesan</h2>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap *</label>
                        <input type="text" name="nama" class="form-control" value="<?php echo $user ? htmlspecialchars($user['nama_lengkap'] ?: $user['username']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $user ? htmlspecialchars($user['email']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subjek *</label>
                        <select name="subject" class="form-control" required>
                            <option value="">Pilih subjek pesan</option>
                            <option value="Pertanyaan Produk">Pertanyaan Produk</option>
                            <option value="Masalah Pembayaran">Masalah Pembayaran</option>
                            <option value="Komplain Pesanan">Komplain Pesanan</option>
                            <option value="Request Produk">Request Produk Baru</option>
                            <option value="Kerjasama">Kerjasama / Partnership</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pesan *</label>
                        <textarea name="pesan" class="form-control" rows="6" placeholder="Tulis pesan Anda..." required></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn btn-primary" style="width: 100%; padding: 1rem; justify-content: center;">
                        <i class="fas fa-paper-plane"></i> Kirim Pesan
                    </button>
                </form>
            </div>

            <div>
                <div class="card" style="margin-bottom: 2rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));">
                    <div style="font-size: 3rem; margin-bottom: 1rem; text-align: center;">💡</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem; text-align: center;">Cek FAQ Dulu</h3>
                    <p style="color: var(--text-muted); text-align: center; margin-bottom: 1.5rem;">
                        Mungkin pertanyaan Anda sudah terjawab di FAQ
                    </p>
                    <a href="faq.php" class="btn btn-primary" style="width: 100%; justify-content: center; text-decoration: none;">
                        <i class="fas fa-question-circle"></i> Lihat FAQ
                    </a>
                </div>

                <div class="card">
                    <h3 style="font-size: 1.5rem; margin-bottom: 1.5rem;"><i class="fas fa-share-alt"></i> Follow Us</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                        Ikuti media sosial kami untuk info promo terbaru!
                    </p>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <a href="#" class="btn btn-secondary" style="flex: 1; justify-content: center; text-decoration: none;">
                            <i class="fab fa-instagram"></i> Instagram
                        </a>
                        <a href="#" class="btn btn-secondary" style="flex: 1; justify-content: center; text-decoration: none;">
                            <i class="fab fa-telegram"></i> Telegram
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3 style="background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.5rem; margin-bottom: 1rem;">premiumisme.store</h3>
                <p>Toko digital terpercaya</p>
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
                <a href="logout.php">Logout</a>
                <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom"><p>Made with 💜 Premiumisme</p></div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>