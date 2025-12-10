<?php
/**
 * FUNGSI KHUSUS STOK LOKAL (MANUAL)
 * File ini menangani pengambilan akun dari tabel `product_accounts`.
 * Digunakan jika produk tidak memiliki product_code (bukan dari Atlantic).
 */

function claimLocalStock($transactionId, $productId) {
    global $conn;
    
    // 1. Cek apakah transaksi ini sudah punya akun sebelumnya?
    $stmt = $conn->prepare("SELECT account_info FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    $existingInfo = $stmt->fetchColumn();
    
    if (!empty($existingInfo)) {
        return $existingInfo; // Sudah ada, kembalikan datanya
    }

    // 2. Mulai Transaksi Database (Locking) agar tidak rebutan stok
    $conn->beginTransaction();
    
    try {
        // Cari 1 akun yang available
        $stmtCheck = $conn->prepare("
            SELECT id, account_data 
            FROM product_accounts 
            WHERE product_id = ? AND status = 'available' 
            LIMIT 1 FOR UPDATE
        ");
        $stmtCheck->execute([$productId]);
        $account = $stmtCheck->fetch();

        if ($account) {
            // Tandai stok sebagai terjual
            $stmtUpdateStock = $conn->prepare("
                UPDATE product_accounts 
                SET status = 'sold', transaction_id = ? 
                WHERE id = ?
            ");
            $stmtUpdateStock->execute([$transactionId, $account['id']]);

            // Simpan snapshot akun ke tabel transaksi utama
            $stmtUpdateTrx = $conn->prepare("
                UPDATE transactions 
                SET account_info = ? 
                WHERE id = ?
            ");
            $stmtUpdateTrx->execute([$account['account_data'], $transactionId]);
            
            // Kurangi angka stok di tabel produk
            $stmtDecrProduct = $conn->prepare("UPDATE products SET stok = stok - 1 WHERE id = ? AND stok > 0");
            $stmtDecrProduct->execute([$productId]);

            $conn->commit();
            return $account['account_data'];
        } else {
            // Stok Habis
            $conn->commit();
            return null;
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error Claim Local Stock: " . $e->getMessage());
        return null;
    }
}
?>