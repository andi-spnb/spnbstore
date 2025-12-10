<?php
require_once 'config.php';
require_once 'google-auth-helper.php';

$error = '';
$success = '';

// Cek session messages
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_SESSION['login_success'])) {
    $success = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

// Redirect jika sudah login
if (isLoggedIn()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $whatsapp = trim($_POST['whatsapp']);
    
    // Validasi
    if (empty($username) || empty($email) || empty($password) || empty($nama_lengkap)) {
        $error = 'Semua field wajib harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter!';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username hanya boleh mengandung huruf, angka, dan underscore!';
    } else {
        // Cek username sudah ada
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username sudah digunakan!';
        } else {
            // Cek email sudah ada
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email sudah terdaftar!';
            } else {
                // Insert user baru
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password, nama_lengkap, whatsapp, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                if ($stmt->execute([$username, $email, $hashed_password, $nama_lengkap, $whatsapp])) {
                    $success = 'Registrasi berhasil! Mengalihkan ke dashboard...';
                    
                    // Auto login
                    $_SESSION['user_id'] = $conn->lastInsertId();
                    $_SESSION['username'] = $username;
                    
                    // Redirect after 2 seconds
                    header("refresh:2;url=dashboard.php");
                } else {
                    $error = 'Terjadi kesalahan. Silakan coba lagi.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - <?php echo SITE_NAME; ?></title>
    <?php include 'meta-tags.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo getGoogleButtonStyles(); ?>
</head>
<body>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
        <div class="card" style="max-width: 500px; width: 100%;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <a href="/" style="font-size: 2rem; font-weight: 700; text-decoration: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    SPNB STORE
                </a>
                <h2 style="margin-top: 1rem; font-size: 1.75rem;">Buat Akun Baru</h2>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Daftar untuk mulai berbelanja produk digital premium</p>
            </div>
            
            <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger-color); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: var(--danger-color);">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success-color); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: var(--success-color);">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <!-- Google Login Button -->
            <?php if (defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID)): ?>
            <div style="margin-bottom: 1.5rem;">
                <?php echo renderGoogleButton('Daftar dengan Google'); ?>
            </div>
            
            <div class="auth-divider">
                <span>atau daftar dengan email</span>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Username <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="Pilih username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               pattern="[a-zA-Z0-9_]+" title="Hanya huruf, angka, dan underscore">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email <span style="color: var(--danger-color);">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nama Lengkap <span style="color: var(--danger-color);">*</span></label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap Anda" required 
                           value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">WhatsApp <span style="color: var(--text-muted); font-weight: normal;">(Opsional)</span></label>
                    <input type="text" name="whatsapp" class="form-control" placeholder="08xxxxxxxxxx" 
                           value="<?php echo isset($_POST['whatsapp']) ? htmlspecialchars($_POST['whatsapp']) : ''; ?>">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Password <span style="color: var(--danger-color);">*</span></label>
                        <div style="position: relative;">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Min. 6 karakter" required minlength="6" style="padding-right: 40px;">
                            <button type="button" onclick="togglePassword('password', 'toggleIcon1')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 5px;">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Konfirmasi <span style="color: var(--danger-color);">*</span></label>
                        <div style="position: relative;">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ketik ulang" required style="padding-right: 40px;">
                            <button type="button" onclick="togglePassword('confirm_password', 'toggleIcon2')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 5px;">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div style="margin: 1.5rem 0; padding: 1rem; background: rgba(99, 102, 241, 0.1); border-radius: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">
                    <i class="fas fa-info-circle" style="color: var(--primary-color);"></i>
                    Dengan mendaftar, Anda menyetujui <a href="#" style="color: var(--primary-color);">Syarat & Ketentuan</a> dan <a href="#" style="color: var(--primary-color);">Kebijakan Privasi</a> kami.
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem;">
                    <i class="fas fa-user-plus"></i> Daftar Sekarang
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 2rem; color: var(--text-muted);">
                Sudah punya akun? <a href="login.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">Login Di sini</a>
            </div>
            
            <div style="text-align: center; margin-top: 1rem;">
                <a href="/" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Password match validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak sama!');
            }
        });
    </script>
</body>
</html>