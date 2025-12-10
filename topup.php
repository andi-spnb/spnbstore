<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();
$error = '';
$success = '';

// Nominal top-up options
$nominal_options = [10000, 20000, 50000, 100000, 200000, 500000, 1000000, 2000000];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nominal = isset($_POST['nominal']) ? intval($_POST['nominal']) : 0;
    $custom_nominal = isset($_POST['custom_nominal']) ? intval($_POST['custom_nominal']) : 0;
    $nomor_kontak = trim($_POST['nomor_kontak']);
    
    // Use custom nominal if provided
    if ($custom_nominal > 0) {
        $nominal = $custom_nominal;
    }
    
    if ($nominal < 10000) {
        $error = 'Nominal minimum top-up adalah Rp 10.000';
    } elseif (empty($nomor_kontak)) {
        $error = 'Nomor kontak (WhatsApp) harus diisi!';
    } else {
        // Create Pakasir payment request
        $order_id = 'TOPUP' . time() . $user['id'];
        
        // Insert to topup_history
        $stmt = $conn->prepare("INSERT INTO topup_history (user_id, jumlah, status, pakasir_order_id, nomor_kontak) VALUES (?, ?, 'pending', ?, ?)");
        $stmt->execute([$user['id'], $nominal, $order_id, $nomor_kontak]);
        
        // Call Pakasir API
        $pakasir_data = [
            'order_id' => $order_id,
            'amount' => $nominal,
            'customer_name' => $user['nama_lengkap'] ?: $user['username'],
            'customer_phone' => $nomor_kontak,
            'customer_email' => $user['email'],
            'return_url' => SITE_URL . '/topup-callback.php',
            'expired_time' => 3600 // 1 hour
        ];
        
        $ch = curl_init(PAKASIR_API_URL . 'payment/create');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pakasir_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . PAKASIR_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            
            if (isset($result['data']['payment_url'])) {
                // Update payment URL
                $stmt = $conn->prepare("UPDATE topup_history SET pakasir_payment_url = ? WHERE pakasir_order_id = ?");
                $stmt->execute([$result['data']['payment_url'], $order_id]);
                
                // Redirect to payment page
                redirect($result['data']['payment_url']);
            } else {
                $error = 'Gagal membuat pembayaran. Silakan coba lagi.';
            }
        } else {
            $error = 'Terjadi kesalahan saat menghubungi gateway pembayaran.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topup Saldo - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto;">
            <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger-color); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: var(--danger-color);">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2 class="card-header">Topup Saldo</h2>
                
                <form method="POST" id="topupForm">
                    <!-- Nominal Selection -->
                    <div class="form-group">
                        <label class="form-label">Pilih Nominal Topup</label>
                        <div class="grid grid-3" style="gap: 1rem;">
                            <?php foreach ($nominal_options as $nominal): ?>
                            <label class="nominal-option" style="cursor: pointer;">
                                <input type="radio" name="nominal" value="<?php echo $nominal; ?>" style="display: none;">
                                <div style="background: var(--dark-bg); border: 2px solid var(--dark-border); border-radius: 0.75rem; padding: 1.5rem; text-align: center; transition: all 0.3s;">
                                    <div style="font-size: 1.25rem; font-weight: 600; color: var(--text-primary);">
                                        <?php echo number_format($nominal, 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                            
                            <!-- Custom Amount -->
                            <label class="nominal-option" style="cursor: pointer;">
                                <input type="radio" name="nominal" value="custom" style="display: none;">
                                <div style="background: var(--dark-bg); border: 2px solid var(--dark-border); border-radius: 0.75rem; padding: 1.5rem; text-align: center; display: flex; align-items: center; justify-content: center; transition: all 0.3s;">
                                    <i class="fas fa-edit" style="margin-right: 0.5rem;"></i> Lainnya
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Custom Amount Input -->
                    <div class="form-group" id="customNominalGroup" style="display: none;">
                        <label class="form-label">Masukkan Nominal Custom</label>
                        <input type="number" name="custom_nominal" id="customNominal" class="form-control" placeholder="Masukkan nominal" min="10000" step="1000">
                    </div>

                    <!-- Contact Number -->
                    <div class="form-group">
                        <label class="form-label">Masukkan Nomor Kontak</label>
                        <input type="text" name="nomor_kontak" class="form-control" placeholder="Nomor Kontak (WA)" value="<?php echo htmlspecialchars($user['whatsapp']); ?>" required>
                    </div>

                    <!-- Estimated Amount -->
                    <div style="background: var(--dark-bg); border-radius: 0.75rem; padding: 1.5rem; margin: 1.5rem 0;">
                        <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Estimasi yang harus dibayarkan</div>
                        <div id="estimatedAmount" style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">Rp 0</div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.1rem;">
                        <i class="fas fa-credit-card"></i> Topup Sekarang
                    </button>

                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 1rem; text-align: center;">
                        Topup saldo akan diproses secara otomatis oleh sistem. Jika terjadi masalah, silahkan hubungi admin.
                    </p>
                </form>
            </div>
        </div>
    </div>

    <script>
        const nominalOptions = document.querySelectorAll('.nominal-option');
        const customNominalGroup = document.getElementById('customNominalGroup');
        const customNominalInput = document.getElementById('customNominal');
        const estimatedAmount = document.getElementById('estimatedAmount');
        
        nominalOptions.forEach(option => {
            option.addEventListener('click', function() {
                nominalOptions.forEach(opt => {
                    const div = opt.querySelector('div');
                    div.style.borderColor = 'var(--dark-border)';
                    div.style.background = 'var(--dark-bg)';
                });
                
                const div = this.querySelector('div');
                div.style.borderColor = 'var(--primary-color)';
                div.style.background = 'rgba(99, 102, 241, 0.1)';
                
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                if (radio.value === 'custom') {
                    customNominalGroup.style.display = 'block';
                    customNominalInput.focus();
                } else {
                    customNominalGroup.style.display = 'none';
                    updateEstimatedAmount(parseInt(radio.value));
                }
            });
        });
        
        customNominalInput.addEventListener('input', function() {
            updateEstimatedAmount(parseInt(this.value) || 0);
        });
        
        function updateEstimatedAmount(amount) {
            estimatedAmount.textContent = 'Rp ' + amount.toLocaleString('id-ID');
        }
        
        // Form validation
        document.getElementById('topupForm').addEventListener('submit', function(e) {
            const selectedNominal = document.querySelector('input[name="nominal"]:checked');
            const customNominal = parseInt(customNominalInput.value) || 0;
            
            if (!selectedNominal) {
                e.preventDefault();
                alert('Silakan pilih nominal topup!');
                return false;
            }
            
            if (selectedNominal.value === 'custom' && customNominal < 10000) {
                e.preventDefault();
                alert('Nominal minimum topup adalah Rp 10.000');
                return false;
            }
        });
    </script>
</body>
</html>