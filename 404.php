<?php
require_once 'config.php';

http_response_code(404);

$user = isLoggedIn() ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .error-content {
            text-align: center;
            max-width: 600px;
        }
        .error-number {
            font-size: 10rem;
            font-weight: 900;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-emoji {
            font-size: 8rem;
            margin-bottom: 2rem;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="/" class="navbar-brand">premiumisme.store</a>
        
        <div class="navbar-actions">
            <div class="nav-icon" id="themeToggle">
                <i class="fas fa-moon"></i>
            </div>
            <?php if ($user): ?>
                <a href="dashboard.php" class="nav-icon">
                    <i class="fas fa-home"></i>
                </a>
                <a href="profil.php" class="avatar-btn">
                    <img src="assets/img/avatars/<?php echo $user['avatar']; ?>.png" alt="Avatar">
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="error-container">
        <div class="error-content">
            <div class="error-emoji">🔍</div>
            <div class="error-number">404</div>
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Oops! Halaman Tidak Ditemukan</h1>
            <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 3rem;">
                Sepertinya halaman yang Anda cari tidak ada atau telah dipindahkan.<br>
                Jangan khawatir, mari kembali ke tempat yang tepat!
            </p>
            
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-bottom: 3rem;">
                <a href="/" class="btn btn-primary" style="padding: 1rem 2rem; text-decoration: none;">
                    <i class="fas fa-home"></i> Kembali ke Beranda
                </a>
                <a href="search.php" class="btn btn-secondary" style="padding: 1rem 2rem; text-decoration: none;">
                    <i class="fas fa-search"></i> Cari Produk
                </a>
                <?php if ($user): ?>
                <a href="dashboard.php" class="btn btn-secondary" style="padding: 1rem 2rem; text-decoration: none;">
                    <i class="fas fa-dashboard"></i> Dashboard
                </a>
                <?php endif; ?>
            </div>

            <!-- Popular Categories -->
            <div style="margin-top: 4rem;">
                <h3 style="font-size: 1.25rem; margin-bottom: 1.5rem;">Atau lihat kategori populer:</h3>
                <?php
                $stmt = $conn->query("SELECT * FROM categories ORDER BY nama ASC LIMIT 4");
                $categories = $stmt->fetchAll();
                ?>
                <div class="grid grid-4">
                    <?php foreach ($categories as $cat): ?>
                    <a href="kategori.php?slug=<?php echo $cat['slug']; ?>" class="card" style="text-decoration: none; text-align: center; padding: 1.5rem; transition: transform 0.3s;">
                        <div style="font-size: 3rem; margin-bottom: 0.5rem;"><?php echo $cat['icon']; ?></div>
                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo $cat['nama']; ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>