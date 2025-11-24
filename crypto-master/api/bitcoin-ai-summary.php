<?php
/**
 * Bitcoin Price AI Summary API
 * Fetches live Bitcoin price from CoinGecko and uses AI to summarize
 * Accessible via: api/bitcoin-ai-summary.php
 */

require_once '../includes/config.php';

header('Content-Type: application/json');
set_cors_headers();
header('Access-Control-Allow-Methods: GET, POST');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/**
 * Fetch Bitcoin price from CoinGecko API
 */
function getBitcoinPrice() {
    $url = "https://api.coingecko.com/api/v3/simple/price";
    $params = [
        "ids" => "bitcoin",
        "vs_currencies" => "usd",
        "include_24hr_change" => "true",
        "include_market_cap" => "true",
        "include_last_updated_at" => "true"
    ];
    
    $query_string = http_build_query($params);
    $full_url = $url . "?" . $query_string;
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => 'Accept: application/json'
        ]
    ]);
    
    try {
        $response = @file_get_contents($full_url, false, $context);
        if ($response === false) {
            throw new Exception("Failed to fetch from CoinGecko API");
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid JSON response from CoinGecko");
        }
        
        return $data;
    } catch (Exception $e) {
        return [
            "error" => true,
            "message" => "API Error: " . $e->getMessage()
        ];
    }
}

/**
 * Send Bitcoin data to OpenAI for AI summary
 */
function askAIAboutPrice($api_data) {
    // Get OpenAI API key from environment or config
    $openai_key = getenv('OPENAI_API_KEY') ?: (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : null);
    
    if (!$openai_key) {
        return [
            "error" => true,
            "message" => "OpenAI API key not configured. Set OPENAI_API_KEY environment variable or add to config.php"
        ];
    }
    
    $api_data_json = json_encode($api_data, JSON_PRETTY_PRINT);
    
    $prompt = "You are a financial data assistant. You MUST use ONLY the API data provided below. Never guess or estimate any prices.\n\n" .
              "API DATA:\n" . $api_data_json . "\n\n" .
              "Extract and summarize:\n" .
              "- Bitcoin price in USD\n" .
              "- 24h percent change\n" .
              "- Market cap\n" .
              "- Last updated time\n\n" .
              "If any field is missing, write: 'data not provided'.";
    
    $request_body = [
        "model" => "gpt-4",
        "messages" => [
            [
                "role" => "user",
                "content" => $prompt
            ]
        ],
        "temperature" => 0.5,
        "max_tokens" => 500
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $openai_key
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
    
    try {
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($http_code !== 200) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : "Unknown error";
            throw new Exception("OpenAI API Error (HTTP $http_code): " . $error_msg);
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("Unexpected response format from OpenAI");
        }
        
        return [
            "success" => true,
            "summary" => $data['choices'][0]['message']['content'],
            "tokens_used" => $data['usage'] ?? null
        ];
    } catch (Exception $e) {
        return [
            "error" => true,
            "message" => $e->getMessage()
        ];
    }
}

/**
 * Fallback: Format Bitcoin price without AI
 */
function formatBitcoinPriceFallback($api_data) {
    if (isset($api_data['error'])) {
        return [
            "error" => true,
            "message" => $api_data['message']
        ];
    }
    
    $bitcoin = $api_data['bitcoin'] ?? [];
    $price = $bitcoin['usd'] ?? 'N/A';
    $change_24h = $bitcoin['usd_24h_change'] ?? 'N/A';
    $market_cap = $bitcoin['usd_market_cap'] ?? 'N/A';
    $last_updated = $bitcoin['last_updated_at'] ?? 'N/A';
    
    $summary = "Bitcoin Price Summary\n";
    $summary .= "=====================\n\n";
    $summary .= "Price (USD): $" . (is_numeric($price) ? number_format($price, 2) : $price) . "\n";
    $summary .= "24h Change: " . (is_numeric($change_24h) ? $change_24h . "%" : $change_24h) . "\n";
    $summary .= "Market Cap: $" . (is_numeric($market_cap) ? number_format($market_cap / 1e9, 2) . "B" : $market_cap) . "\n";
    $summary .= "Last Updated: " . $last_updated . "\n";
    
    return [
        "success" => true,
        "summary" => $summary,
        "raw_data" => $bitcoin,
        "note" => "AI summary not available - showing formatted API data"
    ];
}

/**
 * Main API Handler
 */
try {
    // Get request parameters
    $action = $_GET['action'] ?? 'summary';
    $use_ai = isset($_GET['use_ai']) ? $_GET['use_ai'] === 'true' : true;
    
    // Fetch Bitcoin price
    $bitcoin_data = getBitcoinPrice();
    
    if (isset($bitcoin_data['error'])) {
        http_response_code(500);
        echo json_encode([
            "error" => true,
            "message" => $bitcoin_data['message'],
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Determine response format
    if ($action === 'raw') {
        // Return raw API data only
        echo json_encode([
            "success" => true,
            "data" => $bitcoin_data,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } elseif ($use_ai) {
        // Try AI summary first, fallback to formatted data
        $ai_result = askAIAboutPrice($bitcoin_data);
        
        if (isset($ai_result['error'])) {
            // AI failed, use fallback
            $fallback_result = formatBitcoinPriceFallback($bitcoin_data);
            echo json_encode($fallback_result + [
                "ai_error" => $ai_result['message'],
                "timestamp" => date('Y-m-d H:i:s')
            ]);
        } else {
            // AI success
            echo json_encode($ai_result + [
                "raw_data" => $bitcoin_data['bitcoin'] ?? [],
                "timestamp" => date('Y-m-d H:i:s')
            ]);
        }
    } else {
        // Fallback format (no AI)
        $fallback_result = formatBitcoinPriceFallback($bitcoin_data);
        echo json_encode($fallback_result + [
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage(),
        "timestamp" => date('Y-m-d H:i:s')
    ]);
}
?>
