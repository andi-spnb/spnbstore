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
$active_page = 'categories';
$page_title = 'Kelola Kategori';
$page_subtitle = 'Organize produk dalam kategori untuk navigasi lebih mudah';
$page_icon = 'fas fa-tags';

$message = '';
$message_type = 'success';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $nama = trim($_POST['nama']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'])));
    $deskripsi = trim($_POST['deskripsi']);
    $icon = trim($_POST['icon']);
    
    if (!empty($nama) && !empty($slug)) {
        // Check if slug exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $message = 'Slug sudah digunakan! Gunakan slug yang berbeda.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (nama, slug, deskripsi, icon) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$nama, $slug, $deskripsi, $icon])) {
                $message = 'Kategori berhasil ditambahkan!';
                $message_type = 'success';
            } else {
                $message = 'Gagal menambahkan kategori!';
                $message_type = 'error';
            }
        }
    } else {
        $message = 'Nama dan slug wajib diisi!';
        $message_type = 'error';
    }
}

// Handle Update Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_category'])) {
    $category_id = intval($_POST['category_id']);
    $nama = trim($_POST['nama']);
    $deskripsi = trim($_POST['deskripsi']);
    $icon = trim($_POST['icon']);
    
    $stmt = $conn->prepare("UPDATE categories SET nama = ?, deskripsi = ?, icon = ? WHERE id = ?");
    if ($stmt->execute([$nama, $deskripsi, $icon, $category_id])) {
        $message = 'Kategori berhasil diupdate!';
        $message_type = 'success';
    } else {
        $message = 'Gagal mengupdate kategori!';
        $message_type = 'error';
    }
}

// Handle Delete Category
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $category_id = intval($_GET['delete']);
    
    // Check if category has products
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $product_count = $stmt->fetch()['count'];
    
    if ($product_count > 0) {
        $message = "Tidak dapat menghapus kategori! Masih ada {$product_count} produk menggunakan kategori ini.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$category_id])) {
            $message = 'Kategori berhasil dihapus!';
            $message_type = 'success';
        }
    }
}

// Get all categories with product count
$query = "SELECT c.*, COUNT(p.id) as product_count 
          FROM categories c 
          LEFT JOIN products p ON c.id = p.category_id 
          GROUP BY c.id 
          ORDER BY c.created_at DESC";

$stmt = $conn->query($query);
$categories = $stmt->fetchAll();

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM categories");
$total_categories = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(DISTINCT category_id) as count FROM products WHERE category_id IS NOT NULL");
$used_categories = $stmt->fetch()['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM products WHERE category_id IS NULL");
$uncategorized_products = $stmt->fetch()['count'];

// Get top categories by product count
$stmt = $conn->query("SELECT c.*, COUNT(p.id) as product_count 
                      FROM categories c 
                      LEFT JOIN products p ON c.id = p.category_id 
                      GROUP BY c.id 
                      ORDER BY product_count DESC 
                      LIMIT 5");
$top_categories = $stmt->fetchAll();
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
        
        /* Category Card */
        .category-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        /* Icon Picker */
        .icon-picker {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 1rem;
            background: var(--dark-bg);
            border-radius: 0.5rem;
            margin-top: 0.5rem;
        }
        .icon-option {
            padding: 0.75rem;
            text-align: center;
            font-size: 1.5rem;
            cursor: pointer;
            border-radius: 0.5rem;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .icon-option:hover {
            background: var(--dark-card);
            border-color: var(--primary-color);
        }
        .icon-option.selected {
            background: var(--primary-color);
            border-color: var(--primary-color);
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
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
<?php require_once 'admin-sidebar.php'; ?>
        <!-- Admin Header -->
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
                    <i class="fas fa-plus"></i> Tambah Kategori
                </button>
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
        <div class="grid grid-3 mb-4">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📁</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Total Kategori</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?php echo number_format($total_categories); ?></div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">✅</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Kategori Terpakai</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?php echo number_format($used_categories); ?></div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">⚠️</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Tanpa Kategori</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);"><?php echo number_format($uncategorized_products); ?></div>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="card mb-4">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h2 style="font-size: 1.5rem; margin: 0;">
                    <i class="fas fa-th-large"></i> Semua Kategori
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">
                        (<?php echo count($categories); ?> kategori)
                    </span>
                </h2>
            </div>

            <?php if (count($categories) > 0): ?>
            <div class="grid grid-4">
                <?php foreach ($categories as $cat): ?>
                <div class="card" style="text-align: center; background: var(--dark-bg); border: 2px solid var(--dark-border); transition: transform 0.3s, box-shadow 0.3s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 30px rgba(0,0,0,0.3)';" onmouseout="this.style.transform=''; this.style.boxShadow='';">
                    <div class="category-icon">
                        <?php echo !empty($cat['icon']) ? $cat['icon'] : '📦'; ?>
                    </div>
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.1rem;">
                        <?php echo htmlspecialchars($cat['nama']); ?>
                    </h3>
                    <?php if (!empty($cat['deskripsi'])): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                        <?php echo htmlspecialchars(substr($cat['deskripsi'], 0, 50)) . (strlen($cat['deskripsi']) > 50 ? '...' : ''); ?>
                    </p>
                    <?php endif; ?>
                    <div style="font-size: 0.9rem; margin-bottom: 1rem;">
                        <span class="badge badge-primary">
                            <i class="fas fa-box"></i> <?php echo $cat['product_count']; ?> produk
                        </span>
                    </div>
                    <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                        <button onclick='editCategory(<?php echo json_encode($cat); ?>)' class="btn btn-warning" style="padding: 0.5rem 1rem; font-size: 0.85rem; flex: 1;">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <?php if ($cat['product_count'] == 0): ?>
                        <button onclick="confirmDelete(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['nama']); ?>')" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.85rem; flex: 1;">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                        <?php else: ?>
                        <button class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; flex: 1; cursor: not-allowed;" disabled title="Kategori masih memiliki produk">
                            <i class="fas fa-lock"></i> Locked
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <h3>Belum Ada Kategori</h3>
                <p>Klik tombol "Tambah Kategori" untuk membuat kategori pertama Anda.</p>
                <button onclick="openAddModal()" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Tambah Kategori Pertama
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Categories -->
        <?php if (count($top_categories) > 0): ?>
        <div class="card">
            <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem;">
                <i class="fas fa-trophy"></i> Top Kategori (By Products)
            </h2>
            <div class="grid grid-5">
                <?php foreach ($top_categories as $idx => $top): ?>
                <div class="card" style="text-align: center; background: var(--dark-bg); border: 2px solid var(--dark-border);">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                        <?php echo ['🥇', '🥈', '🥉', '🏅', '🏅'][$idx]; ?>
                    </div>
                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">
                        <?php echo !empty($top['icon']) ? $top['icon'] : '📦'; ?>
                    </div>
                    <div style="font-weight: 600; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($top['nama']); ?>
                    </div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary-color);">
                        <?php echo $top['product_count']; ?> produk
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Category Modal -->
    <div id="addModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('addModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Tambah Kategori Baru</h3>
                <button onclick="closeModal('addModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Nama Kategori <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="nama" class="form-control" required placeholder="Contoh: Mobile Legends">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Slug <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="slug" class="form-control" required placeholder="mobile-legends">
                        <small style="color: var(--text-muted); font-size: 0.85rem;">URL-friendly, gunakan huruf kecil dan tanda hubung</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3" placeholder="Deskripsi kategori..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Icon/Emoji</label>
                        <input type="text" name="icon" id="add_icon" class="form-control" placeholder="Pilih emoji di bawah" readonly>
                        <div class="icon-picker">
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎮</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">📱</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">💎</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">⚔️</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🏆</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎯</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎲</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎪</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎭</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎨</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎬</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎵</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">📺</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🖥️</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">⌚</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">📷</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">💻</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎧</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎤</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🏀</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">⚽</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🏈</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🎾</div>
                            <div class="icon-option" onclick="selectIcon(this, 'add_icon')">🏐</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add_category" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-overlay" onclick="closeModal('editModal')"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Kategori</h3>
                <button onclick="closeModal('editModal')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Nama Kategori <span style="color: var(--danger-color);">*</span></label>
                        <input type="text" name="nama" id="edit_nama" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Slug</label>
                        <input type="text" id="edit_slug" class="form-control" disabled>
                        <small style="color: var(--text-muted);">Slug tidak dapat diubah</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Icon/Emoji</label>
                        <input type="text" name="icon" id="edit_icon" class="form-control" readonly>
                        <div class="icon-picker">
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎮</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">📱</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">💎</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">⚔️</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🏆</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎯</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎲</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎪</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎭</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎨</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎬</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎵</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">📺</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🖥️</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">⌚</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">📷</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">💻</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎧</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎤</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🏀</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">⚽</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🏈</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🎾</div>
                            <div class="icon-option" onclick="selectIcon(this, 'edit_icon')">🏐</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="update_category" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Kategori
                    </button>
                </div>
            </form>
        </div>
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
            openModal('addModal');
        }

        function editCategory(category) {
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_nama').value = category.nama;
            document.getElementById('edit_slug').value = category.slug;
            document.getElementById('edit_deskripsi').value = category.deskripsi || '';
            document.getElementById('edit_icon').value = category.icon || '';
            openModal('editModal');
        }

        function confirmDelete(id, name) {
            if (confirm('Apakah Anda yakin ingin menghapus kategori "' + name + '"?\n\nPeringatan: Tindakan ini tidak dapat dibatalkan!')) {
                window.location.href = 'admin-categories.php?delete=' + id + '&confirm=1';
            }
        }

        // Icon Selection
        function selectIcon(element, targetId) {
            const icon = element.textContent;
            document.getElementById(targetId).value = icon;
            
            // Remove selected class from all icons in same picker
            const picker = element.parentElement;
            picker.querySelectorAll('.icon-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked icon
            element.classList.add('selected');
        }

        // Auto-generate slug from name
        const nameInputs = document.querySelectorAll('input[name="nama"]');
        nameInputs.forEach(input => {
            if (input.closest('#addModal')) {
                input.addEventListener('input', function(e) {
                    const slug = e.target.value
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    const slugInput = e.target.closest('form').querySelector('input[name="slug"]');
                    if (slugInput) {
                        slugInput.value = slug;
                    }
                });
            }
        });

        // Quick Search
        document.getElementById('quickSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.grid-4 > .card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });
    </script>
</body>
</html>