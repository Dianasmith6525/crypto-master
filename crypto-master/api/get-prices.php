<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
set_cors_headers();
header('Cache-Control: no-cache, no-store, must-revalidate');

// Get crypto from query parameter
$crypto = $_GET['crypto'] ?? 'bitcoin';

// Map crypto names to CoinGecko IDs
$cryptoMap = [
    'bitcoin' => 'bitcoin',
    'ethereum' => 'ethereum',
    'litecoin' => 'litecoin',
    'ripple' => 'ripple'
];

$cryptoId = $cryptoMap[$crypto] ?? 'bitcoin';

// Try CoinGecko API first
$url = "https://api.coingecko.com/api/v3/simple/price?ids=$cryptoId&vs_currencies=usd&include_market_cap=true&include_24hr_vol=true&include_24hr_change=true";

$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'header' => 'Accept: application/json'
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && isset($data[$cryptoId])) {
        echo json_encode([
            'success' => true,
            'source' => 'coingecko',
            'data' => $data[$cryptoId],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// CoinGecko failed, try Binance
$binanceSymbols = [
    'bitcoin' => 'BTCUSDT',
    'ethereum' => 'ETHUSDT',
    'litecoin' => 'LTCUSDT',
    'ripple' => 'XRPUSDT'
];

$symbol = $binanceSymbols[$crypto] ?? 'BTCUSDT';
$binanceUrl = "https://api.binance.com/api/v3/ticker/24hr?symbol=$symbol";

$binanceResponse = @file_get_contents($binanceUrl, false, $context);

if ($binanceResponse !== false) {
    $binanceData = json_decode($binanceResponse, true);
    if ($binanceData && isset($binanceData['lastPrice'])) {
        // Get supply for market cap
        $supplies = [
            'bitcoin' => 21000000,
            'ethereum' => 120500000,
            'litecoin' => 84000000,
            'ripple' => 99990000000
        ];
        $supply = $supplies[$crypto] ?? 1000000;
        
        echo json_encode([
            'success' => true,
            'source' => 'binance',
            'data' => [
                'usd' => (float)$binanceData['lastPrice'],
                'usd_market_cap' => (float)$binanceData['lastPrice'] * $supply,
                'usd_24h_vol' => (float)$binanceData['quoteAssetVolume'],
                'usd_24h_change' => (float)$binanceData['priceChangePercent'],
                'usd_24h_high' => (float)$binanceData['highPrice'],
                'usd_24h_low' => (float)$binanceData['lowPrice']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Both APIs failed
http_response_code(503);
echo json_encode([
    'success' => false,
    'error' => 'Unable to fetch prices from both CoinGecko and Binance APIs',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
