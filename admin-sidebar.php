<?php
/**
 * Admin Sidebar Component
 * Include this file in all admin pages
 * Usage: require_once 'admin-sidebar.php';
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Sidebar menu items
$menu_items = [
    [
        'title' => 'Dashboard',
        'icon' => 'fas fa-home',
        'url' => 'admin.php',
        'page' => 'admin',
        'badge' => null
    ],
    [
        'title' => 'Hero Slider',
        'icon' => 'fas fa-images',
        'url' => 'admin-hero-slider.php',
        'page' => 'admin-hero-slider',
        'badge' => null
    ],
    [
        'title' => 'Produk',
        'icon' => 'fas fa-box',
        'url' => 'admin-products.php',
        'page' => 'admin-products',
        'badge' => null
    ],
    [
        'title' => 'Transaksi',
        'icon' => 'fas fa-receipt',
        'url' => 'admin-transactions.php',
        'page' => 'admin-transactions',
        'badge' => null
    ],
    [
        'title' => 'Users',
        'icon' => 'fas fa-users',
        'url' => 'admin-users.php',
        'page' => 'admin-users',
        'badge' => null
    ],
    [
        'title' => 'Kategori',
        'icon' => 'fas fa-tags',
        'url' => 'admin-categories.php',
        'page' => 'admin-categories',
        'badge' => null
    ],
    [
        'title' => 'H2H Monitor',
        'icon' => 'fas fa-exchange-alt',
        'url' => 'admin-h2h.php',
        'page' => 'admin-h2h',
        'badge' => null
    ],
    [
        'title' => 'Kelola Game Top Up',
        'icon' => 'fa fa-gamepad',
        'url' => 'admin-atlantic-games.php',
        'page' => 'admin-atlantic-games',
        'badge' => null
    ],
    [
        'title' => 'Kelola Stok Produk',
        'icon' => 'fa fa-database',
        'url' => 'admin-stock.php',
        'page' => 'admin-stock',
        'badge' => null
    ],
    [
        'title' => 'Settings',
        'icon' => 'fas fa-cog',
        'url' => 'admin-settings.php',
        'page' => 'admin-settings',
        'badge' => null
    ]
];

// Optional: Get dynamic badges (notifications, counts, etc)
try {
    // Get pending transactions count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'");
    $pending_count = $stmt->fetch()['count'];
    if ($pending_count > 0) {
        $menu_items[3]['badge'] = $pending_count; // Transaksi (index updated)
    }
    
    // Get today's h2h failed count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM h2h_transactions WHERE status = 'failed' AND DATE(created_at) = CURDATE()");
    $failed_count = $stmt->fetch()['count'];
    if ($failed_count > 0) {
        $menu_items[6]['badge'] = $failed_count; // H2H Monitor (index updated)
    }
} catch (PDOException $e) {
    // Ignore errors
}
?>

<!-- Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <a href="admin.php" class="sidebar-brand">
            <i class="fas fa-shield-alt"></i>
            <span class="sidebar-brand-text">Admin Panel</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Sidebar User -->
    <div class="sidebar-user">
        <img src="assets/img/avatars/<?php echo $user['avatar']; ?>.png" alt="Avatar" class="sidebar-user-avatar">
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($user['username']); ?></div>
            <div class="sidebar-user-role">
                <i class="fas fa-crown"></i> Administrator
            </div>
        </div>
    </div>

    <!-- Sidebar Menu -->
    <nav class="sidebar-menu">
        <div class="sidebar-menu-title">MAIN MENU</div>
        <?php foreach ($menu_items as $item): 
            $is_active = ($current_page == $item['page']) ? 'active' : '';
        ?>
        <a href="<?php echo $item['url']; ?>" class="sidebar-menu-item <?php echo $is_active; ?>">
            <i class="<?php echo $item['icon']; ?>"></i>
            <span class="sidebar-menu-text"><?php echo $item['title']; ?></span>
            <?php if ($item['badge']): ?>
            <span class="sidebar-badge"><?php echo $item['badge']; ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a href="/" class="sidebar-footer-item" target="_blank" title="View Website">
            <i class="fas fa-globe"></i>
            <span>View Website</span>
        </a>
        <a href="logout.php" class="sidebar-footer-item" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Sidebar Overlay (for mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Mobile Header -->
<header class="mobile-header">
    <button class="mobile-menu-btn" onclick="openSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="mobile-header-title">
        <i class="fas fa-shield-alt"></i> Admin Panel
    </div>
    <a href="profil.php" class="mobile-header-avatar">
        <img src="assets/img/avatars/<?php echo $user['avatar']; ?>.png" alt="Avatar">
    </a>
</header>

<script>
// Sidebar toggle functions
function openSidebar() {
    document.getElementById('adminSidebar').classList.add('active');
    document.getElementById('sidebarOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    document.getElementById('adminSidebar').classList.remove('active');
    document.getElementById('sidebarOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    if (sidebar.classList.contains('active')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

// Close sidebar on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
    }
});

// Close sidebar when clicking menu item on mobile
document.querySelectorAll('.sidebar-menu-item').forEach(item => {
    item.addEventListener('click', function() {
        if (window.innerWidth < 992) {
            setTimeout(closeSidebar, 300);
        }
    });
});
</script>