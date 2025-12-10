<?php
/**
 * Google OAuth Callback Handler
 * SPNB Store - Google Login Integration
 */

require_once 'config.php';

// Cek apakah ada error dari Google
if (isset($_GET['error'])) {
    $_SESSION['login_error'] = 'Login Google dibatalkan atau terjadi kesalahan.';
    redirect('login.php');
}

// Cek authorization code
if (!isset($_GET['code'])) {
    $_SESSION['login_error'] = 'Kode otorisasi tidak ditemukan.';
    redirect('login.php');
}

$code = $_GET['code'];

// Cek state untuk keamanan CSRF
if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || 
    $_GET['state'] !== $_SESSION['google_oauth_state']) {
    $_SESSION['login_error'] = 'Invalid state parameter. Silakan coba lagi.';
    redirect('login.php');
}

// Clear state setelah digunakan
unset($_SESSION['google_oauth_state']);

try {
    // Exchange authorization code untuk access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $tokenResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    
    $tokenData = json_decode($tokenResponse, true);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to get access token: ' . ($tokenData['error_description'] ?? 'Unknown error'));
    }
    
    $accessToken = $tokenData['access_token'];
    
    // Get user info dari Google
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $userInfoResponse = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    
    $googleUser = json_decode($userInfoResponse, true);
    
    if (!isset($googleUser['id']) || !isset($googleUser['email'])) {
        throw new Exception('Failed to get user info from Google');
    }
    
    $googleId = $googleUser['id'];
    $email = $googleUser['email'];
    $nama = $googleUser['name'] ?? '';
    $picture = $googleUser['picture'] ?? '';
    
    // Cek apakah user sudah terdaftar dengan Google ID ini
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        // User sudah ada, langsung login
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['username'] = $existingUser['username'];
        $_SESSION['login_success'] = 'Berhasil login dengan Google!';
        
        // Update last login dan avatar jika perlu
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), google_avatar = ? WHERE id = ?");
        $stmt->execute([$picture, $existingUser['id']]);
        
        redirect('dashboard.php');
    }
    
    // Cek apakah email sudah terdaftar (akun biasa)
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingEmail = $stmt->fetch();
    
    if ($existingEmail) {
        // Email sudah ada, hubungkan dengan Google ID
        $stmt = $conn->prepare("UPDATE users SET google_id = ?, google_avatar = ?, last_login = NOW() WHERE id = ?");
        $stmt->execute([$googleId, $picture, $existingEmail['id']]);
        
        $_SESSION['user_id'] = $existingEmail['id'];
        $_SESSION['username'] = $existingEmail['username'];
        $_SESSION['login_success'] = 'Akun Google berhasil dihubungkan!';
        
        redirect('dashboard.php');
    }
    
    // User baru - buat akun
    // Generate username dari email
    $baseUsername = strstr($email, '@', true);
    $username = $baseUsername;
    $counter = 1;
    
    // Pastikan username unik
    while (true) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) break;
        $username = $baseUsername . $counter;
        $counter++;
    }
    
    // Generate random password (user bisa set manual nanti jika mau)
    $randomPassword = bin2hex(random_bytes(16));
    $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
    
    // Insert user baru
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password, nama_lengkap, google_id, google_avatar, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$username, $email, $hashedPassword, $nama, $googleId, $picture]);
    
    $userId = $conn->lastInsertId();
    
    // Auto login
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['login_success'] = 'Akun berhasil dibuat dengan Google! Selamat datang, ' . $nama . '!';
    
    redirect('dashboard.php');
    
} catch (Exception $e) {
    error_log('Google OAuth Error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'Terjadi kesalahan saat login dengan Google. Silakan coba lagi.';
    redirect('login.php');
}
?>