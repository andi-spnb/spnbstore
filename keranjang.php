<?php
require_once 'config.php';

// Define upload directory
$upload_dir = 'assets/img/products/';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();

// Get cart items with product image
$stmt = $conn->prepare("SELECT c.*, p.nama, p.harga, p.slug, p.tipe_produk, p.gambar, p.stok 
                        FROM cart c 
                        JOIN products p ON c.product_id = p.id 
                        WHERE c.user_id = ? AND p.is_active = 1");
$stmt->execute([$user['id']]);
$cart_items = $stmt->fetchAll();

// Calculate total
$total = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $total += $item['harga'] * $item['quantity'];
    $total_items += $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .info-box i {
            color: #3b82f6;
        }
        
        .cart-item {
            background: var(--dark-bg);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            transition: all 0.3s;
            border: 2px solid var(--dark-border);
        }
        
        .cart-item:hover {
            transform: translateX(5px);
            border-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
        }
        
.cart-item-image {
    width: 100px;
    height: 100px;
    border-radius: 0.75rem;
    overflow: hidden;
    flex-shrink: 0;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    border: 2px solid var(--dark-border);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Hover effect */
.cart-item-image:hover {
    border-color: var(--primary-color);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
    transform: translateY(-2px);
}

/* Image inside */
.cart-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

/* Zoom effect on hover */
.cart-item:hover .cart-item-image img {
    transform: scale(1.05);
}

/* Placeholder styling */
.cart-item-image-placeholder {
    font-size: 3rem;
    opacity: 0.5;
    transition: opacity 0.3s ease;
}

.cart-item-image:hover .cart-item-image-placeholder {
    opacity: 0.7;
}

/* Responsive */
@media (max-width: 768px) {
    .cart-item-image {
        width: 100%;
        height: 180px;
    }
}

@media (max-width: 480px) {
    .cart-item-image {
        height: 150px;
        border-radius: 0.5rem;
    }
}
        
        .cart-item-image-placeholder {
            font-size: 2.5rem;
            opacity: 0.5;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .process-badge {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .cart-item-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .quantity-btn {
            width: 32px;
            height: 32px;
            border: 2px solid var(--dark-border);
            background: var(--dark-bg);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text-primary);
        }
        
        .quantity-btn:hover {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        .quantity-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quantity-display {
            min-width: 40px;
            text-align: center;
            font-weight: 700;
        }
        
        .voucher-section {
            background: var(--dark-bg);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .voucher-input-group {
            display: flex;
            gap: 0.5rem;
        }
        
        .voucher-input-group input {
            flex: 1;
        }
        
        .summary-box {
            background: var(--dark-bg);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--dark-border);
        }
        
        .summary-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                text-align: center;
            }
            
            .cart-item-image {
                width: 100%;
                height: 150px;
            }
            
            .cart-item-actions {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <div class="container" style="padding-top: 2rem;">
        <?php if (count($cart_items) > 0): ?>
        
        <!-- Info Header -->
        <div class="info-box">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-info-circle" style="font-size: 1.5rem;"></i>
                <div>
                    <h3 style="margin-bottom: 0.25rem; font-size: 1rem;">Informasi</h3>
                    <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;">
                        Periksa kembali produk yang akan dibeli kemudian tekan Order Sekarang. 
                        Jangan lupa isi kontak untuk pengiriman produk!
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-3" style="gap: 2rem;">
            <!-- Cart Items -->
            <div style="grid-column: span 2;">
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem;">
                        <i class="fas fa-shopping-cart"></i> Keranjang Belanja
                        <span style="color: var(--text-muted); font-size: 1rem; font-weight: 400;">
                            (<?php echo $total_items; ?> item)
                        </span>
                    </h2>
                    
                    <div id="cartItemsContainer">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" data-cart-id="<?php echo $item['id']; ?>" data-price="<?php echo $item['harga']; ?>">
                            <!-- Product Image -->
                            <div class="cart-item-image">
                                <?php if (!empty($item['gambar']) && file_exists($upload_dir . $item['gambar'])): ?>
                                    <img src="<?php echo $upload_dir . $item['gambar']; ?>" 
                                         alt="<?php echo htmlspecialchars($item['nama']); ?>">
                                <?php else: ?>
                                    <div class="cart-item-image-placeholder">
                                        📦
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cart-item-info">
                                <div class="cart-item-badge <?php echo $item['tipe_produk'] == 'otomatis' ? '' : 'process-badge'; ?>">
                                    <i class="fas fa-<?php echo $item['tipe_produk'] == 'otomatis' ? 'bolt' : 'clock'; ?>"></i>
                                    <?php echo $item['tipe_produk'] == 'otomatis' ? 'Otomatis' : 'Manual'; ?>
                                </div>
                                
                                <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($item['nama']); ?>
                                </h3>
                                
                                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                    <p style="color: var(--primary-color); font-size: 1.1rem; font-weight: 700; margin: 0;">
                                        <?php echo formatRupiah($item['harga']); ?>
                                    </p>
                                    <span style="color: var(--text-muted); font-size: 0.9rem;">
                                        × <span class="item-quantity"><?php echo $item['quantity']; ?></span>
                                    </span>
                                </div>
                                
                                <!-- Quantity Control -->
                                <div class="quantity-control">
                                    <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, -1, <?php echo $item['stok']; ?>)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="quantity-display" id="qty-<?php echo $item['id']; ?>">
                                        <?php echo $item['quantity']; ?>
                                    </span>
                                    <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 1, <?php echo $item['stok']; ?>)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <span style="font-size: 0.85rem; color: var(--text-muted); margin-left: 0.5rem;">
                                        Stok: <?php echo $item['stok']; ?>
                                    </span>
                                </div>
                                
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0 0 0;">
                                    Subtotal: <strong class="item-subtotal" style="color: var(--primary-color);">
                                        <?php echo formatRupiah($item['harga'] * $item['quantity']); ?>
                                    </strong>
                                </p>
                                
                                <?php if ($item['tipe_produk'] != 'otomatis'): ?>
                                <div style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(245, 158, 11, 0.1); border-radius: 0.25rem; border-left: 3px solid #f59e0b;">
                                    <p style="margin: 0; font-size: 0.85rem; color: #f59e0b;">
                                        <i class="fas fa-clock"></i> Produk ini akan diproses secara manual (1-24 jam)
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cart-item-actions">
                                <button onclick="removeFromCart(<?php echo $item['id']; ?>)" class="btn btn-danger" style="padding: 0.75rem 1.25rem; white-space: nowrap;">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div>
                <!-- Voucher Section -->
                <div class="card">
                    <h3 style="margin-bottom: 1rem;">
                        <i class="fas fa-ticket-alt"></i> Gunakan Voucher
                    </h3>
                    
                    <div class="voucher-input-group">
                        <input type="text" 
                               id="voucherCode" 
                               class="form-control" 
                               placeholder="Kode voucher...">
                        <button onclick="applyVoucher()" class="btn btn-primary">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                    
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.75rem; margin-bottom: 0;">
                        Gunakan voucher untuk diskon pembelian
                    </p>
                </div>

                <!-- Contact Information -->
                <div class="card" style="margin-top: 1.5rem;">
                    <h3 style="margin-bottom: 1rem;">
                        <i class="fas fa-phone"></i> Nomor WhatsApp <span style="color: #ef4444;">*</span>
                    </h3>
                    
                    <div class="form-group">
                        <input type="tel" 
                               id="contactNumber" 
                               class="form-control" 
                               placeholder="+62 8xxx xxxx xxxx"
                               value="<?php echo htmlspecialchars($user['whatsapp'] ?: ''); ?>">
                    </div>
                    
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                        <i class="fas fa-info-circle"></i> Diperlukan untuk pengiriman informasi pesanan
                    </p>
                </div>

                <!-- Summary -->
                <div class="card" style="margin-top: 1.5rem;">
                    <h3 style="margin-bottom: 1rem;">
                        <i class="fas fa-receipt"></i> Ringkasan Belanja
                    </h3>
                    
                    <div class="summary-row">
                        <span style="color: var(--text-muted);">Total Item</span>
                        <span style="font-weight: 600;" id="summaryTotalItems"><?php echo $total_items; ?> Item</span>
                    </div>
                    
                    <div class="summary-row">
                        <span style="color: var(--text-muted);">Subtotal</span>
                        <span style="font-weight: 600;" id="summarySubtotal"><?php echo formatRupiah($total); ?></span>
                    </div>
                    
                    <div class="summary-row" style="font-size: 1.25rem; font-weight: 700; padding-top: 0.75rem; border-top: 2px solid var(--dark-border);">
                        <span>Total Pembayaran</span>
                        <span id="totalPrice" style="color: var(--primary-color);"><?php echo formatRupiah($total); ?></span>
                    </div>
                    
                    <button onclick="proceedCheckout()" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 1.5rem; padding: 1rem; font-size: 1.1rem;">
                        <i class="fas fa-shopping-bag"></i> Order Sekarang
                    </button>
                    
                    <a href="/" class="btn btn-secondary" style="width: 100%; justify-content: center; margin-top: 0.75rem; padding: 0.75rem; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Lanjut Belanja
                    </a>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state" style="padding: 5rem 2rem;">
            <div style="width: 200px; height: 200px; margin: 0 auto 2rem; position: relative;">
                <svg viewBox="0 0 200 200" style="width: 100%; height: 100%;">
                    <defs>
                        <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#6366f1;stop-opacity:0.2" />
                            <stop offset="100%" style="stop-color:#8b5cf6;stop-opacity:0.2" />
                        </linearGradient>
                    </defs>
                    <circle cx="100" cy="120" r="50" fill="url(#grad1)"/>
                    <path d="M 70,80 L 70,110 L 50,110 L 50,130 L 150,130 L 150,110 L 130,110 L 130,80 Z" 
                          fill="none" stroke="currentColor" stroke-width="3" opacity="0.3"/>
                    <text x="100" y="120" font-size="48" text-anchor="middle" opacity="0.5">🛒</text>
                </svg>
            </div>
            
            <h2 style="font-size: 1.75rem; margin-bottom: 0.75rem;">Keranjang Masih Kosong</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">
                Kamu belum memilih produk apapun.<br>
                Yuk, cari dulu produk yang Kamu suka!
            </p>
            
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="/" class="btn btn-primary" style="padding: 1rem 2rem; text-decoration: none;">
                    <i class="fas fa-store"></i> Belanja Sekarang
                </a>
                <a href="riwayat.php" class="btn btn-secondary" style="padding: 1rem 2rem; text-decoration: none;">
                    <i class="fas fa-receipt"></i> Lihat Transaksi
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Update Quantity
        function updateQuantity(cartId, change, maxStock) {
            const qtyDisplay = document.getElementById(`qty-${cartId}`);
            const currentQty = parseInt(qtyDisplay.textContent);
            const newQty = currentQty + change;
            
            if (newQty < 1) {
                if (confirm('Hapus item dari keranjang?')) {
                    removeFromCart(cartId);
                }
                return;
            }
            
            if (newQty > maxStock) {
                alert(`Stok maksimal: ${maxStock}`);
                return;
            }
            
            // Update via AJAX
            fetch('cart-update.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    cart_id: cartId,
                    quantity: newQty
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update display
                    qtyDisplay.textContent = newQty;
                    
                    // Update item quantity in card
                    const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
                    const itemQty = cartItem.querySelector('.item-quantity');
                    const itemSubtotal = cartItem.querySelector('.item-subtotal');
                    const itemPrice = parseFloat(cartItem.dataset.price);
                    
                    itemQty.textContent = newQty;
                    itemSubtotal.textContent = formatRupiah(itemPrice * newQty);
                    
                    // Recalculate total
                    updateCartTotal();
                } else {
                    alert(data.message || 'Gagal mengupdate quantity');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan. Silakan coba lagi.');
            });
        }
        
        // Remove from Cart
        function removeFromCart(cartId) {
            if (!confirm('Hapus item dari keranjang?')) {
                return;
            }

            fetch('cart-remove.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({cart_id: cartId})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const cartItem = document.querySelector(`[data-cart-id="${cartId}"]`);
                    if (cartItem) {
                        cartItem.style.animation = 'fadeOut 0.3s ease';
                        setTimeout(() => {
                            cartItem.remove();
                            
                            // Check if cart is empty
                            const remainingItems = document.querySelectorAll('.cart-item');
                            if (remainingItems.length === 0) {
                                location.reload();
                            } else {
                                updateCartTotal();
                            }
                        }, 300);
                    }
                } else {
                    alert(data.message || 'Gagal menghapus item');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan. Silakan coba lagi.');
            });
        }
        
        // Apply Voucher
        function applyVoucher() {
            const voucherCode = document.getElementById('voucherCode').value.trim();
            
            if (!voucherCode) {
                alert('Masukkan kode voucher terlebih dahulu!');
                return;
            }
            
            // TODO: Implement voucher validation
            alert('Fitur voucher akan segera hadir!');
        }
        
        // Proceed Checkout
        function proceedCheckout() {
            const contactNumber = document.getElementById('contactNumber').value.trim();
            
            if (!contactNumber) {
                alert('Mohon isi nomor WhatsApp terlebih dahulu!');
                document.getElementById('contactNumber').focus();
                return;
            }
            
            // Validate phone number format
            if (!contactNumber.match(/^(\+62|62|0)[0-9]{9,13}$/)) {
                alert('Format nomor WhatsApp tidak valid! Contoh: 081234567890 atau +6281234567890');
                document.getElementById('contactNumber').focus();
                return;
            }
            
            if (!confirm('Proses checkout sekarang?')) {
                return;
            }
            
            // Disable button
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            
            // Send checkout request
            fetch('checkout.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    contact_number: contactNumber
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Checkout berhasil!');
                    window.location.href = data.redirect || 'riwayat.php';
                } else {
                    alert(data.message || 'Gagal checkout');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-shopping-bag"></i> Order Sekarang';
                    
                    if (data.need_topup) {
                        if (confirm('Saldo tidak cukup. Mau top-up sekarang?')) {
                            window.location.href = 'topup.php';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan. Silakan coba lagi.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shopping-bag"></i> Order Sekarang';
            });
        }
        
        // Update Cart Total
        function updateCartTotal() {
            let totalItems = 0;
            let totalPrice = 0;
            
            document.querySelectorAll('.cart-item').forEach(item => {
                const qty = parseInt(item.querySelector('.quantity-display').textContent);
                const price = parseFloat(item.dataset.price);
                totalItems += qty;
                totalPrice += qty * price;
            });
            
            document.getElementById('summaryTotalItems').textContent = totalItems + ' Item';
            document.getElementById('summarySubtotal').textContent = formatRupiah(totalPrice);
            document.getElementById('totalPrice').textContent = formatRupiah(totalPrice);
        }
        
        // Format Rupiah Helper
        function formatRupiah(angka) {
            return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
        }
        
        // Add fadeOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: scale(1); }
                to { opacity: 0; transform: scale(0.9); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>