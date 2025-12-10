<?php
/**
 * TOP UP GAME - Portal
 * Halaman utama untuk memilih game yang ingin di top up
 */

require_once 'config.php';
require_once 'topup-game-helper.php';

// Get active games
$games = [];
try {
    $stmt = $conn->query("SELECT * FROM atlantic_games WHERE is_active = 1 ORDER BY sort_order ASC, game_name ASC");
    $games = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}

// Get page settings
$siteName = getSiteName();
$pageTitle = "Top Up Game - " . $siteName;
$pageDesc = "Top up game online murah dan cepat. Mobile Legends, Free Fire, PUBG Mobile, dan game populer lainnya.";

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDesc); ?>">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #1a1a2e 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }
        
        /* Header */
        .topup-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: white;
        }
        
        .logo i {
            font-size: 1.75rem;
        }
        
        .logo span {
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .header-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .header-nav a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .header-nav .btn-login {
            background: white;
            color: var(--primary);
            font-weight: 600;
        }
        
        .header-nav .btn-login:hover {
            background: rgba(255,255,255,0.9);
        }
        
        /* Banner */
        .banner-section {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .banner-slider {
            position: relative;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .banner-default {
            height: 250px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, #ec4899 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        
        .banner-default h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .banner-default p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        /* Search Section */
        .search-section {
            max-width: 1200px;
            margin: 0 auto 2rem;
            padding: 0 1rem;
        }
        
        .search-box {
            position: relative;
            max-width: 500px;
        }
        
        .search-box input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border-radius: 1rem;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .search-box input::placeholder {
            color: var(--text-secondary);
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        /* Section Title */
        .section-title {
            max-width: 1200px;
            margin: 0 auto 1.5rem;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .section-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        /* Games Grid */
        .games-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem 3rem;
        }
        
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1.25rem;
        }
        
        .game-card {
            background: var(--bg-card);
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
        }
        
        .game-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }
        
        .game-card-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--bg-card) 0%, #2d3748 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .game-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .game-card-img i {
            font-size: 3rem;
            color: var(--text-secondary);
        }
        
        .game-card-body {
            padding: 1rem;
            text-align: center;
        }
        
        .game-card-body h3 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }
        
        .game-card-body span {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        /* Check Order Section */
        .check-order-section {
            max-width: 1200px;
            margin: 0 auto 3rem;
            padding: 0 1rem;
        }
        
        .check-order-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            border: 2px solid var(--border-color);
        }
        
        .check-order-card > i {
            font-size: 3rem;
            color: var(--primary);
        }
        
        .check-order-content {
            flex: 1;
        }
        
        .check-order-content h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        
        .check-order-content p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .check-order-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .check-order-form input {
            flex: 1;
            min-width: 200px;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 2px solid var(--border-color);
            background: var(--bg-dark);
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .check-order-form input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .check-order-form button {
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .check-order-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(99, 102, 241, 0.4);
        }
        
        /* Footer */
        .topup-footer {
            background: var(--bg-card);
            padding: 2rem 1rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }
        
        .topup-footer p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .topup-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header-nav span {
                display: none;
            }
            
            .banner-default {
                height: 180px;
            }
            
            .banner-default h2 {
                font-size: 1.5rem;
            }
            
            .games-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 1rem;
            }
            
            .game-card-img {
                height: 120px;
            }
            
            .game-card-body {
                padding: 0.75rem;
            }
            
            .game-card-body h3 {
                font-size: 0.85rem;
            }
            
            .check-order-card {
                flex-direction: column;
                text-align: center;
            }
            
            .check-order-form {
                width: 100%;
                flex-direction: column;
            }
            
            .check-order-form input,
            .check-order-form button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="topup-header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <i class="fas fa-gamepad"></i>
                <span><?php echo htmlspecialchars($siteName); ?></span>
            </a>
            
            <nav class="header-nav">
                <a href="index.php"><i class="fas fa-home"></i> <span>Beranda</span></a>
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php"><i class="fas fa-user"></i> <span><?php echo htmlspecialchars($user['nama'] ?? 'User'); ?></span></a>
                    <a href="riwayat.php"><i class="fas fa-history"></i> <span>Riwayat</span></a>
                <?php else: ?>
                    <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <!-- Banner Slider -->
    <section class="banner-section">
        <div class="banner-slider">
            <div class="banner-default">
                <div>
                    <h2>🎮 Top Up Game Instant</h2>
                    <p>Proses cepat, harga terjangkau, pembayaran mudah!</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Search -->
    <section class="search-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchGame" placeholder="Cari game..." onkeyup="filterGames()">
        </div>
    </section>
    
    <!-- Games Grid -->
    <section class="games-section">
        <div class="section-title">
            <i class="fas fa-fire"></i>
            <h2>Game Populer</h2>
        </div>
        
        <?php if (empty($games)): ?>
            <div class="empty-state">
                <i class="fas fa-gamepad"></i>
                <h3>Belum Ada Game</h3>
                <p>Game akan segera tersedia. Silakan cek kembali nanti.</p>
            </div>
        <?php else: ?>
            <div class="games-grid" id="gamesGrid">
                <?php foreach ($games as $game): ?>
                    <a href="topup-game-detail.php?game=<?php echo urlencode($game['game_code']); ?>" 
                       class="game-card" 
                       data-name="<?php echo strtolower($game['game_name']); ?>">
                        <div class="game-card-img">
                            <?php if (!empty($game['game_icon']) && file_exists('assets/img/games/' . $game['game_icon'])): ?>
                                <img src="assets/img/games/<?php echo htmlspecialchars($game['game_icon']); ?>" 
                                     alt="<?php echo htmlspecialchars($game['game_name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-gamepad"></i>
                            <?php endif; ?>
                        </div>
                        <div class="game-card-body">
                            <h3><?php echo htmlspecialchars($game['game_name']); ?></h3>
                            <span><?php echo htmlspecialchars($game['provider'] ?? 'Top Up'); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    
    <!-- Check Order -->
    <section class="check-order-section">
        <div class="check-order-card">
            <i class="fas fa-search-dollar"></i>
            <div class="check-order-content">
                <h3>Cek Status Pesanan</h3>
                <p>Masukkan ID Order dan nomor WhatsApp untuk melihat status pesanan Anda</p>
                <form class="check-order-form" action="topup-game-status.php" method="GET">
                    <input type="text" name="order" placeholder="ID Order (TG-XXXXXXXX)" required>
                    <input type="text" name="phone" placeholder="Nomor WhatsApp" required>
                    <button type="submit"><i class="fas fa-search"></i> Cek Status</button>
                </form>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="topup-footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. All rights reserved.</p>
        <p><a href="index.php">Kembali ke Beranda</a></p>
    </footer>
    
    <script>
        // Search/Filter games
        function filterGames() {
            const search = document.getElementById('searchGame').value.toLowerCase();
            const cards = document.querySelectorAll('.game-card');
            
            cards.forEach(card => {
                const name = card.dataset.name;
                if (name.includes(search)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
