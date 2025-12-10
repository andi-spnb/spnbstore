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

// Get POST data
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if ($product_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Produk tidak valid'
    ]);
    exit;
}

if ($quantity <= 0) {
    $quantity = 1;
}

try {
    // Check if product exists and is active
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => 'Produk tidak ditemukan atau tidak aktif'
        ]);
        exit;
    }
    
    // Check stock
    if ($product['stok'] < $quantity) {
        echo json_encode([
            'success' => false,
            'message' => 'Stok tidak mencukupi! Stok tersedia: ' . $product['stok']
        ]);
        exit;
    }
    
    // Check if product already in cart
    $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user['id'], $product_id]);
    $cart_item = $stmt->fetch();
    
    if ($cart_item) {
        // Update quantity
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($new_quantity > $product['stok']) {
            echo json_encode([
                'success' => false,
                'message' => 'Total quantity melebihi stok yang tersedia!'
            ]);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$new_quantity, $cart_item['id']]);
        
        $message = 'Jumlah produk di keranjang diperbarui!';
    } else {
        // Add new item to cart
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $product_id, $quantity]);
        
        $message = 'Produk berhasil ditambahkan ke keranjang!';
    }
    
    // Get updated cart count
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $cart_count = $stmt->fetch()['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'cart_count' => $cart_count,
        'product_name' => $product['nama']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}