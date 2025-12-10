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

// Page Configuration
$active_page = 'admin-hero-slider';
$page_title = 'Hero Slider Management';
$page_subtitle = 'Kelola gambar slider di homepage';
$page_icon = 'fas fa-images';

$message = '';
$message_type = 'success';

// Handle Add Hero Slider
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_slider'])) {
    $title = trim($_POST['title']);
    $subtitle = trim($_POST['subtitle']);
    $image_url = trim($_POST['image_url']);
    $button_text = trim($_POST['button_text']);
    $button_link = trim($_POST['button_link']);
    $sort_order = intval($_POST['sort_order']);
    
    try {
        $stmt = $conn->prepare("INSERT INTO hero_sliders (title, subtitle, image_url, button_text, button_link, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $subtitle, $image_url, $button_text, $button_link, $sort_order]);
        
        $message = 'Hero slider berhasil ditambahkan!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Gagal menambahkan hero slider: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Update Hero Slider
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_slider'])) {
    $id = intval($_POST['slider_id']);
    $title = trim($_POST['title']);
    $subtitle = trim($_POST['subtitle']);
    $image_url = trim($_POST['image_url']);
    $button_text = trim($_POST['button_text']);
    $button_link = trim($_POST['button_link']);
    $sort_order = intval($_POST['sort_order']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        $stmt = $conn->prepare("UPDATE hero_sliders SET title = ?, subtitle = ?, image_url = ?, button_text = ?, button_link = ?, sort_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $subtitle, $image_url, $button_text, $button_link, $sort_order, $is_active, $id]);
        
        $message = 'Hero slider berhasil diupdate!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Gagal mengupdate hero slider: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Delete Hero Slider
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_slider'])) {
    $id = intval($_POST['slider_id']);
    
    try {
        $stmt = $conn->prepare("DELETE FROM hero_sliders WHERE id = ?");
        $stmt->execute([$id]);
        
        $message = 'Hero slider berhasil dihapus!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Gagal menghapus hero slider: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Toggle Active Status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_status'])) {
    $id = intval($_POST['slider_id']);
    
    try {
        $stmt = $conn->prepare("UPDATE hero_sliders SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        
        $message = 'Status slider berhasil diubah!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Gagal mengubah status: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all hero sliders
$stmt = $conn->query("SELECT * FROM hero_sliders ORDER BY sort_order ASC");
$hero_sliders = $stmt->fetchAll();

// Get statistics
$total_sliders = count($hero_sliders);
$active_sliders = count(array_filter($hero_sliders, function($s) { return $s['is_active'] == 1; }));
$inactive_sliders = $total_sliders - $active_sliders;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Variables */
        :root {
            --primary-color: #6366f1;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-bg: #1e293b;
            --dark-card: #0f172a;
            --dark-border: #334155;
            --text-primary: #f1f5f9;
            --text-muted: #94a3b8;
        }
        
        /* Modal Styles - CRITICAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex !important;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 1;
        }
        
        .modal-container {
            position: relative;
            background: var(--dark-card);
            border-radius: 1rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            z-index: 2;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--dark-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: var(--dark-bg);
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 2px solid var(--dark-border);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        /* Enhanced Slider Card Styles */
        .slider-card {
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            border: 2px solid var(--dark-border);
        }
        
        .slider-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
            border-color: var(--primary-color);
        }
        
        .slider-preview {
            width: 100%;
            height: 200px;
            background-size: cover;
            background-position: center;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .slider-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%);
        }
        
        .slider-preview-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            color: white;
            z-index: 2;
        }
        
        .slider-preview-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        
        .slider-preview-subtitle {
            font-size: 0.85rem;
            opacity: 0.9;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .slider-status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 3;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.85rem;
            backdrop-filter: blur(10px);
        }
        
        .slider-status-badge.active {
            background: rgba(16, 185, 129, 0.9);
            color: white;
        }
        
        .slider-status-badge.inactive {
            background: rgba(239, 68, 68, 0.9);
            color: white;
        }
        
        .slider-card-body {
            padding: 1.25rem;
        }
        
        .slider-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--dark-bg);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .slider-info-item {
            flex: 1;
            text-align: center;
        }
        
        .slider-info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        
        .slider-info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .slider-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        .slider-actions .btn {
            width: 100%;
            justify-content: center;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card-compact {
            background: var(--dark-card);
            border: 2px solid var(--dark-border);
            border-radius: 1rem;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s;
        }
        
        .stat-card-compact:hover {
            transform: translateY(-3px);
            border-color: var(--primary-color);
        }
        
        .stat-card-compact.primary {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border-color: rgba(99, 102, 241, 0.3);
        }
        
        .stat-card-compact.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .stat-card-compact.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .stat-icon-compact {
            width: 60px;
            height: 60px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon-compact.primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }
        
        .stat-icon-compact.success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .stat-icon-compact.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .stat-content-compact {
            flex: 1;
        }
        
        .stat-label-compact {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        
        .stat-value-compact {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        /* Modal Enhancements */
        .modal-container {
            max-width: 600px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .image-preview-box {
            width: 100%;
            height: 200px;
            background: var(--dark-bg);
            border: 2px dashed var(--dark-border);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .image-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview-placeholder {
            text-align: center;
            color: var(--text-muted);
        }
        
        /* Empty State Enhancement */
        .empty-state-enhanced {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state-icon-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: var(--primary-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .slider-actions {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-container {
                width: 95%;
                max-height: 95vh;
            }
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--dark-bg);
            transition: 0.3s;
            border-radius: 26px;
            border: 2px solid var(--dark-border);
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 2px;
            bottom: 2px;
            background-color: var(--text-muted);
            transition: 0.3s;
            border-radius: 50%;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
            background-color: white;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: var(--dark-bg);
            border: 2px solid var(--dark-border);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: var(--dark-card);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Badge Styles */
        .badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.5);
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.5);
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php require_once 'admin-sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-content">
        <div class="admin-content-inner">
            <!-- Admin Header -->
            <div class="admin-header">
                <div class="admin-header-content">
                    <h1>
                        <i class="<?php echo $page_icon; ?>"></i> <?php echo $page_title; ?>
                    </h1>
                    <p><?php echo $page_subtitle; ?></p>
                </div>
                <div class="admin-header-actions">
                    <button onclick="openAddModal()" class="btn btn-primary" type="button">
                        <i class="fas fa-plus"></i> Tambah Slider
                    </button>
                    <a href="/" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> Lihat Website
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>" id="alertMessage">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <div><?php echo htmlspecialchars($message); ?></div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; padding: 0.5rem;" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card-compact primary">
                    <div class="stat-icon-compact primary">
                        <i class="fas fa-images"></i>
                    </div>
                    <div class="stat-content-compact">
                        <div class="stat-label-compact">Total Sliders</div>
                        <div class="stat-value-compact"><?php echo $total_sliders; ?></div>
                    </div>
                </div>
                
                <div class="stat-card-compact success">
                    <div class="stat-icon-compact success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content-compact">
                        <div class="stat-label-compact">Active Sliders</div>
                        <div class="stat-value-compact"><?php echo $active_sliders; ?></div>
                    </div>
                </div>
                
                <div class="stat-card-compact danger">
                    <div class="stat-icon-compact danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content-compact">
                        <div class="stat-label-compact">Inactive Sliders</div>
                        <div class="stat-value-compact"><?php echo $inactive_sliders; ?></div>
                    </div>
                </div>
            </div>

            <!-- Hero Sliders Grid -->
            <?php if (count($hero_sliders) > 0): ?>
            <div class="grid grid-3 mb-4">
                <?php foreach ($hero_sliders as $slider): ?>
                <div class="card slider-card">
                    <div class="slider-preview" style="background-image: url('<?php echo htmlspecialchars($slider['image_url']); ?>');">
                        <span class="slider-status-badge <?php echo $slider['is_active'] ? 'active' : 'inactive'; ?>">
                            <i class="fas fa-<?php echo $slider['is_active'] ? 'check' : 'times'; ?>"></i>
                            <?php echo $slider['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                        <div class="slider-preview-overlay">
                            <div class="slider-preview-title"><?php echo htmlspecialchars($slider['title']); ?></div>
                            <div class="slider-preview-subtitle"><?php echo htmlspecialchars($slider['subtitle']); ?></div>
                        </div>
                    </div>
                    
                    <div class="slider-card-body">
                        <div class="slider-info">
                            <div class="slider-info-item">
                                <div class="slider-info-label">Sort Order</div>
                                <div class="slider-info-value">#<?php echo $slider['sort_order']; ?></div>
                            </div>
                            <div class="slider-info-item">
                                <div class="slider-info-label">Created</div>
                                <div class="slider-info-value" style="font-size: 0.85rem;">
                                    <?php echo date('d M', strtotime($slider['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($slider['button_text'])): ?>
                        <div style="background: var(--dark-bg); padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1rem; text-align: center;">
                            <i class="fas fa-mouse-pointer" style="color: var(--text-muted); margin-right: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: var(--text-muted);">
                                Button: "<?php echo htmlspecialchars($slider['button_text']); ?>"
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="slider-actions">
                            <button onclick="openEditModal(<?php echo $slider['id']; ?>)" class="btn btn-primary" type="button">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="toggleStatus(<?php echo $slider['id']; ?>)" class="btn btn-<?php echo $slider['is_active'] ? 'warning' : 'success'; ?>" type="button">
                                <i class="fas fa-<?php echo $slider['is_active'] ? 'eye-slash' : 'eye'; ?>"></i> 
                                <?php echo $slider['is_active'] ? 'Hide' : 'Show'; ?>
                            </button>
                            <button onclick="openDeleteModal(<?php echo $slider['id']; ?>)" class="btn btn-danger" type="button">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button onclick="openDetailsModal(<?php echo $slider['id']; ?>)" class="btn btn-secondary" type="button">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="empty-state-enhanced">
                    <div class="empty-state-icon-large">
                        <i class="fas fa-images"></i>
                    </div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Belum Ada Hero Slider</h3>
                    <p style="color: var(--text-muted); margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto;">
                        Tambahkan gambar slider untuk membuat homepage Anda lebih menarik dan professional. 
                        Slider akan berganti otomatis setiap 5 detik.
                    </p>
                    <button onclick="openAddModal()" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;" type="button">
                        <i class="fas fa-plus"></i> Tambah Slider Pertama
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Instructions -->
            <div class="card">
                <h2 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <span>Panduan Penggunaan</span>
                </h2>
                <div class="grid grid-2" style="gap: 1.5rem;">
                    <div>
                        <h3 style="font-size: 1rem; margin-bottom: 0.75rem; color: var(--primary-color);">
                            <i class="fas fa-check-circle"></i> Best Practices
                        </h3>
                        <ul style="color: var(--text-muted); line-height: 2;">
                            <li>Upload gambar dengan dimensi minimal <strong>1920x500 px</strong></li>
                            <li>Ukuran file maksimal <strong>500 KB</strong> (optimize dengan TinyPNG)</li>
                            <li>Gunakan Sort Order untuk mengatur urutan tampilan</li>
                            <li>Button text dan link bersifat opsional</li>
                            <li>Slider akan berganti otomatis setiap <strong>5 detik</strong></li>
                        </ul>
                    </div>
                    <div>
                        <h3 style="font-size: 1rem; margin-bottom: 0.75rem; color: var(--primary-color);">
                            <i class="fas fa-paint-brush"></i> Design Tips
                        </h3>
                        <ul style="color: var(--text-muted); line-height: 2;">
                            <li>Gunakan gambar berkualitas tinggi dengan kontras yang baik</li>
                            <li>Text harus mudah dibaca di atas gambar</li>
                            <li>Konsisten dengan brand colors website Anda</li>
                            <li>Highlight produk terlaris atau promo terbaru</li>
                            <li>Update slider secara berkala (minimal sebulan sekali)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Slider Modal -->
    <div id="addModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('addModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Tambah Hero Slider</h3>
                <button onclick="closeModal('addModal')" class="modal-close" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Title <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="contoh: Youtube Premium" required>
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">
                            <i class="fas fa-info-circle"></i> Judul utama yang akan ditampilkan di slider
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subtitle</label>
                        <textarea name="subtitle" class="form-control" rows="3" placeholder="Deskripsi singkat tentang produk atau promo..."></textarea>
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">
                            <i class="fas fa-info-circle"></i> Text tambahan yang muncul di bawah judul
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Image URL <span style="color: var(--danger-color);">*</span></label>
                        <input type="url" name="image_url" id="add_image_url" class="form-control" placeholder="https://example.com/image.jpg" required>
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">
                            <i class="fas fa-image"></i> Upload gambar ke hosting atau gunakan URL gambar
                        </small>
                        <div id="add_image_preview" class="image-preview-box">
                            <div class="image-preview-placeholder">
                                <i class="fas fa-image" style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                                <div>Preview gambar akan muncul di sini</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Button Text</label>
                            <input type="text" name="button_text" class="form-control" placeholder="Beli Sekarang">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Button Link</label>
                            <input type="text" name="button_link" class="form-control" placeholder="https://example.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">
                            <i class="fas fa-sort-numeric-up"></i> Angka kecil muncul duluan (0, 1, 2, ...)
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add_slider" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Slider
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Slider Modal -->
    <div id="editModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('editModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Hero Slider</h3>
                <button onclick="closeModal('editModal')" class="modal-close" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="slider_id" id="edit_slider_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Title <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subtitle</label>
                        <textarea name="subtitle" id="edit_subtitle" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Image URL <span style="color: var(--danger-color);">*</span></label>
                        <input type="url" name="image_url" id="edit_image_url" class="form-control" required>
                        <div id="edit_image_preview" class="image-preview-box">
                            <div class="image-preview-placeholder">
                                <i class="fas fa-image" style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                                <div>Preview gambar akan muncul di sini</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Button Text</label>
                            <input type="text" name="button_text" id="edit_button_text" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Button Link</label>
                            <input type="text" name="button_link" id="edit_button_link" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_sort_order" class="form-control" min="0">
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: 1rem; background: var(--dark-bg); border-radius: 0.5rem;">
                            <label class="toggle-switch">
                                <input type="checkbox" name="is_active" id="edit_is_active">
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="font-weight: 600; flex: 1;">
                                <i class="fas fa-eye"></i> Aktifkan Slider
                            </span>
                            <small style="color: var(--text-muted);">Tampilkan di homepage</small>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="update_slider" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Slider
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('detailsModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Slider Details</h3>
                <button onclick="closeModal('detailsModal')" class="modal-close" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="details_content">
                <!-- Content will be loaded by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('detailsModal')" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
        <div class="modal-container" style="max-width: 500px;">
            <div class="modal-header" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); border-bottom-color: rgba(239, 68, 68, 0.3);">
                <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i> Konfirmasi Hapus</h3>
                <button onclick="closeModal('deleteModal')" class="modal-close" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="slider_id" id="delete_slider_id">
                <div class="modal-body">
                    <div style="text-align: center; padding: 1rem 0;">
                        <div style="width: 80px; height: 80px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem; color: var(--danger-color);">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <p style="font-size: 1.1rem; margin-bottom: 1rem;">Apakah Anda yakin ingin menghapus slider:</p>
                        <p style="font-size: 1.3rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1.5rem;">
                            "<span id="delete_slider_title"></span>"?
                        </p>
                        <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 0.5rem; border-left: 4px solid var(--danger-color);">
                            <i class="fas fa-exclamation-circle" style="color: var(--danger-color);"></i>
                            <strong style="color: var(--danger-color);"> Peringatan:</strong>
                            <span style="color: var(--text-muted);"> Tindakan ini tidak dapat dibatalkan!</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('deleteModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="delete_slider" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Ya, Hapus Slider
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Status Form (Hidden) -->
    <form method="POST" action="" id="toggleForm" style="display: none;">
        <input type="hidden" name="slider_id" id="toggle_slider_id">
        <input type="hidden" name="toggle_status" value="1">
    </form>

    <script src="assets/js/main.js"></script>
    <script>
        // Store slider data for JavaScript functions
        var sliders = <?php echo json_encode($hero_sliders); ?>;
        
        console.log('Sliders loaded:', sliders);
        
        // Modal functions
        function openModal(modalId) {
            console.log('Opening modal:', modalId);
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(modalId) {
            console.log('Closing modal:', modalId);
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        // Add modal
        function openAddModal() {
            console.log('Opening add modal');
            document.getElementById('add_image_url').value = '';
            document.getElementById('add_image_preview').innerHTML = '<div class="image-preview-placeholder"><i class="fas fa-image" style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.3;"></i><div>Preview gambar akan muncul di sini</div></div>';
            openModal('addModal');
        }

        // Edit modal
        function openEditModal(sliderId) {
            console.log('Opening edit modal for ID:', sliderId);
            var slider = null;
            for (var i = 0; i < sliders.length; i++) {
                if (sliders[i].id == sliderId) {
                    slider = sliders[i];
                    break;
                }
            }
            
            if (!slider) {
                console.error('Slider not found:', sliderId);
                alert('Slider tidak ditemukan!');
                return;
            }
            
            console.log('Found slider:', slider);
            
            document.getElementById('edit_slider_id').value = slider.id;
            document.getElementById('edit_title').value = slider.title;
            document.getElementById('edit_subtitle').value = slider.subtitle || '';
            document.getElementById('edit_image_url').value = slider.image_url;
            document.getElementById('edit_button_text').value = slider.button_text || '';
            document.getElementById('edit_button_link').value = slider.button_link || '';
            document.getElementById('edit_sort_order').value = slider.sort_order;
            document.getElementById('edit_is_active').checked = slider.is_active == 1;
            
            // Preview image
            if (slider.image_url) {
                document.getElementById('edit_image_preview').innerHTML = '<img src="' + slider.image_url + '" alt="Preview">';
            }
            
            openModal('editModal');
        }

        // Details modal
        function openDetailsModal(sliderId) {
            console.log('Opening details modal for ID:', sliderId);
            var slider = null;
            for (var i = 0; i < sliders.length; i++) {
                if (sliders[i].id == sliderId) {
                    slider = sliders[i];
                    break;
                }
            }
            
            if (!slider) {
                console.error('Slider not found:', sliderId);
                alert('Slider tidak ditemukan!');
                return;
            }
            
            var content = '<div style="display: grid; gap: 1.5rem;">' +
                '<div><img src="' + slider.image_url + '" style="width: 100%; border-radius: 0.5rem;"></div>' +
                '<div style="background: var(--dark-bg); padding: 1rem; border-radius: 0.5rem;">' +
                '<strong style="color: var(--primary-color);">Title:</strong><br><span>' + slider.title + '</span></div>' +
                '<div style="background: var(--dark-bg); padding: 1rem; border-radius: 0.5rem;">' +
                '<strong style="color: var(--primary-color);">Subtitle:</strong><br><span>' + (slider.subtitle || '-') + '</span></div>' +
                '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">' +
                '<div style="background: var(--dark-bg); padding: 1rem; border-radius: 0.5rem;">' +
                '<strong style="color: var(--primary-color);">Button Text:</strong><br><span>' + (slider.button_text || '-') + '</span></div>' +
                '<div style="background: var(--dark-bg); padding: 1rem; border-radius: 0.5rem;">' +
                '<strong style="color: var(--primary-color);">Sort Order:</strong><br><span>#' + slider.sort_order + '</span></div></div>' +
                '<div style="background: var(--dark-bg); padding: 1rem; border-radius: 0.5rem;">' +
                '<strong style="color: var(--primary-color);">Button Link:</strong><br>' +
                '<span>' + (slider.button_link || '-') + '</span></div>' +
                '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">' +
                '<div style="background: var(--dark-bg); padding: 1rem; border-radius: 0.5rem; text-align: center;">' +
                '<strong style="color: var(--primary-color);">Status</strong><br>' +
                '<span class="badge badge-' + (slider.is_active ? 'success' : 'danger') + '" style="margin-top: 0.5rem;">' + (slider.is_active ? 'Active' : 'Inactive') + '</span></div>' +
                '<div style="background: var(--dark-bg); padding: 1rem; border-radius: 0.5rem; text-align: center;">' +
                '<strong style="color: var(--primary-color);">Created</strong><br><small>' + slider.created_at + '</small></div></div></div>';
            
            document.getElementById('details_content').innerHTML = content;
            openModal('detailsModal');
        }

        // Delete modal
        function openDeleteModal(sliderId) {
            console.log('Opening delete modal for ID:', sliderId);
            var slider = null;
            for (var i = 0; i < sliders.length; i++) {
                if (sliders[i].id == sliderId) {
                    slider = sliders[i];
                    break;
                }
            }
            
            if (!slider) {
                console.error('Slider not found:', sliderId);
                alert('Slider tidak ditemukan!');
                return;
            }
            
            document.getElementById('delete_slider_id').value = slider.id;
            document.getElementById('delete_slider_title').textContent = slider.title;
            openModal('deleteModal');
        }

        // Toggle status
        function toggleStatus(sliderId) {
            console.log('Toggling status for ID:', sliderId);
            if (confirm('Apakah Anda yakin ingin mengubah status slider ini?')) {
                document.getElementById('toggle_slider_id').value = sliderId;
                document.getElementById('toggleForm').submit();
            }
        }

        // Image preview for add
        document.getElementById('add_image_url').addEventListener('change', function() {
            var url = this.value;
            var preview = document.getElementById('add_image_preview');
            if (url) {
                preview.innerHTML = '<img src="' + url + '" alt="Preview" onerror="this.parentElement.innerHTML=\'<div class=&quot;image-preview-placeholder&quot;><i class=&quot;fas fa-exclamation-triangle&quot; style=&quot;font-size: 3rem; color: var(--danger-color); opacity: 0.3;&quot;></i><div>Gagal memuat gambar</div></div>\'">';
            } else {
                preview.innerHTML = '<div class="image-preview-placeholder"><i class="fas fa-image" style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.3;"></i><div>Preview gambar akan muncul di sini</div></div>';
            }
        });

        // Image preview for edit
        document.getElementById('edit_image_url').addEventListener('change', function() {
            var url = this.value;
            var preview = document.getElementById('edit_image_preview');
            if (url) {
                preview.innerHTML = '<img src="' + url + '" alt="Preview" onerror="this.parentElement.innerHTML=\'<div class=&quot;image-preview-placeholder&quot;><i class=&quot;fas fa-exclamation-triangle&quot; style=&quot;font-size: 3rem; color: var(--danger-color); opacity: 0.3;&quot;></i><div>Gagal memuat gambar</div></div>\'">';
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var modals = document.querySelectorAll('.modal.active');
                modals.forEach(function(modal) {
                    closeModal(modal.id);
                });
            }
        });

        // Auto-hide success message
        setTimeout(function() {
            var alert = document.getElementById('alertMessage');
            if (alert && alert.classList.contains('alert-success')) {
                alert.style.transition = 'opacity 0.5s, transform 0.5s';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(function() { 
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 500);
            }
        }, 5000);
    </script>
</body>
</html>