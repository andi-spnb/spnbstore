<?php
/**
 * Cart Count API
 * Returns cart item count for current user
 * 
 * Usage: fetch('cart-count.php')
 * Response: {"success": true, "count": 5}
 */

require_once 'config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => true,
        'count' => 0,
        'message' => 'User not logged in'
    ]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get cart item count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    // Return count
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
    
} catch (Exception $e) {
    // Error handling
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Error fetching cart count',
        'error' => $e->getMessage()
    ]);
}
?>