<?php
/**
 * Bot Executor - Automated Trading Bot Engine
 * 
 * Run this script via cron job every 5 minutes (replace with actual crontab entry):
 * @code 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/bot-executor.php >> /path/to/logs/bot-executor.log 2>&1
 * 
 * Windows Task Scheduler:
 * C:\php\php.exe C:\path\to\bot-executor.php
 */

require_once 'includes/config.php';

// Set execution time
set_time_limit(300);
ini_set('max_execution_time', 300);

echo "[" . date('Y-m-d H:i:s') . "] Bot Executor Started\n";

// Get all active bots
$stmt = $conn->prepare("SELECT * FROM trading_bots WHERE status = 'active'");
$stmt->execute();
$bots = $stmt->get_result();

$processed = 0;
$trades_executed = 0;

while ($bot = $bots->fetch_assoc()) {
    echo "[" . date('Y-m-d H:i:s') . "] Processing Bot #{$bot['bot_id']}: {$bot['bot_name']}\n";
    
    try {
        // Check if bot has reached max trades for today
        $today_trades = getTodayTradesCount($conn, $bot['bot_id']);
        
        if ($today_trades >= $bot['max_trades_per_day']) {
            echo "  -> Max trades per day reached ({$bot['max_trades_per_day']})\n";
            logBotActivity($conn, $bot['bot_id'], 'info', 'Max daily trades reached', [
                'count' => $today_trades,
                'limit' => $bot['max_trades_per_day']
            ]);
            continue;
        }
        
        // Generate AI signal
        $signal = generateTradingSignal($conn, $bot);
        
        if (!$signal) {
            echo "  -> No signal generated\n";
            continue;
        }
        
        echo "  -> Signal: {$signal['type']} (Confidence: {$signal['confidence']}%)\n";
        
        // Check if signal meets confidence threshold
        if ($signal['confidence'] < $bot['ai_confidence_threshold']) {
            echo "  -> Signal confidence too low (threshold: {$bot['ai_confidence_threshold']}%)\n";
            continue;
        }
        
        // Execute trade based on strategy
        $trade_result = executeStrategy($conn, $bot, $signal);
        
        if ($trade_result) {
            $trades_executed++;
            echo "  -> Trade executed: {$trade_result['type']}\n";
        }
        
        $processed++;
        
    } catch (Exception $e) {
        echo "  -> ERROR: " . $e->getMessage() . "\n";
        logBotActivity($conn, $bot['bot_id'], 'error', 'Execution error: ' . $e->getMessage());
    }
    
    // Small delay between bots
    usleep(500000); // 0.5 seconds
}

// Check open trades and manage positions
manageOpenPositions($conn);

echo "[" . date('Y-m-d H:i:s') . "] Execution Complete - Processed: {$processed} bots, Trades: {$trades_executed}\n";
echo str_repeat("=", 80) . "\n";

$conn->close();

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function getTodayTradesCount($conn, $bot_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bot_trades 
                           WHERE bot_id = ? AND DATE(entry_time) = CURDATE()");
    $stmt->bind_param("i", $bot_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'];
}

function generateTradingSignal($conn, $bot) {
    // Get current price
    $price = getCurrentCryptoPrice($bot['crypto_symbol']);
    
    if (!$price || $price == 0) {
        return null;
    }
    
    // Get historical data
    $history = getPriceHistory($bot['crypto_name'], 14);
    
    if (!$history || empty($history['prices'])) {
        return null;
    }
    
    // Calculate technical indicators
    $indicators = calculateIndicators($history);
    
    // AI decision logic based on strategy
    $signal = analyzeMarketByStrategy($bot, $indicators, $price);
    
    // Store signal in database
    storeSignal($conn, $bot['crypto_symbol'], $signal, $price, $indicators);
    
    return $signal;
}

function executeStrategy($conn, $bot, $signal) {
    // Get open trades for this bot
    $open_trades = getOpenTrades($conn, $bot['bot_id']);
    
    switch ($bot['strategy']) {
        case 'scalping':
            return executeScalping($conn, $bot, $signal, $open_trades);
        
        case 'day_trading':
            return executeDayTrading($conn, $bot, $signal, $open_trades);
        
        case 'swing_trading':
            return executeSwingTrading($conn, $bot, $signal, $open_trades);
        
        case 'grid_trading':
            return executeGridTrading($conn, $bot, $signal, $open_trades);
        
        default:
            return executeScalping($conn, $bot, $signal, $open_trades);
    }
}

function executeScalping($conn, $bot, $signal, $open_trades) {
    // Scalping: Quick in and out, no open positions
    
    // If we have open trades, check if we should close them
    foreach ($open_trades as $trade) {
        $current_price = getCurrentCryptoPrice($bot['crypto_symbol']);
        $profit_percent = (($current_price - $trade['price_at_entry']) / $trade['price_at_entry']) * 100;
        
        // Close on take profit or stop loss
        if ($profit_percent >= $bot['take_profit_percent'] || 
            $profit_percent <= -$bot['stop_loss_percent']) {
            closeTrade($conn, $trade, $current_price, 'Scalping exit');
            return ['type' => 'close', 'trade_id' => $trade['trade_id']];
        }
    }
    
    // If no open trades and signal is BUY, open new trade
    if (empty($open_trades) && $signal['type'] === 'buy') {
        return openTrade($conn, $bot, $signal);
    }
    
    return null;
}

function executeDayTrading($conn, $bot, $signal, $open_trades) {
    // Day trading: Hold positions during the day, close by end of day
    
    // Check time - close all positions after market hours
    $hour = (int)date('H');
    if ($hour >= 22 || $hour < 6) { // Close positions overnight
        foreach ($open_trades as $trade) {
            $current_price = getCurrentCryptoPrice($bot['crypto_symbol']);
            closeTrade($conn, $trade, $current_price, 'End of day exit');
        }
        return null;
    }
    
    // Manage existing positions
    foreach ($open_trades as $trade) {
        $current_price = getCurrentCryptoPrice($bot['crypto_symbol']);
        $profit_percent = (($current_price - $trade['price_at_entry']) / $trade['price_at_entry']) * 100;
        
        if ($profit_percent >= $bot['take_profit_percent'] || 
            $profit_percent <= -$bot['stop_loss_percent']) {
            closeTrade($conn, $trade, $current_price, 'Target reached');
            return ['type' => 'close', 'trade_id' => $trade['trade_id']];
        }
    }
    
    // Open new trade if conditions are right
    if (count($open_trades) < 2 && ($signal['type'] === 'buy' || $signal['type'] === 'sell')) {
        return openTrade($conn, $bot, $signal);
    }
    
    return null;
}

function executeSwingTrading($conn, $bot, $signal, $open_trades) {
    // Swing trading: Hold positions for days/weeks
    
    foreach ($open_trades as $trade) {
        $current_price = getCurrentCryptoPrice($bot['crypto_symbol']);
        $profit_percent = (($current_price - $trade['price_at_entry']) / $trade['price_at_entry']) * 100;
        
        // Use trailing stop if enabled
        if ($bot['use_trailing_stop'] && $profit_percent > 0) {
            $trailing_stop = -$bot['trailing_stop_percent'];
            if ($profit_percent <= $trailing_stop) {
                closeTrade($conn, $trade, $current_price, 'Trailing stop triggered');
                return ['type' => 'close', 'trade_id' => $trade['trade_id']];
            }
        }
        
        // Regular stop loss/take profit
        if ($profit_percent >= $bot['take_profit_percent'] || 
            $profit_percent <= -$bot['stop_loss_percent']) {
            closeTrade($conn, $trade, $current_price, 'Target reached');
            return ['type' => 'close', 'trade_id' => $trade['trade_id']];
        }
    }
    
    // Open new swing trade
    if (empty($open_trades) && $signal['type'] === 'buy' && $signal['confidence'] >= 75) {
        return openTrade($conn, $bot, $signal);
    }
    
    return null;
}

function executeGridTrading($conn, $bot, $signal, $open_trades) {
    // Grid trading: Multiple positions at different price levels
    
    $current_price = getCurrentCryptoPrice($bot['crypto_symbol']);
    
    // Check existing grid positions
    foreach ($open_trades as $trade) {
        $profit_percent = (($current_price - $trade['price_at_entry']) / $trade['price_at_entry']) * 100;
        
        // Close positions that hit profit targets
        if ($profit_percent >= $bot['take_profit_percent']) {
            closeTrade($conn, $trade, $current_price, 'Grid profit target');
            return ['type' => 'close', 'trade_id' => $trade['trade_id']];
        }
        
        // Stop loss for individual positions
        if ($profit_percent <= -$bot['stop_loss_percent']) {
            closeTrade($conn, $trade, $current_price, 'Grid stop loss');
            return ['type' => 'close', 'trade_id' => $trade['trade_id']];
        }
    }
    
    // Open new grid positions (max 5 positions)
    if (count($open_trades) < 5 && $signal['type'] === 'buy') {
        return openTrade($conn, $bot, $signal);
    }
    
    return null;
}

function openTrade($conn, $bot, $signal) {
    $current_price = getCurrentCryptoPrice($bot['crypto_symbol']);
    
    if (!$current_price || $current_price == 0) {
        return null;
    }
    
    // Calculate trade amount based on risk level
    $trade_amount = calculateTradeAmount($bot);
    
    if ($trade_amount < $bot['min_trade_amount'] || $trade_amount > $bot['max_trade_amount']) {
        return null;
    }
    
    // Check user has sufficient balance
    $balance = getUserBalance($conn, $bot['user_id']);
    if ($balance < $trade_amount) {
        logBotActivity($conn, $bot['bot_id'], 'warning', 'Insufficient balance', [
            'required' => $trade_amount,
            'available' => $balance
        ]);
        return null;
    }
    
    $crypto_amount = $trade_amount / $current_price;
    
    // Create trade
    $stmt = $conn->prepare("INSERT INTO bot_trades (
        bot_id, user_id, trade_type, crypto_symbol, crypto_amount,
        price_at_entry, usd_amount, entry_signal, ai_confidence, status
    ) VALUES (?, ?, 'buy', ?, ?, ?, ?, ?, ?, 'open')");
    
    $stmt->bind_param("iisdddsd",
        $bot['bot_id'],
        $bot['user_id'],
        $bot['crypto_symbol'],
        $crypto_amount,
        $current_price,
        $trade_amount,
        $signal['reasoning'],
        $signal['confidence']
    );
    
    if ($stmt->execute()) {
        $trade_id = $stmt->insert_id;
        
        // Deduct from user wallet
        deductFromWallet($conn, $bot['user_id'], $trade_amount);
        
        // Log trade
        logBotActivity($conn, $bot['bot_id'], 'trade', 'Trade opened', [
            'trade_id' => $trade_id,
            'amount' => $trade_amount,
            'price' => $current_price,
            'confidence' => $signal['confidence']
        ]);
        
        return [
            'type' => 'open',
            'trade_id' => $trade_id,
            'amount' => $trade_amount,
            'price' => $current_price
        ];
    }
    
    return null;
}

function closeTrade($conn, $trade, $exit_price, $exit_signal) {
    $profit_loss = ($exit_price - $trade['price_at_entry']) * $trade['crypto_amount'];
    $profit_loss_percent = (($exit_price - $trade['price_at_entry']) / $trade['price_at_entry']) * 100;
    
    // Update trade
    $stmt = $conn->prepare("UPDATE bot_trades SET 
        price_at_exit = ?, profit_loss = ?, profit_loss_percent = ?,
        status = 'closed', exit_signal = ?, exit_time = NOW()
        WHERE trade_id = ?
    ");
    $stmt->bind_param("dddsi", $exit_price, $profit_loss, $profit_loss_percent, $exit_signal, $trade['trade_id']);
    $stmt->execute();
    
    // Update bot stats
    $is_winning = $profit_loss > 0 ? 1 : 0;
    $losing = $is_winning ? 0 : 1;
    
    $bot_stmt = $conn->prepare("UPDATE trading_bots SET 
        total_profit_loss = total_profit_loss + ?,
        total_trades = total_trades + 1,
        winning_trades = winning_trades + ?,
        losing_trades = losing_trades + ?,
        last_trade_at = NOW()
        WHERE bot_id = ?
    ");
    $bot_stmt->bind_param("diii", $profit_loss, $is_winning, $losing, $trade['bot_id']);
    $bot_stmt->execute();
    
    // Return funds to wallet
    $return_amount = $trade['usd_amount'] + $profit_loss;
    addToWallet($conn, $trade['user_id'], $return_amount);
    
    // Log trade close
    logBotActivity($conn, $trade['bot_id'], 'trade', 'Trade closed', [
        'trade_id' => $trade['trade_id'],
        'profit_loss' => $profit_loss,
        'exit_price' => $exit_price,
        'exit_signal' => $exit_signal
    ]);
    
    // Update daily performance
    updateDailyPerformance($conn, $trade['bot_id']);
    
    echo "  -> Trade #{$trade['trade_id']} closed: P/L = $" . number_format($profit_loss, 2) . "\n";
}

function manageOpenPositions($conn) {
    echo "[" . date('Y-m-d H:i:s') . "] Checking open positions...\n";
    
    $stmt = $conn->prepare("SELECT bt.*, tb.* 
                           FROM bot_trades bt 
                           JOIN trading_bots tb ON bt.bot_id = tb.bot_id 
                           WHERE bt.status = 'open'");
    $stmt->execute();
    $trades = $stmt->get_result();
    
    while ($trade = $trades->fetch_assoc()) {
        $current_price = getCurrentCryptoPrice($trade['crypto_symbol']);
        
        if (!$current_price) continue;
        
        $profit_percent = (($current_price - $trade['price_at_entry']) / $trade['price_at_entry']) * 100;
        
        // Check stop loss and take profit
        if ($profit_percent >= $trade['take_profit_percent']) {
            closeTrade($conn, $trade, $current_price, 'Take profit triggered');
        } elseif ($profit_percent <= -$trade['stop_loss_percent']) {
            closeTrade($conn, $trade, $current_price, 'Stop loss triggered');
        }
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function getCurrentCryptoPrice($symbol) {
    static $cache = [];
    $cache_key = $symbol . '_' . floor(time() / 60); // Cache for 1 minute
    
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }
    
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
    $price = $data[$crypto_id]['usd'] ?? 0;
    
    $cache[$cache_key] = $price;
    return $price;
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
    $ma_7 = array_sum(array_slice($prices, -7)) / min(7, count($prices));
    $ma_14 = array_sum($prices) / count($prices);
    
    $rsi = calculateRSI($prices);
    $volatility = calculateVolatility($prices);
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

function analyzeMarketByStrategy($bot, $indicators, $price) {
    $rsi = $indicators['rsi'];
    $trend = $indicators['trend'];
    $ma_7 = $indicators['ma_7'];
    $ma_14 = $indicators['ma_14'];
    
    $confidence = 50;
    $signal_type = 'hold';
    $reasoning = '';
    
    // Adjust thresholds based on risk level
    $risk_multiplier = [
        'conservative' => 0.8,
        'moderate' => 1.0,
        'aggressive' => 1.2
    ];
    $multiplier = $risk_multiplier[$bot['risk_level']] ?? 1.0;
    
    // RSI-based signals
    if ($rsi < 30) {
        $signal_type = 'buy';
        $confidence += 20 * $multiplier;
        $reasoning = 'RSI oversold. ';
    } elseif ($rsi > 70) {
        $signal_type = 'sell';
        $confidence += 20 * $multiplier;
        $reasoning = 'RSI overbought. ';
    }
    
    // Moving average crossover
    if ($ma_7 > $ma_14 && $trend === 'bullish') {
        if ($signal_type !== 'sell') {
            $signal_type = 'buy';
            $confidence += 15;
            $reasoning .= 'Bullish crossover. ';
        }
    } elseif ($ma_7 < $ma_14 && $trend === 'bearish') {
        $signal_type = 'sell';
        $confidence += 15;
        $reasoning .= 'Bearish crossover. ';
    }
    
    // Trend confirmation
    if ($signal_type === 'buy' && $trend === 'bullish') {
        $confidence += 10;
        $reasoning .= 'Trend confirmation. ';
    } elseif ($signal_type === 'sell' && $trend === 'bearish') {
        $confidence += 10;
        $reasoning .= 'Trend confirmation. ';
    }
    
    return [
        'type' => $signal_type,
        'confidence' => min($confidence, 95),
        'sentiment' => $trend,
        'timeframe' => 'short-term',
        'reasoning' => trim($reasoning) ?: 'Market neutral.'
    ];
}

function storeSignal($conn, $crypto_symbol, $signal, $price, $indicators) {
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
    
    $stmt->execute();
}

function getOpenTrades($conn, $bot_id) {
    $stmt = $conn->prepare("SELECT * FROM bot_trades WHERE bot_id = ? AND status = 'open'");
    $stmt->bind_param("i", $bot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trades = [];
    while ($row = $result->fetch_assoc()) {
        $trades[] = $row;
    }
    
    return $trades;
}

function calculateTradeAmount($bot) {
    $base_amount = $bot['investment_amount'] / 10; // 10% of investment per trade
    
    // Adjust based on risk level
    switch ($bot['risk_level']) {
        case 'conservative':
            return $base_amount * 0.5;
        case 'moderate':
            return $base_amount;
        case 'aggressive':
            return $base_amount * 1.5;
        default:
            return $base_amount;
    }
}

function getUserBalance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND currency = 'USD'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['balance'];
    }
    
    return 0;
}

function deductFromWallet($conn, $user_id, $amount) {
    $stmt = $conn->prepare("UPDATE user_wallets SET balance = balance - ? 
                           WHERE user_id = ? AND currency = 'USD'");
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();
}

function addToWallet($conn, $user_id, $amount) {
    $stmt = $conn->prepare("UPDATE user_wallets SET balance = balance + ? 
                           WHERE user_id = ? AND currency = 'USD'");
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();
}

function updateDailyPerformance($conn, $bot_id) {
    $today = date('Y-m-d');
    
    // Get today's stats
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as trades_count,
        SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as losing_trades,
        SUM(profit_loss) as total_profit_loss,
        AVG(profit_loss) as avg_profit_per_trade
        FROM bot_trades 
        WHERE bot_id = ? AND DATE(exit_time) = ? AND status = 'closed'
    ");
    $stmt->bind_param("is", $bot_id, $today);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    $win_rate = $stats['trades_count'] > 0 
        ? ($stats['winning_trades'] / $stats['trades_count']) * 100 
        : 0;
    
    // Insert or update performance record
    $perf_stmt = $conn->prepare("INSERT INTO bot_performance (
        bot_id, date, trades_count, winning_trades, losing_trades,
        total_profit_loss, win_rate, avg_profit_per_trade
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        trades_count = VALUES(trades_count),
        winning_trades = VALUES(winning_trades),
        losing_trades = VALUES(losing_trades),
        total_profit_loss = VALUES(total_profit_loss),
        win_rate = VALUES(win_rate),
        avg_profit_per_trade = VALUES(avg_profit_per_trade)
    ");
    
    $perf_stmt->bind_param("isiidddd",
        $bot_id,
        $today,
        $stats['trades_count'],
        $stats['winning_trades'],
        $stats['losing_trades'],
        $stats['total_profit_loss'],
        $win_rate,
        $stats['avg_profit_per_trade']
    );
    
    $perf_stmt->execute();
}

function logBotActivity($conn, $bot_id, $log_type, $message, $data = null) {
    $data_json = $data ? json_encode($data) : null;
    $stmt = $conn->prepare("INSERT INTO bot_logs (bot_id, log_type, message, data) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $bot_id, $log_type, $message, $data_json);
    $stmt->execute();
}
?>
