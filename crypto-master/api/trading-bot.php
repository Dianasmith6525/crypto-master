<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!is_user_authenticated()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

switch ($action) {
    case 'create_bot':
        createBot($conn, $user_id);
        break;
    
    case 'get_bots':
        getBots($conn, $user_id);
        break;
    
    case 'get_bot':
        getBot($conn, $user_id);
        break;
    
    case 'update_bot':
        updateBot($conn, $user_id);
        break;
    
    case 'delete_bot':
        deleteBot($conn, $user_id);
        break;
    
    case 'start_bot':
        startBot($conn, $user_id);
        break;
    
    case 'pause_bot':
        pauseBot($conn, $user_id);
        break;
    
    case 'stop_bot':
        stopBot($conn, $user_id);
        break;
    
    case 'get_trades':
        getTrades($conn, $user_id);
        break;
    
    case 'get_performance':
        getPerformance($conn, $user_id);
        break;
    
    case 'get_signals':
        getSignals($conn);
        break;
    
    case 'get_bot_logs':
        getBotLogs($conn, $user_id);
        break;
    
    case 'generate_ai_signal':
        generateAISignal($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function createBot($conn, $user_id) {
    $bot_name = $_POST['bot_name'] ?? '';
    $strategy = $_POST['strategy'] ?? 'scalping';
    $crypto_symbol = $_POST['crypto_symbol'] ?? 'BTC';
    $crypto_name = $_POST['crypto_name'] ?? 'bitcoin';
    $investment_amount = floatval($_POST['investment_amount'] ?? 100);
    $risk_level = $_POST['risk_level'] ?? 'moderate';
    $take_profit = floatval($_POST['take_profit_percent'] ?? 5);
    $stop_loss = floatval($_POST['stop_loss_percent'] ?? 3);
    
    // Validate user has sufficient balance
    $balance_check = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND currency = 'USD'");
    $balance_check->bind_param("i", $user_id);
    $balance_check->execute();
    $balance_result = $balance_check->get_result();
    
    if ($balance_result->num_rows > 0) {
        $wallet = $balance_result->fetch_assoc();
        if ($wallet['balance'] < $investment_amount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
            return;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Wallet not found']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO trading_bots (
        user_id, bot_name, strategy, crypto_symbol, crypto_name, investment_amount,
        risk_level, take_profit_percent, stop_loss_percent
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("issssdsdd", 
        $user_id, $bot_name, $strategy, $crypto_symbol, $crypto_name,
        $investment_amount, $risk_level, $take_profit, $stop_loss
    );
    
    if ($stmt->execute()) {
        $bot_id = $stmt->insert_id;
        
        // Log bot creation
        logBotActivity($conn, $bot_id, 'info', 'Bot created successfully', [
            'strategy' => $strategy,
            'investment' => $investment_amount
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bot created successfully',
            'bot_id' => $bot_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create bot']);
    }
}

function getBots($conn, $user_id) {
    $status = $_GET['status'] ?? 'all';
    
    $sql = "SELECT * FROM trading_bots WHERE user_id = ?";
    if ($status !== 'all') {
        $sql .= " AND status = ?";
    }
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($status !== 'all') {
        $stmt->bind_param("is", $user_id, $status);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $bots = [];
    
    while ($row = $result->fetch_assoc()) {
        // Calculate win rate
        $win_rate = $row['total_trades'] > 0 
            ? ($row['winning_trades'] / $row['total_trades']) * 100 
            : 0;
        
        $row['win_rate'] = round($win_rate, 2);
        $row['profit_loss_formatted'] = '$' . number_format($row['total_profit_loss'], 2);
        $bots[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'bots' => $bots
    ]);
}

function getBot($conn, $user_id) {
    $bot_id = intval($_GET['bot_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT * FROM trading_bots WHERE bot_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bot_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $bot = $result->fetch_assoc();
        echo json_encode(['success' => true, 'bot' => $bot]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bot not found']);
    }
}

function updateBot($conn, $user_id) {
    $bot_id = intval($_POST['bot_id'] ?? 0);
    $updates = [];
    $types = "";
    $values = [];
    
    $allowed_fields = [
        'bot_name' => 's',
        'investment_amount' => 'd',
        'risk_level' => 's',
        'take_profit_percent' => 'd',
        'stop_loss_percent' => 'd',
        'max_trades_per_day' => 'i',
        'min_trade_amount' => 'd',
        'max_trade_amount' => 'd',
        'use_trailing_stop' => 'i',
        'trailing_stop_percent' => 'd',
        'ai_confidence_threshold' => 'd'
    ];
    
    foreach ($allowed_fields as $field => $type) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = ?";
            $types .= $type;
            $values[] = $_POST[$field];
        }
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $types .= "ii";
    $values[] = $bot_id;
    $values[] = $user_id;
    
    $sql = "UPDATE trading_bots SET " . implode(', ', $updates) . " WHERE bot_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        logBotActivity($conn, $bot_id, 'info', 'Bot settings updated', $_POST);
        echo json_encode(['success' => true, 'message' => 'Bot updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update bot']);
    }
}

function deleteBot($conn, $user_id) {
    $bot_id = intval($_POST['bot_id'] ?? 0);
    
    // Check if bot has open trades
    $check = $conn->prepare("SELECT COUNT(*) as open_trades FROM bot_trades WHERE bot_id = ? AND status = 'open'");
    $check->bind_param("i", $bot_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['open_trades'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete bot with open trades']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM trading_bots WHERE bot_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bot_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Bot deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete bot']);
    }
}

function startBot($conn, $user_id) {
    $bot_id = intval($_POST['bot_id'] ?? 0);
    
    $stmt = $conn->prepare("UPDATE trading_bots SET status = 'active' WHERE bot_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bot_id, $user_id);
    
    if ($stmt->execute()) {
        logBotActivity($conn, $bot_id, 'info', 'Bot started', ['status' => 'active']);
        echo json_encode(['success' => true, 'message' => 'Bot started successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to start bot']);
    }
}

function pauseBot($conn, $user_id) {
    $bot_id = intval($_POST['bot_id'] ?? 0);
    
    $stmt = $conn->prepare("UPDATE trading_bots SET status = 'paused' WHERE bot_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bot_id, $user_id);
    
    if ($stmt->execute()) {
        logBotActivity($conn, $bot_id, 'info', 'Bot paused', ['status' => 'paused']);
        echo json_encode(['success' => true, 'message' => 'Bot paused successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to pause bot']);
    }
}

function stopBot($conn, $user_id) {
    $bot_id = intval($_POST['bot_id'] ?? 0);
    
    // Close all open trades at market price
    $open_trades = $conn->prepare("SELECT * FROM bot_trades WHERE bot_id = ? AND status = 'open'");
    $open_trades->bind_param("i", $bot_id);
    $open_trades->execute();
    $trades = $open_trades->get_result();
    
    while ($trade = $trades->fetch_assoc()) {
        // Get current price and close trade
        $current_price = getCurrentCryptoPrice($trade['crypto_symbol']);
        closeTrade($conn, $trade['trade_id'], $current_price, 'Bot stopped - forced exit');
    }
    
    $stmt = $conn->prepare("UPDATE trading_bots SET status = 'stopped' WHERE bot_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bot_id, $user_id);
    
    if ($stmt->execute()) {
        logBotActivity($conn, $bot_id, 'info', 'Bot stopped', ['status' => 'stopped']);
        echo json_encode(['success' => true, 'message' => 'Bot stopped successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to stop bot']);
    }
}

function getTrades($conn, $user_id) {
    $bot_id = isset($_GET['bot_id']) ? intval($_GET['bot_id']) : null;
    $status = $_GET['status'] ?? 'all';
    $limit = intval($_GET['limit'] ?? 50);
    
    $sql = "SELECT bt.*, tb.bot_name 
            FROM bot_trades bt 
            JOIN trading_bots tb ON bt.bot_id = tb.bot_id 
            WHERE bt.user_id = ?";
    
    $params = [$user_id];
    $types = "i";
    
    if ($bot_id) {
        $sql .= " AND bt.bot_id = ?";
        $params[] = $bot_id;
        $types .= "i";
    }
    
    if ($status !== 'all') {
        $sql .= " AND bt.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY bt.entry_time DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trades = [];
    while ($row = $result->fetch_assoc()) {
        $row['profit_loss_formatted'] = '$' . number_format($row['profit_loss'], 2);
        $trades[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'trades' => $trades
    ]);
}

function getPerformance($conn, $user_id) {
    $bot_id = intval($_GET['bot_id'] ?? 0);
    $days = intval($_GET['days'] ?? 30);
    
    $stmt = $conn->prepare("
        SELECT * FROM bot_performance 
        WHERE bot_id = ? 
        AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY date ASC
    ");
    $stmt->bind_param("ii", $bot_id, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $performance = [];
    while ($row = $result->fetch_assoc()) {
        $performance[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'performance' => $performance
    ]);
}

function getSignals($conn) {
    $crypto_symbol = $_GET['crypto_symbol'] ?? null;
    $limit = intval($_GET['limit'] ?? 10);
    
    $sql = "SELECT * FROM ai_signals WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($crypto_symbol) {
        $sql .= " AND crypto_symbol = ?";
        $params[] = $crypto_symbol;
        $types .= "s";
    }
    
    $sql .= " AND (expires_at IS NULL OR expires_at > NOW())";
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $signals = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['indicators']) {
            $row['indicators'] = json_decode($row['indicators'], true);
        }
        $signals[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'signals' => $signals
    ]);
}

function getBotLogs($conn, $user_id) {
    $bot_id = intval($_GET['bot_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 50);
    
    // Verify bot belongs to user
    $verify = $conn->prepare("SELECT bot_id FROM trading_bots WHERE bot_id = ? AND user_id = ?");
    $verify->bind_param("ii", $bot_id, $user_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bot not found']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM bot_logs WHERE bot_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $bot_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['data']) {
            $row['data'] = json_decode($row['data'], true);
        }
        $logs[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
}

function generateAISignal($conn) {
    $crypto_symbol = $_POST['crypto_symbol'] ?? 'BTC';
    $crypto_name = $_POST['crypto_name'] ?? 'bitcoin';
    
    // Get current price
    $price = getCurrentCryptoPrice($crypto_symbol);
    
    // Get historical data for analysis
    $history = getPriceHistory($crypto_name, 14);
    
    // Calculate technical indicators
    $indicators = calculateIndicators($history);
    
    // AI decision logic
    $signal = analyzeMarket($indicators, $price);
    
    // Store signal
    $stmt = $conn->prepare("INSERT INTO ai_signals (
        crypto_symbol, signal_type, confidence, price, indicators,
        market_sentiment, prediction_timeframe, reasoning, expires_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    
    $indicators_json = json_encode($indicators);
    $stmt->bind_param("ssdssss",
        $crypto_symbol,
        $signal['type'],
        $signal['confidence'],
        $price,
        $indicators_json,
        $signal['sentiment'],
        $signal['timeframe'],
        $signal['reasoning']
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'signal' => $signal,
            'indicators' => $indicators
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate signal']);
    }
}

// Helper Functions

function getCurrentCryptoPrice($symbol) {
    $crypto_map = [
        'BTC' => 'bitcoin', 'ETH' => 'ethereum', 'USDT' => 'tether',
        'BNB' => 'binancecoin', 'XRP' => 'ripple', 'ADA' => 'cardano',
        'SOL' => 'solana', 'DOT' => 'polkadot', 'DOGE' => 'dogecoin'
    ];
    
    $crypto_id = $crypto_map[$symbol] ?? strtolower($symbol);
    $url = "https://api.coingecko.com/api/v3/simple/price?ids={$crypto_id}&vs_currencies=usd";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data[$crypto_id]['usd'] ?? 0;
}

function getPriceHistory($crypto_name, $days) {
    $url = "https://api.coingecko.com/api/v3/coins/{$crypto_name}/market_chart?vs_currency=usd&days={$days}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function calculateIndicators($history) {
    $prices = array_column($history['prices'] ?? [], 1);
    
    if (empty($prices)) {
        return [
            'rsi' => 50,
            'ma_7' => 0,
            'ma_14' => 0,
            'volatility' => 0,
            'trend' => 'neutral'
        ];
    }
    
    $current_price = end($prices);
    $ma_7 = array_sum(array_slice($prices, -7)) / 7;
    $ma_14 = array_sum($prices) / count($prices);
    
    // Simple RSI calculation
    $rsi = calculateRSI($prices);
    
    // Volatility
    $volatility = calculateVolatility($prices);
    
    // Trend
    $trend = $current_price > $ma_14 ? 'bullish' : 'bearish';
    
    return [
        'rsi' => round($rsi, 2),
        'ma_7' => round($ma_7, 2),
        'ma_14' => round($ma_14, 2),
        'volatility' => round($volatility, 2),
        'trend' => $trend,
        'current_price' => $current_price
    ];
}

function calculateRSI($prices, $period = 14) {
    $gains = [];
    $losses = [];
    
    for ($i = 1; $i < count($prices); $i++) {
        $change = $prices[$i] - $prices[$i - 1];
        $gains[] = $change > 0 ? $change : 0;
        $losses[] = $change < 0 ? abs($change) : 0;
    }
    
    $avg_gain = array_sum(array_slice($gains, -$period)) / $period;
    $avg_loss = array_sum(array_slice($losses, -$period)) / $period;
    
    if ($avg_loss == 0) return 100;
    
    $rs = $avg_gain / $avg_loss;
    return 100 - (100 / (1 + $rs));
}

function calculateVolatility($prices) {
    $mean = array_sum($prices) / count($prices);
    $variance = 0;
    
    foreach ($prices as $price) {
        $variance += pow($price - $mean, 2);
    }
    
    return sqrt($variance / count($prices));
}

function analyzeMarket($indicators, $price) {
    $rsi = $indicators['rsi'];
    $trend = $indicators['trend'];
    $ma_7 = $indicators['ma_7'];
    $ma_14 = $indicators['ma_14'];
    
    $confidence = 50;
    $signal_type = 'hold';
    $reasoning = '';
    
    // RSI-based signals
    if ($rsi < 30) {
        $signal_type = 'buy';
        $confidence += 20;
        $reasoning = 'RSI indicates oversold conditions. ';
    } elseif ($rsi > 70) {
        $signal_type = 'sell';
        $confidence += 20;
        $reasoning = 'RSI indicates overbought conditions. ';
    }
    
    // Moving average crossover
    if ($ma_7 > $ma_14 && $trend === 'bullish') {
        $signal_type = 'buy';
        $confidence += 15;
        $reasoning .= 'Bullish crossover detected. ';
    } elseif ($ma_7 < $ma_14 && $trend === 'bearish') {
        $signal_type = 'sell';
        $confidence += 15;
        $reasoning .= 'Bearish crossover detected. ';
    }
    
    // Trend confirmation
    if ($signal_type === 'buy' && $trend === 'bullish') {
        $confidence += 10;
    } elseif ($signal_type === 'sell' && $trend === 'bearish') {
        $confidence += 10;
    }
    
    return [
        'type' => $signal_type,
        'confidence' => min($confidence, 95),
        'sentiment' => $trend,
        'timeframe' => 'short-term',
        'reasoning' => trim($reasoning) ?: 'Market conditions are neutral.'
    ];
}

function closeTrade($conn, $trade_id, $exit_price, $exit_signal) {
    // Get trade details
    $stmt = $conn->prepare("SELECT * FROM bot_trades WHERE trade_id = ?");
    $stmt->bind_param("i", $trade_id);
    $stmt->execute();
    $trade = $stmt->get_result()->fetch_assoc();
    
    if (!$trade) return false;
    
    // Calculate profit/loss
    $profit_loss = ($exit_price - $trade['price_at_entry']) * $trade['crypto_amount'];
    $profit_loss_percent = (($exit_price - $trade['price_at_entry']) / $trade['price_at_entry']) * 100;
    
    // Update trade
    $update = $conn->prepare("UPDATE bot_trades SET 
        price_at_exit = ?, profit_loss = ?, profit_loss_percent = ?,
        status = 'closed', exit_signal = ?, exit_time = NOW()
        WHERE trade_id = ?
    ");
    $update->bind_param("dddsi", $exit_price, $profit_loss, $profit_loss_percent, $exit_signal, $trade_id);
    $update->execute();
    
    // Update bot stats
    $is_winning = $profit_loss > 0 ? 1 : 0;
    $bot_update = $conn->prepare("UPDATE trading_bots SET 
        total_profit_loss = total_profit_loss + ?,
        total_trades = total_trades + 1,
        winning_trades = winning_trades + ?,
        losing_trades = losing_trades + ?
        WHERE bot_id = ?
    ");
    $losing = $is_winning ? 0 : 1;
    $bot_update->bind_param("diii", $profit_loss, $is_winning, $losing, $trade['bot_id']);
    $bot_update->execute();
    
    // Update user wallet
    $wallet_update = $conn->prepare("UPDATE user_wallets SET 
        balance = balance + ?
        WHERE user_id = ? AND currency = 'USD'
    ");
    $wallet_update->bind_param("di", $profit_loss, $trade['user_id']);
    $wallet_update->execute();
    
    return true;
}

function logBotActivity($conn, $bot_id, $log_type, $message, $data = null) {
    $data_json = $data ? json_encode($data) : null;
    $stmt = $conn->prepare("INSERT INTO bot_logs (bot_id, log_type, message, data) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $bot_id, $log_type, $message, $data_json);
    $stmt->execute();
}

$conn->close();
?>
