<?php
/**
 * QR Code Helper Class
 * Helper untuk generate QR Code image dari string
 * Menggunakan chillerlan/php-qrcode library
 */

class QRCodeHelper {
    
    /**
     * Generate QR Code image dan return sebagai base64 data URL
     * 
     * @param string $data String yang akan di-encode ke QR
     * @param int $size Ukuran QR code (default: 256)
     * @return string Base64 data URL
     */
    public static function generateBase64($data, $size = 256) {
        try {
            // Bersihkan data dari encoding yang salah
            $cleanData = self::cleanQRISString($data);
            
            // Generate QR code menggunakan Google Charts API sebagai fallback
            // Ini tidak memerlukan library tambahan dan sangat reliable
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/';
            $params = http_build_query([
                'data' => $cleanData,
                'size' => $size . 'x' . $size,
                'format' => 'png',
                'ecc' => 'H' // High error correction
            ]);
            
            $imageData = @file_get_contents($qrUrl . '?' . $params);
            
            if ($imageData === false) {
                throw new Exception('Failed to generate QR code from API');
            }
            
            // Convert to base64 data URL
            $base64 = base64_encode($imageData);
            return 'data:image/png;base64,' . $base64;
            
        } catch (Exception $e) {
            error_log('QR Code Generation Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate QR Code dan save sebagai file PNG
     * 
     * @param string $data String yang akan di-encode ke QR
     * @param string $filename Path file output
     * @param int $size Ukuran QR code (default: 256)
     * @return bool Success status
     */
    public static function generateFile($data, $filename, $size = 256) {
        try {
            $cleanData = self::cleanQRISString($data);
            
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/';
            $params = http_build_query([
                'data' => $cleanData,
                'size' => $size . 'x' . $size,
                'format' => 'png',
                'ecc' => 'H'
            ]);
            
            $imageData = @file_get_contents($qrUrl . '?' . $params);
            
            if ($imageData === false) {
                throw new Exception('Failed to generate QR code from API');
            }
            
            // Save to file
            $result = file_put_contents($filename, $imageData);
            
            return $result !== false;
            
        } catch (Exception $e) {
            error_log('QR Code File Generation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean QRIS string untuk memastikan format yang benar
     * 
     * @param string $qrisString QRIS string dari payment gateway
     * @return string Cleaned QRIS string
     */
    private static function cleanQRISString($qrisString) {
        // Remove any sandbox markers
        $cleaned = str_replace('THIS.IS.JUST.AN.EXAMPLE.FOR.SANDBOX.', '', $qrisString);
        
        // Remove any leading/trailing whitespace only
        $cleaned = trim($cleaned);
        
        // CRITICAL: JANGAN ENCODE APAPUN!
        // QRIS string HARUS RAW/ASLI tanpa encoding
        // Jangan replace spasi, jangan urlencode, jangan addslashes
        // QRIS format sangat sensitif - encoding apapun akan merusak QR code
        
        return $cleaned;
    }
    
    /**
     * Validate QRIS string format
     * 
     * @param string $qrisString QRIS string to validate
     * @return bool Valid status
     */
    public static function validateQRIS($qrisString) {
        // Basic QRIS validation
        // QRIS harus dimulai dengan "0002" dan berisi "5802ID" (Indonesia country code)
        if (strlen($qrisString) < 50) {
            return false;
        }
        
        if (substr($qrisString, 0, 4) !== '0002') {
            return false;
        }
        
        if (strpos($qrisString, '5802ID') === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate QR Code dengan custom styling
     * Menggunakan metode inline SVG untuk customization
     * 
     * @param string $data String untuk QR code
     * @param array $options Custom options (color, logo, etc)
     * @return string SVG string atau base64 image
     */
    public static function generateCustom($data, $options = []) {
        $defaults = [
            'size' => 256,
            'color' => '000000',
            'bgcolor' => 'ffffff',
            'format' => 'png'
        ];
        
        $options = array_merge($defaults, $options);
        $cleanData = self::cleanQRISString($data);
        
        try {
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/';
            $params = http_build_query([
                'data' => $cleanData,
                'size' => $options['size'] . 'x' . $options['size'],
                'format' => $options['format'],
                'color' => $options['color'],
                'bgcolor' => $options['bgcolor'],
                'ecc' => 'H'
            ]);
            
            $imageData = @file_get_contents($qrUrl . '?' . $params);
            
            if ($imageData === false) {
                throw new Exception('Failed to generate custom QR code');
            }
            
            if ($options['format'] === 'svg') {
                return $imageData;
            }
            
            // Return as base64 for other formats
            $base64 = base64_encode($imageData);
            return 'data:image/' . $options['format'] . ';base64,' . $base64;
            
        } catch (Exception $e) {
            error_log('Custom QR Code Generation Error: ' . $e->getMessage());
            return null;
        }
    }
}