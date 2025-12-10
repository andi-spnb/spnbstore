<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu'
    ]);
    exit;
}

$user = getUserData();

// Get contact number from request
$input = file_get_contents('php://input');
$request_data = json_decode($input, true);
$contact_number = isset($request_data['contact_number']) ? trim($request_data['contact_number']) : '';

if (empty($contact_number)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nomor kontak harus diisi!'
    ]);
    exit;
}

try {
    // Get cart items
    $stmt = $conn->prepare("SELECT c.*, p.nama, p.harga, p.stok, p.tipe_produk 
                           FROM cart c 
                           JOIN products p ON c.product_id = p.id 
                           WHERE c.user_id = ? AND p.is_active = 1");
    $stmt->execute([$user['id']]);
    $cart_items = $stmt->fetchAll();
    
    if (count($cart_items) == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Keranjang Anda kosong'
        ]);
        exit;
    }
    
    // Calculate total and check stock
    $total = 0;
    $errors = [];
    
    foreach ($cart_items as $item) {
        if ($item['stok'] < $item['quantity']) {
            $errors[] = "Stok {$item['nama']} tidak mencukupi. Tersedia: {$item['stok']}";
        }
        $total += ($item['harga'] * $item['quantity']);
    }
    
    if (count($errors) > 0) {
        echo json_encode([
            'success' => false,
            'message' => implode(', ', $errors)
        ]);
        exit;
    }
    
    // Check user balance
    if ($user['saldo'] < $total) {
        echo json_encode([
            'success' => false,
            'message' => 'Saldo tidak mencukupi! Saldo Anda: ' . formatRupiah($user['saldo']) . ', Total belanja: ' . formatRupiah($total),
            'need_topup' => true,
            'shortage' => $total - $user['saldo']
        ]);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    $success_count = 0;
    $failed_products = [];
    
    foreach ($cart_items as $item) {
        // Generate transaction ID
        $transaction_id = 'TRX-' . time() . '-' . rand(1000, 9999);
        
        // Insert transaction
        $stmt = $conn->prepare("INSERT INTO transactions 
                               (user_id, product_id, transaction_id, quantity, total_harga, status, payment_method, contact_number, keterangan) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $item_total = $item['harga'] * $item['quantity'];
        $status = $item['tipe_produk'] == 'otomatis' ? 'ready' : 'proses';
        $keterangan = "Pembelian dari keranjang: {$item['quantity']}x {$item['nama']}";
        
        if ($stmt->execute([$user['id'], $item['product_id'], $transaction_id, $item['quantity'], $item_total, $status, 'saldo', $contact_number, $keterangan])) {
            // Update product stock
            $stmt = $conn->prepare("UPDATE products SET stok = stok - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);
            
            // Update user balance
            $stmt = $conn->prepare("UPDATE users SET saldo = saldo - ? WHERE id = ?");
            $stmt->execute([$item_total, $user['id']]);
            
            $success_count++;
        } else {
            $failed_products[] = $item['nama'];
        }
    }
    
    if (count($failed_products) > 0) {
        $conn->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memproses: ' . implode(', ', $failed_products)
        ]);
        exit;
    }
    
    // Clear cart
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    // Update user contact if not set
    if (empty($user['whatsapp'])) {
        $stmt = $conn->prepare("UPDATE users SET whatsapp = ? WHERE id = ?");
        $stmt->execute([$contact_number, $user['id']]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Berhasil membeli {$success_count} produk! Total: " . formatRupiah($total),
        'total' => $total,
        'items_count' => $success_count,
        'redirect' => 'riwayat.php'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log error
    file_put_contents('checkout_error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>