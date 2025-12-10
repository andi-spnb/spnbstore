<?php
require_once 'config.php';

// Admin check
if (!isLoggedIn()) {
    redirect('login.php');
}
$user = getUserData();

if ($user['is_admin'] != 1) {
    redirect('dashboard.php');
}

$log_file = __DIR__ . '/error.log';
$log_content = '';
$log_lines = [];

if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_lines = array_reverse(explode("\n", $log_content));
    $log_lines = array_filter($log_lines); // Remove empty lines
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Log - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-nav {
            background: rgba(99, 102, 241, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .admin-nav a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
        }
        .admin-nav a:hover, .admin-nav a.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        .log-container {
            background: #1a1a1a;
            border: 1px solid var(--dark-border);
            border-radius: 0.75rem;
            padding: 1.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            max-height: 600px;
            overflow-y: auto;
            color: #00ff00;
        }
        .log-line {
            padding: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            word-break: break-all;
        }
        .log-line.error {
            color: #ff6b6b;
        }
        .log-line.warning {
            color: #ffd93d;
        }
        .log-line.info {
            color: #6bcf7f;
        }
        .empty-log {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="admin.php" class="navbar-brand">
            <i class="fas fa-shield-alt"></i> Admin Panel
        </a>
        
        <div class="navbar-actions">
            <a href="admin-settings.php" class="nav-icon" title="Settings">
                <i class="fas fa-cog"></i>
            </a>
            <a href="admin.php" class="nav-icon" title="Dashboard">
                <i class="fas fa-home"></i>
            </a>
            <a href="profil.php" class="avatar-btn">
                <img src="assets/img/avatars/<?php echo $user['avatar']; ?>.png" alt="Avatar">
            </a>
        </div>
    </nav>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">
                    <i class="fas fa-bug"></i> Error Log
                </h1>
                <p style="color: var(--text-muted);">Monitor dan troubleshoot error aplikasi</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button onclick="refreshLog()" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button onclick="clearLog()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Clear Log
                </button>
            </div>
        </div>

        <!-- Admin Navigation -->
        <div class="admin-nav">
            <a href="admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="admin-products.php"><i class="fas fa-box"></i> Produk</a>
            <a href="admin-transactions.php"><i class="fas fa-receipt"></i> Transaksi</a>
            <a href="admin-users.php"><i class="fas fa-users"></i> Users</a>
            <a href="admin-categories.php"><i class="fas fa-tags"></i> Kategori</a>
            <a href="admin-settings.php"><i class="fas fa-cog"></i> Settings</a>
        </div>

        <!-- Log Statistics -->
        <div class="grid grid-4" style="margin-bottom: 2rem;">
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📊</div>
                <div style="font-size: 1.5rem; font-weight: 700;"><?php echo count($log_lines); ?></div>
                <div style="color: var(--text-muted); font-size: 0.9rem;">Total Lines</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔴</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger-color);">
                    <?php echo count(array_filter($log_lines, function($line) { return stripos($line, 'error') !== false; })); ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.9rem;">Errors</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">⚠️</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning-color);">
                    <?php echo count(array_filter($log_lines, function($line) { return stripos($line, 'warning') !== false; })); ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.9rem;">Warnings</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📁</div>
                <div style="font-size: 1.5rem; font-weight: 700;">
                    <?php echo file_exists($log_file) ? round(filesize($log_file) / 1024, 2) : 0; ?> KB
                </div>
                <div style="color: var(--text-muted); font-size: 0.9rem;">File Size</div>
            </div>
        </div>

        <!-- Log Content -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; margin: 0;">
                    <i class="fas fa-file-alt"></i> Log Content
                </h2>
                <div style="color: var(--text-muted); font-size: 0.85rem;">
                    <?php echo file_exists($log_file) ? 'Last modified: ' . date('Y-m-d H:i:s', filemtime($log_file)) : 'No log file'; ?>
                </div>
            </div>

            <?php if (count($log_lines) > 0): ?>
            <div class="log-container" id="logContainer">
                <?php foreach ($log_lines as $index => $line): 
                    $class = '';
                    if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                        $class = 'error';
                    } elseif (stripos($line, 'warning') !== false) {
                        $class = 'warning';
                    } elseif (stripos($line, 'info') !== false || stripos($line, 'notice') !== false) {
                        $class = 'info';
                    }
                ?>
                <div class="log-line <?php echo $class; ?>">
                    <span style="color: var(--text-muted); margin-right: 1rem;">[<?php echo $index + 1; ?>]</span>
                    <?php echo htmlspecialchars($line); ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-log">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h3 style="margin-bottom: 0.5rem;">No Errors Found</h3>
                <p>Log file kosong atau tidak ada error yang tercatat.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="margin-top: 2rem;">
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-bolt"></i> Quick Actions
            </h2>
            <div class="grid grid-4">
                <a href="admin-settings.php" class="btn btn-secondary" style="padding: 1.5rem; text-decoration: none; text-align: center;">
                    <i class="fas fa-cog" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    <span>Settings</span>
                </a>
                <a href="backup-database.php" class="btn btn-secondary" style="padding: 1.5rem; text-decoration: none; text-align: center;">
                    <i class="fas fa-database" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    <span>Backup Database</span>
                </a>
                <button onclick="clearCache()" class="btn btn-secondary" style="padding: 1.5rem; text-align: center;">
                    <i class="fas fa-trash" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    <span>Clear Cache</span>
                </button>
                <a href="admin.php" class="btn btn-secondary" style="padding: 1.5rem; text-decoration: none; text-align: center;">
                    <i class="fas fa-home" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        function refreshLog() {
            location.reload();
        }

        function clearLog() {
            if (confirm('Apakah Anda yakin ingin clear error log? Backup akan dibuat secara otomatis.')) {
                fetch('clear-error-log.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.status === 'success') {
                            location.reload();
                        }
                    })
                    .catch(error => {
                        alert('Error: ' + error);
                    });
            }
        }

        function clearCache() {
            if (confirm('Apakah Anda yakin ingin clear cache?')) {
                fetch('clear-cache.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                    })
                    .catch(error => {
                        alert('Error: ' + error);
                    });
            }
        }

        // Auto-scroll to bottom of log
        document.addEventListener('DOMContentLoaded', function() {
            const logContainer = document.getElementById('logContainer');
            if (logContainer) {
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>