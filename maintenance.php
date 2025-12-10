<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Maintenance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .maintenance-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .icon {
            font-size: 80px;
            color: #667eea;
            margin-bottom: 30px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        h1 {
            font-size: 2.5rem;
            color: #2d3748;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .message {
            font-size: 1.1rem;
            color: #4a5568;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .feature {
            padding: 20px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            transition: transform 0.3s ease;
        }
        
        .feature:hover {
            transform: translateY(-5px);
        }
        
        .feature i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .feature-text {
            font-size: 0.9rem;
            color: #4a5568;
            font-weight: 600;
        }
        
        .countdown {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .countdown-text {
            font-size: 0.9rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .countdown-timer {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
            color: #667eea;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.5rem;
        }
        
        .social-link:hover {
            background: #667eea;
            color: white;
            transform: scale(1.1);
        }
        
        .admin-link {
            margin-top: 30px;
            font-size: 0.85rem;
            color: #718096;
        }
        
        .admin-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .admin-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .maintenance-container {
                padding: 40px 30px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .icon {
                font-size: 60px;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="icon">
            <i class="fas fa-tools"></i>
        </div>
        
        <h1>Website Under Maintenance</h1>
        
        <p class="message">
            <?php
            // Load settings from database
            require_once 'config.php';
            $maintenance_message = '';
            try {
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_message'");
                $stmt->execute();
                $result = $stmt->fetch();
                $maintenance_message = $result ? $result['setting_value'] : '';
            } catch (Exception $e) {
                // Default message
            }
            
            if (empty($maintenance_message)) {
                $maintenance_message = 'Kami sedang melakukan maintenance untuk meningkatkan performa website. Mohon maaf atas ketidaknyamanannya.';
            }
            
            echo htmlspecialchars($maintenance_message);
            ?>
        </p>
        
        <div class="countdown">
            <div class="countdown-text">Estimasi selesai dalam:</div>
            <div class="countdown-timer" id="countdown">2 Jam</div>
        </div>
        
        <div class="features">
            <div class="feature">
                <i class="fas fa-rocket"></i>
                <div class="feature-text">Performance Upgrade</div>
            </div>
            <div class="feature">
                <i class="fas fa-shield-alt"></i>
                <div class="feature-text">Security Enhancement</div>
            </div>
            <div class="feature">
                <i class="fas fa-star"></i>
                <div class="feature-text">New Features</div>
            </div>
        </div>
        
        <p style="color: #718096; font-size: 0.95rem;">
            Terima kasih atas kesabaran Anda. Kami akan segera kembali!
        </p>
        
        <div class="social-links">
            <?php
            // Load social media links from database
            $facebook_url = '';
            $instagram_url = '';
            $twitter_url = '';
            $telegram_url = '';
            
            try {
                $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('facebook_url', 'instagram_url', 'twitter_url', 'telegram_url')");
                while ($row = $stmt->fetch()) {
                    ${$row['setting_key']} = $row['setting_value'];
                }
            } catch (Exception $e) {
                // Ignore errors
            }
            
            if (!empty($facebook_url)):
            ?>
            <a href="<?php echo htmlspecialchars($facebook_url); ?>" target="_blank" class="social-link" title="Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <?php endif; ?>
            
            <?php if (!empty($instagram_url)): ?>
            <a href="<?php echo htmlspecialchars($instagram_url); ?>" target="_blank" class="social-link" title="Instagram">
                <i class="fab fa-instagram"></i>
            </a>
            <?php endif; ?>
            
            <?php if (!empty($twitter_url)): ?>
            <a href="<?php echo htmlspecialchars($twitter_url); ?>" target="_blank" class="social-link" title="Twitter">
                <i class="fab fa-twitter"></i>
            </a>
            <?php endif; ?>
            
            <?php if (!empty($telegram_url)): ?>
            <a href="<?php echo htmlspecialchars($telegram_url); ?>" target="_blank" class="social-link" title="Telegram">
                <i class="fab fa-telegram"></i>
            </a>
            <?php endif; ?>
        </div>
        
        <div class="admin-link">
            <i class="fas fa-user-shield"></i> Admin? 
            <a href="login.php">Login disini</a>
        </div>
    </div>
    
    <script>
        // Simple countdown timer
        let hours = 2;
        let minutes = 0;
        let seconds = 0;
        
        function updateCountdown() {
            if (seconds === 0) {
                if (minutes === 0) {
                    if (hours === 0) {
                        document.getElementById('countdown').textContent = 'Segera...';
                        return;
                    }
                    hours--;
                    minutes = 59;
                    seconds = 59;
                } else {
                    minutes--;
                    seconds = 59;
                }
            } else {
                seconds--;
            }
            
            const hoursStr = hours.toString().padStart(2, '0');
            const minutesStr = minutes.toString().padStart(2, '0');
            const secondsStr = seconds.toString().padStart(2, '0');
            
            document.getElementById('countdown').textContent = `${hoursStr}:${minutesStr}:${secondsStr}`;
        }
        
        setInterval(updateCountdown, 1000);
    </script>
</body>
</html>