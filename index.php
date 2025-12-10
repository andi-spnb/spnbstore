<?php
require_once 'config.php';

// SEO Meta Tags
$page_title = SITE_NAME . " - Toko Produk Digital Terpercaya #1 Indonesia";
$page_description = "SPNB Store menyediakan Netflix, Spotify, dan produk digital premium lainnya dengan harga termurah. Pembayaran mudah via QRIS, Transfer Bank, E-Wallet. Pengiriman instan 24/7!";
$page_keywords = "netflix murah, spotify premium, produk digital, toko digital indonesia, SPNB Store, akun streaming murah";
// Get active service categories
$categories = $conn->query("SELECT * FROM atlantic_service_categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

// Get popular games (top 6)
$popularGames = $conn->query("SELECT * FROM atlantic_games WHERE is_active = 1 ORDER BY sort_order ASC, game_name ASC LIMIT 6")->fetchAll();

// Get hero sliders
$stmt = $conn->query("SELECT * FROM hero_sliders WHERE is_active = 1 ORDER BY sort_order ASC");
$hero_sliders = $stmt->fetchAll();

// Get categories
$stmt = $conn->query("SELECT c.*, COUNT(p.id) as product_count 
                      FROM categories c 
                      LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                      GROUP BY c.id 
                      ORDER BY c.nama ASC");
$categories = $stmt->fetchAll();

// Get filter parameter
$filter_category = isset($_GET['category']) ? $_GET['category'] : 'all';

// Get products based on filter
if ($filter_category == 'all') {
    $stmt = $conn->query("SELECT p.*, c.nama as category_name, c.icon as category_icon 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.is_active = 1 
                          ORDER BY p.created_at DESC LIMIT 12");
} else {
    $stmt = $conn->prepare("SELECT p.*, c.nama as category_name, c.icon as category_icon 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.is_active = 1 AND c.slug = ?
                            ORDER BY p.created_at DESC LIMIT 12");
    $stmt->execute([$filter_category]);
}
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <?php if (file_exists('meta-tags.php')) include 'meta-tags.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #475569;
            --primary-color: #8b5cf6;
            --primary-hover: #7c3aed;
            --primary-glow: rgba(139, 92, 246, 0.3);
            --gradient-primary: linear-gradient(135deg, #6366f1, #8b5cf6);
            --success-color: #10b981;
            --danger-color: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* ============================================================
           HERO SLIDER - FULLY RESPONSIVE FOR MOBILE/ANDROID
           ============================================================ */
        .hero-section {
            padding: 0.75rem 0 1.5rem;
        }

        @media (min-width: 768px) {
            .hero-section {
                padding: 1rem 0 2rem;
            }
        }

        .hero-slider {
            position: relative;
            width: 100%;
            /* Tinggi fleksibel berdasarkan viewport */
            height: clamp(220px, 50vw, 500px);
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            touch-action: pan-y pinch-zoom;
        }

        @media (min-width: 480px) {
            .hero-slider {
                height: clamp(250px, 45vw, 450px);
                border-radius: 1rem;
            }
        }

        @media (min-width: 768px) {
            .hero-slider {
                height: clamp(300px, 40vw, 500px);
                border-radius: 1.25rem;
            }
        }

        @media (min-width: 1024px) {
            .hero-slider {
                height: 500px;
                border-radius: 1.5rem;
            }
        }

        /* Slider Track untuk efek geser */
        .slider-track {
            display: flex;
            height: 100%;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: transform;
        }

        .slider-track.dragging {
            transition: none;
        }

        /* Individual Slide */
        .hero-slide {
            min-width: 100%;
            width: 100%;
            height: 100%;
            position: relative;
            flex-shrink: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        /* Gradient Overlay - lebih ringan di mobile */
        .hero-slide::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(15, 23, 42, 0.2) 0%,
                rgba(15, 23, 42, 0.4) 40%,
                rgba(15, 23, 42, 0.85) 100%
            );
        }

        /* Hero Content - Responsive */
        .hero-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            z-index: 2;
            text-align: center;
        }

        @media (min-width: 480px) {
            .hero-content {
                padding: 1.25rem;
            }
        }

        @media (min-width: 768px) {
            .hero-content {
                position: relative;
                bottom: auto;
                left: auto;
                right: auto;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                height: 100%;
                padding: 2rem;
                max-width: 800px;
                margin: 0 auto;
            }
        }

        /* Badge */
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            background: rgba(139, 92, 246, 0.25);
            border: 1px solid rgba(139, 92, 246, 0.4);
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #c4b5fd;
            margin-bottom: 0.5rem;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        @media (min-width: 480px) {
            .hero-badge {
                font-size: 0.75rem;
                padding: 0.5rem 1rem;
                margin-bottom: 0.75rem;
            }
        }

        @media (min-width: 768px) {
            .hero-badge {
                font-size: 0.8rem;
                margin-bottom: 1rem;
            }
        }

        /* Title - Responsive Font Size */
        .hero-content h1 {
            font-size: clamp(1.125rem, 5vw, 2.75rem);
            font-weight: 700;
            margin: 0 0 0.5rem;
            line-height: 1.25;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            /* Batasi jumlah baris di mobile */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @media (min-width: 480px) {
            .hero-content h1 {
                font-size: clamp(1.25rem, 5vw, 2.5rem);
                margin-bottom: 0.625rem;
                -webkit-line-clamp: 3;
            }
        }

        @media (min-width: 768px) {
            .hero-content h1 {
                font-size: clamp(1.75rem, 4vw, 3rem);
                margin-bottom: 1rem;
                -webkit-line-clamp: unset;
            }
        }

        /* Subtitle - Hide on very small screens */
        .hero-content p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0 0 0.875rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @media (max-width: 359px) {
            .hero-content p {
                display: none;
            }
        }

        @media (min-width: 480px) {
            .hero-content p {
                font-size: 0.875rem;
                margin-bottom: 1rem;
                -webkit-line-clamp: 2;
            }
        }

        @media (min-width: 768px) {
            .hero-content p {
                font-size: 1rem;
                margin-bottom: 1.5rem;
                -webkit-line-clamp: 3;
            }
        }

        @media (min-width: 1024px) {
            .hero-content p {
                font-size: 1.125rem;
                -webkit-line-clamp: unset;
            }
        }

        /* Buttons Container */
        .hero-buttons {
            display: flex;
            flex-direction: row;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media (min-width: 480px) {
            .hero-buttons {
                gap: 0.75rem;
            }
        }

        /* Hero Buttons - Touch Friendly */
        .btn-hero {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            /* Minimum touch target 44px untuk accessibility */
            min-height: 44px;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            -webkit-tap-highlight-color: transparent;
        }

        @media (min-width: 480px) {
            .btn-hero {
                padding: 0.75rem 1.25rem;
                font-size: 0.85rem;
                gap: 0.5rem;
            }
        }

        @media (min-width: 768px) {
            .btn-hero {
                padding: 0.875rem 1.75rem;
                font-size: 0.95rem;
                border-radius: 0.625rem;
            }
        }

        .btn-hero-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 15px var(--primary-glow);
        }

        .btn-hero-primary:hover,
        .btn-hero-primary:active {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--primary-glow);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .btn-hero-secondary:hover,
        .btn-hero-secondary:active {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Navigation Arrows */
        .hero-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            /* Touch friendly size */
            width: 40px;
            height: 40px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.8;
            -webkit-tap-highlight-color: transparent;
        }

        .hero-nav:hover,
        .hero-nav:active {
            background: rgba(0, 0, 0, 0.5);
            opacity: 1;
        }

        .hero-nav.prev { left: 0.5rem; }
        .hero-nav.next { right: 0.5rem; }

        @media (min-width: 480px) {
            .hero-nav {
                width: 44px;
                height: 44px;
                font-size: 1.125rem;
            }
            .hero-nav.prev { left: 0.75rem; }
            .hero-nav.next { right: 0.75rem; }
        }

        @media (min-width: 768px) {
            .hero-nav {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
                opacity: 0.6;
            }
            .hero-nav.prev { left: 1rem; }
            .hero-nav.next { right: 1rem; }
            
            .hero-slider:hover .hero-nav {
                opacity: 1;
            }
        }

        /* Slider Indicators/Dots */
        .slider-indicators {
            position: absolute;
            bottom: 0.625rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            gap: 0.375rem;
            align-items: center;
            padding: 0.375rem 0.75rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 9999px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        @media (min-width: 768px) {
            .slider-indicators {
                bottom: 1rem;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
            }
        }

        .slider-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        .slider-dot:hover {
            background: rgba(255, 255, 255, 0.6);
        }

        .slider-dot.active {
            width: 24px;
            border-radius: 4px;
            background: white;
        }

        @media (min-width: 768px) {
            .slider-dot {
                width: 10px;
                height: 10px;
            }
            .slider-dot.active {
                width: 30px;
            }
        }

        /* Progress Bar */
        .slider-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--primary-color);
            width: 0%;
            z-index: 10;
            transition: width 0.1s linear;
        }

        /* Swipe Hint (mobile only) */
        .swipe-hint {
            position: absolute;
            bottom: 3rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.7rem;
            animation: swipeHint 2s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes swipeHint {
            0%, 100% { opacity: 0.6; transform: translateX(-50%); }
            50% { opacity: 1; transform: translateX(-60%); }
        }

        @media (min-width: 768px) {
            .swipe-hint {
                display: none;
            }
        }

        /* ============================================================
           CATEGORY SECTION
           ============================================================ */
        .category-section {
            padding: 1.5rem 0;
        }

        @media (min-width: 768px) {
            .category-section {
                padding: 2rem 0;
            }
        }

        .section-header {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 768px) {
            .section-header {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 1.5rem;
            }
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (min-width: 768px) {
            .section-title {
                font-size: 1.5rem;
            }
        }

        .section-title i {
            color: var(--primary-color);
        }

        /* Category Tabs - Horizontal Scroll */
        .category-tabs-wrapper {
            position: relative;
            margin: 0 -1rem;
            padding: 0 1rem;
        }

        .category-tabs {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding: 0.25rem 0 0.75rem;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
            -ms-overflow-style: none;
            -webkit-overflow-scrolling: touch;
        }

        .category-tabs::-webkit-scrollbar {
            display: none;
        }

        .category-tab {
            flex-shrink: 0;
            scroll-snap-align: start;
            padding: 0.5rem 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 9999px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            -webkit-tap-highlight-color: transparent;
        }

        @media (min-width: 768px) {
            .category-tab {
                padding: 0.625rem 1.25rem;
                font-size: 0.875rem;
            }
        }

        .category-tab:hover,
        .category-tab:active {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .category-tab.active {
            background: var(--gradient-primary);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px var(--primary-glow);
        }

        .category-tab .count {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
        }

        /* ============================================================
           PRODUCTS SECTION
           ============================================================ */
        .products-section {
            padding: 1rem 0 2rem;
        }

        @media (min-width: 768px) {
            .products-section {
                padding: 1rem 0 3rem;
            }
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        @media (min-width: 480px) {
            .products-grid {
                gap: 1rem;
            }
        }

        @media (min-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1.25rem;
            }
        }

        @media (min-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
            }
        }

        /* Product Card */
        .product-card {
            background: var(--bg-secondary);
            border-radius: 0.75rem;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 768px) {
            .product-card {
                border-radius: 1rem;
            }
        }

        .product-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.2);
        }

        .product-image {
            position: relative;
            width: 100%;
            aspect-ratio: 1/1;
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-primary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            overflow: hidden;
        }

        @media (min-width: 480px) {
            .product-image {
                aspect-ratio: 4/3;
                font-size: 3rem;
            }
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.05);
        }

        .product-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        @media (min-width: 480px) {
            .product-badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.625rem;
            }
        }

        .product-badge.in-stock {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .product-badge.out-stock {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }

        .product-info {
            padding: 0.75rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        @media (min-width: 480px) {
            .product-info {
                padding: 1rem;
            }
        }

        .product-category {
            font-size: 0.7rem;
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        @media (min-width: 480px) {
            .product-category {
                font-size: 0.75rem;
                margin-bottom: 0.375rem;
            }
        }

        .product-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.5rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @media (min-width: 480px) {
            .product-name {
                font-size: 0.95rem;
                margin-bottom: 0.75rem;
            }
        }

        .product-price {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .price-current {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        @media (min-width: 480px) {
            .price-current {
                font-size: 1.1rem;
            }
        }

        .btn-buy {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.5rem 0.75rem;
            background: var(--gradient-primary);
            color: white;
            border-radius: 0.375rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            -webkit-tap-highlight-color: transparent;
        }

        @media (min-width: 480px) {
            .btn-buy {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
                gap: 0.375rem;
                border-radius: 0.5rem;
            }
        }

        .btn-buy:hover,
        .btn-buy:active {
            box-shadow: 0 4px 15px var(--primary-glow);
        }

        /* ============================================================
           FEATURES SECTION
           ============================================================ */
        .features-section {
            padding: 2rem 0;
            background: linear-gradient(180deg, transparent, var(--bg-secondary) 50%, transparent);
        }

        @media (min-width: 768px) {
            .features-section {
                padding: 3rem 0;
            }
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .features-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
            }
        }

        .feature-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        @media (min-width: 768px) {
            .feature-card {
                padding: 1.5rem;
                border-radius: 1rem;
            }
        }

        .feature-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-color);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            font-size: 1.5rem;
        }

        @media (min-width: 768px) {
            .feature-icon {
                width: 64px;
                height: 64px;
                font-size: 1.75rem;
                margin-bottom: 1rem;
            }
        }

        .feature-card h3 {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0 0 0.375rem;
            color: var(--text-primary);
        }

        @media (min-width: 768px) {
            .feature-card h3 {
                font-size: 1.1rem;
                margin-bottom: 0.5rem;
            }
        }

        .feature-card p {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.4;
        }

        @media (min-width: 768px) {
            .feature-card p {
                font-size: 0.875rem;
                line-height: 1.5;
            }
        }

        /* ============================================================
           EMPTY STATE
           ============================================================ */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--bg-secondary);
            border-radius: 1rem;
            border: 1px dashed var(--border-color);
        }

        .empty-icon {
            width: 60px;
            height: 60px;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .empty-state h3 {
            font-size: 1.1rem;
            margin: 0 0 0.5rem;
        }

        .empty-state p {
            color: var(--text-muted);
            margin: 0 0 1.25rem;
            font-size: 0.9rem;
        }

        /* ============================================================
           VIEW ALL BUTTON
           ============================================================ */
        .view-all-wrapper {
            text-align: center;
            margin-top: 1.5rem;
        }

        @media (min-width: 768px) {
            .view-all-wrapper {
                margin-top: 2rem;
            }
        }

        .btn-view-all {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.3s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-view-all:hover,
        .btn-view-all:active {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            padding: 2rem 0 1.5rem;
            margin-top: 2rem;
        }

        @media (min-width: 768px) {
            .footer {
                padding: 3rem 0 1.5rem;
                margin-top: 3rem;
            }
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 640px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 2rem;
            }
        }

        @media (min-width: 1024px) {
            .footer-grid {
                grid-template-columns: 2fr 1fr 1fr 1fr;
            }
        }

        .footer-brand {
            max-width: 300px;
        }

        .footer-logo {
            font-size: 1.25rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.75rem;
            display: inline-block;
        }

        @media (min-width: 768px) {
            .footer-logo {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }
        }

        .footer-brand p {
            color: var(--text-muted);
            font-size: 0.85rem;
            line-height: 1.5;
            margin: 0 0 1rem;
        }

        .social-links {
            display: flex;
            gap: 0.625rem;
        }

        .social-link {
            width: 36px;
            height: 36px;
            background: var(--bg-tertiary);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .social-link:hover,
        .social-link:active {
            background: var(--primary-color);
            color: white;
        }

        .footer-section h4 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0 0 0.75rem;
            color: var(--text-primary);
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 1.5rem;
            margin-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        @media (min-width: 768px) {
            .footer-bottom {
                padding-top: 2rem;
                margin-top: 2rem;
                font-size: 0.875rem;
            }
        }
        .topup-services-section {
    padding: 3rem 0;
    background: #0f172a;
}

.topup-services-section .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Banner */
.topup-banner {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
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
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
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
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Hero Slider -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-slider" id="heroSlider">
                <div class="slider-track" id="sliderTrack">
                    <?php if (count($hero_sliders) > 0): ?>
                        <?php foreach ($hero_sliders as $index => $slide): ?>
                        <div class="hero-slide" style="background-image: url('<?php echo $slide['image_url']; ?>');">
                            <div class="hero-content">
                                <span class="hero-badge">
                                    <i class="fas fa-bolt"></i> Promo Spesial
                                </span>
                                <h1><?php echo htmlspecialchars($slide['title']); ?></h1>
                                <p><?php echo htmlspecialchars($slide['subtitle']); ?></p>
                                <div class="hero-buttons">
                                    <?php if (!empty($slide['button_text']) && !empty($slide['button_link'])): ?>
                                    <a href="<?php echo $slide['button_link']; ?>" class="btn-hero btn-hero-primary">
                                        <i class="fas fa-shopping-bag"></i> 
                                        <span><?php echo htmlspecialchars($slide['button_text']); ?></span>
                                    </a>
                                    <?php endif; ?>
                                    <a href="#products" class="btn-hero btn-hero-secondary">
                                        <i class="fas fa-arrow-down"></i>
                                        <span>Lihat Produk</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Default Hero -->
                        <div class="hero-slide" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="hero-content">
                                <span class="hero-badge">
                                    <i class="fas fa-star"></i> #1 Toko Digital
                                </span>
                                <h1>Produk Digital Premium Harga Terjangkau</h1>
                                <p>Netflix, Spotify, dan layanan streaming lainnya. Transaksi otomatis & instan 24/7!</p>
                                <div class="hero-buttons">
                                    <a href="#products" class="btn-hero btn-hero-primary">
                                        <i class="fas fa-shopping-bag"></i>
                                        <span>Belanja</span>
                                    </a>
                                    <a href="about.php" class="btn-hero btn-hero-secondary">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Info</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($hero_sliders) > 1): ?>
                <!-- Navigation -->
                <button class="hero-nav prev" onclick="prevSlide()" aria-label="Previous slide">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="hero-nav next" onclick="nextSlide()" aria-label="Next slide">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <!-- Indicators -->
                <div class="slider-indicators">
                    <?php foreach ($hero_sliders as $index => $slide): ?>
                    <button class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                            onclick="goToSlide(<?php echo $index; ?>)" 
                            aria-label="Go to slide <?php echo $index + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>

                <!-- Progress Bar -->
                <div class="slider-progress" id="sliderProgress"></div>

                <!-- Swipe Hint (Mobile) -->
                <div class="swipe-hint" id="swipeHint">
                    <i class="fas fa-hand-point-left"></i>
                    <span>Geser</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
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
    <!-- Category Filter -->
    <section class="category-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-th-large"></i>
                    Kategori
                </h2>
            </div>
            <div class="category-tabs-wrapper">
                <div class="category-tabs">
                    <a href="index.php?category=all" class="category-tab <?php echo $filter_category == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-border-all"></i> Semua
                    </a>
                    <?php foreach ($categories as $category): ?>
                    <a href="index.php?category=<?php echo $category['slug']; ?>" 
                       class="category-tab <?php echo $filter_category == $category['slug'] ? 'active' : ''; ?>">
                        <?php echo $category['icon'] ?? '📦'; ?> 
                        <?php echo htmlspecialchars($category['nama']); ?>
                        <?php if ($category['product_count'] > 0): ?>
                        <span class="count"><?php echo $category['product_count']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Products -->
    <section class="products-section" id="products">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-fire"></i>
                    <?php 
                    if ($filter_category == 'all') {
                        echo 'Produk Terbaru';
                    } else {
                        foreach ($categories as $cat) {
                            if ($cat['slug'] == $filter_category) {
                                echo htmlspecialchars($cat['nama']);
                                break;
                            }
                        }
                    }
                    ?>
                </h2>
            </div>

            <?php if (count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                <a href="produk.php?slug=<?php echo $product['slug']; ?>" class="product-card">
                    <div class="product-image">
                        <?php 
                        $upload_dir = 'assets/img/products/';
                        if (!empty($product['gambar']) && file_exists($upload_dir . $product['gambar'])): 
                        ?>
                            <img src="<?php echo $upload_dir . $product['gambar']; ?>" 
                                 alt="<?php echo htmlspecialchars($product['nama']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <?php echo $product['category_icon'] ?? '📦'; ?>
                        <?php endif; ?>
                        
                        <span class="product-badge <?php echo $product['stok'] > 0 ? 'in-stock' : 'out-stock'; ?>">
                            <?php echo $product['stok'] > 0 ? 'Ready' : 'Habis'; ?>
                        </span>
                    </div>
                    <div class="product-info">
                        <span class="product-category">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </span>
                        <h3 class="product-name"><?php echo htmlspecialchars($product['nama']); ?></h3>
                        <div class="product-price">
                            <span class="price-current"><?php echo formatRupiah($product['harga']); ?></span>
                            <span class="btn-buy">
                                <i class="fas fa-shopping-cart"></i> Beli
                            </span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="view-all-wrapper">
                <a href="kategori.php" class="btn-view-all">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>Produk Tidak Ditemukan</h3>
                <p>Tidak ada produk dalam kategori ini.</p>
                <a href="index.php" class="btn-hero btn-hero-primary">
                    <i class="fas fa-arrow-left"></i> Lihat Semua
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features -->
    <section class="features-section">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 1.5rem;">
                <h2 class="section-title" style="justify-content: center;">
                    <i class="fas fa-shield-alt"></i>
                    Kenapa Pilih Kami?
                </h2>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Proses Instan</h3>
                    <p>Transaksi otomatis 24 jam</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💎</div>
                    <h3>Harga Terbaik</h3>
                    <p>Produk premium murah</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3>100% Aman</h3>
                    <p>Garansi kepuasan</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎧</div>
                    <h3>Support 24/7</h3>
                    <p>Bantuan via WhatsApp</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="footer-logo">SPNB Store</div>
                    <p>Toko produk digital terpercaya di Indonesia. Akses mudah ke layanan streaming premium.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link" aria-label="Telegram"><i class="fab fa-telegram"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h4>Kategori</h4>
                    <ul class="footer-links">
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                        <li><a href="kategori.php?slug=<?php echo $category['slug']; ?>"><?php echo htmlspecialchars($category['nama']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Informasi</h4>
                    <ul class="footer-links">
                        <li><a href="cara-order.php">Cara Order</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                        <li><a href="about.php">Tentang Kami</a></li>
                        <li><a href="contact.php">Kontak</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Akun</h4>
                    <ul class="footer-links">
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Daftar</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Made with 💜</p>
            </div>
        </div>
    </footer>

    <script>
    // ==================== HERO SLIDER - TOUCH FRIENDLY ====================
    const sliderTrack = document.getElementById('sliderTrack');
    const heroSlider = document.getElementById('heroSlider');
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.slider-dot');
    const progressBar = document.getElementById('sliderProgress');
    const swipeHint = document.getElementById('swipeHint');
    const totalSlides = slides.length;
    
    let currentSlide = 0;
    let autoPlayInterval;
    let progressInterval;
    let touchStartX = 0;
    let touchEndX = 0;
    let touchStartY = 0;
    let isDragging = false;
    let dragOffset = 0;

    const AUTOPLAY_DELAY = 6000;
    const SWIPE_THRESHOLD = 50;

    // Hide swipe hint after first interaction
    function hideSwipeHint() {
        if (swipeHint) {
            swipeHint.style.opacity = '0';
            setTimeout(() => swipeHint.style.display = 'none', 300);
        }
    }

    function updateSlider(animate = true) {
        if (sliderTrack) {
            sliderTrack.classList.toggle('dragging', !animate);
            sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
        }
        
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentSlide);
        });

        resetProgress();
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        updateSlider();
        resetAutoPlay();
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        updateSlider();
        resetAutoPlay();
    }

    function goToSlide(index) {
        currentSlide = index;
        updateSlider();
        resetAutoPlay();
    }

    // Progress bar
    function startProgress() {
        let progress = 0;
        const increment = 100 / (AUTOPLAY_DELAY / 50);
        
        progressInterval = setInterval(() => {
            progress += increment;
            if (progressBar) {
                progressBar.style.width = `${Math.min(progress, 100)}%`;
            }
        }, 50);
    }

    function resetProgress() {
        clearInterval(progressInterval);
        if (progressBar) progressBar.style.width = '0%';
        startProgress();
    }

    // Autoplay
    function startAutoPlay() {
        if (totalSlides <= 1) return;
        autoPlayInterval = setInterval(nextSlide, AUTOPLAY_DELAY);
        startProgress();
    }

    function resetAutoPlay() {
        clearInterval(autoPlayInterval);
        clearInterval(progressInterval);
        startAutoPlay();
    }

    function pauseAutoPlay() {
        clearInterval(autoPlayInterval);
        clearInterval(progressInterval);
        if (progressBar) progressBar.style.width = '0%';
    }

    // Touch Events
    if (heroSlider && totalSlides > 1) {
        heroSlider.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            isDragging = true;
            pauseAutoPlay();
            hideSwipeHint();
            sliderTrack.classList.add('dragging');
        }, { passive: true });

        heroSlider.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            
            const currentX = e.touches[0].clientX;
            const currentY = e.touches[0].clientY;
            const diffX = touchStartX - currentX;
            const diffY = Math.abs(touchStartY - currentY);
            
            // Jika geser vertikal lebih dominan, biarkan scroll
            if (diffY > Math.abs(diffX)) {
                return;
            }
            
            // Hitung offset untuk efek drag
            dragOffset = diffX;
            const slideWidth = heroSlider.offsetWidth;
            const maxDrag = slideWidth * 0.3; // Max 30% drag
            
            // Batasi drag
            if (Math.abs(dragOffset) > maxDrag) {
                dragOffset = dragOffset > 0 ? maxDrag : -maxDrag;
            }
            
            // Terapkan transformasi langsung
            const baseOffset = currentSlide * 100;
            const dragPercent = (dragOffset / slideWidth) * 100;
            sliderTrack.style.transform = `translateX(-${baseOffset + dragPercent}%)`;
        }, { passive: true });

        heroSlider.addEventListener('touchend', (e) => {
            if (!isDragging) return;
            isDragging = false;
            sliderTrack.classList.remove('dragging');
            
            touchEndX = e.changedTouches[0].clientX;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > SWIPE_THRESHOLD) {
                if (diff > 0 && currentSlide < totalSlides - 1) {
                    nextSlide();
                } else if (diff < 0 && currentSlide > 0) {
                    prevSlide();
                } else if (diff > 0 && currentSlide === totalSlides - 1) {
                    goToSlide(0); // Loop ke awal
                } else if (diff < 0 && currentSlide === 0) {
                    goToSlide(totalSlides - 1); // Loop ke akhir
                } else {
                    updateSlider(); // Kembali ke posisi
                }
            } else {
                updateSlider(); // Kembali ke posisi
            }
            
            dragOffset = 0;
        });

        // Mouse Events (Desktop)
        heroSlider.addEventListener('mouseenter', pauseAutoPlay);
        heroSlider.addEventListener('mouseleave', () => {
            if (!isDragging) resetAutoPlay();
        });

        // Start
        startAutoPlay();

        // Hide swipe hint after 5 seconds
        setTimeout(hideSwipeHint, 5000);
    }

    // Keyboard
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') prevSlide();
        if (e.key === 'ArrowRight') nextSlide();
    });

    // Visibility API - pause when tab not visible
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            pauseAutoPlay();
        } else {
            resetAutoPlay();
        }
    });
    </script>
</body>
</html>
