<?php
/**
 * AJAX - Get Products for Game
 */
require_once 'config.php';

// Check admin
if (!isLoggedIn()) {
    echo '<p style="color: #ef4444;">Unauthorized</p>';
    exit;
}

$user = getUserData();
if ($user['is_admin'] != 1) {
    echo '<p style="color: #ef4444;">Unauthorized</p>';
    exit;
}

$gameId = intval($_GET['game_id'] ?? 0);

if (!$gameId) {
    echo '<p style="color: #94a3b8;">Game ID tidak valid</p>';
    exit;
}

$stmt = $conn->prepare("SELECT * FROM atlantic_game_products WHERE game_id = ? ORDER BY price_sell ASC");
$stmt->execute([$gameId]);
$products = $stmt->fetchAll();

if (empty($products)) {
    echo '<p style="text-align: center; color: #94a3b8; padding: 2rem;">Belum ada produk. Klik tombol <strong>Import</strong> pada card game untuk mengambil produk dari Atlantic H2H.</p>';
    exit;
}
?>
<div style="overflow-x: auto;">
    <table class="products-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="padding: 0.75rem; text-align: left; background: rgba(255,255,255,0.05); border-bottom: 1px solid #334155; font-size: 0.85rem; color: #94a3b8;">Kode</th>
                <th style="padding: 0.75rem; text-align: left; background: rgba(255,255,255,0.05); border-bottom: 1px solid #334155; font-size: 0.85rem; color: #94a3b8;">Produk</th>
                <th style="padding: 0.75rem; text-align: left; background: rgba(255,255,255,0.05); border-bottom: 1px solid #334155; font-size: 0.85rem; color: #94a3b8;">Harga Modal</th>
                <th style="padding: 0.75rem; text-align: left; background: rgba(255,255,255,0.05); border-bottom: 1px solid #334155; font-size: 0.85rem; color: #94a3b8;">Harga Jual</th>
                <th style="padding: 0.75rem; text-align: left; background: rgba(255,255,255,0.05); border-bottom: 1px solid #334155; font-size: 0.85rem; color: #94a3b8;">Margin</th>
                <th style="padding: 0.75rem; text-align: left; background: rgba(255,255,255,0.05); border-bottom: 1px solid #334155; font-size: 0.85rem; color: #94a3b8;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): 
                $margin = $p['price_sell'] - $p['price_atlantic'];
                $marginPercent = $p['price_atlantic'] > 0 ? round(($margin / $p['price_atlantic']) * 100, 1) : 0;
            ?>
            <tr style="border-bottom: 1px solid #334155;">
                <td style="padding: 0.75rem;"><code style="font-size: 0.8rem; background: #0f172a; padding: 0.25rem 0.5rem; border-radius: 0.25rem;"><?php echo htmlspecialchars($p['product_code']); ?></code></td>
                <td style="padding: 0.75rem;">
                    <div style="font-weight: 500;"><?php echo htmlspecialchars($p['nominal_display']); ?></div>
                    <div style="font-size: 0.8rem; color: #94a3b8;"><?php echo htmlspecialchars($p['product_name']); ?></div>
                </td>
                <td style="padding: 0.75rem; color: #f59e0b;">Rp <?php echo number_format($p['price_atlantic'], 0, ',', '.'); ?></td>
                <td style="padding: 0.75rem; font-weight: 600; color: #10b981;">Rp <?php echo number_format($p['price_sell'], 0, ',', '.'); ?></td>
                <td style="padding: 0.75rem;">
                    <span style="color: <?php echo $margin > 0 ? '#10b981' : '#ef4444'; ?>;">
                        +Rp <?php echo number_format($margin, 0, ',', '.'); ?>
                    </span>
                    <span style="font-size: 0.8rem; color: #94a3b8;">(<?php echo $marginPercent; ?>%)</span>
                </td>
                <td style="padding: 0.75rem;">
                    <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; background: <?php echo $p['status'] === 'available' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)'; ?>; color: <?php echo $p['status'] === 'available' ? '#10b981' : '#ef4444'; ?>;">
                        <?php echo $p['status'] === 'available' ? 'Available' : 'Empty'; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p style="margin-top: 1rem; font-size: 0.85rem; color: #94a3b8;">
    <i class="fas fa-info-circle"></i> Total <?php echo count($products); ?> produk
</p>
