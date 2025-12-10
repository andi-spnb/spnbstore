<?php
require_once 'config.php';

// Pastikan Timezone Server Benar (WIB)
date_default_timezone_set('Asia/Jakarta');

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserData();

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($transaction_id <= 0) {
    redirect('riwayat.php');
}

// Get transaction details
$stmt = $conn->prepare("SELECT t.*, p.nama as product_name 
                        FROM transactions t 
                        LEFT JOIN products p ON t.product_id = p.id 
                        WHERE t.id = ? AND t.user_id = ?");
$stmt->execute([$transaction_id, $user['id']]);
$transaction = $stmt->fetch();

if (!$transaction) {
    redirect('riwayat.php');
}

// Payment Method Logic
$payment_methods = [
    'qris' => 'QRIS',
    'bni_va' => 'BNI Virtual Account',
    'bri_va' => 'BRI Virtual Account',
    'mandiri_va' => 'Mandiri Virtual Account',
    'cimb_niaga_va' => 'CIMB Niaga Virtual Account',
    'bca_va' => 'BCA Virtual Account',
    'permata_va' => 'Permata Virtual Account',
    'gopay' => 'GoPay',
    'shopeepay' => 'ShopeePay',
    'ovo' => 'OVO'
];

$payment_method_name = 'Unknown';
$method_key = '';
if (strpos($transaction['payment_method'], 'pakasir_') === 0) {
    $method_key = str_replace('pakasir_', '', $transaction['payment_method']);
    $payment_method_name = $payment_methods[$method_key] ?? ucfirst($method_key);
}

$payment_number = $transaction['pakasir_payment_number'] ?? '';
// Bersihkan string sandbox jika ada
$clean_payment_number = str_replace('THIS.IS.JUST.AN.EXAMPLE.FOR.SANDBOX.', '', $payment_number);
$clean_payment_number = trim($clean_payment_number);
$is_sandbox = strpos($payment_number, 'THIS.IS.JUST.AN.EXAMPLE.FOR.SANDBOX') !== false;
$is_qris = ($method_key == 'qris');

// =========================================================
// COUNTDOWN LOGIC (SERVER SIDE)
// =========================================================
// Menghitung sisa waktu di PHP agar tidak tergantung jam browser user
$expired_time_str = $transaction['pakasir_expired_at'];
$remaining_seconds = 0;

if ($expired_time_str) {
    $expired_ts = strtotime($expired_time_str);
    $now_ts = time(); // Waktu server sekarang (WIB)
    $remaining_seconds = $expired_ts - $now_ts;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruksi Pembayaran - <?php echo $transaction['transaction_id']; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/navbar-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --glass-bg: rgba(255, 255, 255, 0.05);
            --border-light: rgba(255, 255, 255, 0.1);
        }

        /* Global Responsive Fixes */
        body {
            overflow-x: hidden; /* Mencegah scroll horizontal di HP */
        }

        .container {
            padding: 1rem;
            max-width: 100%;
        }

        /* Card Styling */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Header Section */
        .status-header {
            text-align: center;
            padding: 2rem 1rem;
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .status-title {
            font-size: clamp(1.5rem, 4vw, 2rem); /* Font size fleksibel */
            margin-bottom: 0.5rem;
            font-weight: 800;
        }

        /* Countdown */
        .countdown-box {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
            display: inline-block;
            font-weight: 700;
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            margin-top: 1rem;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        /* Payment Card Info */
        .payment-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(99, 102, 241, 0.2);
            position: relative;
            overflow: hidden;
        }

        .payment-number-container {
            background: var(--dark-bg);
            border: 1px dashed var(--primary-color);
            padding: 1rem;
            border-radius: 0.75rem;
            margin: 1.5rem 0;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .payment-number {
            font-family: 'Courier New', monospace;
            font-size: clamp(1rem, 4vw, 1.75rem); /* Sangat penting untuk HP kecil */
            font-weight: 700;
            color: var(--primary-color);
            word-break: break-all; /* Mencegah teks keluar container */
            line-height: 1.4;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            width: 100%; /* Default full width di mobile */
            margin-bottom: 0.5rem;
            text-decoration: none;
        }

        .btn-copy {
            background: var(--primary-color);
            color: white;
            width: auto; /* Tombol copy tidak perlu full width */
            font-size: 0.9rem;
        }

        .btn-primary { background: var(--primary-gradient); color: white; }
        .btn-secondary { background: transparent; border: 1px solid var(--text-muted); color: var(--text-primary); }
        .btn-success { background: #10b981; color: white; }

        /* QR Code Responsive */
        #qrcode {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            display: inline-block;
            max-width: 100%;
            margin: 1rem auto;
        }

        #qrcode img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        /* Payment Details Table */
        .payment-details {
            width: 100%;
            margin-top: 1.5rem;
            border-top: 1px solid var(--border-light);
            padding-top: 1.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .detail-row.total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed var(--border-light);
        }

        /* Instructions List */
        .instruction-step {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: flex-start;
        }

        .step-number {
            background: var(--primary-color);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0; /* Mencegah nomor gepeng */
        }

        .step-content h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
        }

        .step-content p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Desktop Responsive Adjustments */
        @media (min-width: 768px) {
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 2rem;
            }

            .payment-number-container {
                flex-direction: row;
                justify-content: space-between;
                text-align: left;
                padding: 1.5rem 2rem;
            }

            .payment-number {
                font-size: 1.5rem;
                margin-bottom: 0;
            }

            .btn-copy {
                margin-left: 1rem;
                margin-bottom: 0;
            }

            .action-buttons {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .btn {
                margin-bottom: 0;
            }
            
            #btnCheckStatus {
                grid-column: 1 / -1; /* Tombol cek status full width */
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <!-- Status Header -->
        <div class="card status-header">
            <div class="status-icon">⏳</div>
            <h1 class="status-title">Menunggu Pembayaran</h1>
            <p style="color: var(--text-muted);">
                Selesaikan pembayaran Anda sebelum waktu habis.
            </p>
            
            <?php if ($remaining_seconds > 0): ?>
                <div class="countdown-box" id="countdown">
                    Menghitung...
                </div>
                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-muted);">
                    Batas Waktu: <?php echo date('d M Y, H:i', strtotime($transaction['pakasir_expired_at'])); ?> WIB
                </div>
            <?php else: ?>
                <div class="countdown-box" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: #ef4444;">
                    Waktu Habis
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Information -->
        <div class="card">
            <h2 style="font-size: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-credit-card" style="color: var(--primary-color);"></i> Informasi Pembayaran
            </h2>
            
            <div class="payment-card">
                <div style="font-weight: 600; margin-bottom: 1rem;">Metode: <?php echo $payment_method_name; ?></div>
                
                <?php if ($is_sandbox): ?>
                <div style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 0.5rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.85rem;">
                    <i class="fas fa-exclamation-triangle"></i> Mode Sandbox / Testing
                </div>
                <?php endif; ?>

                <?php if ($is_qris): ?>
                <div style="margin: 1.5rem 0;">
                    <div id="qrcode">
                        <div style="padding: 2rem; color: var(--text-muted);">
                            <i class="fas fa-spinner fa-spin fa-2x"></i><br>Memuat QRIS...
                        </div>
                    </div>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem;">
                        Scan QRIS di atas menggunakan E-Wallet pilihan Anda
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($clean_payment_number)): ?>
                <div class="payment-number-container">
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Nomor Pembayaran / Kode:</div>
                        <div class="payment-number" id="paymentNumber">
                            <?php echo htmlspecialchars($clean_payment_number); ?>
                        </div>
                    </div>
                    <button onclick="copyPaymentNumber()" class="btn btn-copy">
                        <i class="fas fa-copy"></i> Salin
                    </button>
                </div>
                <?php endif; ?>
                
                <div class="payment-details">
                    <div class="detail-row">
                        <span style="color: var(--text-muted);">Tagihan</span>
                        <span>Rp <?php echo number_format($transaction['total_harga'], 0, ',', '.'); ?></span>
                    </div>
                    <?php if ($transaction['pakasir_fee'] > 0): ?>
                    <div class="detail-row">
                        <span style="color: var(--text-muted);">Biaya Admin</span>
                        <span>Rp <?php echo number_format($transaction['pakasir_fee'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row total">
                        <span>Total Bayar</span>
                        <span>Rp <?php echo number_format($transaction['pakasir_total_payment'], 0, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card">
            <h2 style="font-size: 1.25rem; margin-bottom: 1.5rem;">
                <i class="fas fa-list-ol"></i> Cara Pembayaran
            </h2>
            
            <?php if ($is_qris): ?>
            <div class="instruction-step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h4>Buka Aplikasi E-Wallet</h4>
                    <p>Buka aplikasi (DANA, OVO, GoPay, ShopeePay, atau Mobile Banking) yang mendukung QRIS.</p>
                </div>
            </div>
            <div class="instruction-step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h4>Scan QR Code</h4>
                    <p>Pilih menu "Scan" atau "Bayar" dan arahkan kamera ke QR Code di atas.</p>
                </div>
            </div>
            <div class="instruction-step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h4>Konfirmasi & Bayar</h4>
                    <p>Periksa nominal pembayaran (Rp <?php echo number_format($transaction['pakasir_total_payment'], 0, ',', '.'); ?>) dan selesaikan transaksi.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="instruction-step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h4>Salin Nomor Pembayaran</h4>
                    <p>Salin nomor Virtual Account atau kode pembayaran yang tertera di atas.</p>
                </div>
            </div>
            <div class="instruction-step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h4>Masuk ke Menu Pembayaran</h4>
                    <p>Buka aplikasi bank atau e-wallet Anda, pilih menu Transfer Virtual Account atau Pembayaran yang sesuai.</p>
                </div>
            </div>
            <div class="instruction-step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h4>Selesaikan Transaksi</h4>
                    <p>Masukkan nomor yang disalin, pastikan nominal sesuai, dan konfirmasi pembayaran.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="card action-buttons" style="border: none; background: transparent; box-shadow: none; padding: 0;">
            <button onclick="checkPaymentStatus()" id="btnCheckStatus" class="btn btn-primary">
                <i class="fas fa-sync" style="margin-right: 0.5rem;"></i> Cek Status Pembayaran
            </button>
            
            <a href="https://wa.me/6281234567890?text=Halo, saya butuh bantuan untuk transaksi <?php echo $transaction['transaction_id']; ?>" 
               target="_blank" class="btn btn-success">
                <i class="fab fa-whatsapp" style="margin-right: 0.5rem;"></i> Bantuan CS
            </a>
            
            <a href="riwayat.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i> Kembali ke Riwayat
            </a>
        </div>
    </div>

    <script>
    const transactionId = <?php echo $transaction_id; ?>;
    // Mengambil sisa detik dari server PHP agar akurat
    let remainingSeconds = <?php echo max(0, $remaining_seconds); ?>;
    
    // Countdown Timer Logic
    function startCountdown() {
        const display = document.getElementById('countdown');
        if (!display || remainingSeconds <= 0) return;

        const updateDisplay = () => {
            if (remainingSeconds < 0) {
                display.textContent = "Waktu Habis";
                display.style.backgroundColor = "rgba(239, 68, 68, 0.1)";
                display.style.color = "#ef4444";
                display.style.borderColor = "#ef4444";
                return;
            }

            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = Math.floor(remainingSeconds % 60);

            display.textContent = 
                (hours > 0 ? String(hours).padStart(2, '0') + ':' : '') + 
                String(minutes).padStart(2, '0') + ':' + 
                String(seconds).padStart(2, '0');
            
            remainingSeconds--;
        };

        updateDisplay(); // Run immediately
        const timer = setInterval(() => {
            updateDisplay();
            if (remainingSeconds < 0) clearInterval(timer);
        }, 1000);
    }

    startCountdown();
    
    // Generate QR Code (QRIS)
    <?php if ($is_qris && !empty($clean_payment_number)): ?>
    window.addEventListener('DOMContentLoaded', function() {
        const qrContainer = document.getElementById('qrcode');
        
        fetch('generate-qr.php?transaction_id=' + transactionId + '&type=qris&format=base64')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data.qr_image) {
                    qrContainer.innerHTML = '<img src="' + data.data.qr_image + '" alt="QRIS Code">';
                } else {
                    qrContainer.innerHTML = '<div style="color:red; padding:1rem;">Gagal memuat QR</div>';
                }
            })
            .catch(err => {
                console.error(err);
                qrContainer.innerHTML = '<div style="color:red; padding:1rem;">Gagal memuat QR</div>';
            });
    });
    <?php endif; ?>

    function copyPaymentNumber() {
        const text = document.getElementById('paymentNumber').innerText;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.querySelector('.btn-copy');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Tersalin';
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('btn-success');
            }, 2000);
        }).catch(err => alert('Gagal menyalin'));
    }
    
    // Manual Check
    function checkPaymentStatus() {
        const btn = document.getElementById('btnCheckStatus');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengecek...';
        btn.disabled = true;
        
        fetch('check-payment-status.php?id=' + transactionId)
            .then(res => res.json())
            .then(data => {
                handleStatus(data, true); // 'true' artinya show alert
            })
            .catch(err => {
                console.error(err);
                alert('Terjadi kesalahan koneksi.');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }

    // Handle Status Check Result
    function handleStatus(data, showAlert = false) {
        if(data.success) {
            if (data.status === 'ready' || data.status === 'proses' || data.status === 'selesai') {
                if (showAlert) alert('✅ Pembayaran Berhasil!');
                window.location.href = 'transaction-detail.php?id=' + transactionId;
            } else if (data.status === 'gagal') {
                if (showAlert) alert('❌ Pembayaran Gagal atau Expired.');
                // Optional: Reload page to show expired status
            } else {
                if (showAlert) alert('⏳ Pembayaran belum diterima. Silakan refresh beberapa saat lagi.');
            }
        } else {
            if (showAlert) alert(data.message || 'Gagal mengecek status.');
        }
    }

    // =========================================
    // AUTO CHECK STATUS (Setiap 5 Detik)
    // =========================================
    setInterval(() => {
        // Background check (silent)
        fetch('check-payment-status.php?id=' + transactionId)
            .then(res => res.json())
            .then(data => {
                handleStatus(data, false); // 'false' artinya silent (no alert)
            })
            .catch(err => console.log('Background check error:', err));
    }, 5000);

    </script>
</body>
</html>