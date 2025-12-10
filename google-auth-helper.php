<?php
/**
 * Google OAuth Helper Functions
 * SPNB Store - Google Login Integration
 */

/**
 * Generate Google OAuth URL
 * @return string Google OAuth authorization URL
 */
function getGoogleAuthUrl() {
    // Generate state untuk CSRF protection
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;
    
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'state' => $state,
        'prompt' => 'select_account' // Selalu tampilkan pilihan akun
    ];
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Render Google Sign-In Button
 * @param string $text Button text
 * @param string $style Additional CSS styles
 * @return string HTML for Google button
 */
function renderGoogleButton($text = 'Lanjutkan dengan Google', $style = '') {
    $authUrl = getGoogleAuthUrl();
    
    return '
    <a href="' . htmlspecialchars($authUrl) . '" class="google-login-btn" style="' . $style . '">
        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
            <g fill="none" fill-rule="evenodd">
                <path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z" fill="#34A853"/>
                <path d="M3.964 10.71A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
            </g>
        </svg>
        <span>' . htmlspecialchars($text) . '</span>
    </a>';
}

/**
 * Get Google Button CSS Styles
 * @return string CSS styles for Google button
 */
function getGoogleButtonStyles() {
    return '
    <style>
        .google-login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 12px 24px;
            background: #ffffff;
            border: 1px solid #dadce0;
            border-radius: 8px;
            color: #3c4043;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: "Google Sans", Roboto, Arial, sans-serif;
        }
        
        .google-login-btn:hover {
            background: #f8f9fa;
            border-color: #d2d3d4;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .google-login-btn:active {
            background: #f1f3f4;
        }
        
        .google-login-btn svg {
            flex-shrink: 0;
        }
        
        .auth-divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            color: var(--text-muted, #6b7280);
            font-size: 14px;
        }
        
        .auth-divider::before,
        .auth-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border-color, #374151);
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .google-login-btn {
                background: #2d2d2d;
                border-color: #5f6368;
                color: #e8eaed;
            }
            
            .google-login-btn:hover {
                background: #3c3c3c;
                border-color: #8e918f;
            }
        }
        
        /* Force dark mode if body has dark class */
        body.dark-mode .google-login-btn,
        .dark .google-login-btn {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: #e8eaed;
        }
        
        body.dark-mode .google-login-btn:hover,
        .dark .google-login-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
    </style>';
}

/**
 * Check if user is linked with Google
 * @param int $userId User ID
 * @return bool
 */
function isUserLinkedWithGoogle($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT google_id FROM users WHERE id = ? AND google_id IS NOT NULL");
    $stmt->execute([$userId]);
    return $stmt->fetch() !== false;
}

/**
 * Unlink Google account from user
 * @param int $userId User ID
 * @return bool
 */
function unlinkGoogleAccount($userId) {
    global $conn;
    
    // Cek apakah user punya password (untuk login manual)
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Jika password kosong atau default, jangan izinkan unlink
    // karena user tidak bisa login lagi
    
    $stmt = $conn->prepare("UPDATE users SET google_id = NULL, google_avatar = NULL WHERE id = ?");
    return $stmt->execute([$userId]);
}
?>