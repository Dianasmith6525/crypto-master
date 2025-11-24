<?php
/**
 * Trade History API
 * Retrieves user's trading history with filtering and statistics
 * 
 * Endpoints:
 * - GET: Get trade history with optional filters
 * - Statistics: Return trading statistics
 */

header('Content-Type: application/json');
require_once '../includes/config.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get filter parameters
        $crypto_type = isset($_GET['crypto_type']) ? $_GET['crypto_type'] : '';
        $trade_type = isset($_GET['trade_type']) ? $_GET['trade_type'] : ''; // 'buy' or 'sell'
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        $action = isset($_GET['action']) ? $_GET['action'] : 'list'; // 'list' or 'stats'
        
        if ($action === 'stats') {
            // Get trading statistics
            getTradeStatistics($conn, $user_id, $crypto_type, $trade_type, $start_date, $end_date);
        } else {
            // Get trade history
            getTradeHistory($conn, $user_id, $crypto_type, $trade_type, $start_date, $end_date);
        }
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Get trade history with optional filters
 */
function getTradeHistory($conn, $user_id, $crypto_type, $trade_type, $start_date, $end_date) {
    // Build query with filters
    $query = "SELECT 
                id,
                crypto_type,
                trade_type,
                quantity,
                price_per_unit,
                total_amount,
                fees,
                trade_date,
                notes,
                created_at
              FROM trades 
              WHERE user_id = ?";
    
    $params = [$user_id];
    $types = "i";
    
    // Add crypto type filter
    if (!empty($crypto_type)) {
        $query .= " AND crypto_type = ?";
        $params[] = $crypto_type;
        $types .= "s";
    }
    
    // Add trade type filter
    if (!empty($trade_type)) {
        $query .= " AND trade_type = ?";
        $params[] = $trade_type;
        $types .= "s";
    }
    
    // Add date range filter
    if (!empty($start_date)) {
        $query .= " AND trade_date >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    
    if (!empty($end_date)) {
        $query .= " AND trade_date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    
    // Order by most recent first
    $query .= " ORDER BY trade_date DESC, created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trades = [];
    while ($row = $result->fetch_assoc()) {
        $trades[] = [
            'id' => $row['id'],
            'crypto_type' => $row['crypto_type'],
            'trade_type' => $row['trade_type'],
            'quantity' => floatval($row['quantity']),
            'price_per_unit' => floatval($row['price_per_unit']),
            'total_amount' => floatval($row['total_amount']),
            'fees' => floatval($row['fees']),
            'trade_date' => $row['trade_date'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'trades' => $trades,
        'count' => count($trades)
    ]);
}

/**
 * Get trading statistics
 */
function getTradeStatistics($conn, $user_id, $crypto_type, $trade_type, $start_date, $end_date) {
    // Build base query for stats
    $whereClause = "WHERE user_id = ?";
    $params = [$user_id];
    $types = "i";
    
    if (!empty($crypto_type)) {
        $whereClause .= " AND crypto_type = ?";
        $params[] = $crypto_type;
        $types .= "s";
    }
    
    if (!empty($trade_type)) {
        $whereClause .= " AND trade_type = ?";
        $params[] = $trade_type;
        $types .= "s";
    }
    
    if (!empty($start_date)) {
        $whereClause .= " AND trade_date >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    
    if (!empty($end_date)) {
        $whereClause .= " AND trade_date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    
    // Get overall statistics
    $query = "SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN trade_type = 'buy' THEN 1 ELSE 0 END) as total_buys,
                SUM(CASE WHEN trade_type = 'sell' THEN 1 ELSE 0 END) as total_sells,
                SUM(CASE WHEN trade_type = 'buy' THEN total_amount ELSE 0 END) as total_bought,
                SUM(CASE WHEN trade_type = 'sell' THEN total_amount ELSE 0 END) as total_sold,
                SUM(fees) as total_fees,
                AVG(CASE WHEN trade_type = 'buy' THEN price_per_unit END) as avg_buy_price,
                AVG(CASE WHEN trade_type = 'sell' THEN price_per_unit END) as avg_sell_price
              FROM trades 
              $whereClause";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    // Get per-crypto breakdown
    $cryptoQuery = "SELECT 
                        crypto_type,
                        COUNT(*) as trade_count,
                        SUM(CASE WHEN trade_type = 'buy' THEN quantity ELSE 0 END) as total_bought_qty,
                        SUM(CASE WHEN trade_type = 'sell' THEN quantity ELSE 0 END) as total_sold_qty,
                        SUM(CASE WHEN trade_type = 'buy' THEN total_amount ELSE 0 END) as buy_volume,
                        SUM(CASE WHEN trade_type = 'sell' THEN total_amount ELSE 0 END) as sell_volume,
                        AVG(price_per_unit) as avg_price
                    FROM trades 
                    $whereClause
                    GROUP BY crypto_type
                    ORDER BY trade_count DESC";
    
    $stmt2 = $conn->prepare($cryptoQuery);
    $stmt2->bind_param($types, ...$params);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    $cryptoStats = [];
    while ($row = $result2->fetch_assoc()) {
        $cryptoStats[] = [
            'crypto_type' => $row['crypto_type'],
            'trade_count' => intval($row['trade_count']),
            'total_bought_qty' => floatval($row['total_bought_qty']),
            'total_sold_qty' => floatval($row['total_sold_qty']),
            'buy_volume' => floatval($row['buy_volume']),
            'sell_volume' => floatval($row['sell_volume']),
            'avg_price' => floatval($row['avg_price']),
            'net_volume' => floatval($row['buy_volume']) - floatval($row['sell_volume'])
        ];
    }
    
    // Calculate net profit/loss (simplified - actual P/L would need current prices)
    $netPL = floatval($stats['total_sold']) - floatval($stats['total_bought']) - floatval($stats['total_fees']);
    
    echo json_encode([
        'success' => true,
        'statistics' => [
            'total_trades' => intval($stats['total_trades']),
            'total_buys' => intval($stats['total_buys']),
            'total_sells' => intval($stats['total_sells']),
            'total_bought' => floatval($stats['total_bought']),
            'total_sold' => floatval($stats['total_sold']),
            'total_fees' => floatval($stats['total_fees']),
            'net_profit_loss' => $netPL,
            'avg_buy_price' => floatval($stats['avg_buy_price']),
            'avg_sell_price' => floatval($stats['avg_sell_price']),
            'total_volume' => floatval($stats['total_bought']) + floatval($stats['total_sold'])
        ],
        'crypto_breakdown' => $cryptoStats
    ]);
}

$conn->close();
?>