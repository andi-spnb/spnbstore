<?php
// Get current page
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$user = isLoggedIn() ? getUserData() : null;

// Get cart count
$cart_count = 0;
if ($user) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $cart_count = $stmt->fetchColumn();
}
?>

<!-- Navbar -->
<nav class="navbar">
    <!-- Brand -->
    <a href="/" class="navbar-brand">
        <span>SPNB STORE</span>
    </a>
    
    <!-- Search Bar (Desktop) -->
    <div class="navbar-search">
        <form method="GET" action="search.php" style="width: 100%; position: relative;">
            <i class="fas fa-search search-icon"></i>
            <input 
                type="text" 
                name="q" 
                class="search-input" 
                placeholder="Cari produk & transaksi" 
                id="searchInput"
                autocomplete="off"
            >
            <span class="search-shortcut">ctrl+k</span>
        </form>
    </div>
    
    <!-- Actions -->
    <div class="navbar-actions">
        <!-- Theme Toggle -->
        <div class="nav-icon" id="themeToggle" title="Toggle Theme">
            <i class="fas fa-moon"></i>
        </div>
        
        <!-- Cart (Always visible) -->
        <?php if ($user): ?>
        <a href="keranjang.php" class="nav-icon" title="Keranjang">
            <i class="fas fa-shopping-cart"></i>
            <?php if ($cart_count > 0): ?>
            <span class="cart-badge"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        <!-- Desktop Only Icons -->
        <div class="desktop-only" style="display: flex; gap: 0.75rem;">
            <?php if ($user): ?>
                <a href="dashboard.php" class="nav-icon" title="Dashboard">
                    <i class="fas fa-home"></i>
                </a>
                <a href="profil.php" class="avatar-btn" title="Profil">
                    <img src="assets/img/avatars/<?php echo $user['avatar']; ?>.png" alt="Avatar">
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <!-- Mobile Search -->
    <div class="mobile-menu-search">
        <form method="GET" action="search.php" style="width: 100%; position: relative;">
            <i class="fas fa-search search-icon"></i>
            <input 
                type="text" 
                name="q" 
                class="search-input" 
                placeholder="Cari produk..."
            >
        </form>
    </div>
    
    <!-- Mobile Links -->
    <div class="mobile-menu-links">
        <a href="/" class="mobile-menu-link <?php echo $current_page == 'index' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Beranda</span>
        </a>
        
        <?php if ($user): ?>
        <a href="dashboard.php" class="mobile-menu-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="riwayat.php" class="mobile-menu-link <?php echo $current_page == 'riwayat' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Riwayat Transaksi</span>
        </a>
        
        <a href="profil.php" class="mobile-menu-link <?php echo $current_page == 'profil' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profil</span>
        </a>
        
        <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 1rem 0;"></div>
        
        <a href="logout.php" class="mobile-menu-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Keluar</span>
        </a>
        <?php else: ?>
        <a href="login.php" class="mobile-menu-link">
            <i class="fas fa-sign-in-alt"></i>
            <span>Masuk</span>
        </a>
        <a href="register.php" class="mobile-menu-link">
            <i class="fas fa-user-plus"></i>
            <span>Daftar</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Menu Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            mobileMenu.classList.toggle('active');
            document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
        });
        
        // Close on link click
        const mobileLinks = mobileMenu.querySelectorAll('.mobile-menu-link');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenuToggle.classList.remove('active');
                mobileMenu.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
    }
    
    // Search shortcut (Ctrl+K)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.focus();
        }
    });
});
</script>

<style>
@media (min-width: 769px) {
    .mobile-menu-toggle {
        display: none !important;
    }
    .mobile-menu {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .desktop-only {
        display: none !important;
    }
}
</style>