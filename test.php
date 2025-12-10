<?php
/**
 * DEBUG - Atlantic Payment Methods
 * Test berbagai endpoint untuk menemukan yang benar
 */

require_once 'config.php';

// Check admin
if (!isLoggedIn()) {
    die('Please login first');
}

$user = getUserData();
if ($user['is_admin'] != 1) {
    die('Admin only');
}

// Atlantic API Config
$atlanticApiKey = '';
$atlanticApiUrl = 'https://atlantich2h.com';

// Load from database
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('atlantic_api_key', 'atlantic_api_url')");
    while ($row = $stmt->fetch()) {
        if ($row['setting_key'] === 'atlantic_api_key') $atlanticApiKey = $row['setting_value'];
        if ($row['setting_key'] === 'atlantic_api_url') $atlanticApiUrl = $row['setting_value'];
    }
} catch (Exception $e) {}

// Fallback to constants
if (empty($atlanticApiKey) && defined('ATLANTIC_API_KEY')) $atlanticApiKey = ATLANTIC_API_KEY;
if (defined('ATLANTIC_API_URL')) $atlanticApiUrl = ATLANTIC_API_URL;

/**
 * Test API endpoint
 */
function testEndpoint($url, $params = []) {
    global $atlanticApiKey;
    
    $params['api_key'] = $atlanticApiKey;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'error' => $curlError,
        'response' => $response,
        'data' => json_decode($response, true)
    ];
}

// List of possible endpoints to test
$endpointsToTest = [
    // Payment/Deposit methods
    '/payment/methods',
    '/payment/method',
    '/deposit/methods',
    '/deposit/method',
    '/metode/pembayaran',
    '/metode',
    '/payment_methods',
    '/deposit_methods',
    '/api/payment/methods',
    '/api/deposit/methods',
    
    // Balance
    '/balance',
    '/saldo',
    '/user/balance',
    '/api/balance',
    
    // Profile/Info
    '/profile',
    '/user/profile',
    '/info',
    '/api/info',
];

$results = [];
$testEndpoint = $_GET['test'] ?? '';

if ($testEndpoint === 'all') {
    foreach ($endpointsToTest as $endpoint) {
        $url = rtrim($atlanticApiUrl, '/') . $endpoint;
        $results[$endpoint] = testEndpoint($url);
    }
} elseif ($testEndpoint) {
    $url = rtrim($atlanticApiUrl, '/') . '/' . ltrim($testEndpoint, '/');
    $results[$testEndpoint] = testEndpoint($url);
}

// Test custom endpoint
$customEndpoint = $_GET['custom'] ?? '';
if ($customEndpoint) {
    $url = rtrim($atlanticApiUrl, '/') . '/' . ltrim($customEndpoint, '/');
    $results['CUSTOM: ' . $customEndpoint] = testEndpoint($url);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Atlantic Payment Methods</title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --border: #334155;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --primary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { margin-bottom: 1.5rem; }
        
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 1rem; margin-bottom: 1.5rem; overflow: hidden; }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); font-weight: 600; }
        .card-body { padding: 1.5rem; }
        
        .config { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .config-item { padding: 1rem; background: var(--bg); border-radius: 0.5rem; }
        .config-label { font-size: 0.8rem; color: var(--muted); }
        .config-value { font-family: monospace; word-break: break-all; }
        
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; margin: 0.25rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        
        .endpoints-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .endpoint-btn { padding: 0.5rem 1rem; background: var(--bg); border: 1px solid var(--border); border-radius: 0.5rem; color: var(--text); text-decoration: none; font-size: 0.85rem; font-family: monospace; }
        .endpoint-btn:hover { background: var(--primary); border-color: var(--primary); }
        
        .custom-form { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .custom-form input { flex: 1; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: monospace; }
        
        .result { background: var(--bg); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; }
        .result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .result-endpoint { font-family: monospace; color: var(--primary); }
        .result-status { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.8rem; font-weight: 600; }
        .result-status.success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .result-status.error { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .result-status.warning { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        
        .result-body { font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; background: #000; padding: 1rem; border-radius: 0.5rem; }
        
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-info { background: rgba(99, 102, 241, 0.1); border: 1px solid var(--primary); }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: rgba(255,255,255,0.05); font-size: 0.85rem; color: var(--muted); }
        
        .nav-links { margin-bottom: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .nav-links a { padding: 0.5rem 1rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 0.5rem; color: var(--muted); text-decoration: none; }
        .nav-links a:hover { background: var(--primary); border-color: var(--primary); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Atlantic Payment Methods</h1>
        
        <nav class="nav-links">
            <a href="admin.php">← Dashboard</a>
            <a href="atlantic-debug.php">Test Price List</a>
            <a href="atlantic-providers.php">Providers</a>
            <a href="admin-atlantic-games.php">Games</a>
        </nav>
        
        <!-- Config -->
        <div class="card">
            <div class="card-header">⚙️ Konfigurasi API</div>
            <div class="card-body">
                <div class="config">
                    <div class="config-item">
                        <div class="config-label">API URL</div>
                        <div class="config-value"><?php echo htmlspecialchars($atlanticApiUrl); ?></div>
                    </div>
                    <div class="config-item">
                        <div class="config-label">API Key</div>
                        <div class="config-value" style="color: <?php echo $atlanticApiKey ? 'var(--success)' : 'var(--danger)'; ?>">
                            <?php echo $atlanticApiKey ? substr($atlanticApiKey, 0, 4) . '****' . substr($atlanticApiKey, -4) : '⚠️ NOT SET'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Test Endpoints -->
        <div class="card">
            <div class="card-header">🧪 Test Endpoints</div>
            <div class="card-body">
                <p style="margin-bottom: 1rem; color: var(--muted);">Klik endpoint untuk test, atau test semua sekaligus:</p>
                
                <div style="margin-bottom: 1rem;">
                    <a href="?test=all" class="btn btn-primary">🔍 Test Semua Endpoints</a>
                </div>
                
                <h4 style="margin-bottom: 0.5rem; color: var(--muted);">Payment Methods:</h4>
                <div class="endpoints-list">
                    <?php foreach (array_slice($endpointsToTest, 0, 10) as $ep): ?>
                    <a href="?test=<?php echo urlencode($ep); ?>" class="endpoint-btn"><?php echo $ep; ?></a>
                    <?php endforeach; ?>
                </div>
                
                <h4 style="margin-bottom: 0.5rem; color: var(--muted);">Balance/Profile:</h4>
                <div class="endpoints-list">
                    <?php foreach (array_slice($endpointsToTest, 10) as $ep): ?>
                    <a href="?test=<?php echo urlencode($ep); ?>" class="endpoint-btn"><?php echo $ep; ?></a>
                    <?php endforeach; ?>
                </div>
                
                <h4 style="margin-bottom: 0.5rem; color: var(--muted);">Custom Endpoint:</h4>
                <form method="GET" class="custom-form">
                    <input type="text" name="custom" placeholder="/endpoint/path" value="<?php echo htmlspecialchars($customEndpoint); ?>">
                    <button type="submit" class="btn btn-success">Test</button>
                </form>
            </div>
        </div>
        
        <!-- Results -->
        <?php if (!empty($results)): ?>
        <div class="card">
            <div class="card-header">📊 Hasil Test (<?php echo count($results); ?> endpoints)</div>
            <div class="card-body">
                <?php 
                $successEndpoints = [];
                foreach ($results as $endpoint => $result): 
                    $httpCode = $result['http_code'];
                    $isSuccess = $httpCode === 200 && isset($result['data']['status']) && $result['data']['status'] === true;
                    $hasData = !empty($result['data']['data']);
                    
                    if ($isSuccess && $hasData) {
                        $successEndpoints[$endpoint] = $result;
                    }
                    
                    $statusClass = 'error';
                    $statusText = "HTTP $httpCode";
                    if ($httpCode === 200) {
                        if ($isSuccess && $hasData) {
                            $statusClass = 'success';
                            $statusText = '✓ SUCCESS';
                        } elseif ($isSuccess) {
                            $statusClass = 'warning';
                            $statusText = '⚠ OK but empty';
                        } else {
                            $statusClass = 'warning';
                            $statusText = '⚠ Status false';
                        }
                    }
                ?>
                <div class="result">
                    <div class="result-header">
                        <span class="result-endpoint"><?php echo htmlspecialchars($endpoint); ?></span>
                        <span class="result-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </div>
                    <?php if ($result['error']): ?>
                    <div style="color: var(--danger); margin-bottom: 0.5rem;">Error: <?php echo htmlspecialchars($result['error']); ?></div>
                    <?php endif; ?>
                    <details>
                        <summary style="cursor: pointer; padding: 0.5rem 0;">Lihat Response</summary>
                        <div class="result-body"><?php echo htmlspecialchars($result['response'] ?: 'Empty response'); ?></div>
                    </details>
                </div>
                <?php endforeach; ?>
                
                <?php if (!empty($successEndpoints)): ?>
                <div class="alert alert-success">
                    <strong>✓ Endpoint yang berhasil:</strong><br>
                    <?php foreach ($successEndpoints as $ep => $r): ?>
                    <code><?php echo htmlspecialchars($ep); ?></code> - <?php echo count($r['data']['data']); ?> items<br>
                    <?php endforeach; ?>
                </div>
                
                <!-- Show data from first successful endpoint -->
                <?php 
                $firstSuccess = reset($successEndpoints);
                $firstEndpoint = key($successEndpoints);
                if ($firstSuccess && !empty($firstSuccess['data']['data'])):
                ?>
                <h4 style="margin: 1rem 0;">📦 Data dari <?php echo htmlspecialchars($firstEndpoint); ?>:</h4>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <?php 
                                $firstItem = $firstSuccess['data']['data'][0] ?? [];
                                foreach (array_keys($firstItem) as $key): 
                                ?>
                                <th><?php echo htmlspecialchars($key); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($firstSuccess['data']['data'], 0, 20) as $item): ?>
                            <tr>
                                <?php foreach ($item as $val): ?>
                                <td><?php echo htmlspecialchars(is_array($val) ? json_encode($val) : $val); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Instructions -->
        <div class="card">
            <div class="card-header">📖 Petunjuk</div>
            <div class="card-body">
                <ol style="line-height: 2;">
                    <li>Klik <strong>"Test Semua Endpoints"</strong> untuk mencari endpoint payment methods yang benar</li>
                    <li>Lihat endpoint mana yang bertanda <span style="color: var(--success);">✓ SUCCESS</span></li>
                    <li>Catat endpoint yang berhasil, lalu update class <code>AtlanticH2H.php</code></li>
                    <li>Atau coba endpoint custom jika tahu dari dokumentasi Atlantic</li>
                </ol>
                
                <div class="alert alert-info" style="margin-top: 1rem;">
                    <strong>💡 Tips:</strong> Cek dokumentasi Atlantic H2H untuk endpoint yang benar. 
                    Biasanya ada di menu API Documentation atau Panduan Integrasi.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
