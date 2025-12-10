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
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Update last login
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            redirect('dashboard.php');
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php include 'meta-tags.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo getGoogleButtonStyles(); ?>
</head>
<body>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
        <div class="card" style="max-width: 450px; width: 100%;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <a href="/" style="font-size: 2rem; font-weight: 700; text-decoration: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    SPNB STORE
                </a>
                <h2 style="margin-top: 1rem; font-size: 1.75rem;">Selamat Datang Kembali!</h2>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Login untuk melanjutkan ke dashboard Anda</p>
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
                <?php echo renderGoogleButton('Masuk dengan Google'); ?>
            </div>
            
            <div class="auth-divider">
                <span>atau masuk dengan email</span>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Username atau Email</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username atau email" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required style="padding-right: 45px;">
                        <button type="button" onclick="togglePassword()" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted);">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 2rem; color: var(--text-muted);">
                Belum punya akun? <a href="register.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">Daftar Sekarang</a>
            </div>
            
            <div style="text-align: center; margin-top: 1rem;">
                <a href="/" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
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
    </script>
</body>
</html>