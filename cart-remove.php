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

// Get POST data (support both JSON and form data)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Fallback to POST data
    $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
} else {
    $cart_id = isset($data['cart_id']) ? intval($data['cart_id']) : 0;
}

if ($cart_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID cart tidak valid'
    ]);
    exit;
}

try {
    // Verify that cart item belongs to this user
    $stmt = $conn->prepare("SELECT * FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cart_id, $user['id']]);
    $cart_item = $stmt->fetch();
    
    if (!$cart_item) {
        echo json_encode([
            'success' => false,
            'message' => 'Item tidak ditemukan atau bukan milik Anda'
        ]);
        exit;
    }
    
    // Delete cart item
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
    if ($stmt->execute([$cart_id])) {
        // Get updated cart count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $cart_count = $stmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Item berhasil dihapus dari keranjang',
            'cart_count' => $cart_count
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menghapus item'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>