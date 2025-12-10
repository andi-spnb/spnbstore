<?php
/**
 * ADMIN - Atlantic Games
 * Kelola daftar game dan import produk dari Atlantic H2H
 * * Standalone version - compatible dengan SPNB Store
 */

require_once 'config.php';

// Check login dan admin
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUserData();
if ($user['is_admin'] != 1) {
    header('Location: dashboard.php');
    exit;
}

// Include Atlantic class
if (!class_exists('AtlanticH2H')) {
    $paths = [
        __DIR__ . '/AtlanticH2H.php',
        __DIR__ . '/classes/AtlanticH2H.php'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

$message = '';
$messageType = '';

/**
 * Import products dari Atlantic untuk game tertentu
 */
function importGameProducts($conn, $game) {
    if (!class_exists('AtlanticH2H')) {
        return ['success' => false, 'message' => 'Atlantic H2H class tidak ditemukan'];
    }
    
    try {
        $atlantic = new AtlanticH2H();
        $result = $atlantic->getPriceList('prabayar');
        
        if (!$result['success']) {
            return ['success' => false, 'message' => 'Gagal mengambil data: ' . ($result['message'] ?? 'Unknown error')];
        }
        
        $products = $result['data']['data'] ?? [];
        $provider = strtoupper($game['provider'] ?? '');
        $gameId = $game['id'];
        
        // Filter produk berdasarkan provider
        $matchedProducts = array_filter($products, function($p) use ($provider) {
            return stripos($p['type'] ?? '', $provider) !== false || 
                   stripos($p['category'] ?? '', $provider) !== false ||
                   stripos($p['provider'] ?? '', $provider) !== false;
        });
        
        if (empty($matchedProducts)) {
            return ['success' => false, 'message' => "Tidak ada produk ditemukan untuk provider: $provider"];
        }
        
        $imported = 0;
        $updated = 0;
        
        foreach ($matchedProducts as $product) {
            $productCode = $product['code'] ?? '';
            $productName = $product['name'] ?? '';
            $priceAtlantic = intval($product['price'] ?? 0);
            $status = $product['status'] ?? 'available';
            
            // Extract nominal dari nama (misal: "Mobile Legend 86 Diamonds" -> "86 Diamonds")
            $nominal = preg_replace('/.*?(\d+\s*(Diamond|DM|UC|Coin|CP|VP|Token|Credit|Weekly|Monthly|Pass).*)/i', '$1', $productName);
            if ($nominal === $productName) {
                $nominal = $productName;
            }
            
            // Calculate sell price dengan markup
            $priceSell = calculateSellPrice($priceAtlantic, $game['markup_type'], $game['markup_percent'], $game['markup_fixed']);
            
            // Check if product exists
            $stmt = $conn->prepare("SELECT id FROM atlantic_game_products WHERE product_code = ?");
            $stmt->execute([$productCode]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update
                $stmt = $conn->prepare("UPDATE atlantic_game_products SET 
                    product_name = ?, nominal_display = ?, price_atlantic = ?, price_sell = ?, status = ?, updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([$productName, $nominal, $priceAtlantic, $priceSell, $status, $existing['id']]);
                $updated++;
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO atlantic_game_products 
                    (game_id, product_code, product_name, nominal_display, price_atlantic, price_sell, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$gameId, $productCode, $productName, $nominal, $priceAtlantic, $priceSell, $status]);
                $imported++;
            }
        }
        
        return ['success' => true, 'message' => "Import berhasil: $imported produk baru, $updated produk diupdate"];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Calculate sell price dengan markup
 */
function calculateSellPrice($basePrice, $markupType, $markupPercent, $markupFixed) {
    switch ($markupType) {
        case 'percent':
            return $basePrice + ($basePrice * $markupPercent / 100);
        case 'fixed':
            return $basePrice + $markupFixed;
        case 'both':
            return $basePrice + ($basePrice * $markupPercent / 100) + $markupFixed;
        default:
            return $basePrice + $markupFixed;
    }
}

/**
 * Recalculate all product prices for a game
 */
function recalculateProductPrices($conn, $gameId, $markupType, $markupPercent, $markupFixed) {
    $stmt = $conn->prepare("SELECT id, price_atlantic FROM atlantic_game_products WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $products = $stmt->fetchAll();
    
    foreach ($products as $product) {
        $newPrice = calculateSellPrice($product['price_atlantic'], $markupType, $markupPercent, $markupFixed);
        $stmt = $conn->prepare("UPDATE atlantic_game_products SET price_sell = ? WHERE id = ?");
        $stmt->execute([$newPrice, $product['id']]);
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Toggle game status
    if ($action === 'toggle_status') {
        $gameId = intval($_POST['game_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE atlantic_games SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$gameId]);
        $message = 'Status game berhasil diubah';
        $messageType = 'success';
    }
    
    // Update markup
    if ($action === 'update_markup') {
        $gameId = intval($_POST['game_id'] ?? 0);
        $markupType = $_POST['markup_type'] ?? 'fixed';
        $markupPercent = floatval($_POST['markup_percent'] ?? 0);
        $markupFixed = intval($_POST['markup_fixed'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE atlantic_games SET markup_type = ?, markup_percent = ?, markup_fixed = ? WHERE id = ?");
        $stmt->execute([$markupType, $markupPercent, $markupFixed, $gameId]);
        
        recalculateProductPrices($conn, $gameId, $markupType, $markupPercent, $markupFixed);
        
        $message = 'Markup berhasil diperbarui dan harga produk dihitung ulang';
        $messageType = 'success';
    }
    
    // Import products
    if ($action === 'import_products') {
        $gameId = intval($_POST['game_id'] ?? 0);
        
        $stmt = $conn->prepare("SELECT * FROM atlantic_games WHERE id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();
        
        if ($game) {
            $result = importGameProducts($conn, $game);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
    }
    
    // Add new game
    if ($action === 'add_game') {
        $gameCode = strtoupper(trim($_POST['game_code'] ?? ''));
        $gameName = trim($_POST['game_name'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $input1Label = trim($_POST['input1_label'] ?? 'User ID');
        $input2Label = trim($_POST['input2_label'] ?? '');
        
        if ($gameCode && $gameName) {
            // Set target format
            $targetFormat = !empty($input2Label) ? '{input1}{input2}' : '{input1}';
            
            $stmt = $conn->prepare("INSERT INTO atlantic_games 
                (game_code, game_name, provider, category, input1_label, input2_label, target_format) 
                VALUES (?, ?, ?, 'Voucher Game', ?, ?, ?)");
            $stmt->execute([$gameCode, $gameName, $provider, $input1Label, $input2Label ?: null, $targetFormat]);
            $message = 'Game berhasil ditambahkan';
            $messageType = 'success';
        }
    }
    
    // Delete game
    if ($action === 'delete_game') {
        $gameId = intval($_POST['game_id'] ?? 0);
        // Delete products first
        $stmt = $conn->prepare("DELETE FROM atlantic_game_products WHERE game_id = ?");
        $stmt->execute([$gameId]);
        // Then delete game
        $stmt = $conn->prepare("DELETE FROM atlantic_games WHERE id = ?");
        $stmt->execute([$gameId]);
        $message = 'Game dan semua produknya berhasil dihapus';
        $messageType = 'success';
    }
}

// Get all games with product count
$stmt = $conn->query("
    SELECT g.*, 
           COUNT(p.id) as total_products,
           SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as active_products
    FROM atlantic_games g
    LEFT JOIN atlantic_game_products p ON g.id = p.game_id
    GROUP BY g.id
    ORDER BY g.sort_order ASC, g.game_name ASC
");
$games = $stmt->fetchAll();

// Get Atlantic providers for reference
$atlanticProviders = [];
if (class_exists('AtlanticH2H')) {
    try {
        $atlantic = new AtlanticH2H();
        $result = $atlantic->getPriceList('prabayar');
        if ($result['success']) {
            $products = $result['data']['data'] ?? [];
            foreach ($products as $p) {
                $type = $p['type'] ?? 'Unknown';
                if (!isset($atlanticProviders[$type])) {
                    $atlanticProviders[$type] = 0;
                }
                $atlanticProviders[$type]++;
            }
        }
    } catch (Exception $e) {
        // Ignore
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Game - Admin <?php echo SITE_NAME; ?></title>
    <!-- Include Sidebar CSS -->
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">
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
            background: var(--bg-dark);
            min-height: 100vh;
            color: var(--text-primary);
        }
        
        /* Layout Structure for Sidebar */
        .main-content {
            transition: all 0.3s ease;
            padding: 2rem;
            min-height: 100vh;
        }
        
        /* Responsive Breakpoints */
        @media (min-width: 992px) {
            .main-content {
                margin-left: 280px; /* Lebar sidebar desktop */
            }
            .mobile-header {
                display: none !important;
            }
        }
        
        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
                padding-top: 80px; /* Space untuk mobile header */
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-card .value { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .stat-card .label { color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem; }
        
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-title h2 {
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(99, 102, 241, 0.4); }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .card-body { padding: 1.5rem; }
        
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); /* Mengurangi min-width sedikit agar pas di HP kecil */
            gap: 1.5rem;
        }
        
        .game-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .game-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .game-card-header {
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .game-card-title h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .game-card-title span { font-size: 0.8rem; color: var(--text-secondary); }
        
        .game-status {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .game-status.active { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .game-status.inactive { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        .game-card-body { padding: 1.25rem; }
        
        .game-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .game-stat {
            text-align: center;
            padding: 0.75rem;
            background: var(--bg-dark);
            border-radius: 0.5rem;
        }
        
        .game-stat .value { font-size: 1.25rem; font-weight: 700; color: var(--primary); }
        .game-stat .label { font-size: 0.7rem; color: var(--text-secondary); margin-top: 0.25rem; }
        
        .game-markup {
            background: var(--bg-dark);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .game-markup-title { font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
        
        .markup-form { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        
        .markup-form select, .markup-form input {
            padding: 0.5rem;
            border-radius: 0.375rem;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 0.85rem;
            max-width: 100%;
        }
        
        .markup-form input { width: 80px; }
        
        .game-card-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .game-card-actions .btn { flex: 1; justify-content: center; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 1rem;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 { font-size: 1.1rem; }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.25rem;
            cursor: pointer;
        }
        
        .modal-body { padding: 1.25rem; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-secondary); font-size: 0.9rem; }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            background: var(--bg-dark);
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .form-hint { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem; }
        
        .modal-footer {
            padding: 1.25rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        /* Products Table */
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .products-table th, .products-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .products-table th {
            background: rgba(255,255,255,0.05);
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .products-table tr:hover { background: rgba(255,255,255,0.02); }
        
        .product-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .product-status.available { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .product-status.empty { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        /* Providers List */
        .providers-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .provider-badge {
            padding: 0.375rem 0.75rem;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 1rem;
            font-size: 0.8rem;
            color: var(--primary);
        }
        
        .provider-badge span { color: var(--text-secondary); font-size: 0.75rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 0; }
            .games-grid { grid-template-columns: 1fr; }
            .game-stats { grid-template-columns: 1fr; }
            .section-title { flex-direction: column; align-items: flex-start; }
            .section-title button { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php require_once 'admin-sidebar.php'; ?>
    
    <!-- Main Content Wrapper -->
    <main class="main-content">
        <div class="container">
            <!-- Header Title in Content (Since Sidebar handles nav) -->
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.8rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-gamepad" style="color: var(--primary);"></i> Kelola Game Top Up
                </h1>
                <p style="color: var(--text-secondary); margin-top: 0.5rem;">Atur daftar game, markup harga, dan provider.</p>
            </div>
            
            <!-- Alert -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="value"><?php echo count($games); ?></div>
                    <div class="label">Total Game</div>
                </div>
                <div class="stat-card">
                    <div class="value"><?php echo array_sum(array_column($games, 'total_products')); ?></div>
                    <div class="label">Total Produk</div>
                </div>
                <div class="stat-card">
                    <div class="value"><?php echo array_sum(array_column($games, 'active_products')); ?></div>
                    <div class="label">Produk Aktif</div>
                </div>
                <div class="stat-card">
                    <div class="value"><?php echo count($atlanticProviders); ?></div>
                    <div class="label">Atlantic Providers</div>
                </div>
            </div>
            
            <!-- Available Providers from Atlantic -->
            <?php if (!empty($atlanticProviders)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-server"></i> Provider Tersedia di Atlantic H2H</h3>
                </div>
                <div class="card-body">
                    <div class="providers-list">
                        <?php foreach ($atlanticProviders as $provider => $count): ?>
                        <span class="provider-badge">
                            <?php echo htmlspecialchars($provider); ?>
                            <span>(<?php echo $count; ?>)</span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i> Gunakan nama provider di atas saat menambahkan game baru untuk import produk.
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Games Section -->
            <div class="section-title">
                <h2><i class="fas fa-gamepad"></i> Daftar Game</h2>
                <button class="btn btn-primary" onclick="openModal('addGameModal')">
                    <i class="fas fa-plus"></i> Tambah Game
                </button>
            </div>
            
            <div class="games-grid">
                <?php foreach ($games as $game): ?>
                <div class="game-card">
                    <div class="game-card-header">
                        <div class="game-card-title">
                            <h3><?php echo htmlspecialchars($game['game_name']); ?></h3>
                            <span><?php echo htmlspecialchars($game['game_code']); ?> • <?php echo htmlspecialchars($game['provider'] ?: 'No provider'); ?></span>
                        </div>
                        <span class="game-status <?php echo $game['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $game['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </div>
                    
                    <div class="game-card-body">
                        <div class="game-stats">
                            <div class="game-stat">
                                <div class="value"><?php echo $game['total_products']; ?></div>
                                <div class="label">Total Produk</div>
                            </div>
                            <div class="game-stat">
                                <div class="value"><?php echo $game['active_products']; ?></div>
                                <div class="label">Aktif</div>
                            </div>
                            <div class="game-stat">
                                <div class="value"><?php echo $game['total_products'] - $game['active_products']; ?></div>
                                <div class="label">Nonaktif</div>
                            </div>
                        </div>
                        
                        <div class="game-markup">
                            <div class="game-markup-title">Markup Harga</div>
                            <form method="POST" class="markup-form">
                                <input type="hidden" name="action" value="update_markup">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <select name="markup_type">
                                    <option value="fixed" <?php echo $game['markup_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                    <option value="percent" <?php echo $game['markup_type'] === 'percent' ? 'selected' : ''; ?>>Percent</option>
                                    <option value="both" <?php echo $game['markup_type'] === 'both' ? 'selected' : ''; ?>>Both</option>
                                </select>
                                <input type="number" name="markup_percent" value="<?php echo $game['markup_percent']; ?>" placeholder="%" step="0.1">
                                <input type="number" name="markup_fixed" value="<?php echo $game['markup_fixed']; ?>" placeholder="Rp">
                                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-save"></i></button>
                            </form>
                        </div>
                        
                        <div class="game-card-actions">
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="action" value="import_products">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm" style="width: 100%;">
                                    <i class="fas fa-download"></i> Import
                                </button>
                            </form>
                            
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <button type="submit" class="btn btn-warning btn-sm" style="width: 100%;">
                                    <i class="fas fa-power-off"></i> Toggle
                                </button>
                            </form>
                            
                            <button class="btn btn-primary btn-sm" onclick="viewProducts(<?php echo $game['id']; ?>, '<?php echo htmlspecialchars($game['game_name']); ?>')">
                                <i class="fas fa-eye"></i> Produk
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($games)): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-gamepad" style="font-size: 3rem; color: var(--text-secondary); opacity: 0.5; margin-bottom: 1rem;"></i>
                    <h3 style="margin-bottom: 0.5rem;">Belum Ada Game</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem;">Tambahkan game pertama untuk memulai</p>
                    <button class="btn btn-primary" onclick="openModal('addGameModal')">
                        <i class="fas fa-plus"></i> Tambah Game
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Add Game Modal -->
    <div class="modal" id="addGameModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Tambah Game Baru</h3>
                <button class="modal-close" onclick="closeModal('addGameModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_game">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Kode Game *</label>
                        <input type="text" name="game_code" class="form-control" placeholder="ML, FF, PUBGM" required>
                        <div class="form-hint">Kode unik untuk game (uppercase)</div>
                    </div>
                    <div class="form-group">
                        <label>Nama Game *</label>
                        <input type="text" name="game_name" class="form-control" placeholder="Mobile Legends" required>
                    </div>
                    <div class="form-group">
                        <label>Provider Atlantic</label>
                        <input type="text" name="provider" class="form-control" placeholder="MOBILELEGEND">
                        <div class="form-hint">Nama provider di Atlantic H2H untuk import produk</div>
                    </div>
                    <div class="form-group">
                        <label>Label Input 1</label>
                        <input type="text" name="input1_label" class="form-control" value="User ID" placeholder="User ID">
                    </div>
                    <div class="form-group">
                        <label>Label Input 2 (opsional)</label>
                        <input type="text" name="input2_label" class="form-control" placeholder="Zone ID (kosongkan jika tidak perlu)">
                        <div class="form-hint">Untuk game seperti ML yang butuh Zone ID</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addGameModal')">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Game</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Products Modal -->
    <div class="modal" id="productsModal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-box"></i> Produk: <span id="productsGameName"></span></h3>
                <button class="modal-close" onclick="closeModal('productsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="productsTableContainer">
                    <p style="text-align: center; color: var(--text-secondary);">Loading...</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function viewProducts(gameId, gameName) {
            document.getElementById('productsGameName').textContent = gameName;
            document.getElementById('productsTableContainer').innerHTML = '<p style="text-align: center; color: var(--text-secondary);"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
            openModal('productsModal');
            
            // Fetch products via AJAX
            fetch('admin-atlantic-products-ajax.php?game_id=' + gameId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('productsTableContainer').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('productsTableContainer').innerHTML = '<p style="color: var(--danger);">Error loading products</p>';
                });
        }
        
        // Close modal on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
            }
        });
        
        // Close modal on overlay click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>