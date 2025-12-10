<?php
/**
 * Contoh Penggunaan QRCodeHelper Class
 * File ini mendemonstrasikan berbagai cara menggunakan QRCodeHelper
 */

require_once 'QRCodeHelper.php';

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>QR Code Helper - Examples</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 1rem;
        }
        h2 {
            color: #555;
            margin-top: 0;
        }
        .qr-container {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .qr-item {
            text-align: center;
        }
        .qr-item img {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            background: white;
        }
        code {
            background: #f4f4f4;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
        }
        .success {
            color: #4CAF50;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            font-weight: bold;
        }
        .info {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>";

echo "<h1>🎯 QR Code Helper - Contoh Penggunaan</h1>";

// ================================================================
// Example 1: Generate QR Code as Base64
// ================================================================
echo "<div class='card'>";
echo "<h2>1️⃣ Generate QR Code sebagai Base64 Data URL</h2>";
echo "<p>Cocok untuk: Display langsung di HTML tanpa save file</p>";

$sampleQRIS = "00020101021226610016ID.CO.SHOPEE.WWW01189360091800216005230208216005230303UME51440014ID.CO.QRIS.WWW0215ID10243228429300303UME52047929530336054071317.005802ID5907Pakasir6012KAB. KEBUMEN6105543926222051814838848485737686263045E79";

echo "<pre><code class='php'>
\$qrisString = '00020101021226610016ID.CO.SHOPEE.WWW...';
\$qrBase64 = QRCodeHelper::generateBase64(\$qrisString, 256);
echo '&lt;img src=\"' . \$qrBase64 . '\"&gt;';
</code></pre>";

$qrBase64 = QRCodeHelper::generateBase64($sampleQRIS, 256);

if ($qrBase64) {
    echo "<p class='success'>✅ QR Code generated successfully!</p>";
    echo "<div class='qr-container'>";
    echo "<div class='qr-item'>";
    echo "<img src='{$qrBase64}' alt='QR Code' style='max-width: 256px;'>";
    echo "<p><strong>Size: 256x256</strong></p>";
    echo "</div>";
    
    // Generate larger size
    $qrBase64Large = QRCodeHelper::generateBase64($sampleQRIS, 400);
    echo "<div class='qr-item'>";
    echo "<img src='{$qrBase64Large}' alt='QR Code Large' style='max-width: 400px;'>";
    echo "<p><strong>Size: 400x400</strong></p>";
    echo "</div>";
    echo "</div>";
} else {
    echo "<p class='error'>❌ Failed to generate QR code</p>";
}

echo "</div>";

// ================================================================
// Example 2: Generate QR Code as File
// ================================================================
echo "<div class='card'>";
echo "<h2>2️⃣ Generate QR Code dan Save sebagai File PNG</h2>";
echo "<p>Cocok untuk: Download QR, email attachment, atau static storage</p>";

echo "<pre><code class='php'>
\$filename = 'qr_codes/order_12345.png';
\$success = QRCodeHelper::generateFile(\$qrisString, \$filename, 300);

if (\$success) {
    echo 'QR Code saved to: ' . \$filename;
}
</code></pre>";

// Create directory if not exists
$qrDir = __DIR__ . '/qr_examples';
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0755, true);
}

$filename = $qrDir . '/example_order_' . time() . '.png';
$success = QRCodeHelper::generateFile($sampleQRIS, $filename, 300);

if ($success) {
    echo "<p class='success'>✅ QR Code saved successfully!</p>";
    echo "<p><strong>File:</strong> <code>" . basename($filename) . "</code></p>";
    
    // Display the saved file
    $relativeFilename = 'qr_examples/' . basename($filename);
    echo "<div class='qr-container'>";
    echo "<div class='qr-item'>";
    echo "<img src='{$relativeFilename}' alt='Saved QR Code' style='max-width: 300px;'>";
    echo "<p><a href='{$relativeFilename}' download>⬇️ Download QR Code</a></p>";
    echo "</div>";
    echo "</div>";
} else {
    echo "<p class='error'>❌ Failed to save QR code file</p>";
}

echo "</div>";

// ================================================================
// Example 3: Validate QRIS String
// ================================================================
echo "<div class='card'>";
echo "<h2>3️⃣ Validasi Format QRIS String</h2>";
echo "<p>Pastikan QRIS string valid sebelum generate QR code</p>";

echo "<pre><code class='php'>
if (QRCodeHelper::validateQRIS(\$qrisString)) {
    // Generate QR code
} else {
    // Show error message
}
</code></pre>";

$testStrings = [
    [
        'label' => 'Valid QRIS',
        'value' => $sampleQRIS,
        'expected' => true
    ],
    [
        'label' => 'Invalid - Too short',
        'value' => '00020101021',
        'expected' => false
    ],
    [
        'label' => 'Invalid - Wrong start',
        'value' => '11110101021226610016ID.CO.SHOPEE.WWW...',
        'expected' => false
    ],
    [
        'label' => 'Invalid - Missing country code',
        'value' => '00020101021226610016ID.CO.SHOPEE.WWW01189360091800216005230208216005230303UME',
        'expected' => false
    ]
];

echo "<table style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #f5f5f5;'>";
echo "<th style='padding: 1rem; text-align: left; border: 1px solid #ddd;'>Test Case</th>";
echo "<th style='padding: 1rem; text-align: left; border: 1px solid #ddd;'>String Preview</th>";
echo "<th style='padding: 1rem; text-align: center; border: 1px solid #ddd;'>Valid?</th>";
echo "<th style='padding: 1rem; text-align: center; border: 1px solid #ddd;'>Result</th>";
echo "</tr>";

foreach ($testStrings as $test) {
    $isValid = QRCodeHelper::validateQRIS($test['value']);
    $status = $isValid ? '✅ Valid' : '❌ Invalid';
    $statusClass = $isValid ? 'success' : 'error';
    $preview = substr($test['value'], 0, 30) . '...';
    
    echo "<tr>";
    echo "<td style='padding: 1rem; border: 1px solid #ddd;'><strong>{$test['label']}</strong></td>";
    echo "<td style='padding: 1rem; border: 1px solid #ddd;'><code>{$preview}</code></td>";
    echo "<td style='padding: 1rem; border: 1px solid #ddd; text-align: center;'>{$test['expected']} expected</td>";
    echo "<td class='{$statusClass}' style='padding: 1rem; border: 1px solid #ddd; text-align: center;'>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

echo "</div>";

// ================================================================
// Example 4: Custom QR Code with Colors
// ================================================================
echo "<div class='card'>";
echo "<h2>4️⃣ Generate QR Code dengan Custom Colors</h2>";
echo "<p>Personalisasi QR code dengan warna brand Anda</p>";

echo "<pre><code class='php'>
\$qrCustom = QRCodeHelper::generateCustom(\$qrisString, [
    'size' => 300,
    'color' => '4CAF50',    // Green QR
    'bgcolor' => 'ffffff',  // White background
    'format' => 'png'
]);
</code></pre>";

$colorVariants = [
    ['color' => '000000', 'bgcolor' => 'ffffff', 'name' => 'Classic (Black)'],
    ['color' => '4CAF50', 'bgcolor' => 'ffffff', 'name' => 'Green'],
    ['color' => '2196F3', 'bgcolor' => 'ffffff', 'name' => 'Blue'],
    ['color' => 'FF5722', 'bgcolor' => 'ffffff', 'name' => 'Orange']
];

echo "<div class='qr-container'>";
foreach ($colorVariants as $variant) {
    $qrCustom = QRCodeHelper::generateCustom($sampleQRIS, [
        'size' => 200,
        'color' => $variant['color'],
        'bgcolor' => $variant['bgcolor'],
        'format' => 'png'
    ]);
    
    if ($qrCustom) {
        echo "<div class='qr-item'>";
        echo "<img src='{$qrCustom}' alt='Custom QR' style='max-width: 200px;'>";
        echo "<p><strong>{$variant['name']}</strong></p>";
        echo "<p><small>Color: #{$variant['color']}</small></p>";
        echo "</div>";
    }
}
echo "</div>";

echo "</div>";

// ================================================================
// Example 5: Practical Use Case - Payment Flow
// ================================================================
echo "<div class='card'>";
echo "<h2>5️⃣ Use Case: Complete Payment Flow</h2>";
echo "<p>Simulasi real-world payment scenario</p>";

echo "<div class='info'>";
echo "<strong>📝 Scenario:</strong><br>";
echo "User membeli produk Netflix Private seharga Rp 140,100. Payment menggunakan QRIS.";
echo "</div>";

echo "<pre><code class='php'>
// 1. User checkout
\$order_id = 'ORDER-' . time() . '-' . \$user_id;
\$amount = 140100;

// 2. Create payment via Pakasir API
\$pakasir_response = createPakasirPayment('qris', \$order_id, \$amount);
\$payment_number = \$pakasir_response['payment']['payment_number'];

// 3. Generate QR Code
\$qr_image = QRCodeHelper::generateBase64(\$payment_number);

// 4. Save to database
\$stmt->execute([
    'order_id' => \$order_id,
    'payment_number' => \$payment_number,
    'qr_image' => \$qr_image,
    'status' => 'pending'
]);

// 5. Display to user
echo '&lt;img src=\"' . \$qr_image . '\" alt=\"Scan untuk bayar\"&gt;';
</code></pre>";

// Simulate the flow
$simulatedOrderId = 'ORDER-' . time() . '-999';
$simulatedAmount = 140100;
$simulatedFee = 317;
$simulatedTotal = $simulatedAmount + $simulatedFee;

echo "<div style='background: #f9f9f9; padding: 2rem; border-radius: 8px; margin-top: 1rem;'>";
echo "<h3 style='margin-top: 0;'>💳 Payment Details</h3>";
echo "<table style='width: 100%;'>";
echo "<tr><td style='padding: 0.5rem 0;'><strong>Order ID:</strong></td><td>{$simulatedOrderId}</td></tr>";
echo "<tr><td style='padding: 0.5rem 0;'><strong>Amount:</strong></td><td>Rp " . number_format($simulatedAmount, 0, ',', '.') . "</td></tr>";
echo "<tr><td style='padding: 0.5rem 0;'><strong>Admin Fee:</strong></td><td>Rp " . number_format($simulatedFee, 0, ',', '.') . "</td></tr>";
echo "<tr><td style='padding: 0.5rem 0; border-top: 2px solid #ddd; padding-top: 1rem;'><strong>Total Payment:</strong></td><td style='border-top: 2px solid #ddd; padding-top: 1rem;'><strong style='color: #4CAF50; font-size: 1.2rem;'>Rp " . number_format($simulatedTotal, 0, ',', '.') . "</strong></td></tr>";
echo "</table>";

echo "<div style='margin-top: 2rem; text-align: center;'>";
echo "<h4>Scan QR Code untuk Bayar</h4>";
$paymentQR = QRCodeHelper::generateBase64($sampleQRIS, 300);
if ($paymentQR) {
    echo "<img src='{$paymentQR}' alt='Payment QR' style='max-width: 300px; border: 3px solid #4CAF50; border-radius: 12px; padding: 1rem; background: white;'>";
    echo "<p style='margin-top: 1rem; color: #666;'><small>Scan dengan GoPay, OVO, DANA, atau ShopeePay</small></p>";
}
echo "</div>";

echo "</div>";

echo "</div>";

// ================================================================
// Example 6: Error Handling
// ================================================================
echo "<div class='card'>";
echo "<h2>6️⃣ Error Handling Best Practices</h2>";
echo "<p>Cara handle errors dengan graceful</p>";

echo "<pre><code class='php'>
try {
    // Validate input
    if (empty(\$payment_number)) {
        throw new Exception('Payment number is empty');
    }
    
    if (!QRCodeHelper::validateQRIS(\$payment_number)) {
        throw new Exception('Invalid QRIS format');
    }
    
    // Generate QR code
    \$qr_image = QRCodeHelper::generateBase64(\$payment_number);
    
    if (!\$qr_image) {
        throw new Exception('Failed to generate QR code');
    }
    
    // Success
    return [
        'success' => true,
        'qr_image' => \$qr_image
    ];
    
} catch (Exception \$e) {
    // Log error
    error_log('QR Generation Error: ' . \$e->getMessage());
    
    // Return error response
    return [
        'success' => false,
        'error' => \$e->getMessage()
    ];
}
</code></pre>";

// Demonstrate error handling
$errorCases = [
    ['input' => '', 'description' => 'Empty string'],
    ['input' => '12345', 'description' => 'Too short'],
    ['input' => 'invalid-qris-string', 'description' => 'Invalid format']
];

echo "<h3>Error Test Cases:</h3>";
echo "<table style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #f5f5f5;'>";
echo "<th style='padding: 1rem; text-align: left; border: 1px solid #ddd;'>Input</th>";
echo "<th style='padding: 1rem; text-align: left; border: 1px solid #ddd;'>Description</th>";
echo "<th style='padding: 1rem; text-align: left; border: 1px solid #ddd;'>Result</th>";
echo "</tr>";

foreach ($errorCases as $case) {
    $errorMessage = '';
    
    try {
        if (empty($case['input'])) {
            throw new Exception('Payment number is empty');
        }
        
        if (!QRCodeHelper::validateQRIS($case['input'])) {
            throw new Exception('Invalid QRIS format');
        }
        
        $errorMessage = '<span class="success">✅ Passed validation</span>';
    } catch (Exception $e) {
        $errorMessage = '<span class="error">❌ ' . htmlspecialchars($e->getMessage()) . '</span>';
    }
    
    echo "<tr>";
    echo "<td style='padding: 1rem; border: 1px solid #ddd;'><code>" . htmlspecialchars(substr($case['input'], 0, 30)) . "</code></td>";
    echo "<td style='padding: 1rem; border: 1px solid #ddd;'>{$case['description']}</td>";
    echo "<td style='padding: 1rem; border: 1px solid #ddd;'>{$errorMessage}</td>";
    echo "</tr>";
}

echo "</table>";

echo "</div>";

// ================================================================
// Performance Metrics
// ================================================================
echo "<div class='card'>";
echo "<h2>⚡ Performance Metrics</h2>";

$startTime = microtime(true);
$qrPerf = QRCodeHelper::generateBase64($sampleQRIS, 256);
$endTime = microtime(true);
$executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

echo "<table style='width: 100%;'>";
echo "<tr><td style='padding: 0.5rem 0;'><strong>Generation Time:</strong></td><td>" . number_format($executionTime, 2) . " ms</td></tr>";
echo "<tr><td style='padding: 0.5rem 0;'><strong>QR Size:</strong></td><td>256x256 pixels</td></tr>";
echo "<tr><td style='padding: 0.5rem 0;'><strong>Image Format:</strong></td><td>PNG (base64)</td></tr>";
echo "<tr><td style='padding: 0.5rem 0;'><strong>Data Length:</strong></td><td>" . strlen($sampleQRIS) . " characters</td></tr>";

if ($qrPerf) {
    $base64Size = strlen($qrPerf);
    echo "<tr><td style='padding: 0.5rem 0;'><strong>Base64 Size:</strong></td><td>" . number_format($base64Size / 1024, 2) . " KB</td></tr>";
}

echo "</table>";

$avgTime = $executionTime;
if ($avgTime < 1000) {
    echo "<p class='success'>✅ Excellent performance! Average generation time under 1 second.</p>";
} elseif ($avgTime < 2000) {
    echo "<p style='color: #FFA500;'>⚠️ Good performance. Average generation time under 2 seconds.</p>";
} else {
    echo "<p class='error'>❌ Slow performance. Consider optimization or caching.</p>";
}

echo "</div>";

// Footer
echo "<div style='text-align: center; padding: 2rem; color: #999;'>";
echo "<p>📚 QRCodeHelper Examples | Version 1.0.0</p>";
echo "<p><small>Generated on " . date('Y-m-d H:i:s') . "</small></p>";
echo "</div>";

echo "</body></html>";
?>