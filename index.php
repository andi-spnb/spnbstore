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
            --bg-card: rgba(30, 41, 59, 0.8);
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #475569;
            --primary-color: #8b5cf6;
            --primary-hover: #7c3aed;
            --primary-glow: rgba(139, 92, 246, 0.4);
            --gradient-primary: linear-gradient(135deg, #6366f1, #8b5cf6);
            --gradient-secondary: linear-gradient(135deg, #f59e0b, #f97316);
            --gradient-accent: linear-gradient(135deg, #10b981, #059669);
            --gradient-dark: linear-gradient(135deg, #1e293b, #334155);
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-glow: 0 0 20px var(--primary-glow);
            --radius-sm: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        @media (max-width: 640px) {
            .container {
                padding: 0 1rem;
            }
        }

        /* ============================================================
           HERO SECTION - IMPROVED DESIGN
           ============================================================ */
        .hero-section {
            padding: 1.5rem 0 2rem;
            position: relative;
            overflow: hidden;
        }

        @media (min-width: 768px) {
            .hero-section {
                padding: 2rem 0 3rem;
            }
        }

        .hero-slider {
            position: relative;
            width: 100%;
            height: clamp(280px, 50vw, 520px);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .hero-slider:hover {
            box-shadow: var(--shadow-lg), var(--shadow-glow);
        }

        .slider-track {
            display: flex;
            height: 100%;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hero-slide {
            min-width: 100%;
            position: relative;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .hero-slide::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                135deg,
                rgba(15, 23, 42, 0.9) 0%,
                rgba(15, 23, 42, 0.7) 50%,
                rgba(15, 23, 42, 0.5) 100%
            );
        }

        .hero-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 800px;
            text-align: center;
            z-index: 2;
            padding: 2rem;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--gradient-primary);
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            margin-bottom: 1rem;
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.6s ease-out;
        }

        .hero-content h1 {
            font-size: clamp(1.5rem, 5vw, 3rem);
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.2;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .hero-content p {
            font-size: clamp(0.9rem, 3vw, 1.2rem);
            color: var(--text-secondary);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .btn-hero {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition);
            border: 2px solid transparent;
            min-width: 140px;
        }

        .btn-hero-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* ============================================================
           TOPUP SECTION - REDESIGNED
           ============================================================ */
        .topup-section {
            padding: 2rem 0;
            position: relative;
        }

        .topup-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
        }

        .section-title-wrapper {
            text-align: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .section-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .topup-banner {
            background: var(--gradient-dark);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(139, 92, 246, 0.2);
            box-shadow: var(--shadow-md);
        }

        .topup-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 50%, rgba(139, 92, 246, 0.1), transparent 70%);
        }

        .banner-content {
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .banner-content h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .banner-content p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        .btn-banner {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .btn-banner:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .banner-decoration {
            display: flex;
            gap: 1rem;
            font-size: 3rem;
            opacity: 0.3;
            position: relative;
            z-index: 2;
        }

        .banner-decoration i {
            animation: float 3s ease-in-out infinite;
        }

        /* ============================================================
           POPULAR GAMES SECTION
           ============================================================ */
        .popular-games-section {
            padding: 2rem 0;
        }

        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .games-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .games-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }

        .game-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .game-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0;
            transition: var(--transition);
            z-index: 1;
        }

        .game-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: var(--shadow-lg);
        }

        .game-card:hover::before {
            opacity: 0.1;
        }

        .game-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-md);
            background: var(--gradient-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            position: relative;
            z-index: 2;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .game-icon img {
            width: 80%;
            height: 80%;
            object-fit: contain;
        }

        .game-card:hover .game-icon {
            transform: scale(1.1);
            border-color: var(--primary-color);
        }

        .game-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            position: relative;
            z-index: 2;
        }

        .view-all-btn {
            text-align: center;
            margin-top: 2rem;
        }

        .btn-view-all {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: 2px solid var(--primary-color);
            border-radius: var(--radius-md);
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-view-all:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ============================================================
           CATEGORY SECTION
           ============================================================ */
        .category-section {
            padding: 2rem 0;
        }

        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        .category-tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }

        .category-tab:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            transform: translateY(-2px);
            border-color: var(--primary-color);
        }

        .category-tab.active {
            background: var(--gradient-primary);
            color: white;
            border-color: transparent;
            box-shadow: var(--shadow-md);
        }

        .category-tab .count {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
        }

        /* ============================================================
           PRODUCTS SECTION
           ============================================================ */
        .products-section {
            padding: 2rem 0 3rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        @media (max-width: 640px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        .product-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            text-decoration: none;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0;
            transition: var(--transition);
            z-index: 1;
        }

        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: var(--shadow-lg);
        }

        .product-card:hover::before {
            opacity: 0.1;
        }

        .product-image {
            position: relative;
            width: 100%;
            height: 180px;
            overflow: hidden;
            z-index: 2;
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
            top: 0.75rem;
            right: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 3;
        }

        .badge-stock {
            background: var(--gradient-accent);
            color: white;
        }

        .badge-out {
            background: var(--danger-color);
            color: white;
        }

        .product-info {
            padding: 1rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 2;
        }

        .product-category {
            font-size: 0.8rem;
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: inline-block;
        }

        .product-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .price-current {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .btn-buy {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--gradient-primary);
            color: white;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .btn-buy:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* ============================================================
           FEATURES SECTION
           ============================================================ */
        .features-section {
            padding: 3rem 0;
            background: var(--gradient-dark);
            position: relative;
            overflow: hidden;
        }

        .features-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(139, 92, 246, 0.1), transparent 50%);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .feature-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .feature-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .feature-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* ============================================================
           EMPTY STATE
           ============================================================ */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            border: 2px dashed var(--border-color);
            max-width: 500px;
            margin: 0 auto;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            padding: 3rem 0 1.5rem;
            margin-top: 3rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 2rem;
        }

        @media (min-width: 768px) {
            .footer-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .footer-brand {
            max-width: 300px;
        }

        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .footer-brand p {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .social-links {
            display: flex;
            gap: 0.75rem;
        }

        .social-link {
            width: 40px;
            height: 40px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
        }

        .social-link:hover {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-2px);
        }

        .footer-section h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
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
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-block;
        }

        .footer-links a:hover {
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* ============================================================
           ANIMATIONS
           ============================================================ */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* ============================================================
           UTILITIES
           ============================================================ */
        .text-gradient {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .bg-gradient {
            background: var(--gradient-primary);
        }

        .glass-effect {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .hover-lift {
            transition: var(--transition);
        }

        .hover-lift:hover {
            transform: translateY(-3px);
        }

        /* ============================================================
           RESPONSIVE ADJUSTMENTS
           ============================================================ */
        @media (max-width: 768px) {
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-hero {
                width: 100%;
                max-width: 250px;
            }
            
            .topup-banner {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .banner-decoration {
                justify-content: center;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .category-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                justify-content: flex-start;
                padding-bottom: 0.5rem;
                margin-bottom: -0.5rem;
            }
            
            .category-tab {
                flex-shrink: 0;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
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
                                    <i class="fas fa-bolt"></i> <?php echo htmlspecialchars($slide['badge_text'] ?? 'Promo Spesial'); ?>
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
                                        <i class="fas fa-fire"></i>
                                        <span>Lihat Produk</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Default Hero -->
                        <div class="hero-slide" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);">
                            <div class="hero-content">
                                <span class="hero-badge">
                                    <i class="fas fa-star"></i> #1 Toko Digital
                                </span>
                                <h1>Produk Digital Premium Harga Terjangkau</h1>
                                <p>Netflix, Spotify, dan layanan streaming lainnya. Transaksi otomatis & instan 24/7!</p>
                                <div class="hero-buttons">
                                    <a href="#products" class="btn-hero btn-hero-primary">
                                        <i class="fas fa-shopping-bag"></i>
                                        <span>Belanja Sekarang</span>
                                    </a>
                                    <a href="categories.php" class="btn-hero btn-hero-secondary">
                                        <i class="fas fa-th-large"></i>
                                        <span>Lihat Kategori</span>
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
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Topup Services Section -->
    <section class="topup-section">
        <div class="container">
            <div class="section-title-wrapper">
                <h2 class="section-title">🎮 Top Up Game Online</h2>
                <p class="section-subtitle">Isi ulang diamond, UC, dan voucher game favorit Anda dengan harga terbaik</p>
            </div>
            
            <!-- Banner -->
            <div class="topup-banner">
                <div class="banner-content">
                    <h2>Top Up Cepat & Aman</h2>
                    <p>Diamond Mobile Legends, UC PUBG Mobile, Voucher Game, dan masih banyak lagi! Proses instan 24/7.</p>
                    <a href="topup-game.php" class="btn-banner">
                        <i class="fas fa-gamepad"></i> Mulai Top Up
                    </a>
                </div>
                <div class="banner-decoration">
                    <i class="fas fa-gem"></i>
                    <i class="fas fa-coins"></i>
                    <i class="fas fa-crown"></i>
                </div>
            </div>
            
            <!-- Popular Games -->
            <?php if (!empty($popularGames)): ?>
            <div class="popular-games-section">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--text-primary);">
                    <i class="fas fa-fire" style="color: var(--warning-color);"></i> Game Populer
                </h3>
                
                <div class="games-grid">
                    <?php foreach ($popularGames as $game): ?>
                    <a href="topup-game-detail.php?game=<?php echo urlencode($game['game_code']); ?>" class="game-card">
                        <div class="game-icon">
                            <?php if (!empty($game['game_icon']) && file_exists('assets/img/games/' . $game['game_icon'])): ?>
                                <img src="assets/img/games/<?php echo htmlspecialchars($game['game_icon']); ?>" 
                                     alt="<?php echo htmlspecialchars($game['game_name']); ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <i class="fas fa-gamepad"></i>
                            <?php endif; ?>
                        </div>
                        <span class="game-name"><?php echo htmlspecialchars($game['game_name']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="view-all-btn">
                    <a href="topup-game.php" class="btn-view-all">
                        Lihat Semua Game <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Category Filter -->
    <section class="category-section">
        <div class="container">
            <div class="section-title-wrapper">
                <h2 class="section-title"><i class="fas fa-th-large"></i> Kategori Produk</h2>
                <p class="section-subtitle">Temukan produk digital terbaik sesuai kebutuhan Anda</p>
            </div>
            
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
    </section>

    <!-- Products -->
    <section class="products-section" id="products">
        <div class="container">
            <div class="section-title-wrapper">
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
                <p class="section-subtitle">Produk digital berkualitas dengan harga terbaik</p>
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
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--gradient-dark);">
                                <i class="fas fa-box" style="font-size: 3rem; color: var(--primary-color);"></i>
                            </div>
                        <?php endif; ?>
                        
                        <span class="product-badge <?php echo $product['stok'] > 0 ? 'badge-stock' : 'badge-out'; ?>">
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

            <div style="text-align: center; margin-top: 3rem;">
                <a href="kategori.php" class="btn-view-all">
                    Lihat Semua Produk <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>Produk Tidak Ditemukan</h3>
                <p>Maaf, tidak ada produk dalam kategori ini.</p>
                <a href="index.php?category=all" class="btn-banner" style="margin-top: 1rem;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Semua Produk
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features -->
    <section class="features-section">
        <div class="container">
            <div class="section-title-wrapper" style="margin-bottom: 2.5rem;">
                <h2 class="section-title" style="background: linear-gradient(135deg, #fff, #cbd5e1); -webkit-background-clip: text;">Kenapa Pilih SPNB Store?</h2>
                <p class="section-subtitle" style="color: rgba(255, 255, 255, 0.8);">Keunggulan yang membuat kami berbeda</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                    <h3>Proses Instan</h3>
                    <p>Transaksi otomatis 24 jam, pengiriman cepat dalam hitungan menit</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-gem"></i></div>
                    <h3>Harga Terbaik</h3>
                    <p>Produk digital premium dengan harga paling kompetitif di pasaran</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>100% Aman</h3>
                    <p>Transaksi terenkripsi dengan garansi kepuasan pelanggan</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-headset"></i></div>
                    <h3>Support 24/7</h3>
                    <p>Tim customer service siap membantu kapan saja via WhatsApp</p>
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
                    <p>Toko produk digital terpercaya di Indonesia. Akses mudah ke layanan streaming premium dan top up game online.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link" aria-label="Telegram"><i class="fab fa-telegram"></i></a>
                        <a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h4>Produk</h4>
                    <ul class="footer-links">
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                        <li><a href="kategori.php?slug=<?php echo $category['slug']; ?>"><?php echo htmlspecialchars($category['nama']); ?></a></li>
                        <?php endforeach; ?>
                        <li><a href="topup-game.php">Top Up Game</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Informasi</h4>
                    <ul class="footer-links">
                        <li><a href="cara-order.php">Cara Order</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                        <li><a href="about.php">Tentang Kami</a></li>
                        <li><a href="contact.php">Kontak</a></li>
                        <li><a href="syarat-ketentuan.php">Syarat & Ketentuan</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Akun</h4>
                    <ul class="footer-links">
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Daftar</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="riwayat.php">Riwayat Transaksi</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. Made with <i class="fas fa-heart" style="color: var(--primary-color);"></i></p>
            </div>
        </div>
    </footer>

    <script>
    // Hero Slider JavaScript (Tetap sama)
    const sliderTrack = document.getElementById('sliderTrack');
    const heroSlider = document.getElementById('heroSlider');
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.slider-dot');
    const totalSlides = slides.length;
    
    let currentSlide = 0;
    let autoPlayInterval;
    let touchStartX = 0;
    let isDragging = false;
    let dragOffset = 0;

    const AUTOPLAY_DELAY = 6000;
    const SWIPE_THRESHOLD = 50;

    function updateSlider(animate = true) {
        if (sliderTrack) {
            sliderTrack.style.transition = animate ? 'transform 0.5s cubic-bezier(0.4, 0, 0.2, 1)' : 'none';
            sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
        }
        
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentSlide);
        });
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

    function startAutoPlay() {
        if (totalSlides <= 1) return;
        autoPlayInterval = setInterval(nextSlide, AUTOPLAY_DELAY);
    }

    function resetAutoPlay() {
        clearInterval(autoPlayInterval);
        startAutoPlay();
    }

    function pauseAutoPlay() {
        clearInterval(autoPlayInterval);
    }

    // Touch Events
    if (heroSlider && totalSlides > 1) {
        heroSlider.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            isDragging = true;
            pauseAutoPlay();
            sliderTrack.style.transition = 'none';
        }, { passive: true });

        heroSlider.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            
            const currentX = e.touches[0].clientX;
            const diffX = touchStartX - currentX;
            const slideWidth = heroSlider.offsetWidth;
            
            dragOffset = diffX;
            const dragPercent = (dragOffset / slideWidth) * 100;
            const baseOffset = currentSlide * 100;
            
            sliderTrack.style.transform = `translateX(-${baseOffset + dragPercent}%)`;
        }, { passive: true });

        heroSlider.addEventListener('touchend', (e) => {
            if (!isDragging) return;
            isDragging = false;
            
            const touchEndX = e.changedTouches[0].clientX;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > SWIPE_THRESHOLD) {
                if (diff > 0) {
                    nextSlide();
                } else {
                    prevSlide();
                }
            } else {
                updateSlider();
            }
            
            dragOffset = 0;
        });

        // Mouse Events (Desktop)
        heroSlider.addEventListener('mouseenter', pauseAutoPlay);
        heroSlider.addEventListener('mouseleave', resetAutoPlay);

        // Keyboard Navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') prevSlide();
            if (e.key === 'ArrowRight') nextSlide();
        });

        // Start autoplay
        startAutoPlay();
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            
            if (href.startsWith('#') && document.querySelector(href)) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Add fade-in animation on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe elements for scroll animation
    document.querySelectorAll('.product-card, .feature-card, .game-card').forEach(el => {
        observer.observe(el);
    });
    </script>
</body>
</html>