<?php
require_once 'config.php';
$upload_dir = 'assets/img/products/';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    redirect('/');
}

// Get product
$stmt = $conn->prepare("SELECT p.*, c.nama as category_name FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.slug = ? AND p.is_active = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    redirect('/');
}

$user = isLoggedIn() ? getUserData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['nama']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php include 'meta-tags.php'; ?>
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --success-gradient: linear-gradient(135deg, #10b981, #059669);
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 8px 30px rgba(99, 102, 241, 0.2);
        }
        
        /* Product Page Layout */
        .product-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: 1fr 500px;
            gap: 3rem;
            margin-bottom: 3rem;
        }
        
        /* Product Image Card */
        .product-image-card {
            background: var(--card-bg);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            border: 2px solid var(--dark-border);
            transition: all 0.3s;
        }
        
        .product-image-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-5px);
        }
        
        .product-image {
            width: 100%;
            aspect-ratio: 1/1;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .product-image::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        /* Product Info */
        .product-header {
            margin-bottom: 2rem;
        }
        
        .product-category {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary-gradient);
            color: white;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .product-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--text-primary), var(--primary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .product-price {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .product-stock {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .product-stock.in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .product-stock.low-stock {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .product-stock.out-stock {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        /* Product Description */
        .product-description {
            background: var(--dark-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .product-description h3 {
            font-size: 1.125rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .product-description p {
            line-height: 1.8;
            color: var(--text-muted);
        }
        
        /* Features List */
        .product-features {
            background: var(--dark-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }
        
        .product-features h3 {
            font-size: 1.125rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .features-list {
            display: grid;
            gap: 0.75rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--card-bg);
            border-radius: 0.5rem;
            border: 1px solid var(--dark-border);
            transition: all 0.3s;
        }
        
        .feature-item:hover {
            border-color: var(--primary-color);
            transform: translateX(5px);
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        /* Order Sidebar */
        .order-sidebar {
            position: sticky;
            top: 2rem;
        }
        
        .order-card {
            background: var(--card-bg);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            border: 2px solid var(--dark-border);
        }
        
        .order-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--dark-bg);
            border: 2px solid var(--dark-border);
            border-radius: 0.75rem;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: var(--card-bg);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Quantity Input */
        .quantity-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            background: var(--dark-bg);
            border: 2px solid var(--dark-border);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text-primary);
            font-size: 1.25rem;
        }
        
        .quantity-btn:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .quantity-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quantity-input {
            flex: 1;
            text-align: center;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        /* Total Price Display */
        .total-price-box {
            background: var(--dark-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            border: 2px solid var(--dark-border);
        }
        
        .total-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .total-price {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary-color);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: grid;
            gap: 1rem;
        }
        
        .btn-large {
            padding: 1rem 1.5rem;
            font-size: 1.125rem;
            font-weight: 700;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary-gradient {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        
        .btn-primary-gradient:hover {
            box-shadow: 0 6px 25px rgba(99, 102, 241, 0.6);
            transform: translateY(-2px);
        }
        
        .btn-secondary-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--dark-border);
        }
        
        .btn-secondary-outline:hover {
            background: var(--dark-bg);
            border-color: var(--primary-color);
        }
        
        .btn-success-gradient {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        
        .btn-success-gradient:hover {
            box-shadow: 0 6px 25px rgba(16, 185, 129, 0.6);
            transform: translateY(-2px);
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
            padding: 1rem;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 1.5rem;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 2px solid var(--dark-border);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 2rem;
            border-bottom: 2px solid var(--dark-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .modal-close {
            width: 40px;
            height: 40px;
            background: var(--dark-bg);
            border: 2px solid var(--dark-border);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text-muted);
            font-size: 1.25rem;
        }
        
        .modal-close:hover {
            background: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        /* Payment Method */
        .payment-method {
            border: 2px solid var(--dark-border);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .payment-method:hover {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.05);
        }
        
        .payment-method.selected {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
        }
        
        .payment-radio {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-title {
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
        }
        
        .payment-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        
        .payment-icon {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        /* Pakasir Methods */
        .payment-method-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .method-option {
            border: 2px solid var(--dark-border);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--dark-bg);
        }
        
        .method-option:hover {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
            transform: translateY(-3px);
        }
        
        .method-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }
        
        .method-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .method-name {
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        /* Order Summary */
        .order-summary {
            background: var(--dark-bg);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 2px solid var(--dark-border);
        }
        
        .summary-title {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--dark-border);
        }
        
        .summary-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .summary-label {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .summary-value {
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .summary-row.total {
            font-size: 1.25rem;
            padding-top: 0.75rem;
            border-top: 2px solid var(--dark-border);
        }
        
        .summary-row.total .summary-label {
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .summary-row.total .summary-value {
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .order-sidebar {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .product-container {
                padding: 1rem;
            }
            
            .product-image {
                font-size: 5rem;
                aspect-ratio: 4/3;
            }
            
            .product-title {
                font-size: 1.5rem;
            }
            
            .product-price {
                font-size: 1.75rem;
            }
            
            .total-price {
                font-size: 1.5rem;
            }
            
            .modal-content {
                margin: 1rem;
                width: calc(100% - 2rem);
            }
            
            .modal-header,
            .modal-body {
                padding: 1.5rem;
            }
            
            .payment-method-list {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .btn-large {
                font-size: 1rem;
                padding: 0.875rem 1.25rem;
            }
        }
        
        @media (max-width: 480px) {
            .product-image {
                font-size: 4rem;
            }
            
            .product-title {
                font-size: 1.25rem;
            }
            
            .payment-method {
                flex-direction: column;
                text-align: center;
            }
            
            .payment-method-list {
                grid-template-columns: 1fr;
            }
        }
        
        /* Loading State */
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Atlantic Product Target Input */
.atlantic-input-group {
    margin-bottom: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05));
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 0.75rem;
}

.atlantic-input-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.atlantic-input-group .input-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
}

.atlantic-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.75rem;
    background: rgba(139, 92, 246, 0.2);
    color: var(--primary-color);
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
}
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    <div class="product-container">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <a href="/" style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-home"></i> Beranda
            </a>
            <span style="margin: 0 0.5rem; color: var(--text-muted);">/</span>
            <a href="kategori.php?slug=<?php echo $product['category_name']; ?>" style="color: var(--text-muted); text-decoration: none;">
                <?php echo htmlspecialchars($product['category_name']); ?>
            </a>
            <span style="margin: 0 0.5rem; color: var(--text-muted);">/</span>
            <span style="color: var(--text-primary); font-weight: 600;">
                <?php echo htmlspecialchars($product['nama']); ?>
            </span>
        </div>

        <div class="product-grid">
            <!-- Left Column: Product Info -->
            <div>
                <div class="product-image-card">
                    <!-- Product Image -->
                        <div class="product-image">
                            <?php if (!empty($product['gambar']) && file_exists($upload_dir . $product['gambar'])): ?>
                                <img src="<?php echo $upload_dir . $product['gambar']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['nama']); ?>"
                                     style="width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; border-radius: 1rem;">
                            <?php else: ?>
                                <?php 
                                $icons = ['🎨', '🎵', '📊', '🎬', '✏️', '📚', '💎', '⚡', '🎮', '📱'];
                                echo $icons[array_rand($icons)]; 
                                ?>
                            <?php endif; ?>
                        </div>
                    <!-- Product Header -->
                    <div class="product-header">
                        <div class="product-category">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </div>
                        <h1 class="product-title"><?php echo htmlspecialchars($product['nama']); ?></h1>
                        <div class="product-price">
                            Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?>
                        </div>
                        <div class="product-stock <?php 
                            if ($product['stok'] > 10) {
                                echo 'in-stock';
                            } elseif ($product['stok'] > 0) {
                                echo 'low-stock';
                            } else {
                                echo 'out-stock';
                            }
                        ?>">
                            <i class="fas fa-box"></i>
                            <?php 
                            if ($product['stok'] > 0) {
                                echo "Stok: " . $product['stok'] . " tersedia";
                            } else {
                                echo "Stok habis";
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Product Description -->
                    <div class="product-description">
                        <h3><i class="fas fa-info-circle"></i> Deskripsi Produk</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['deskripsi'])); ?></p>
                    </div>
                    
                    <!-- Product Features -->
                    <div class="product-features">
                        <h3><i class="fas fa-star"></i> Fitur Unggulan</h3>
                        <div class="features-list">
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                                <div>
                                    <strong>Proses Cepat</strong>
                                    <div style="font-size: 0.875rem; color: var(--text-muted);">Pengiriman otomatis dalam hitungan menit</div>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                                <div>
                                    <strong>Aman & Terpercaya</strong>
                                    <div style="font-size: 0.875rem; color: var(--text-muted);">Garansi uang kembali 100%</div>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-headset"></i></div>
                                <div>
                                    <strong>Support 24/7</strong>
                                    <div style="font-size: 0.875rem; color: var(--text-muted);">Tim support siap membantu kapan saja</div>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                                <div>
                                    <strong>Kualitas Premium</strong>
                                    <div style="font-size: 0.875rem; color: var(--text-muted);">Produk berkualitas tinggi dan original</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Form -->
            <div class="order-sidebar">
                <div class="order-card">
                    <h3><i class="fas fa-shopping-bag"></i> Pesan Sekarang</h3>
                    
                    <?php if ($user && $product['stok'] > 0): ?>
                    <form id="orderForm">
                        <!-- Quantity -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-layer-group"></i> Jumlah
                            </label>
                            <div class="quantity-group">
                                <button type="button" class="quantity-btn" onclick="decrementQuantity()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="quantity" class="form-control quantity-input" value="1" min="1" max="<?php echo $product['stok']; ?>" readonly>
                                <button type="button" class="quantity-btn" onclick="incrementQuantity()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-sticky-note"></i> Catatan (Opsional)
                            </label>
                            <textarea id="notes" class="form-control" placeholder="Tambahkan catatan untuk pesanan Anda..."></textarea>
                        </div>
                        <?php 
// Cek apakah produk Atlantic
$isAtlanticProduct = !empty($product['product_code']);
?>

<?php if ($isAtlanticProduct): ?>
<!-- Target Input for Atlantic Product -->
<div class="form-group atlantic-input-group" id="targetInputGroup">
    <span class="atlantic-badge">
        <i class="fas fa-cloud"></i> Produk API
    </span>
    <label class="form-label">
        <i class="fas fa-mobile-alt"></i> Info Device / Target
    </label>
    <input type="text" id="target_input" class="form-control" 
           placeholder="Contoh: Samsung TV - Jakarta"
           required>
    <div class="input-hint">
        <i class="fas fa-info-circle"></i>
        <?php
        // Tampilkan hint berdasarkan jenis produk
        $productCode = strtoupper($product['product_code']);
        if (strpos($productCode, 'NTF') !== false || strpos($productCode, 'NTS') !== false):
        ?>
            Format: <strong>Nama Device - Lokasi</strong><br>
            Contoh: iPhone 15 Pro - Surabaya, Samsung TV - Jakarta
        <?php else: ?>
            Masukkan informasi yang diperlukan untuk produk ini
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

                        <!-- Total Price -->
                        <div class="total-price-box">
                            <div class="total-label">Total Pembayaran</div>
                            <div class="total-price" id="totalPrice">
                                Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button type="button" class="btn-large btn-primary-gradient" onclick="showOrderConfirmation()">
                                <i class="fas fa-bolt"></i> Beli Sekarang
                            </button>
                            <button type="button" class="btn-large btn-secondary-outline" onclick="addToCart(<?php echo $product['id']; ?>)">
                                <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif (!$user): ?>
                    <div style="text-align: center; padding: 2rem 0;">
                        <i class="fas fa-lock" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                        <h3 style="margin-bottom: 1rem;">Login Diperlukan</h3>
                        <p style="color: var(--text-muted); margin-bottom: 2rem;">Silakan login terlebih dahulu untuk melakukan pembelian</p>
                        <a href="login.php" class="btn-large btn-success-gradient" style="text-decoration: none;">
                            <i class="fas fa-sign-in-alt"></i> Login Sekarang
                        </a>
                    </div>
                    
                    <?php else: ?>
                    <div style="text-align: center; padding: 2rem 0;">
                        <i class="fas fa-box-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                        <h3 style="margin-bottom: 1rem;">Stok Habis</h3>
                        <p style="color: var(--text-muted);">Produk ini sedang tidak tersedia</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Confirmation Modal -->
    <div id="orderConfirmModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-clipboard-check"></i> Konfirmasi Pesanan
                </div>
                <button class="modal-close" onclick="closeModal('orderConfirmModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="order-summary">
                    <div class="summary-title">
                        <i class="fas fa-receipt"></i> Detail Pesanan
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Produk</span>
                        <span class="summary-value" id="confirmProductName"></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Harga Satuan</span>
                        <span class="summary-value" id="confirmPrice"></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Jumlah</span>
                        <span class="summary-value" id="confirmQuantity"></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Catatan</span>
                        <span class="summary-value" id="confirmNotes"></span>
                    </div>
                    <div class="summary-row total">
                        <span class="summary-label">Total Pembayaran</span>
                        <span class="summary-value" id="confirmTotal"></span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn-large btn-success-gradient" onclick="showPaymentMethod()">
                        <i class="fas fa-arrow-right"></i> Lanjut ke Pembayaran
                    </button>
                    <button type="button" class="btn-large btn-secondary-outline" onclick="closeModal('orderConfirmModal')">
                        <i class="fas fa-times"></i> Batal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Method Modal -->
    <div id="paymentMethodModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-credit-card"></i> Pilih Metode Pembayaran
                </div>
                <button class="modal-close" onclick="closeModal('paymentMethodModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Saldo Payment -->
                <div class="payment-method" id="paymentSaldo" onclick="selectPaymentType('saldo')">
                    <input type="radio" name="payment_type" value="saldo" class="payment-radio">
                    <div class="payment-info">
                        <div class="payment-title">Saldo SPNB</div>
                        <div class="payment-desc">
                            Saldo Anda: <strong>Rp <?php echo $user ? number_format($user['saldo'], 0, ',', '.') : 0; ?></strong>
                        </div>
                    </div>
                    <div class="payment-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                
                <!-- Pakasir Payment -->
                <div class="payment-method" id="paymentPakasir" onclick="selectPaymentType('pakasir')">
                    <input type="radio" name="payment_type" value="pakasir" class="payment-radio">
                    <div class="payment-info">
                        <div class="payment-title">Payment Gateway</div>
                        <div class="payment-desc">QRIS, Virtual Account, E-Wallet</div>
                    </div>
                    <div class="payment-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                </div>
                
                <!-- Pakasir Methods -->
                <div id="pakasirMethods" style="display: none; margin-top: 1rem;">
                    <label class="form-label">Pilih Metode</label>
                    <div class="payment-method-list">
                        <div class="method-option" data-method="qris" onclick="selectPakasirMethod('qris')">
                            <div class="method-icon">📱</div>
                            <div class="method-name">QRIS</div>
                        </div>
                        <div class="method-option" data-method="bni_va" onclick="selectPakasirMethod('bni_va')">
                            <div class="method-icon">🏦</div>
                            <div class="method-name">BNI VA</div>
                        </div>
                        <div class="method-option" data-method="bri_va" onclick="selectPakasirMethod('bri_va')">
                            <div class="method-icon">🏦</div>
                            <div class="method-name">BRI VA</div>
                        </div>
                        <div class="method-option" data-method="cimb_niaga_va" onclick="selectPakasirMethod('cimb_niaga_va')">
                            <div class="method-icon">🏦</div>
                            <div class="method-name">CIMB VA</div>
                        </div>
                        <div class="method-option" data-method="permata_va" onclick="selectPakasirMethod('permata_va')">
                            <div class="method-icon">🏦</div>
                            <div class="method-name">Permata VA</div>
                        </div>
                        <div class="method-option" data-method="mandiri_va" onclick="selectPakasirMethod('mandiri_va')">
                            <div class="method-icon">🏦</div>
                            <div class="method-name">Mandiri VA</div>
                        </div>
                    </div>
                    <input type="hidden" id="selectedPakasirMethod">
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="button" id="btnProcessPayment" class="btn-large btn-primary-gradient" onclick="processPayment()" disabled style="width: 100%;">
                        <i class="fas fa-check"></i> Proses Pembayaran
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const productData = {
    id: <?php echo $product['id']; ?>,
    name: "<?php echo addslashes($product['nama']); ?>",
    price: <?php echo $product['harga']; ?>,
    stock: <?php echo $product['stok']; ?>,
    userBalance: <?php echo $user ? $user['saldo'] : 0; ?>,
    productCode: "<?php echo addslashes($product['product_code'] ?? ''); ?>",
    isAtlantic: <?php echo !empty($product['product_code']) ? 'true' : 'false'; ?>
};
console.log('Product Data:', productData);
console.log('Is Atlantic Product:', productData.isAtlantic);

    const quantityInput = document.getElementById('quantity');
    const totalPriceEl = document.getElementById('totalPrice');
    
    let selectedPaymentType = null;
    let selectedPakasirMethod = null;
    
    // Quantity functions
    function incrementQuantity() {
        const current = parseInt(quantityInput.value);
        if (current < productData.stock) {
            quantityInput.value = current + 1;
            updateTotalPrice();
        }
    }
    
    function decrementQuantity() {
        const current = parseInt(quantityInput.value);
        if (current > 1) {
            quantityInput.value = current - 1;
            updateTotalPrice();
        }
    }
    
    function updateTotalPrice() {
        const quantity = parseInt(quantityInput.value) || 1;
        const total = productData.price * quantity;
        totalPriceEl.textContent = formatRupiah(total);
    }
    
    // Update total price on quantity change
    if (quantityInput) {
        quantityInput.addEventListener('input', updateTotalPrice);
    }

    // Show Order Confirmation Modal
    function showOrderConfirmation() {
        const quantity = parseInt(quantityInput.value) || 1;
        const notes = document.getElementById('notes').value || '-';
        const total = productData.price * quantity;

        // Validate quantity
        if (quantity <= 0 || quantity > productData.stock) {
            alert('Jumlah tidak valid!');
            return;
        }

        // Fill confirmation details
        document.getElementById('confirmProductName').textContent = productData.name;
        document.getElementById('confirmPrice').textContent = formatRupiah(productData.price);
        document.getElementById('confirmQuantity').textContent = quantity + 'x';
        document.getElementById('confirmNotes').textContent = notes;
        document.getElementById('confirmTotal').textContent = formatRupiah(total);

        // Show modal
        document.getElementById('orderConfirmModal').classList.add('active');
    }

    // Show Payment Method Modal
    function showPaymentMethod() {
        closeModal('orderConfirmModal');
        document.getElementById('paymentMethodModal').classList.add('active');
    }

    // Select Payment Type
    function selectPaymentType(type) {
        selectedPaymentType = type;
        
        // Reset selection
        document.querySelectorAll('.payment-method').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Select current
        if (type === 'saldo') {
            document.getElementById('paymentSaldo').classList.add('selected');
            document.querySelector('input[value="saldo"]').checked = true;
            document.getElementById('pakasirMethods').style.display = 'none';
            
            const quantity = parseInt(quantityInput.value) || 1;
            const total = productData.price * quantity;
            
            // Check if balance is sufficient
            if (productData.userBalance >= total) {
                document.getElementById('btnProcessPayment').disabled = false;
            } else {
                document.getElementById('btnProcessPayment').disabled = true;
                alert('Saldo tidak mencukupi! Silakan top-up terlebih dahulu atau pilih metode pembayaran lain.');
            }
        } else if (type === 'pakasir') {
            document.getElementById('paymentPakasir').classList.add('selected');
            document.querySelector('input[value="pakasir"]').checked = true;
            document.getElementById('pakasirMethods').style.display = 'block';
            document.getElementById('btnProcessPayment').disabled = true;
        }
    }

    // Select Pakasir Method
    function selectPakasirMethod(method) {
        selectedPakasirMethod = method;
        
        // Reset selection
        document.querySelectorAll('.method-option').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Select current
        document.querySelector(`[data-method="${method}"]`).classList.add('selected');
        document.getElementById('selectedPakasirMethod').value = method;
        document.getElementById('btnProcessPayment').disabled = false;
    }

    // Process Payment
    function processPayment() {
    const quantity = parseInt(quantityInput.value) || 1;
    const notes = document.getElementById('notes').value;
    const total = productData.price * quantity;
    
    // Get target input jika ada (untuk produk Atlantic)
    const targetInputEl = document.getElementById('target_input');
    const targetInput = targetInputEl ? targetInputEl.value.trim() : '';

    if (!selectedPaymentType) {
        alert('Pilih metode pembayaran terlebih dahulu!');
        return;
    }

    if (selectedPaymentType === 'pakasir' && !selectedPakasirMethod) {
        alert('Pilih metode pembayaran Pakasir terlebih dahulu!');
        return;
    }
    
    // Validasi target input untuk produk Atlantic
    if (productData.isAtlantic && !targetInput) {
        alert('Silakan isi informasi Device/Target terlebih dahulu!');
        if (targetInputEl) targetInputEl.focus();
        return;
    }

    // Disable button
    const btnProcess = document.getElementById('btnProcessPayment');
    btnProcess.disabled = true;
    btnProcess.classList.add('btn-loading');
    btnProcess.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

    // Prepare data
    const formData = new URLSearchParams();
    formData.append('product_id', productData.id);
    formData.append('quantity', quantity);
    formData.append('notes', notes);
    formData.append('payment_type', selectedPaymentType);
    formData.append('target_input', targetInput); // TAMBAHKAN INI
    
    if (selectedPaymentType === 'pakasir') {
        formData.append('payment_method', selectedPakasirMethod);
    }

    console.log('=== PAYMENT REQUEST ===');
    console.log('Product ID:', productData.id);
    console.log('Is Atlantic:', productData.isAtlantic);
    console.log('Target Input:', targetInput);
    console.log('Payment Type:', selectedPaymentType);

    // Send request
    fetch('process-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        // DEBUG: Log response ke console
        console.log('=== PAYMENT RESPONSE ===');
        console.log('Success:', data.success);
        console.log('Message:', data.message);
        console.log('Is Atlantic:', data.is_atlantic);
        console.log('Redirect:', data.redirect);
        
        if (data.debug) {
            console.log('=== DEBUG LOGS ===');
            data.debug.forEach((log, i) => {
                console.log(`[${log.step || i}] ${log.time} - ${log.message}`, log.data || '');
            });
        }
        
        if (data.atlantic_response) {
            console.log('=== ATLANTIC RESPONSE ===');
            console.log(data.atlantic_response);
        }
        
        if (data.success) {
            if (data.redirect) {
                alert(data.message || 'Pembelian berhasil! Mengarahkan...');
                window.location.href = data.redirect;
            } 
            else if (data.payment_url) {
                alert('Mengarahkan ke halaman pembayaran...');
                window.location.href = data.payment_url;
            } 
            else {
                alert(data.message || 'Pembelian berhasil!');
                window.location.href = 'riwayat.php';
            }
        } else {
            alert(data.message || 'Gagal memproses pembayaran');
            btnProcess.disabled = false;
            btnProcess.classList.remove('btn-loading');
            btnProcess.innerHTML = '<i class="fas fa-check"></i> Proses Pembayaran';
        }
    })
    .catch(error => {
        console.error('Payment Error:', error);
        alert('Terjadi kesalahan sistem. Silakan coba lagi.');
        btnProcess.disabled = false;
        btnProcess.classList.remove('btn-loading');
        btnProcess.innerHTML = '<i class="fas fa-check"></i> Proses Pembayaran';
    });
}
    // Close Modal
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    // Add to Cart Function
    function addToCart(productId) {
        const quantity = parseInt(document.getElementById('quantity').value) || 1;
        
        // Disable button
        const btnAddCart = event.target;
        btnAddCart.disabled = true;
        btnAddCart.classList.add('btn-loading');
        btnAddCart.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menambahkan...';
        
        // Create form data
        const formData = new URLSearchParams();
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        
        fetch('cart-add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Berhasil ditambahkan ke keranjang!');
                
                // Optional: redirect to cart page
                if (confirm('Produk ditambahkan ke keranjang. Lihat keranjang sekarang?')) {
                    window.location.href = 'keranjang.php';
                }
            } else {
                alert(data.message || 'Gagal menambahkan ke keranjang');
            }
            
            // Re-enable button
            btnAddCart.disabled = false;
            btnAddCart.classList.remove('btn-loading');
            btnAddCart.innerHTML = '<i class="fas fa-cart-plus"></i> Tambah ke Keranjang';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan sistem. Silakan coba lagi.');
            
            // Re-enable button
            btnAddCart.disabled = false;
            btnAddCart.classList.remove('btn-loading');
            btnAddCart.innerHTML = '<i class="fas fa-cart-plus"></i> Tambah ke Keranjang';
        });
    }

    // Format Rupiah Helper
    function formatRupiah(angka) {
        return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
    }

    // Close modal on outside click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
    </script>
</body>
</html>