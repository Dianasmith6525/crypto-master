<?php
// Bot Executor - Automated Trading Logic
// This script should be run via cron job every few minutes

require_once '../includes/config.php';

// Get active bots
$stmt = $conn->prepare("
    SELECT b.*, s.strategy_type, s.parameters
    FROM user_bot_instances b
    JOIN bot_trading_strategies s ON b.strategy_id = s.strategy_id
    WHERE b.status = 'active' AND b.is_backtesting = 0
");
$stmt->execute();
$result = $stmt->get_result();

while ($bot = $result->fetch_assoc()) {
    try {
        executeBotStrategy($bot);
    } catch (Exception $e) {
        logBotError($bot['bot_id'], 'Execution failed: ' . $e->getMessage());
    }
}

function executeBotStrategy($bot) {
    global $conn;

    $bot_id = $bot['bot_id'];
    $crypto_symbol = $bot['crypto_symbol'];
    $strategy_type = $bot['strategy_type'];
    $config = json_decode($bot['configuration'], true);
    $max_daily_trades = $bot['max_daily_trades'];
    $max_position_size = $bot['max_position_size'];
    $stop_loss_percentage = $bot['stop_loss_percentage'];
    $take_profit_percentage = $bot['take_profit_percentage'];

    // Check daily trade limit
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as trade_count
        FROM bot_execution_logs
        WHERE bot_id = ? AND DATE(execution_time) = ? AND status = 'success'
    ");
    $stmt->bind_param("is", $bot_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['trade_count'] >= $max_daily_trades) {
        return; // Daily limit reached
    }

    // Get current price
    $price_data = getCryptoPrice($crypto_symbol);
    if (!$price_data) {
        logBotError($bot_id, "Could not fetch price for $crypto_symbol");
        return;
    }

    $current_price = $price_data['usd'];

    // Get current position
    $position = getCurrentPosition($bot['user_id'], $crypto_symbol);

    // Execute strategy logic
    switch ($strategy_type) {
        case 'trend_following':
            executeTrendFollowing($bot, $current_price, $position, $price_data);
            break;
        case 'mean_reversion':
            executeMeanReversion($bot, $current_price, $position, $price_data);
            break;
        case 'breakout':
            executeBreakout($bot, $current_price, $position, $price_data);
            break;
        case 'scalping':
            executeScalping($bot, $current_price, $position, $price_data);
            break;
    }

    // Update last run time
    $stmt = $conn->prepare("UPDATE user_bot_instances SET last_run = NOW() WHERE bot_id = ?");
    $stmt->bind_param("i", $bot_id);
    $stmt->execute();
}

function executeTrendFollowing($bot, $current_price, $position, $price_data) {
    $change_24h = $price_data['usd_24h_change'] ?? 0;
    $config = json_decode($bot['configuration'], true);

    $trend_threshold = $config['trend_threshold'] ?? 2.0; // Minimum trend strength
    $position_size = min($bot['allocated_balance'] * 0.1, $bot['max_position_size']);

    if ($change_24h > $trend_threshold && !$position) {
        // Bullish trend - buy
        $quantity = $position_size / $current_price;
        executeBotTrade($bot, 'buy', $crypto_symbol, $quantity, $current_price, 'Bullish trend detected');
    } elseif ($change_24h < -$trend_threshold && $position) {
        // Bearish trend - sell
        executeBotTrade($bot, 'sell', $crypto_symbol, $position['quantity'], $current_price, 'Bearish trend detected');
    }
}

function executeMeanReversion($bot, $current_price, $position, $price_data) {
    // Simple mean reversion based on recent price movement
    $config = json_decode($bot['configuration'], true);
    $mean_reversion_threshold = $config['mean_reversion_threshold'] ?? 3.0;

    // Get price history (simplified - in production, use proper historical data)
    $avg_price = getAveragePrice($bot['crypto_symbol'], 24); // 24 hour average

    if (!$avg_price) return;

    $deviation = (($current_price - $avg_price) / $avg_price) * 100;
    $position_size = min($bot['allocated_balance'] * 0.1, $bot['max_position_size']);

    if ($deviation < -$mean_reversion_threshold && !$position) {
        // Price below average - buy
        $quantity = $position_size / $current_price;
        executeBotTrade($bot, 'buy', $bot['crypto_symbol'], $quantity, $current_price, 'Price below average - mean reversion');
    } elseif ($deviation > $mean_reversion_threshold && $position) {
        // Price above average - sell
        executeBotTrade($bot, 'sell', $bot['crypto_symbol'], $position['quantity'], $current_price, 'Price above average - mean reversion');
    }
}

function executeBreakout($bot, $current_price, $position, $price_data) {
    $config = json_decode($bot['configuration'], true);
    $breakout_threshold = $config['breakout_threshold'] ?? 5.0;

    // Get recent high/low (simplified)
    $recent_high = getRecentHigh($bot['crypto_symbol'], 24);
    $recent_low = getRecentLow($bot['crypto_symbol'], 24);

    if (!$recent_high || !$recent_low) return;

    $position_size = min($bot['allocated_balance'] * 0.1, $bot['max_position_size']);

    if ($current_price > $recent_high * (1 + $breakout_threshold/100) && !$position) {
        // Breakout above resistance - buy
        $quantity = $position_size / $current_price;
        executeBotTrade($bot, 'buy', $bot['crypto_symbol'], $quantity, $current_price, 'Breakout above resistance');
    } elseif ($current_price < $recent_low * (1 - $breakout_threshold/100) && $position) {
        // Breakdown below support - sell
        executeBotTrade($bot, 'sell', $bot['crypto_symbol'], $position['quantity'], $current_price, 'Breakdown below support');
    }
}

function executeScalping($bot, $current_price, $position, $price_data) {
    $config = json_decode($bot['configuration'], true);
    $scalping_threshold = $config['scalping_threshold'] ?? 0.5; // Small percentage moves

    // Get very recent price change (last hour)
    $hour_change = getHourChange($bot['crypto_symbol']);

    if (abs($hour_change) < $scalping_threshold) return; // Not enough movement

    $position_size = min($bot['allocated_balance'] * 0.05, $bot['max_position_size']); // Smaller positions for scalping

    if ($hour_change > $scalping_threshold && !$position) {
        // Quick upmove - buy
        $quantity = $position_size / $current_price;
        executeBotTrade($bot, 'buy', $bot['crypto_symbol'], $quantity, $current_price, 'Scalping - quick upmove');
    } elseif ($hour_change < -$scalping_threshold && $position) {
        // Quick downmove - sell
        executeBotTrade($bot, 'sell', $bot['crypto_symbol'], $position['quantity'], $current_price, 'Scalping - quick downmove');
    }
}

function executeBotTrade($bot, $trade_type, $crypto_symbol, $quantity, $price, $reason) {
    global $conn;

    $total_value = $quantity * $price;
    $user_id = $bot['user_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check balance/position limits
        if ($trade_type === 'buy') {
            // Check allocated balance
            if ($total_value > $bot['allocated_balance']) {
                throw new Exception('Insufficient allocated balance');
            }
        } else {
            // Check position
            $position = getCurrentPosition($user_id, $crypto_symbol);
            if (!$position || $position['quantity'] < $quantity) {
                throw new Exception('Insufficient position');
            }
        }

        // Execute trade via existing API
        $trade_data = [
            'action' => 'place_trade',
            'crypto' => $crypto_symbol,
            'type' => $trade_type,
            'usd_amount' => $total_value,
            'crypto_amount' => $quantity,
            'price' => $price
        ];

        // Simulate API call (in production, make actual HTTP request)
        $success = simulateTradeExecution($user_id, $trade_data);

        if ($success) {
            // Log bot execution
            $stmt = $conn->prepare("
                INSERT INTO bot_execution_logs (
                    bot_id, trade_type, crypto_symbol, quantity, price, total_value, reason, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'success')
            ");
            $stmt->bind_param(
                "issddds",
                $bot['bot_id'], $trade_type, $crypto_symbol, $quantity, $price, $total_value, $reason
            );
            $stmt->execute();

            $conn->commit();
        } else {
            throw new Exception('Trade execution failed');
        }

    } catch (Exception $e) {
        $conn->rollback();

        // Log failed execution
        $stmt = $conn->prepare("
            INSERT INTO bot_execution_logs (
                bot_id, trade_type, crypto_symbol, quantity, price, total_value, reason, status, error_message
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'failed', ?)
        ");
        $stmt->bind_param(
            "issdddss",
            $bot['bot_id'], $trade_type, $crypto_symbol, $quantity, $price, $total_value, $reason, $e->getMessage()
        );
        $stmt->execute();
    }
}

function getCryptoPrice($symbol) {
    // In production, fetch from CoinGecko API
    $url = "https://api.coingecko.com/api/v3/simple/price?ids=" . strtolower($symbol) . "&vs_currencies=usd&include_24hr_change=true";

    $context = stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: CryptoBot/1.0\r\n"
        ]
    ]);

    $response = file_get_contents($url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        $key = strtolower($symbol);
        if (isset($data[$key])) {
            return [
                'usd' => $data[$key]['usd'],
                'usd_24h_change' => $data[$key]['usd_24h_change'] ?? 0
            ];
        }
    }
    return null;
}

function getCurrentPosition($user_id, $crypto_symbol) {
    global $conn;

    $stmt = $conn->prepare("SELECT quantity FROM portfolio_holdings WHERE user_id = ? AND crypto_symbol = ?");
    $stmt->bind_param("is", $user_id, $crypto_symbol);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return ['quantity' => $row['quantity']];
    }
    return null;
}

function getAveragePrice($crypto_symbol, $hours) {
    // Simplified - in production, use historical price data
    return getCryptoPrice($crypto_symbol)['usd'] ?? null;
}

function getRecentHigh($crypto_symbol, $hours) {
    // Simplified - in production, query historical data
    $price = getCryptoPrice($crypto_symbol);
    return $price ? $price['usd'] * 1.02 : null; // Assume 2% higher than current
}

function getRecentLow($crypto_symbol, $hours) {
    // Simplified - in production, query historical data
    $price = getCryptoPrice($crypto_symbol);
    return $price ? $price['usd'] * 0.98 : null; // Assume 2% lower than current
}

function getHourChange($crypto_symbol) {
    // Simplified - in production, calculate actual hourly change
    return (mt_rand(-200, 200) / 100); // Random change between -2% and +2%
}

function simulateTradeExecution($user_id, $trade_data) {
    // Simulate successful trade execution
    // In production, this would call the actual trade API
    return true;
}

function logBotError($bot_id, $message) {
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO bot_execution_logs (
            bot_id, trade_type, crypto_symbol, quantity, price, total_value, reason, status, error_message
        ) VALUES (?, 'error', 'N/A', 0, 0, 0, ?, 'failed', ?)
    ");
    $stmt->bind_param("iss", $bot_id, $message, $message);
    $stmt->execute();
}
?>
