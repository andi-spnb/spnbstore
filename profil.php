<?php
require_once 'config.php';

// Check if google auth helper exists
if (file_exists('google-auth-helper.php')) {
    require_once 'google-auth-helper.php';
}

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();
$success = '';
$error = '';

// Check session messages
if (isset($_SESSION['profile_success'])) {
    $success = $_SESSION['profile_success'];
    unset($_SESSION['profile_success']);
}

if (isset($_SESSION['profile_error'])) {
    $error = $_SESSION['profile_error'];
    unset($_SESSION['profile_error']);
}

// Available avatars
$avatars = ['cap-back', 'cute', 'headset', 'king', 'truck-hat'];

// Get user statistics
$stats = [
    'total_transactions' => 0,
    'total_spent' => 0,
    'member_since' => $user['created_at'] ?? date('Y-m-d')
];

try {
    // Total transactions
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as spent FROM transactions WHERE user_id = ? AND status = 'success'");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $stats['total_transactions'] = $result['total'] ?? 0;
    $stats['total_spent'] = $result['spent'] ?? 0;
} catch (Exception $e) {
    // Silent fail
}

// Handle avatar update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_avatar'])) {
    $avatar = $_POST['avatar'] ?? '';
    
    if (in_array($avatar, $avatars)) {
        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt->execute([$avatar, $user['id']])) {
            $_SESSION['profile_success'] = 'Avatar berhasil diperbarui!';
            header("Location: profil.php");
            exit;
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $whatsapp = trim($_POST['whatsapp']);
    $email = trim($_POST['email']);
    
    if (empty($nama_lengkap) || empty($email)) {
        $error = 'Nama lengkap dan email harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } else {
        // Check if email already used by other user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            $error = 'Email sudah digunakan oleh akun lain!';
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, whatsapp = ?, email = ? WHERE id = ?");
            if ($stmt->execute([$nama_lengkap, $whatsapp, $email, $user['id']])) {
                $_SESSION['profile_success'] = 'Profil berhasil diperbarui!';
                header("Location: profil.php");
                exit;
            } else {
                $error = 'Gagal memperbarui profil!';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Check if user has Google login only (no password set)
    $hasGoogleOnly = !empty($user['google_id']) && (empty($user['password']) || $user['password'] === '');
    
    if ($hasGoogleOnly) {
        // User only has Google login, allow setting new password without old password
        if (empty($new_password) || empty($confirm_password)) {
            $error = 'Password baru dan konfirmasi harus diisi!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru dan konfirmasi tidak sama!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password minimal 6 karakter!';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user['id']])) {
                $_SESSION['profile_success'] = 'Password berhasil dibuat! Sekarang Anda bisa login dengan email & password.';
                header("Location: profil.php");
                exit;
            } else {
                $error = 'Gagal membuat password!';
            }
        }
    } else {
        // Normal password change flow
        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Semua field password harus diisi!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru dan konfirmasi tidak sama!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password minimal 6 karakter!';
        } elseif (!password_verify($old_password, $user['password'])) {
            $error = 'Password lama salah!';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user['id']])) {
                $_SESSION['profile_success'] = 'Password berhasil diubah!';
                header("Location: profil.php");
                exit;
            } else {
                $error = 'Gagal mengubah password!';
            }
        }
    }
}

// Handle Google unlink
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unlink_google'])) {
    // Check if user has password set
    if (empty($user['password'])) {
        $error = 'Anda harus membuat password terlebih dahulu sebelum memutus koneksi Google!';
    } else {
        $stmt = $conn->prepare("UPDATE users SET google_id = NULL, google_avatar = NULL WHERE id = ?");
        if ($stmt->execute([$user['id']])) {
            $_SESSION['profile_success'] = 'Akun Google berhasil diputus!';
            header("Location: profil.php");
            exit;
        } else {
            $error = 'Gagal memutus koneksi Google!';
        }
    }
}

// Refresh user data
$user = getUserData();

// Check if user has Google linked
$hasGoogleLinked = !empty($user['google_id']);
$hasPassword = !empty($user['password']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php include 'meta-tags.php'; ?>
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --bg-hover: #3b4a63;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #475569;
            --primary-color: #8b5cf6;
            --primary-hover: #7c3aed;
            --primary-glow: rgba(139, 92, 246, 0.3);
            --success-color: #10b981;
            --success-bg: rgba(16, 185, 129, 0.1);
            --danger-color: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.1);
            --warning-color: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.1);
            --info-color: #3b82f6;
            --info-bg: rgba(59, 130, 246, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Container */
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .profile-container {
                padding: 2rem;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            background: linear-gradient(135deg, var(--primary-color), #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.9rem;
        }

        @media (min-width: 768px) {
            .page-header {
                margin-bottom: 2rem;
            }
            .page-header h1 {
                font-size: 2rem;
            }
            .page-header p {
                font-size: 1rem;
            }
        }

        /* User Summary Card */
        .user-summary {
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            border: 1px solid var(--border-color);
        }

        @media (min-width: 640px) {
            .user-summary {
                flex-direction: row;
                align-items: center;
                padding: 2rem;
            }
        }

        .user-avatar-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar-display img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            box-shadow: 0 0 20px var(--primary-glow);
        }

        @media (min-width: 640px) {
            .user-avatar-display img {
                width: 100px;
                height: 100px;
            }
        }

        .user-info {
            flex: 1;
            text-align: center;
        }

        @media (min-width: 640px) {
            .user-info {
                text-align: left;
            }
        }

        .user-info h2 {
            font-size: 1.25rem;
            margin: 0 0 0.25rem 0;
            color: var(--text-primary);
        }

        .user-info .username {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .user-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        @media (min-width: 640px) {
            .user-badges {
                justify-content: flex-start;
            }
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-google {
            background: var(--info-bg);
            color: var(--info-color);
            border: 1px solid var(--info-color);
        }

        .badge-verified {
            background: var(--success-bg);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .badge-member {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        /* Stats Grid */
        .user-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            width: 100%;
        }

        @media (min-width: 640px) {
            .user-stats {
                width: auto;
                min-width: 280px;
            }
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: var(--bg-primary);
            border-radius: 0.75rem;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            display: block;
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (min-width: 640px) {
            .stat-value {
                font-size: 1.5rem;
            }
            .stat-label {
                font-size: 0.75rem;
            }
        }

        /* Grid Layout */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 768px) {
            .profile-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }

            .profile-grid .full-width {
                grid-column: 1 / -1;
            }
        }

        /* Card Styles */
        .profile-card {
            background: var(--bg-secondary);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .profile-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.1);
        }

        /* Card Header */
        .card-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h3 {
            font-size: 1.125rem;
            color: var(--text-primary);
            margin: 0 0 0.375rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h3 i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        .card-header p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
        }

        /* Avatar Grid */
        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }

        @media (min-width: 480px) {
            .avatar-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        .avatar-option {
            position: relative;
            cursor: pointer;
            border-radius: 0.75rem;
            padding: 0.75rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background: var(--bg-tertiary);
        }

        .avatar-option:hover {
            transform: translateY(-3px);
            border-color: var(--border-color);
        }

        .avatar-option.active {
            border-color: var(--primary-color);
            background: rgba(139, 92, 246, 0.1);
        }

        .avatar-option img {
            width: 100%;
            max-width: 50px;
            height: auto;
            border-radius: 50%;
            margin: 0 auto;
            display: block;
        }

        @media (min-width: 480px) {
            .avatar-option img {
                max-width: 60px;
            }
        }

        .avatar-check {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background: var(--primary-color);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-check i {
            color: white;
            font-size: 0.6rem;
        }

        .avatar-label {
            margin-top: 0.5rem;
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .form-label .required {
            color: var(--danger-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .form-control:disabled {
            background: var(--bg-primary);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-hint {
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-top: 0.375rem;
            display: block;
        }

        /* Password Wrapper */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 3rem;
        }

        .password-toggle {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0 1rem;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px var(--primary-glow);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: var(--bg-tertiary);
            border-color: var(--text-muted);
        }

        .btn-google {
            background: #ffffff;
            color: #3c4043;
            border: 1px solid #dadce0;
        }

        .btn-google:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .btn-full {
            width: 100%;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Alert Styles */
        .alert {
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.875rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: var(--success-bg);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .alert-error {
            background: var(--danger-bg);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }

        .alert i {
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        /* Google Section */
        .google-account-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-tertiary);
            border-radius: 0.75rem;
            margin-bottom: 1rem;
        }

        .google-account-info img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
        }

        .google-account-info .info {
            flex: 1;
        }

        .google-account-info .email {
            font-weight: 600;
            color: var(--text-primary);
        }

        .google-account-info .status {
            font-size: 0.8rem;
            color: var(--success-color);
        }

        /* Logout Section */
        .logout-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .logout-section {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .logout-info h4 {
            margin: 0 0 0.375rem 0;
            font-size: 1rem;
            color: var(--text-primary);
        }

        .logout-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Scrolling */
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="profile-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-user-cog"></i> Pengaturan Profil</h1>
            <p>Kelola informasi akun dan preferensi Anda</p>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- User Summary -->
        <div class="user-summary">
            <div class="user-avatar-display">
                <?php 
                $avatarSrc = 'assets/img/avatars/' . ($user['avatar'] ?? 'cute') . '.png';
                if (!empty($user['google_avatar'])) {
                    $avatarSrc = $user['google_avatar'];
                }
                ?>
                <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Avatar">
            </div>
            
            <div class="user-info">
                <h2><?php echo htmlspecialchars($user['nama_lengkap'] ?? $user['username']); ?></h2>
                <div class="username">@<?php echo htmlspecialchars($user['username']); ?></div>
                <div class="user-badges">
                    <?php if ($hasGoogleLinked): ?>
                    <span class="badge badge-google">
                        <i class="fab fa-google"></i> Google Connected
                    </span>
                    <?php endif; ?>
                    <span class="badge badge-member">
                        <i class="fas fa-calendar"></i> 
                        Member sejak <?php echo date('M Y', strtotime($stats['member_since'])); ?>
                    </span>
                </div>
            </div>

            <div class="user-stats">
                <div class="stat-item">
                    <span class="stat-value"><?php echo formatRupiah($user['balance'] ?? 0); ?></span>
                    <span class="stat-label">Saldo</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $stats['total_transactions']; ?></span>
                    <span class="stat-label">Transaksi</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo formatRupiah($stats['total_spent']); ?></span>
                    <span class="stat-label">Total Belanja</span>
                </div>
            </div>
        </div>

        <div class="profile-grid">
            <!-- Avatar Selection -->
            <div class="profile-card">
                <div class="card-header">
                    <h3><i class="fas fa-image"></i> Pilih Avatar</h3>
                    <p>Klik avatar untuk mengubah</p>
                </div>
                
                <form method="POST" id="avatarForm">
                    <input type="hidden" name="update_avatar" value="1">
                    <div class="avatar-grid">
                        <?php foreach ($avatars as $avatar_name): ?>
                        <label class="avatar-option <?php echo ($user['avatar'] ?? '') == $avatar_name ? 'active' : ''; ?>">
                            <input type="radio" name="avatar" value="<?php echo htmlspecialchars($avatar_name); ?>" 
                                   <?php echo ($user['avatar'] ?? '') == $avatar_name ? 'checked' : ''; ?> 
                                   style="display: none;">
                            <img src="assets/img/avatars/<?php echo htmlspecialchars($avatar_name); ?>.png" 
                                 alt="<?php echo ucfirst($avatar_name); ?>" loading="lazy">
                            <?php if (($user['avatar'] ?? '') == $avatar_name): ?>
                            <div class="avatar-check">
                                <i class="fas fa-check"></i>
                            </div>
                            <?php endif; ?>
                            <div class="avatar-label">
                                <?php echo ucwords(str_replace('-', ' ', $avatar_name)); ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>

            <!-- Google Account -->
            <?php if (defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID)): ?>
            <div class="profile-card">
                <div class="card-header">
                    <h3><i class="fab fa-google"></i> Akun Google</h3>
                    <p>Hubungkan akun Google untuk login lebih mudah</p>
                </div>
                
                <?php if ($hasGoogleLinked): ?>
                <div class="google-account-info">
                    <?php if (!empty($user['google_avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['google_avatar']); ?>" alt="Google Avatar">
                    <?php else: ?>
                    <div style="width: 48px; height: 48px; background: var(--bg-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fab fa-google" style="font-size: 1.5rem; color: var(--primary-color);"></i>
                    </div>
                    <?php endif; ?>
                    <div class="info">
                        <div class="email"><?php echo htmlspecialchars($user['email']); ?></div>
                        <div class="status"><i class="fas fa-check-circle"></i> Terhubung</div>
                    </div>
                </div>
                
                <?php if ($hasPassword): ?>
                <form method="POST" onsubmit="return confirm('Yakin ingin memutus koneksi Google? Anda tetap bisa login dengan email & password.')">
                    <input type="hidden" name="unlink_google" value="1">
                    <button type="submit" class="btn btn-outline btn-full">
                        <i class="fas fa-unlink"></i> Putuskan Koneksi Google
                    </button>
                </form>
                <?php else: ?>
                <div style="padding: 0.75rem; background: var(--warning-bg); border-radius: 0.5rem; font-size: 0.85rem; color: var(--warning-color);">
                    <i class="fas fa-info-circle"></i> Buat password terlebih dahulu sebelum memutus koneksi Google.
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <a href="<?php echo function_exists('getGoogleAuthUrl') ? getGoogleAuthUrl() : '#'; ?>" class="btn btn-google btn-full">
                    <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                        <g fill="none" fill-rule="evenodd">
                            <path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                            <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z" fill="#34A853"/>
                            <path d="M3.964 10.71A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                            <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
                        </g>
                    </svg>
                    Hubungkan Akun Google
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Profile Information -->
            <div class="profile-card">
                <div class="card-header">
                    <h3><i class="fas fa-id-card"></i> Informasi Profil</h3>
                    <p>Perbarui informasi akun Anda</p>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <small class="form-hint">Username tidak dapat diubah</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control" 
                               value="<?php echo htmlspecialchars($user['nama_lengkap'] ?? ''); ?>" 
                               placeholder="Masukkan nama lengkap" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               placeholder="email@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">WhatsApp</label>
                        <input type="tel" name="whatsapp" class="form-control" 
                               placeholder="08xxxxxxxxxx" 
                               value="<?php echo htmlspecialchars($user['whatsapp'] ?? ''); ?>">
                        <small class="form-hint">Untuk notifikasi transaksi</small>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-card">
                <div class="card-header">
                    <h3><i class="fas fa-lock"></i> <?php echo $hasPassword ? 'Ubah Password' : 'Buat Password'; ?></h3>
                    <p><?php echo $hasPassword ? 'Pastikan menggunakan password yang kuat' : 'Buat password untuk login dengan email'; ?></p>
                </div>
                
                <form method="POST" id="passwordForm">
                    <?php if ($hasPassword): ?>
                    <div class="form-group">
                        <label class="form-label">Password Saat Ini <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="old_password" id="oldPassword" 
                                   class="form-control" placeholder="Masukkan password lama">
                            <button type="button" class="password-toggle" onclick="togglePassword('oldPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Password Baru <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" id="newPassword" 
                                   class="form-control" placeholder="Minimal 6 karakter">
                            <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-hint">Minimal 6 karakter</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirmPassword" 
                                   class="form-control" placeholder="Ketik ulang password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> <?php echo $hasPassword ? 'Ubah Password' : 'Buat Password'; ?>
                    </button>
                </form>
            </div>

            <!-- Logout Section -->
            <div class="profile-card full-width">
                <div class="logout-section">
                    <div class="logout-info">
                        <h4><i class="fas fa-sign-out-alt"></i> Keluar dari Akun</h4>
                        <p>Keluar dari sesi akun Anda di perangkat ini</p>
                    </div>
                    <a href="logout.php" class="btn btn-danger" onclick="return confirm('Yakin ingin keluar?')">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Avatar selection with auto-submit
        document.querySelectorAll('.avatar-option').forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all
                document.querySelectorAll('.avatar-option').forEach(opt => {
                    opt.classList.remove('active');
                    const check = opt.querySelector('.avatar-check');
                    if (check) check.remove();
                });
                
                // Add active to clicked
                this.classList.add('active');
                this.querySelector('input[type="radio"]').checked = true;
                
                // Add check icon
                const checkDiv = document.createElement('div');
                checkDiv.className = 'avatar-check';
                checkDiv.innerHTML = '<i class="fas fa-check"></i>';
                this.appendChild(checkDiv);
                
                // Auto submit form
                setTimeout(() => {
                    document.getElementById('avatarForm').submit();
                }, 300);
            });
        });

        // Password toggle
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-hide alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });

        // Form validation
        document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Password baru dan konfirmasi tidak sama!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password minimal 6 karakter!');
                return false;
            }
        });

        // Prevent double submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    setTimeout(() => button.disabled = false, 3000);
                }
            });
        });
    </script>
</body>
</html>