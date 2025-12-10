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
$active_page = 'products';
$page_title = 'Kelola Produk';
$page_subtitle = 'Tambah, edit, dan hapus produk digital';
$page_icon = 'fas fa-box';

$message = '';
$message_type = 'success';

// Create uploads directory if not exists
$upload_dir = 'assets/img/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Function to handle image upload
function handleImageUpload($file, $old_image = null) {
    global $upload_dir;
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error']);
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Tipe file tidak diizinkan. Gunakan JPG, PNG, GIF, atau WebP');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 5MB');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal menyimpan file');
    }
    
    // Delete old image if exists
    if ($old_image && file_exists($upload_dir . $old_image)) {
        unlink($upload_dir . $old_image);
    }
    
    return $filename;
}

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    try {
        $nama = trim($_POST['nama']);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'])));
        $product_code = !empty($_POST['product_code']) ? trim($_POST['product_code']) : null;
        $category_id = intval($_POST['category_id']);
        $deskripsi = trim($_POST['deskripsi']);
        $harga = floatval($_POST['harga']);
        $stok = intval($_POST['stok']);
        $tipe_produk = $_POST['tipe_produk'];
        
        if (!empty($nama) && !empty($slug) && $category_id > 0 && $harga > 0) {
            $stmt = $conn->prepare("SELECT id FROM products WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                throw new Exception('Slug sudah digunakan! Gunakan slug yang berbeda.');
            }
            
            // Handle image upload
            $gambar = null;
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $gambar = handleImageUpload($_FILES['gambar']);
            }
            
            $stmt = $conn->prepare("INSERT INTO products (category_id, nama, slug, product_code, deskripsi, harga, stok, tipe_produk, gambar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$category_id, $nama, $slug, $product_code, $deskripsi, $harga, $stok, $tipe_produk, $gambar])) {
                $message = 'Produk berhasil ditambahkan!';
                $message_type = 'success';
            } else {
                throw new Exception('Gagal menambahkan produk!');
            }
        } else {
            throw new Exception('Mohon lengkapi semua field yang wajib diisi!');
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Update Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    try {
        $product_id = intval($_POST['product_id']);
        $nama = trim($_POST['nama']);
        $product_code = !empty($_POST['product_code']) ? trim($_POST['product_code']) : null;
        $category_id = intval($_POST['category_id']);
        $deskripsi = trim($_POST['deskripsi']);
        $harga = floatval($_POST['harga']);
        $stok = intval($_POST['stok']);
        $tipe_produk = $_POST['tipe_produk'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("SELECT gambar FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $current_product = $stmt->fetch();
        $old_image = $current_product['gambar'];
        $gambar = $old_image;
        
        // Check if user wants to delete image
        if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
            if ($old_image && file_exists($upload_dir . $old_image)) {
                unlink($upload_dir . $old_image);
            }
            $gambar = null;
        }
        // Check if new image is uploaded
        elseif (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $gambar = handleImageUpload($_FILES['gambar'], $old_image);
        }
        
        $stmt = $conn->prepare("UPDATE products SET category_id = ?, nama = ?, product_code = ?, deskripsi = ?, harga = ?, stok = ?, tipe_produk = ?, is_active = ?, gambar = ? WHERE id = ?");
        if ($stmt->execute([$category_id, $nama, $product_code, $deskripsi, $harga, $stok, $tipe_produk, $is_active, $gambar, $product_id])) {
            $message = 'Produk berhasil diupdate!';
            $message_type = 'success';
        } else {
            throw new Exception('Gagal mengupdate produk!');
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Delete Product
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $product_id = intval($_GET['delete']);
    
    // Get product image
    $stmt = $conn->prepare("SELECT gambar FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    // Delete product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$product_id])) {
        // Delete image file if exists
        if ($product && $product['gambar'] && file_exists($upload_dir . $product['gambar'])) {
            unlink($upload_dir . $product['gambar']);
        }
        $message = 'Produk berhasil dihapus!';
        $message_type = 'success';
    }
}

// Get all products with filters
$filter_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT p.*, c.nama as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if ($filter_category > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $filter_category;
}

if ($filter_status == 'active') {
    $query .= " AND p.is_active = 1";
} elseif ($filter_status == 'inactive') {
    $query .= " AND p.is_active = 0";
}

if (!empty($search)) {
    $query .= " AND (p.nama LIKE ? OR p.slug LIKE ? OR p.product_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for dropdown
$stmt = $conn->query("SELECT * FROM categories ORDER BY nama ASC");
$categories = $stmt->fetchAll();

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM products");
$total_count = $stmt->fetch()['total'];
$stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
$active_count = $stmt->fetch()['total'];
$stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE is_active = 0");
$inactive_count = $stmt->fetch()['total'];
$stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE stok < 10");
$low_stock_count = $stmt->fetch()['total'];
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
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        .modal.active {
            display: block;
        }
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }
        .modal-container {
            position: relative;
            background: var(--dark-card);
            max-width: 600px;
            width: 90%;
            margin: 2rem auto;
            border-radius: 1rem;
            z-index: 10000;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--dark-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--dark-card);
            z-index: 10;
        }
        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.5rem;
            padding: 0.25rem;
            transition: color 0.3s;
        }
        .modal-close:hover {
            color: var(--danger-color);
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--dark-border);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            position: sticky;
            bottom: 0;
            background: var(--dark-card);
        }
        
        /* Image Upload Styles */
        .image-upload-container {
            border: 2px dashed var(--dark-border);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: var(--dark-bg);
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            margin-bottom: 1rem;
        }
        
        .image-upload-container:hover {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.05);
        }
        
        .image-upload-container.has-image {
            padding: 0;
            border: 2px solid var(--dark-border);
        }
        
        .image-upload-label {
            cursor: pointer;
            display: block;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 12px;
            display: none;
            margin: 0 auto;
        }
        
        .image-preview.active {
            display: block;
        }
        
        .upload-placeholder {
            color: var(--text-muted);
        }
        
        .upload-placeholder i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: block;
        }
        
        .image-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
        }
        
        .product-image-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--dark-border);
        }
        
        .no-image-placeholder {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--dark-bg);
            border: 2px dashed var(--dark-border);
            border-radius: 8px;
            color: var(--text-muted);
            font-size: 1.5rem;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .filter-bar > * {
            flex: 1;
            min-width: 150px;
        }
        .filter-bar .btn {
            flex: 0;
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .modal-container {
                width: 95%;
                margin: 1rem auto;
            }
            .modal-footer {
                flex-direction: column;
            }
            .modal-footer .btn {
                width: 100%;
            }
            .action-buttons {
                flex-direction: column;
            }
            .action-buttons .btn {
                width: 100%;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php require_once 'admin-sidebar.php'; ?>
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
                <button onclick="openAddModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Produk
                </button>
                <a href="admin-categories.php" class="btn btn-secondary">
                    <i class="fas fa-tags"></i> Kategori
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <div><?php echo htmlspecialchars($message); ?></div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-4 mb-4">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📦</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Total Produk</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?php echo number_format($total_count); ?></div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">✅</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Produk Aktif</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?php echo number_format($active_count); ?></div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">❌</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Tidak Aktif</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--danger-color);"><?php echo number_format($inactive_count); ?></div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">⚠️</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Stok Rendah</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);"><?php echo number_format($low_stock_count); ?></div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card mb-4">
            <form method="GET" class="filter-bar">
                <input type="text" name="search" class="form-control" placeholder="Cari produk..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="category" class="form-control">
                    <option value="0">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nama']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" class="form-control">
                    <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                    <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                </select>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <a href="admin-products.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </form>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h2 style="font-size: 1.5rem; margin: 0;">
                    <i class="fas fa-list"></i> Daftar Produk
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">
                        (<?php echo count($products); ?> produk)
                    </span>
                </h2>
            </div>

            <?php if (count($products) > 0): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="60">Gambar</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['gambar'] && file_exists($upload_dir . $product['gambar'])): ?>
                                    <img src="<?php echo $upload_dir . $product['gambar']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['nama']); ?>"
                                         class="product-image-thumb">
                                <?php else: ?>
                                    <div class="no-image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($product['nama']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    <i class="fas fa-link"></i> <?php echo htmlspecialchars($product['slug']); ?>
                                </div>
                                <?php if ($product['product_code']): ?>
                                <div style="font-size: 0.8rem; color: var(--primary-color);">
                                    <i class="fas fa-code"></i> <?php echo htmlspecialchars($product['product_code']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: var(--success-color);">
                                <?php echo formatRupiah($product['harga']); ?>
                            </td>
                            <td>
                                <?php if ($product['stok'] < 10): ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo $product['stok']; ?>
                                </span>
                                <?php else: ?>
                                <span class="badge badge-success"><?php echo $product['stok']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $type_badges = [
                                    'otomatis' => 'primary',
                                    'manual' => 'warning'
                                ];
                                $badge_class = $type_badges[$product['tipe_produk']] ?? 'primary';
                                ?>
                                <span class="badge badge-<?php echo $badge_class; ?>">
                                    <?php echo ucfirst($product['tipe_produk']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($product['is_active']): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Aktif
                                </span>
                                <?php else: ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-times"></i> Nonaktif
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick='openEditModal(<?php echo json_encode($product); ?>)' class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo addslashes($product['nama']); ?>')" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>Belum Ada Produk</h3>
                <p>Klik tombol "Tambah Produk" untuk menambahkan produk pertama Anda.</p>
                <button onclick="openAddModal()" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Tambah Produk Pertama
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('addModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Tambah Produk Baru</h3>
                <button onclick="closeModal('addModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Image Upload -->
                    <div class="form-group">
                        <label class="form-label">Gambar Produk</label>
                        <div class="image-upload-container" id="addImageContainer">
                            <label for="addImageInput" class="image-upload-label">
                                <input 
                                    type="file" 
                                    name="gambar" 
                                    id="addImageInput" 
                                    accept="image/*" 
                                    style="display: none;"
                                    onchange="previewImage(this, 'addImagePreview', 'addImageContainer')"
                                >
                                <div class="upload-placeholder" id="addImagePlaceholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Klik untuk upload gambar</strong></p>
                                    <p style="font-size: 0.85rem;">JPG, PNG, GIF, WebP (Max 5MB)</p>
                                </div>
                                <img id="addImagePreview" class="image-preview" alt="Preview">
                            </label>
                        </div>
                        <div class="image-actions" id="addImageActions" style="display: none;">
                            <button type="button" onclick="document.getElementById('addImageInput').click()" class="btn btn-sm btn-primary">
                                <i class="fas fa-sync"></i> Ganti Gambar
                            </button>
                            <button type="button" onclick="clearImage('addImageInput', 'addImagePreview', 'addImageContainer')" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nama Produk <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="nama" id="addNamaInput" class="form-control" required placeholder="Contoh: Netflix Premium 1 Bulan">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Slug <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="slug" id="addSlugInput" class="form-control" required placeholder="netflix-premium-1-bulan">
                        <small style="color: var(--text-muted); font-size: 0.85rem;">URL-friendly, akan auto-generate dari nama</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kode Produk (Product Code)</label>
                        <input type="text" name="product_code" class="form-control" placeholder="Contoh: NTFS1, NFX1PR">
                        <small style="color: var(--text-muted); font-size: 0.85rem;">Kode untuk integrasi dengan API (opsional)</small>
                    </div>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Kategori <span style="color: var(--danger-color);">*</span></label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tipe Produk <span style="color: var(--danger-color);">*</span></label>
                            <select name="tipe_produk" class="form-control" required>
                                <option value="otomatis">Otomatis</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Harga (Rp) <span style="color: var(--danger-color);">*</span></label>
                            <input type="number" name="harga" class="form-control" required min="0" step="0.01" placeholder="10000">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Stok <span style="color: var(--danger-color);">*</span></label>
                            <input type="number" name="stok" class="form-control" required min="0" value="999" placeholder="999">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="4" placeholder="Deskripsi produk..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('editModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Produk</h3>
                <button onclick="closeModal('editModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="product_id" id="edit_product_id">
                <input type="hidden" name="delete_image" id="edit_delete_image" value="0">
                <div class="modal-body">
                    <!-- Image Upload -->
                    <div class="form-group">
                        <label class="form-label">Gambar Produk</label>
                        <div class="image-upload-container" id="editImageContainer">
                            <label for="editImageInput" class="image-upload-label">
                                <input 
                                    type="file" 
                                    name="gambar" 
                                    id="editImageInput" 
                                    accept="image/*" 
                                    style="display: none;"
                                    onchange="previewImage(this, 'editImagePreview', 'editImageContainer')"
                                >
                                <div class="upload-placeholder" id="editImagePlaceholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Klik untuk upload gambar</strong></p>
                                    <p style="font-size: 0.85rem;">JPG, PNG, GIF, WebP (Max 5MB)</p>
                                </div>
                                <img id="editImagePreview" class="image-preview" alt="Preview">
                            </label>
                        </div>
                        <div class="image-actions" id="editImageActions" style="display: none;">
                            <button type="button" onclick="document.getElementById('editImageInput').click()" class="btn btn-sm btn-primary">
                                <i class="fas fa-sync"></i> Ganti Gambar
                            </button>
                            <button type="button" onclick="deleteEditImage()" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Hapus Gambar
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nama Produk <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="nama" id="edit_nama" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kode Produk (Product Code)</label>
                        <input type="text" name="product_code" id="edit_product_code" class="form-control" placeholder="Contoh: NTFS1">
                    </div>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Kategori <span style="color: var(--danger-color);">*</span></label>
                            <select name="category_id" id="edit_category_id" class="form-control" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tipe Produk <span style="color: var(--danger-color);">*</span></label>
                            <select name="tipe_produk" id="edit_tipe_produk" class="form-control" required>
                                <option value="otomatis">Otomatis</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Harga (Rp) <span style="color: var(--danger-color);">*</span></label>
                            <input type="number" name="harga" id="edit_harga" class="form-control" required min="0" step="0.01">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Stok <span style="color: var(--danger-color);">*</span></label>
                            <input type="number" name="stok" id="edit_stok" class="form-control" required min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width: 20px; height: 20px;">
                            <span style="font-weight: 600;">Produk Aktif</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="update_product" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Produk
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }

        function openAddModal() {
            // Reset form
            document.querySelector('#addModal form').reset();
            clearImage('addImageInput', 'addImagePreview', 'addImageContainer');
            openModal('addModal');
        }

        function openEditModal(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_nama').value = product.nama;
            document.getElementById('edit_product_code').value = product.product_code || '';
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_tipe_produk').value = product.tipe_produk;
            document.getElementById('edit_harga').value = product.harga;
            document.getElementById('edit_stok').value = product.stok;
            document.getElementById('edit_deskripsi').value = product.deskripsi || '';
            document.getElementById('edit_is_active').checked = product.is_active == 1;
            document.getElementById('edit_delete_image').value = '0';
            
            // Handle existing image
            const preview = document.getElementById('editImagePreview');
            const container = document.getElementById('editImageContainer');
            const placeholder = document.getElementById('editImagePlaceholder');
            const actions = document.getElementById('editImageActions');
            
            if (product.gambar) {
                preview.src = '<?php echo $upload_dir; ?>' + product.gambar;
                preview.classList.add('active');
                placeholder.style.display = 'none';
                container.classList.add('has-image');
                actions.style.display = 'flex';
            } else {
                clearImage('editImageInput', 'editImagePreview', 'editImageContainer');
            }
            
            openModal('editModal');
        }

        function confirmDelete(id, name) {
            if (confirm('Apakah Anda yakin ingin menghapus produk "' + name + '"?\n\nPeringatan: Tindakan ini tidak dapat dibatalkan dan gambar produk akan terhapus!')) {
                window.location.href = 'admin-products.php?delete=' + id + '&confirm=1';
            }
        }

        // Image Preview Functions
        function previewImage(input, previewId, containerId) {
            const preview = document.getElementById(previewId);
            const container = document.getElementById(containerId);
            const placeholder = container.querySelector('.upload-placeholder');
            const actions = document.getElementById(previewId.replace('Preview', 'Actions'));
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.add('active');
                    placeholder.style.display = 'none';
                    container.classList.add('has-image');
                    if (actions) actions.style.display = 'flex';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        function clearImage(inputId, previewId, containerId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const container = document.getElementById(containerId);
            const placeholder = container.querySelector('.upload-placeholder');
            const actions = document.getElementById(previewId.replace('Preview', 'Actions'));
            
            input.value = '';
            preview.src = '';
            preview.classList.remove('active');
            placeholder.style.display = 'block';
            container.classList.remove('has-image');
            if (actions) actions.style.display = 'none';
        }

        function deleteEditImage() {
            document.getElementById('edit_delete_image').value = '1';
            clearImage('editImageInput', 'editImagePreview', 'editImageContainer');
        }

        // Auto-generate slug from name
        const namaInput = document.getElementById('addNamaInput');
        if (namaInput) {
            namaInput.addEventListener('input', function(e) {
                const slug = e.target.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                document.getElementById('addSlugInput').value = slug;
            });
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    closeModal(activeModal.id);
                }
            }
        });

        // Format currency input
        const currencyInputs = document.querySelectorAll('input[name="harga"]');
        currencyInputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    this.value = Math.round(parseFloat(this.value) * 100) / 100;
                }
            });
        });
    </script>
</body>
</html>