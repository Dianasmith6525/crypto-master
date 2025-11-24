<?php
/**
 * Trading Bot System Setup Script
 * Initializes default trading strategies
 */

require_once 'includes/config.php';

echo "<h1>Trading Bot System Setup</h1>";
echo "<p>Initializing default trading strategies...</p>";

// Check if strategies already exist
$sql = "SELECT COUNT(*) as count FROM bot_trading_strategies";
$result = $conn->query($sql);
$strategies_exist = $result->fetch_assoc()['count'] > 0;

if ($strategies_exist) {
    echo "<p style='color: orange;'>⚠ Trading strategies already exist. Skipping initialization.</p>";
    echo "<p><a href='dashboard-pro.html'>Go to Dashboard</a></p>";
    exit;
}

// Default trading strategies with pre-configured parameters
$strategies = [
    [
        'name' => 'Trend Follower Pro',
        'description' => 'Follows market trends using moving averages and momentum indicators. Best for trending markets.',
        'type' => 'trend_following',
        'parameters' => json_encode([
            'trend_threshold' => 2.0,
            'ma_period_short' => 20,
            'ma_period_long' => 50,
            'momentum_period' => 14,
            'entry_confirmation' => 'volume_spike'
        ]),
        'risk_level' => 'medium'
    ],
    [
        'name' => 'Mean Reversion Master',
        'description' => 'Identifies overbought/oversold conditions using RSI and Bollinger Bands. Ideal for ranging markets.',
        'type' => 'mean_reversion',
        'parameters' => json_encode([
            'rsi_period' => 14,
            'rsi_oversold' => 30,
            'rsi_overbought' => 70,
            'bb_period' => 20,
            'bb_std_dev' => 2,
            'mean_lookback' => 50
        ]),
        'risk_level' => 'low'
    ],
    [
        'name' => 'Breakout Hunter',
        'description' => 'Detects price breakouts from consolidation zones with volume confirmation. High profit potential.',
        'type' => 'breakout',
        'parameters' => json_encode([
            'consolidation_period' => 24,
            'breakout_threshold' => 3.0,
            'volume_multiplier' => 1.5,
            'retest_wait' => true,
            'false_breakout_filter' => true
        ]),
        'risk_level' => 'high'
    ],
    [
        'name' => 'Scalping Bot Ultra',
        'description' => 'Quick in-and-out trades capitalizing on small price movements. Requires active monitoring.',
        'type' => 'scalping',
        'parameters' => json_encode([
            'trade_duration_minutes' => 5,
            'min_profit_percentage' => 0.5,
            'max_trades_per_hour' => 12,
            'spread_threshold' => 0.1,
            'quick_exit_enabled' => true
        ]),
        'risk_level' => 'high'
    ],
    [
        'name' => 'Arbitrage Seeker',
        'description' => 'Exploits price differences across exchanges for risk-free profits (requires multi-exchange access).',
        'type' => 'arbitrage',
        'parameters' => json_encode([
            'min_spread_percentage' => 0.3,
            'exchanges' => ['binance', 'coinbase', 'kraken'],
            'execution_speed' => 'fast',
            'fee_consideration' => true,
            'auto_balance' => true
        ]),
        'risk_level' => 'low'
    ]
];

$success_count = 0;
$error_count = 0;

foreach ($strategies as $strategy) {
    $sql = "INSERT INTO bot_trading_strategies (
                strategy_name, 
                strategy_description, 
                strategy_type, 
                parameters, 
                risk_level,
                is_active
            ) VALUES (?, ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssss",
        $strategy['name'],
        $strategy['description'],
        $strategy['type'],
        $strategy['parameters'],
        $strategy['risk_level']
    );
    
    if ($stmt->execute()) {
        $success_count++;
        echo "<p style='color: green;'>✓ Created strategy: <strong>" . htmlspecialchars($strategy['name']) . "</strong> (Risk: " . ucfirst($strategy['risk_level']) . ")</p>";
    } else {
        $error_count++;
        echo "<p style='color: red;'>✗ Failed to create strategy: " . htmlspecialchars($strategy['name']) . " - " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<h3>Setup Summary</h3>";
echo "<p>✓ Successfully created: <strong>$success_count</strong> trading strategies</p>";

if ($error_count > 0) {
    echo "<p style='color: red;'>✗ Failed: <strong>$error_count</strong> strategies</p>";
}

echo "<hr>";
echo "<h3>📊 Available Trading Strategies:</h3>";
echo "<ul>";
echo "<li><strong>Trend Follower Pro</strong> - Medium Risk - Best for bull/bear markets</li>";
echo "<li><strong>Mean Reversion Master</strong> - Low Risk - Best for sideways markets</li>";
echo "<li><strong>Breakout Hunter</strong> - High Risk - High profit potential</li>";
echo "<li><strong>Scalping Bot Ultra</strong> - High Risk - Fast-paced trading</li>";
echo "<li><strong>Arbitrage Seeker</strong> - Low Risk - Risk-free profits</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Users can now create bot instances from these strategies</li>";
echo "<li>Configure bot parameters (balance, stop-loss, take-profit)</li>";
echo "<li>Set up cron job to run <code>api/bot-executor.php</code> every 5 minutes</li>";
echo "<li>Monitor bot performance in the trading bot dashboard</li>";
echo "</ol>";

echo "<p><a href='trading-bots.html' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; margin-top: 20px;'>Open Bot Dashboard</a></p>";
echo "<p><a href='dashboard-pro.html'>Go to Main Dashboard</a></p>";

$conn->close();
?>
