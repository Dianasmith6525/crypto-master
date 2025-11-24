<?php
/**
 * Trading Analytics API
 * Provides comprehensive trading performance metrics and analytics
 */

header('Content-Type: application/json');
require_once '../includes/config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $analytics = calculateTradingAnalytics($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating analytics: ' . $e->getMessage()
    ]);
}

function calculateTradingAnalytics($conn, $user_id) {
    // Get all trades
    $query = "SELECT * FROM trades WHERE user_id = ? ORDER BY trade_date ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $trades = $result->fetch_all(MYSQLI_ASSOC);
    
    if (count($trades) === 0) {
        return getEmptyAnalytics();
    }
    
    // Calculate basic metrics
    $totalTrades = count($trades);
    $buyTrades = array_filter($trades, fn($t) => $t['trade_type'] === 'buy');
    $sellTrades = array_filter($trades, fn($t) => $t['trade_type'] === 'sell');
    
    $totalBought = array_sum(array_column($buyTrades, 'total_amount'));
    $totalSold = array_sum(array_column($sellTrades, 'total_amount'));
    $totalFees = array_sum(array_column($trades, 'fees'));
    
    $netProfit = $totalSold - $totalBought - $totalFees;
    $roi = $totalBought > 0 ? ($netProfit / $totalBought) * 100 : 0;
    
    // Calculate win/loss ratio
    $profitableTrades = 0;
    $losingTrades = 0;
    $holdings = [];
    
    foreach ($trades as $trade) {
        $crypto = $trade['crypto_type'];
        
        if (!isset($holdings[$crypto])) {
            $holdings[$crypto] = ['quantity' => 0, 'cost' => 0];
        }
        
        if ($trade['trade_type'] === 'buy') {
            $holdings[$crypto]['quantity'] += $trade['quantity'];
            $holdings[$crypto]['cost'] += $trade['total_amount'];
        } else {
            $avgCost = $holdings[$crypto]['quantity'] > 0 
                ? $holdings[$crypto]['cost'] / $holdings[$crypto]['quantity'] 
                : 0;
            $profit = ($trade['price_per_unit'] - $avgCost) * $trade['quantity'];
            
            if ($profit > 0) {
                $profitableTrades++;
            } else {
                $losingTrades++;
            }
            
            $holdings[$crypto]['quantity'] -= $trade['quantity'];
            $holdings[$crypto]['cost'] -= $avgCost * $trade['quantity'];
        }
    }
    
    $winRate = ($profitableTrades + $losingTrades) > 0 
        ? ($profitableTrades / ($profitableTrades + $losingTrades)) * 100 
        : 0;
    
    // Get per-crypto breakdown
    $cryptoQuery = "SELECT 
        crypto_type,
        COUNT(*) as trade_count,
        SUM(CASE WHEN trade_type = 'buy' THEN total_amount ELSE 0 END) as bought,
        SUM(CASE WHEN trade_type = 'sell' THEN total_amount ELSE 0 END) as sold,
        SUM(fees) as fees
    FROM trades 
    WHERE user_id = ?
    GROUP BY crypto_type
    ORDER BY trade_count DESC";
    
    $stmt = $conn->prepare($cryptoQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cryptoBreakdown = [];
    while ($row = $result->fetch_assoc()) {
        $profit = $row['sold'] - $row['bought'] - $row['fees'];
        $cryptoBreakdown[] = [
            'crypto' => $row['crypto_type'],
            'trades' => intval($row['trade_count']),
            'profit' => floatval($profit),
            'roi' => $row['bought'] > 0 ? ($profit / $row['bought']) * 100 : 0
        ];
    }
    
    // Monthly performance
    $monthlyQuery = "SELECT 
        DATE_FORMAT(trade_date, '%Y-%m') as month,
        SUM(CASE WHEN trade_type = 'buy' THEN total_amount ELSE 0 END) as bought,
        SUM(CASE WHEN trade_type = 'sell' THEN total_amount ELSE 0 END) as sold,
        SUM(fees) as fees,
        COUNT(*) as trade_count
    FROM trades 
    WHERE user_id = ?
    GROUP BY DATE_FORMAT(trade_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12";
    
    $stmt = $conn->prepare($monthlyQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $monthlyPerformance = [];
    while ($row = $result->fetch_assoc()) {
        $profit = $row['sold'] - $row['bought'] - $row['fees'];
        $monthlyPerformance[] = [
            'month' => $row['month'],
            'trades' => intval($row['trade_count']),
            'profit' => floatval($profit)
        ];
    }
    
    // Best and worst trades
    $bestTradeQuery = "SELECT 
        crypto_type,
        trade_type,
        quantity,
        price_per_unit,
        total_amount,
        trade_date
    FROM trades 
    WHERE user_id = ? AND trade_type = 'sell'
    ORDER BY total_amount DESC
    LIMIT 1";
    
    $stmt = $conn->prepare($bestTradeQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $bestTrade = $stmt->get_result()->fetch_assoc();
    
    $worstTradeQuery = "SELECT 
        crypto_type,
        trade_type,
        quantity,
        price_per_unit,
        total_amount,
        trade_date
    FROM trades 
    WHERE user_id = ? AND trade_type = 'sell'
    ORDER BY total_amount ASC
    LIMIT 1";
    
    $stmt = $conn->prepare($worstTradeQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $worstTrade = $stmt->get_result()->fetch_assoc();
    
    // Trading frequency
    $firstTrade = $trades[0];
    $lastTrade = end($trades);
    $daysDiff = (strtotime($lastTrade['trade_date']) - strtotime($firstTrade['trade_date'])) / 86400;
    $avgTradesPerDay = $daysDiff > 0 ? $totalTrades / $daysDiff : 0;
    
    return [
        'overview' => [
            'total_trades' => $totalTrades,
            'total_bought' => round($totalBought, 2),
            'total_sold' => round($totalSold, 2),
            'total_fees' => round($totalFees, 2),
            'net_profit' => round($netProfit, 2),
            'roi' => round($roi, 2),
            'win_rate' => round($winRate, 2),
            'profitable_trades' => $profitableTrades,
            'losing_trades' => $losingTrades
        ],
        'crypto_breakdown' => $cryptoBreakdown,
        'monthly_performance' => array_reverse($monthlyPerformance),
        'best_trade' => $bestTrade,
        'worst_trade' => $worstTrade,
        'trading_frequency' => [
            'avg_per_day' => round($avgTradesPerDay, 2),
            'total_days' => round($daysDiff, 0),
            'first_trade' => $firstTrade['trade_date'],
            'last_trade' => $lastTrade['trade_date']
        ]
    ];
}

function getEmptyAnalytics() {
    return [
        'overview' => [
            'total_trades' => 0,
            'total_bought' => 0,
            'total_sold' => 0,
            'total_fees' => 0,
            'net_profit' => 0,
            'roi' => 0,
            'win_rate' => 0,
            'profitable_trades' => 0,
            'losing_trades' => 0
        ],
        'crypto_breakdown' => [],
        'monthly_performance' => [],
        'best_trade' => null,
        'worst_trade' => null,
        'trading_frequency' => [
            'avg_per_day' => 0,
            'total_days' => 0,
            'first_trade' => null,
            'last_trade' => null
        ]
    ];
}

$conn->close();
?>