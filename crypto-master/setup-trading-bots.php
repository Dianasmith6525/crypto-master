<?php
require_once 'includes/config.php';

// Set execution time limit
set_time_limit(300);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Bot Setup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 30px;
            font-size: 32px;
            text-align: center;
        }
        .step {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .step h3 {
            color: #333;
            margin-bottom: 10px;
        }
        .success {
            color: #10b981;
            font-weight: 600;
        }
        .error {
            color: #ef4444;
            font-weight: 600;
        }
        .info {
            color: #3b82f6;
            font-weight: 600;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            margin-top: 10px;
            font-size: 13px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: block;
            margin: 30px auto 0;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 AI Trading Bot System Setup</h1>
        
        <?php
        echo '<div class="step">';
        echo '<h3>Step 1: Creating trading_bots table</h3>';
        
        $sql = "CREATE TABLE IF NOT EXISTS trading_bots (
            bot_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            bot_name VARCHAR(100) NOT NULL,
            strategy VARCHAR(50) NOT NULL,
            status ENUM('active', 'paused', 'stopped') DEFAULT 'paused',
            crypto_symbol VARCHAR(20) NOT NULL,
            crypto_name VARCHAR(100) NOT NULL,
            investment_amount DECIMAL(20, 2) NOT NULL,
            risk_level ENUM('conservative', 'moderate', 'aggressive') DEFAULT 'moderate',
            take_profit_percent DECIMAL(5, 2) DEFAULT 5.00,
            stop_loss_percent DECIMAL(5, 2) DEFAULT 3.00,
            max_trades_per_day INT DEFAULT 10,
            min_trade_amount DECIMAL(20, 2) DEFAULT 10.00,
            max_trade_amount DECIMAL(20, 2) DEFAULT 1000.00,
            use_trailing_stop BOOLEAN DEFAULT FALSE,
            trailing_stop_percent DECIMAL(5, 2) DEFAULT 2.00,
            ai_confidence_threshold DECIMAL(5, 2) DEFAULT 70.00,
            total_profit_loss DECIMAL(20, 2) DEFAULT 0.00,
            total_trades INT DEFAULT 0,
            winning_trades INT DEFAULT 0,
            losing_trades INT DEFAULT 0,
            last_trade_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_status (user_id, status),
            INDEX idx_strategy (strategy),
            INDEX idx_crypto (crypto_symbol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql) === TRUE) {
            echo '<p class="success">✓ trading_bots table created successfully</p>';
        } else {
            echo '<p class="error">✗ Error: ' . $conn->error . '</p>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>Step 2: Creating bot_trades table</h3>';
        
        $sql = "CREATE TABLE IF NOT EXISTS bot_trades (
            trade_id INT PRIMARY KEY AUTO_INCREMENT,
            bot_id INT NOT NULL,
            user_id INT NOT NULL,
            trade_type ENUM('buy', 'sell') NOT NULL,
            crypto_symbol VARCHAR(20) NOT NULL,
            crypto_amount DECIMAL(20, 8) NOT NULL,
            price_at_entry DECIMAL(20, 8) NOT NULL,
            price_at_exit DECIMAL(20, 8) NULL,
            usd_amount DECIMAL(20, 2) NOT NULL,
            profit_loss DECIMAL(20, 2) DEFAULT 0.00,
            profit_loss_percent DECIMAL(8, 4) DEFAULT 0.00,
            status ENUM('open', 'closed', 'cancelled') DEFAULT 'open',
            entry_signal VARCHAR(100),
            exit_signal VARCHAR(100),
            ai_confidence DECIMAL(5, 2),
            entry_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            exit_time TIMESTAMP NULL,
            notes TEXT,
            FOREIGN KEY (bot_id) REFERENCES trading_bots(bot_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_bot_status (bot_id, status),
            INDEX idx_user_trades (user_id, entry_time),
            INDEX idx_crypto_trades (crypto_symbol, entry_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql) === TRUE) {
            echo '<p class="success">✓ bot_trades table created successfully</p>';
        } else {
            echo '<p class="error">✗ Error: ' . $conn->error . '</p>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>Step 3: Creating bot_performance table</h3>';
        
        $sql = "CREATE TABLE IF NOT EXISTS bot_performance (
            performance_id INT PRIMARY KEY AUTO_INCREMENT,
            bot_id INT NOT NULL,
            date DATE NOT NULL,
            trades_count INT DEFAULT 0,
            winning_trades INT DEFAULT 0,
            losing_trades INT DEFAULT 0,
            total_profit_loss DECIMAL(20, 2) DEFAULT 0.00,
            win_rate DECIMAL(5, 2) DEFAULT 0.00,
            avg_profit_per_trade DECIMAL(20, 2) DEFAULT 0.00,
            max_drawdown DECIMAL(20, 2) DEFAULT 0.00,
            sharpe_ratio DECIMAL(8, 4) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bot_id) REFERENCES trading_bots(bot_id) ON DELETE CASCADE,
            UNIQUE KEY unique_bot_date (bot_id, date),
            INDEX idx_performance_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql) === TRUE) {
            echo '<p class="success">✓ bot_performance table created successfully</p>';
        } else {
            echo '<p class="error">✗ Error: ' . $conn->error . '</p>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>Step 4: Creating ai_signals table</h3>';
        
        $sql = "CREATE TABLE IF NOT EXISTS ai_signals (
            signal_id INT PRIMARY KEY AUTO_INCREMENT,
            crypto_symbol VARCHAR(20) NOT NULL,
            signal_type ENUM('buy', 'sell', 'hold') NOT NULL,
            confidence DECIMAL(5, 2) NOT NULL,
            price DECIMAL(20, 8) NOT NULL,
            indicators JSON,
            market_sentiment VARCHAR(50),
            prediction_timeframe VARCHAR(20),
            reasoning TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            INDEX idx_crypto_signal (crypto_symbol, created_at),
            INDEX idx_confidence (confidence),
            INDEX idx_signal_type (signal_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql) === TRUE) {
            echo '<p class="success">✓ ai_signals table created successfully</p>';
        } else {
            echo '<p class="error">✗ Error: ' . $conn->error . '</p>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>Step 5: Creating bot_logs table</h3>';
        
        $sql = "CREATE TABLE IF NOT EXISTS bot_logs (
            log_id INT PRIMARY KEY AUTO_INCREMENT,
            bot_id INT NOT NULL,
            log_type ENUM('info', 'trade', 'signal', 'error', 'warning') NOT NULL,
            message TEXT NOT NULL,
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bot_id) REFERENCES trading_bots(bot_id) ON DELETE CASCADE,
            INDEX idx_bot_logs (bot_id, created_at),
            INDEX idx_log_type (log_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql) === TRUE) {
            echo '<p class="success">✓ bot_logs table created successfully</p>';
        } else {
            echo '<p class="error">✗ Error: ' . $conn->error . '</p>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>Step 6: Adding wallet integration</h3>';
        
        // Check if user_wallets table exists
        $check = $conn->query("SHOW TABLES LIKE 'user_wallets'");
        if ($check->num_rows > 0) {
            echo '<p class="success">✓ Wallet system detected - bot trades will update user balances</p>';
        } else {
            echo '<p class="info">ℹ Note: Run setup-wallet.php first to enable automatic balance updates</p>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>Step 7: Database summary</h3>';
        echo '<p class="info">Trading bot system tables created:</p>';
        echo '<ul style="margin-left: 20px; margin-top: 10px; color: #666;">';
        echo '<li><strong>trading_bots</strong> - Bot configurations and settings</li>';
        echo '<li><strong>bot_trades</strong> - Individual trade records</li>';
        echo '<li><strong>bot_performance</strong> - Daily performance metrics</li>';
        echo '<li><strong>ai_signals</strong> - AI-generated trading signals</li>';
        echo '<li><strong>bot_logs</strong> - Activity and error logs</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="step">';
        echo '<h3>✅ Setup Complete!</h3>';
        echo '<p style="color: #10b981; margin-top: 10px;">Your AI trading bot system is ready to use.</p>';
        echo '<p style="color: #666; margin-top: 10px;"><strong>Next steps:</strong></p>';
        echo '<ol style="margin-left: 20px; margin-top: 10px; color: #666;">';
        echo '<li>Visit <strong>ai-trading-bot.php</strong> to create your first bot</li>';
        echo '<li>Choose a trading strategy and configure risk settings</li>';
        echo '<li>Set up the cron job: <code>*/5 * * * * php bot-executor.php</code></li>';
        echo '<li>Monitor performance on your dashboard</li>';
        echo '</ol>';
        echo '</div>';

        $conn->close();
        ?>
        
        <a href="ai-trading-bot.php" class="btn">Go to AI Trading Bots →</a>
    </div>
</body>
</html>
