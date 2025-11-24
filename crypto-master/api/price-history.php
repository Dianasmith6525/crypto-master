<?php
/**
 * Price History API
 * Fetches historical cryptocurrency price data from CoinGecko API
 * 
 * Endpoints:
 * - GET: Get historical price data for a cryptocurrency
 */

require_once '../includes/config.php';

header('Content-Type: application/json');
set_cors_headers();

// Get parameters with validation
$crypto_id = isset($_GET['crypto_id']) ? htmlspecialchars(strip_tags($_GET['crypto_id']), ENT_QUOTES, 'UTF-8') : '';
$days = isset($_GET['days']) ? intval($_GET['days']) : 7; // Default 7 days
$vs_currency = isset($_GET['vs_currency']) ? htmlspecialchars(strip_tags($_GET['vs_currency']), ENT_QUOTES, 'UTF-8') : 'usd';

// Whitelist validation for crypto_id
$allowed_cryptos = ['bitcoin', 'ethereum', 'cardano', 'solana', 'polkadot', 'ripple', 'litecoin', 'dogecoin', 'binancecoin', 'avalanche-2'];
if (!empty($crypto_id) && !in_array(strtolower($crypto_id), $allowed_cryptos)) {
    // If not in whitelist, use as-is but with strict sanitization (for dynamic coins)
    $crypto_id = preg_replace('/[^a-z0-9\-]/', '', strtolower($crypto_id));
}

if (empty($crypto_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'crypto_id parameter is required'
    ]);
    exit;
}

// Validate days parameter
$allowed_days = [1, 7, 14, 30, 90, 180, 365, 'max'];
if (!in_array($days, $allowed_days) && $days !== 'max') {
    $days = 7; // Default to 7 days if invalid
}

try {
    // Fetch historical data from CoinGecko API
    $url = "https://api.coingecko.com/api/v3/coins/{$crypto_id}/market_chart?vs_currency={$vs_currency}&days={$days}";
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Set user agent
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception("cURL Error: " . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("API returned HTTP code: " . $httpCode);
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error: " . json_last_error_msg());
    }
    
    if (!isset($data['prices']) || !is_array($data['prices'])) {
        throw new Exception("Invalid response format from CoinGecko API");
    }
    
    // Format the data for Chart.js
    $formatted_data = [
        'labels' => [],
        'prices' => [],
        'volumes' => [],
        'market_caps' => []
    ];
    
    foreach ($data['prices'] as $price_point) {
        $timestamp = $price_point[0];
        $price = $price_point[1];
        
        // Format timestamp based on time range
        if ($days <= 1) {
            // Hourly format for 1 day
            $formatted_data['labels'][] = date('H:i', $timestamp / 1000);
        } else if ($days <= 7) {
            // Day and time for 7 days
            $formatted_data['labels'][] = date('M j, H:i', $timestamp / 1000);
        } else if ($days <= 30) {
            // Date for 30 days
            $formatted_data['labels'][] = date('M j', $timestamp / 1000);
        } else {
            // Month and year for longer periods
            $formatted_data['labels'][] = date('M Y', $timestamp / 1000);
        }
        
        $formatted_data['prices'][] = round($price, 2);
    }
    
    // Add volume data if available
    if (isset($data['total_volumes']) && is_array($data['total_volumes'])) {
        foreach ($data['total_volumes'] as $volume_point) {
            $formatted_data['volumes'][] = round($volume_point[1], 0);
        }
    }
    
    // Add market cap data if available
    if (isset($data['market_caps']) && is_array($data['market_caps'])) {
        foreach ($data['market_caps'] as $mc_point) {
            $formatted_data['market_caps'][] = round($mc_point[1], 0);
        }
    }
    
    // Calculate price statistics
    $prices = $formatted_data['prices'];
    $stats = [
        'current' => end($prices),
        'high' => max($prices),
        'low' => min($prices),
        'average' => round(array_sum($prices) / count($prices), 2),
        'change' => end($prices) - $prices[0],
        'change_percent' => round((end($prices) - $prices[0]) / $prices[0] * 100, 2)
    ];
    
    echo json_encode([
        'success' => true,
        'crypto_id' => $crypto_id,
        'vs_currency' => $vs_currency,
        'days' => $days,
        'data' => $formatted_data,
        'stats' => $stats,
        'data_points' => count($formatted_data['prices'])
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching price history: ' . $e->getMessage()
    ]);
}
?>