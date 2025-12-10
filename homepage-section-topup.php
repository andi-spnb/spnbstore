<?php
/**
 * HOMEPAGE SECTION - Top Up Game
 * 
 * Copy snippet ini ke index.php untuk menambahkan section top up game
 * Letakkan setelah hero section atau di tempat yang diinginkan
 */

// Get active service categories
$categories = $conn->query("SELECT * FROM atlantic_service_categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

// Get popular games (top 6)
$popularGames = $conn->query("SELECT * FROM atlantic_games WHERE is_active = 1 ORDER BY sort_order ASC, game_name ASC LIMIT 6")->fetchAll();
?>

<!-- =====================================================
     SECTION: LAYANAN TOP UP
     ===================================================== -->
<section class="topup-services-section">
    <div class="container">
        <!-- Banner -->
        <div class="topup-banner">
            <div class="topup-banner-content">
                <h2>🎮 Top Up Game Online</h2>
                <p>Diamond ML, UC PUBG, Voucher Game & lebih banyak lagi!</p>
                <a href="topup-game.php" class="btn-topup-banner">
                    <i class="fas fa-gamepad"></i> Mulai Top Up
                </a>
            </div>
            <div class="topup-banner-decoration">
                <i class="fas fa-gem"></i>
                <i class="fas fa-coins"></i>
                <i class="fas fa-crown"></i>
            </div>
        </div>
        
        <!-- Category Cards -->
        <div class="topup-categories">
            <?php foreach ($categories as $cat): ?>
            <a href="<?php echo htmlspecialchars($cat['page_url'] ?? '#'); ?>" class="topup-category-card" style="--accent-color: <?php echo $cat['color']; ?>">
                <div class="category-icon">
                    <i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i>
                </div>
                <div class="category-info">
                    <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                    <p><?php echo htmlspecialchars($cat['description']); ?></p>
                </div>
                <i class="fas fa-chevron-right category-arrow"></i>
            </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Popular Games -->
        <?php if (!empty($popularGames)): ?>
        <div class="popular-games">
            <div class="section-header">
                <h3><i class="fas fa-fire"></i> Game Populer</h3>
                <a href="topup-game.php" class="view-all">Lihat Semua <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="games-grid">
                <?php foreach ($popularGames as $game): ?>
                <a href="topup-game-detail.php?game=<?php echo urlencode($game['game_code']); ?>" class="game-item">
                    <div class="game-icon">
                        <?php if (!empty($game['game_icon']) && file_exists('assets/img/games/' . $game['game_icon'])): ?>
                            <img src="assets/img/games/<?php echo htmlspecialchars($game['game_icon']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-gamepad"></i>
                        <?php endif; ?>
                    </div>
                    <span class="game-name"><?php echo htmlspecialchars($game['game_name']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
/* =====================================================
   TOP UP SERVICES SECTION STYLES
   ===================================================== */
.topup-services-section {
    padding: 3rem 0;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.topup-services-section .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Banner */
.topup-banner {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
    border-radius: 1.5rem;
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    overflow: hidden;
    position: relative;
    color: white;
    box-shadow: 0 10px 40px rgba(99, 102, 241, 0.3);
}

.topup-banner-content h2 {
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
}

.topup-banner-content p {
    opacity: 0.9;
    margin-bottom: 1rem;
}

.btn-topup-banner {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: white;
    color: #6366f1;
    padding: 0.75rem 1.5rem;
    border-radius: 2rem;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-topup-banner:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.topup-banner-decoration {
    display: flex;
    gap: 1rem;
    font-size: 3rem;
    opacity: 0.3;
}

.topup-banner-decoration i {
    animation: float 3s ease-in-out infinite;
}

.topup-banner-decoration i:nth-child(2) {
    animation-delay: 0.5s;
}

.topup-banner-decoration i:nth-child(3) {
    animation-delay: 1s;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

/* Category Cards */
.topup-categories {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.topup-category-card {
    background: white;
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.topup-category-card:hover {
    border-color: var(--accent-color, #6366f1);
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.category-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: var(--accent-color, #6366f1);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.category-info {
    flex: 1;
}

.category-info h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #1e293b;
}

.category-info p {
    font-size: 0.8rem;
    color: #64748b;
    margin: 0;
}

.category-arrow {
    color: #94a3b8;
    transition: all 0.3s ease;
}

.topup-category-card:hover .category-arrow {
    color: var(--accent-color, #6366f1);
    transform: translateX(5px);
}

/* Popular Games */
.popular-games {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #1e293b;
}

.section-header h3 i {
    color: #f59e0b;
}

.view-all {
    font-size: 0.9rem;
    color: #6366f1;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.view-all:hover {
    text-decoration: underline;
}

.games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 1rem;
}

.game-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    padding: 1rem 0.5rem;
    border-radius: 0.75rem;
    transition: all 0.3s ease;
}

.game-item:hover {
    background: #f1f5f9;
    transform: translateY(-3px);
}

.game-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
    overflow: hidden;
}

.game-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.game-icon i {
    font-size: 1.5rem;
    color: #64748b;
}

.game-name {
    font-size: 0.8rem;
    color: #334155;
    text-align: center;
    font-weight: 500;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .topup-banner {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
    }
    
    .topup-banner-content h2 {
        font-size: 1.5rem;
    }
    
    .topup-banner-decoration {
        display: none;
    }
    
    .topup-categories {
        grid-template-columns: 1fr;
    }
    
    .games-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .game-icon {
        width: 50px;
        height: 50px;
    }
}
</style>
