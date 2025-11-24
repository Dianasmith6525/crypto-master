<?php
/**
 * Crypto News API
 * Fetches cryptocurrency news from CryptoPanic API
 */

require_once '../includes/config.php';

header('Content-Type: application/json');
set_cors_headers();

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'rising'; // rising, hot, latest, important
$currencies = isset($_GET['currencies']) ? $_GET['currencies'] : ''; // BTC,ETH,etc
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

try {
    // Using CryptoPanic free API (no key required for basic access)
    $url = "https://cryptopanic.com/api/v1/posts/?auth_token=free";
    
    if (!empty($filter)) {
        $url .= "&filter=" . urlencode($filter);
    }
    
    if (!empty($currencies)) {
        $url .= "&currencies=" . urlencode($currencies);
    }
    
    if ($page > 1) {
        $url .= "&page=" . $page;
    }
    
    // Fetch news
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
        // Fallback to mock data if API fails
        echo json_encode([
            'success' => true,
            'news' => getMockNews(),
            'count' => 10
        ]);
        exit;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error: " . json_last_error_msg());
    }
    
    if (!isset($data['results'])) {
        throw new Exception("Invalid response format");
    }
    
    // Format news items
    $news = [];
    foreach ($data['results'] as $item) {
        $news[] = [
            'id' => $item['id'] ?? uniqid(),
            'title' => $item['title'] ?? 'No title',
            'url' => $item['url'] ?? '#',
            'source' => $item['source']['title'] ?? 'Unknown',
            'published_at' => $item['published_at'] ?? date('Y-m-d H:i:s'),
            'currencies' => isset($item['currencies']) ? array_map(fn($c) => $c['code'], $item['currencies']) : [],
            'kind' => $item['kind'] ?? 'news',
            'domain' => $item['domain'] ?? '',
            'votes' => [
                'positive' => $item['votes']['positive'] ?? 0,
                'negative' => $item['votes']['negative'] ?? 0,
                'important' => $item['votes']['important'] ?? 0
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'news' => $news,
        'count' => count($news),
        'next_page' => isset($data['next']) ? $page + 1 : null
    ]);
    
} catch (Exception $e) {
    // Return mock data on error
    echo json_encode([
        'success' => true,
        'news' => getMockNews(),
        'count' => 10
    ]);
}

function getMockNews() {
    return [
        [
            'id' => 'mock1',
            'title' => 'Bitcoin Reaches New All-Time High as Institutional Adoption Grows',
            'url' => '#',
            'source' => 'CoinDesk',
            'published_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'currencies' => ['BTC'],
            'kind' => 'news',
            'domain' => 'coindesk.com',
            'votes' => ['positive' => 45, 'negative' => 3, 'important' => 12]
        ],
        [
            'id' => 'mock2',
            'title' => 'Ethereum 2.0 Upgrade Shows Promising Results in Latest Tests',
            'url' => '#',
            'source' => 'Decrypt',
            'published_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
            'currencies' => ['ETH'],
            'kind' => 'news',
            'domain' => 'decrypt.co',
            'votes' => ['positive' => 38, 'negative' => 5, 'important' => 8]
        ],
        [
            'id' => 'mock3',
            'title' => 'Major Exchange Announces Support for New DeFi Tokens',
            'url' => '#',
            'source' => 'The Block',
            'published_at' => date('Y-m-d H:i:s', strtotime('-6 hours')),
            'currencies' => ['ETH', 'BTC'],
            'kind' => 'news',
            'domain' => 'theblock.co',
            'votes' => ['positive' => 28, 'negative' => 2, 'important' => 5]
        ],
        [
            'id' => 'mock4',
            'title' => 'Regulatory Clarity Boosts Market Confidence in Cryptocurrency Sector',
            'url' => '#',
            'source' => 'CoinTelegraph',
            'published_at' => date('Y-m-d H:i:s', strtotime('-8 hours')),
            'currencies' => ['BTC', 'ETH'],
            'kind' => 'news',
            'domain' => 'cointelegraph.com',
            'votes' => ['positive' => 52, 'negative' => 8, 'important' => 15]
        ],
        [
            'id' => 'mock5',
            'title' => 'Solana Network Processes Record Number of Transactions',
            'url' => '#',
            'source' => 'Coindesk',
            'published_at' => date('Y-m-d H:i:s', strtotime('-10 hours')),
            'currencies' => ['SOL'],
            'kind' => 'news',
            'domain' => 'coindesk.com',
            'votes' => ['positive' => 31, 'negative' => 4, 'important' => 7]
        ],
        [
            'id' => 'mock6',
            'title' => 'NFT Market Shows Signs of Recovery with Increased Trading Volume',
            'url' => '#',
            'source' => 'The Defiant',
            'published_at' => date('Y-m-d H:i:s', strtotime('-12 hours')),
            'currencies' => ['ETH'],
            'kind' => 'news',
            'domain' => 'thedefiant.io',
            'votes' => ['positive' => 22, 'negative' => 6, 'important' => 3]
        ],
        [
            'id' => 'mock7',
            'title' => 'Central Bank Digital Currencies Gain Momentum Worldwide',
            'url' => '#',
            'source' => 'Reuters',
            'published_at' => date('Y-m-d H:i:s', strtotime('-14 hours')),
            'currencies' => ['BTC'],
            'kind' => 'news',
            'domain' => 'reuters.com',
            'votes' => ['positive' => 41, 'negative' => 12, 'important' => 18]
        ],
        [
            'id' => 'mock8',
            'title' => 'Cardano Smart Contracts Platform Sees Increased Developer Activity',
            'url' => '#',
            'source' => 'Crypto Briefing',
            'published_at' => date('Y-m-d H:i:s', strtotime('-16 hours')),
            'currencies' => ['ADA'],
            'kind' => 'news',
            'domain' => 'cryptobriefing.com',
            'votes' => ['positive' => 27, 'negative' => 3, 'important' => 4]
        ],
        [
            'id' => 'mock9',
            'title' => 'Crypto Gaming Sector Attracts Major Investment from Traditional Gaming Giants',
            'url' => '#',
            'source' => 'VentureBeat',
            'published_at' => date('Y-m-d H:i:s', strtotime('-18 hours')),
            'currencies' => ['ETH'],
            'kind' => 'news',
            'domain' => 'venturebeat.com',
            'votes' => ['positive' => 35, 'negative' => 5, 'important' => 9]
        ],
        [
            'id' => 'mock10',
            'title' => 'Bitcoin Lightning Network Reaches New Milestone in Payment Channels',
            'url' => '#',
            'source' => 'Bitcoin Magazine',
            'published_at' => date('Y-m-d H:i:s', strtotime('-20 hours')),
            'currencies' => ['BTC'],
            'kind' => 'news',
            'domain' => 'bitcoinmagazine.com',
            'votes' => ['positive' => 48, 'negative' => 2, 'important' => 11]
        ]
    ];
}
?>