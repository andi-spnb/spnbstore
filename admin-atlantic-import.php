<?php
require_once 'config.php';

// ==================================================================
// 1. DETEKSI LOKASI CLASS ATLANTIC (PENTING)
// ==================================================================
if (!class_exists('AtlanticH2H')) {
    $paths = [
        __DIR__ . '/AtlanticH2H.php',
        __DIR__ . '/classes/AtlanticH2H.php',
        __DIR__ . '/../classes/AtlanticH2H.php'
    ];
    
    $found = false;
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        die('<div class="alert alert-danger" style="margin:20px; text-align:center;">
            <h3>Gagal Memuat Library!</h3>
            <p>File <b>AtlanticH2H.php</b> tidak ditemukan. Pastikan file tersebut ada di folder yang sama dengan file ini atau di folder <code>classes/</code>.</p>
            </div>');
    }
}

// Cek Login
if (!isLoggedIn()) { redirect('login.php'); }
$user = getUserData();
if ($user['is_admin'] != 1) { redirect('dashboard.php'); }

$atlantic = new AtlanticH2H();
$message = ''; 
$error = '';
$debug_msg = ''; // Variabel untuk menyimpan pesan debug API

// ==================================================================
// 2. FUNGSI DOWNLOAD GAMBAR
// ==================================================================
function downloadImage($url, $name) {
    if (empty($url)) return null;
    
    $uploadDir = 'assets/img/products/';
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) return null;
    }
    
    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
    $filename = 'prod_' . preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $filename;
    
    $ch = curl_init($url);
    $fp = fopen($targetPath, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    // Tambahkan User-Agent agar tidak dianggap bot
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    
    if ($httpCode == 200 && file_exists($targetPath) && filesize($targetPath) > 0) {
        return $filename;
    } else {
        if (file_exists($targetPath)) @unlink($targetPath);
        return null;
    }
}

// ==================================================================
// 3. HANDLE POST REQUEST (IMPORT/UPDATE)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $code = $_POST['code'];
    $name = $_POST['name'];
    $price_asli = $_POST['price_original'];
    $markup = intval($_POST['markup']);
    $category_id = intval($_POST['category_id']);
    $img_url = $_POST['img_url'] ?? ''; 
    $harga_jual = $price_asli + $markup;
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    
    try {
        $localImage = downloadImage($img_url, $name); 
        
        $stmt = $conn->prepare("SELECT id, gambar FROM products WHERE product_code = ?");
        $stmt->execute([$code]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update
            $query = "UPDATE products SET nama=?, harga=?, category_id=?, updated_at=NOW(), is_active=1";
            $params = [$name, $harga_jual, $category_id];
            if ($localImage) {
                $query .= ", gambar=?";
                $params[] = $localImage;
                if (!empty($existing['gambar']) && file_exists('assets/img/products/' . $existing['gambar'])) {
                    @unlink('assets/img/products/' . $existing['gambar']);
                }
            }
            $query .= " WHERE product_code=?";
            $params[] = $code;
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $message = "Produk <b>$name</b> berhasil diupdate!";
        } else {
            // Insert
            $imgFinal = $localImage ?: 'default_product.png';
            $stmt = $conn->prepare("INSERT INTO products (nama, slug, product_code, harga, category_id, deskripsi, stok, tipe_produk, is_active, gambar) VALUES (?, ?, ?, ?, ?, ?, 999, 'otomatis', 1, ?)");
            $stmt->execute([$name, $slug, $code, $harga_jual, $category_id, "Layanan $name Otomatis", $imgFinal]);
            $message = "Produk <b>$name</b> berhasil diimport!";
        }
    } catch (Exception $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// ==================================================================
// 4. AMBIL DATA DARI API ATLANTIC
// ==================================================================
$categories = $conn->query("SELECT * FROM categories ORDER BY nama ASC")->fetchAll();

// Request ke API
$apiResult = $atlantic->getPriceList('prabayar');

$services = [];
if ($apiResult['success']) {
    // Cek struktur data respon API (antisipasi perbedaan format)
    if (isset($apiResult['data']['data']) && is_array($apiResult['data']['data'])) {
        $services = $apiResult['data']['data'];
    } elseif (isset($apiResult['data']) && is_array($apiResult['data'])) {
        $services = $apiResult['data'];
    }
} else {
    // Tangkap pesan error dari API untuk ditampilkan
    $apiMsg = $apiResult['data']['message'] ?? 'Tidak ada pesan error';
    $error = "Gagal mengambil data dari Atlantic: <b>" . htmlspecialchars($apiMsg) . "</b>";
    
    // Simpan raw response untuk debug (SANGAT PENTING UNTUK DIAGNOSA)
    $debug_msg = print_r($apiResult, true);
}

// Filter Kategori
$filter_cat = isset($_GET['category']) ? $_GET['category'] : '';
if ($filter_cat && !empty($services)) {
    $services = array_filter($services, function($item) use ($filter_cat) {
        return $item['category'] == $filter_cat;
    });
}

// List Kategori Unik API
$apiCategories = [];
if (!empty($services)) {
    foreach ($services as $s) { $apiCategories[$s['category']] = true; }
    $apiCategories = array_keys($apiCategories);
    sort($apiCategories);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Produk Atlantic - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Responsive Styles */
        .admin-content { padding: 1.5rem; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #eee; border-radius: 8px; }
        .api-table { font-size: 0.85rem; min-width: 1000px; margin-bottom: 0; }
        .api-table th { background: #f8f9fa; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; vertical-align: middle;}
        .api-table td { vertical-align: middle; }
        .img-preview { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; background: #f1f1f1; border: 1px solid #ddd; }
        
        .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight:bold; text-transform: uppercase; }
        .status-available { background: #d1fae5; color: #065f46; }
        .status-empty { background: #fee2e2; color: #991b1b; }
        
        /* Form Styling */
        .import-form { display: flex; gap: 0.5rem; align-items: center; }
        .import-form select, .import-form input { font-size: 0.85rem; padding: 0.4rem; border: 1px solid #ced4da; border-radius: 4px; }
        .import-form input[type="number"] { width: 100px; }
        .import-form select { width: 140px; }
        
        /* Mobile Breakpoint */
        @media (max-width: 768px) {
            .admin-content { padding: 1rem; }
            .filter-bar { flex-direction: column; align-items: stretch !important; }
            .filter-bar select { margin-bottom: 10px; width: 100%; }
            
            /* Stack form in table on mobile */
            .import-form { flex-direction: column; align-items: stretch; }
            .import-form * { width: 100% !important; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-content-inner">
            <div class="admin-header">
                <h1><i class="fas fa-cloud-download-alt"></i> Import Produk Atlantic</h1>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <h4><i class="fas fa-exclamation-triangle"></i> Gagal Menghubungi Server Atlantic</h4>
                    <p><?php echo $error; ?></p>
                    
                    <!-- TOMBOL UNTUK MENAMPILKAN DEBUG INFO -->
                    <button class="btn btn-sm btn-secondary mt-2" type="button" onclick="document.getElementById('debugInfo').style.display='block'">
                        <i class="fas fa-bug"></i> Lihat Detail Debug
                    </button>
                    
                    <div id="debugInfo" style="display:none; margin-top:10px;">
                        <hr>
                        <p><b>Raw Response dari Server:</b></p>
                        <pre style="background:#fff; padding:10px; border-radius:5px; font-size:0.75rem; max-height:300px; overflow:auto; border:1px solid #ddd; color:#333;"><?php echo htmlspecialchars($debug_msg); ?></pre>
                        <p class="small text-muted mt-2">
                            * Jika respon kosong atau HTML, kemungkinan IP server Anda diblokir atau URL API salah.<br>
                            * Pastikan API Key di <code>config.php</code> sudah benar.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filter & Stats -->
            <div class="card mb-4">
                <div class="filter-bar" style="display:flex; justify-content:space-between; align-items:center; padding:1rem;">
                    <form method="GET" style="flex:1; max-width:400px;">
                        <div style="display:flex; gap:10px;">
                            <select name="category" class="form-control" onchange="this.form.submit()">
                                <option value="">-- Filter Kategori --</option>
                                <?php foreach ($apiCategories as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo $filter_cat == $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if($filter_cat): ?>
                                <a href="admin-atlantic-import.php" class="btn btn-secondary" title="Reset"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <div style="font-weight:bold; color:#555;">
                        Total Layanan: <span class="text-primary"><?php echo count($services); ?></span>
                    </div>
                </div>
            </div>

            <!-- Table Data -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover api-table">
                        <thead>
                            <tr>
                                <th width="60" class="text-center">Icon</th>
                                <th>Kategori</th>
                                <th>Nama Layanan / Kode</th>
                                <th>Harga Modal</th>
                                <th width="80">Status</th>
                                <th width="350">Aksi Import</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($services)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                                        <p class="text-muted">Tidak ada data layanan yang ditemukan.</p>
                                        <?php if(!$error): ?>
                                            <p class="text-muted small">Cek koneksi internet server atau konfigurasi API Key Anda.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($services as $s): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if(!empty($s['img_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($s['img_url']); ?>" class="img-preview" alt="img">
                                        <?php else: ?>
                                            <div class="img-preview" style="display:flex;align-items:center;justify-content:center; color:#ccc;"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="background:#f0f2f5; padding:4px 8px; border-radius:20px; font-size:0.75rem; font-weight:600;">
                                            <?php echo htmlspecialchars($s['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; color:#2d3748;"><?php echo htmlspecialchars($s['name']); ?></div>
                                        <code style="font-size:0.75rem; color:#718096;"><?php echo htmlspecialchars($s['code']); ?></code>
                                    </td>
                                    <td style="color:#d97706; font-weight:700;">
                                        Rp <?php echo number_format($s['price'], 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($s['status']) == 'available' ? 'status-available' : 'status-empty'; ?>">
                                            <?php echo strtoupper($s['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="import-form">
                                            <input type="hidden" name="code" value="<?php echo htmlspecialchars($s['code']); ?>">
                                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($s['name']); ?>">
                                            <input type="hidden" name="price_original" value="<?php echo $s['price']; ?>">
                                            <input type="hidden" name="img_url" value="<?php echo htmlspecialchars($s['img_url'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="import">
                                            
                                            <select name="category_id" required title="Kategori di Web Anda">
                                                <option value="">Pilih Kategori...</option>
                                                <?php foreach ($categories as $c): ?>
                                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nama']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <input type="number" name="markup" placeholder="Laba (Rp)" value="2000" required title="Keuntungan">
                                            
                                            <button type="submit" class="btn btn-primary btn-sm" title="Simpan ke Website">
                                                <i class="fas fa-download"></i> Import
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>