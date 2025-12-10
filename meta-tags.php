<?php
/**
 * SEO & Meta Tags Helper
 * SPNB Store - Favicon, Meta Tags, Open Graph
 * 
 * Cara pakai: include file ini di dalam <head> setiap halaman
 * <?php include 'meta-tags.php'; ?>
 */

// Default meta values (bisa di-override dengan variable sebelum include)
$page_title = $page_title ?? SITE_NAME . ' - Toko Produk Digital Terpercaya';
$page_description = $page_description ?? 'SPNB Store menyediakan produk digital premium berkualitas dengan harga terjangkau. Netflix, Spotify, dan layanan streaming lainnya. Pembayaran mudah, pengiriman instan!';
$page_keywords = $page_keywords ?? 'produk digital, netflix murah, spotify premium, akun streaming, toko digital indonesia, SPNB Store';
$page_image = $page_image ?? SITE_URL . '/assets/img/og-image.png';
$page_url = $page_url ?? SITE_URL . $_SERVER['REQUEST_URI'];
?>

<!-- ========== FAVICON ========== -->
<link rel="icon" href="<?php echo SITE_URL; ?>/assets/img/favicon.ico" type="image/x-icon">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo SITE_URL; ?>/assets/img/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo SITE_URL; ?>/assets/img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo SITE_URL; ?>/assets/img/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="<?php echo SITE_URL; ?>/assets/img/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo SITE_URL; ?>/assets/img/apple-touch-icon.png">

<!-- ========== PRIMARY META TAGS ========== -->
<meta name="title" content="<?php echo htmlspecialchars($page_title); ?>">
<meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
<meta name="author" content="SPNB Store">
<meta name="robots" content="index, follow">
<meta name="language" content="Indonesian">
<meta name="revisit-after" content="7 days">

<!-- ========== OPEN GRAPH / FACEBOOK ========== -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo htmlspecialchars($page_url); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($page_image); ?>">
<meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
<meta property="og:locale" content="id_ID">

<!-- ========== TWITTER CARD ========== -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="<?php echo htmlspecialchars($page_url); ?>">
<meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($page_image); ?>">

<!-- ========== PWA / MOBILE APP ========== -->
<meta name="theme-color" content="#8b5cf6">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo SITE_NAME; ?>">
<link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">

<!-- ========== CANONICAL URL ========== -->
<link rel="canonical" href="<?php echo htmlspecialchars($page_url); ?>">
