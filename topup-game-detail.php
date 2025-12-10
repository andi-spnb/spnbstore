<?php
/**
 * TOP UP GAME - Detail
 * Halaman untuk input ID game dan pilih nominal
 */

require_once 'config.php';
require_once 'topup-game-helper.php';

// Get game code from URL
$gameCode = isset($_GET['game']) ? strtoupper(trim($_GET['game'])) : '';

if (empty($gameCode)) {
    header('Location: topup-game.php');
    exit;
}

// Get game info
$stmt = $conn->prepare("SELECT * FROM atlantic_games WHERE game_code = ? AND is_active = 1");
$stmt->execute([$gameCode]);
$game = $stmt->fetch();

if (!$game) {
    header('Location: topup-game.php');
    exit;
}

// Get products for this game
$stmt = $conn->prepare("SELECT * FROM atlantic_game_products WHERE game_id = ? AND is_active = 1 ORDER BY sort_order ASC, price_sell ASC");
$stmt->execute([$game['id']]);
$products = $stmt->fetchAll();

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getUserData() : null;

$pageTitle = "Top Up " . $game['game_name'] . " - " . getSiteName();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #0f172a;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #1a1a2e 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }
        
        .topup-header {
            background: var(--bg-card);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .btn-back {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--bg-dark);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-back:hover { background: var(--primary); }
        
        .header-title { flex: 1; }
        .header-title h1 { font-size: 1.25rem; font-weight: 600; }
        .header-title span { font-size: 0.85rem; color: var(--text-secondary); }
        
        .game-banner {
            max-width: 800px;
            margin: 0 auto;
            height: 200px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .game-banner img { width: 100%; height: 100%; object-fit: cover; }
        
        .game-banner-placeholder { text-align: center; }
        .game-banner-placeholder i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.7; }
        .game-banner-placeholder h2 { font-size: 1.75rem; }
        
        .main-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 1.5rem 1rem 3rem;
        }
        
        .form-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }
        
        .form-card-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-card-title .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .form-card-title h3 { font-size: 1.1rem; font-weight: 600; }
        
        .input-group { margin-bottom: 1rem; }
        .input-group:last-child { margin-bottom: 0; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-secondary); font-size: 0.9rem; }
        
        .input-group input {
            width: 100%;
            padding: 1rem;
            border-radius: 0.75rem;
            border: 2px solid var(--border-color);
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .input-group input::placeholder { color: var(--text-secondary); }
        
        .input-hint { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem; }
        .input-hint i { color: var(--warning); margin-right: 0.25rem; }
        
        .input-row { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; }
        
        .instructions {
            background: rgba(99, 102, 241, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid var(--primary);
        }
        
        .instructions-title { font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .instructions-title i { color: var(--primary); }
        .instructions p { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
        }
        
        .product-item {
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .product-item:hover { border-color: var(--primary); transform: translateY(-2px); }
        
        .product-item.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.15);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .product-item.selected::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }
        
        .product-item.unavailable { opacity: 0.5; cursor: not-allowed; }
        
        .product-nominal { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.25rem; color: var(--text-primary); }
        .product-name { font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
        .product-price { font-size: 0.95rem; font-weight: 600; color: var(--primary); }
        
        .product-status {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: var(--danger);
            color: white;
        }
        
        .empty-products { text-align: center; padding: 3rem 1rem; color: var(--text-secondary); }
        .empty-products i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 2px solid var(--primary);
            position: sticky;
            bottom: 1rem;
        }
        
        .summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
        .summary-row:last-of-type { margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .summary-label { color: var(--text-secondary); }
        .summary-value { font-weight: 600; }
        .summary-total { font-size: 1.25rem; color: var(--primary); }
        
        .btn-checkout {
            width: 100%;
            padding: 1rem;
            border-radius: 0.75rem;
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-checkout:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }
        
        .btn-checkout:disabled { opacity: 0.5; cursor: not-allowed; }
        
        @media (max-width: 768px) {
            .game-banner { height: 150px; }
            .input-row { grid-template-columns: 1fr; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.75rem; }
            .product-item { padding: 0.75rem; }
            .product-nominal { font-size: 0.95rem; }
            .product-price { font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <header class="topup-header">
        <div class="header-container">
            <a href="topup-game.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="header-title">
                <h1><?php echo htmlspecialchars($game['game_name']); ?></h1>
                <span><?php echo htmlspecialchars($game['provider'] ?? 'Top Up'); ?></span>
            </div>
        </div>
    </header>
    
    <div class="game-banner">
        <?php if (!empty($game['game_banner']) && file_exists('assets/img/games/' . $game['game_banner'])): ?>
            <img src="assets/img/games/<?php echo htmlspecialchars($game['game_banner']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>">
        <?php else: ?>
            <div class="game-banner-placeholder">
                <i class="fas fa-gamepad"></i>
                <h2><?php echo htmlspecialchars($game['game_name']); ?></h2>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="main-content">
        <form id="topupForm" action="topup-game-checkout.php" method="POST">
            <input type="hidden" name="game_code" value="<?php echo htmlspecialchars($game['game_code']); ?>">
            <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
            <input type="hidden" name="product_code" id="selectedProductCode" value="">
            <input type="hidden" name="product_price" id="selectedProductPrice" value="">
            
            <div class="form-card">
                <div class="form-card-title">
                    <span class="step-number">1</span>
                    <h3>Masukkan Data Akun</h3>
                </div>
                
                <?php if (!empty($game['input2_label'])): ?>
                    <div class="input-row">
                        <div class="input-group">
                            <label for="input1"><?php echo htmlspecialchars($game['input1_label']); ?></label>
                            <input type="text" id="input1" name="input1" placeholder="<?php echo htmlspecialchars($game['input1_placeholder']); ?>" required>
                            <?php if (!empty($game['input1_hint'])): ?>
                                <div class="input-hint"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($game['input1_hint']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="input-group">
                            <label for="input2"><?php echo htmlspecialchars($game['input2_label']); ?></label>
                            <input type="text" id="input2" name="input2" placeholder="<?php echo htmlspecialchars($game['input2_placeholder']); ?>" required>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="input-group">
                        <label for="input1"><?php echo htmlspecialchars($game['input1_label']); ?></label>
                        <input type="text" id="input1" name="input1" placeholder="<?php echo htmlspecialchars($game['input1_placeholder']); ?>" required>
                        <?php if (!empty($game['input1_hint'])): ?>
                            <div class="input-hint"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($game['input1_hint']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($game['instructions'])): ?>
                    <div class="instructions">
                        <div class="instructions-title"><i class="fas fa-lightbulb"></i> Cara Menemukan ID</div>
                        <p><?php echo htmlspecialchars($game['instructions']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-card">
                <div class="form-card-title">
                    <span class="step-number">2</span>
                    <h3>Pilih Nominal</h3>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="empty-products">
                        <i class="fas fa-box-open"></i>
                        <h4>Produk Belum Tersedia</h4>
                        <p>Silakan coba lagi nanti atau pilih game lain.</p>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-item <?php echo $product['status'] !== 'available' ? 'unavailable' : ''; ?>"
                                 data-code="<?php echo htmlspecialchars($product['product_code']); ?>"
                                 data-price="<?php echo $product['price_sell']; ?>"
                                 data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                 data-available="<?php echo $product['status'] === 'available' ? '1' : '0'; ?>"
                                 onclick="selectProduct(this)">
                                <?php if ($product['status'] !== 'available'): ?>
                                    <span class="product-status">Habis</span>
                                <?php endif; ?>
                                <div class="product-nominal"><?php echo htmlspecialchars($product['nominal_display'] ?? $product['product_name']); ?></div>
                                <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                <div class="product-price">Rp <?php echo number_format($product['price_sell'], 0, ',', '.'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="summary-card" id="summaryCard" style="display: none;">
                <div class="summary-row">
                    <span class="summary-label">Produk</span>
                    <span class="summary-value" id="summaryProduct">-</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Total Pembayaran</span>
                    <span class="summary-value summary-total" id="summaryTotal">Rp 0</span>
                </div>
                <button type="submit" class="btn-checkout" id="btnCheckout" disabled>
                    <i class="fas fa-shopping-cart"></i> Lanjut ke Pembayaran
                </button>
            </div>
        </form>
    </div>
    
    <script>
        let selectedProduct = null;
        
        function selectProduct(element) {
            if (element.dataset.available === '0') return;
            
            document.querySelectorAll('.product-item').forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');
            
            selectedProduct = {
                code: element.dataset.code,
                price: parseInt(element.dataset.price),
                name: element.dataset.name
            };
            
            document.getElementById('selectedProductCode').value = selectedProduct.code;
            document.getElementById('selectedProductPrice').value = selectedProduct.price;
            document.getElementById('summaryProduct').textContent = selectedProduct.name;
            document.getElementById('summaryTotal').textContent = 'Rp ' + selectedProduct.price.toLocaleString('id-ID');
            document.getElementById('summaryCard').style.display = 'block';
            document.getElementById('btnCheckout').disabled = false;
            document.getElementById('summaryCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        document.getElementById('topupForm').addEventListener('submit', function(e) {
            const input1 = document.getElementById('input1').value.trim();
            const input2 = document.getElementById('input2');
            
            if (!input1) { e.preventDefault(); alert('Mohon masukkan <?php echo $game['input1_label']; ?>'); return; }
            if (input2 && !input2.value.trim()) { e.preventDefault(); alert('Mohon masukkan <?php echo $game['input2_label']; ?>'); return; }
            if (!selectedProduct) { e.preventDefault(); alert('Mohon pilih nominal terlebih dahulu'); return; }
        });
    </script>
</body>
</html>
