-- Cryptocurrency Trading Platform Database Schema
-- Created: November 22, 2025

-- Users Table (Authentication)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    country VARCHAR(100),
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    verification_sent_at TIMESTAMP,
    last_login TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    backup_codes TEXT,
    referral_code VARCHAR(20) UNIQUE,
    referred_by INT,
    total_referral_earnings DECIMAL(20, 2) DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_referral_code (referral_code),
    FOREIGN KEY (referred_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Portfolio Holdings Table
CREATE TABLE IF NOT EXISTS portfolio_holdings (
    holding_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_symbol VARCHAR(10) NOT NULL,
    crypto_name VARCHAR(100) NOT NULL,
    quantity DECIMAL(20, 8) NOT NULL,
    entry_price DECIMAL(20, 2) NOT NULL,
    entry_date DATE NOT NULL,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_crypto_id (crypto_id),
    UNIQUE KEY unique_user_crypto (user_id, crypto_id)
);

-- Watchlist Table
CREATE TABLE IF NOT EXISTS watchlist (
    watchlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_symbol VARCHAR(10) NOT NULL,
    crypto_name VARCHAR(100) NOT NULL,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    UNIQUE KEY unique_user_watchlist (user_id, crypto_id)
);

-- Price Alerts Table
CREATE TABLE IF NOT EXISTS price_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_symbol VARCHAR(10) NOT NULL,
    crypto_name VARCHAR(100) NOT NULL,
    alert_type ENUM('above', 'below') NOT NULL,
    target_price DECIMAL(20, 2) NOT NULL,
    current_price DECIMAL(20, 2),
    is_active BOOLEAN DEFAULT TRUE,
    is_triggered BOOLEAN DEFAULT FALSE,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_checked TIMESTAMP,
    triggered_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active),
    INDEX idx_triggered (is_triggered)
);

-- Trade History Table
CREATE TABLE IF NOT EXISTS trade_history (
    trade_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_symbol VARCHAR(10) NOT NULL,
    trade_type ENUM('buy', 'sell') NOT NULL,
    quantity DECIMAL(20, 8) NOT NULL,
    price_per_unit DECIMAL(20, 2) NOT NULL,
    total_value DECIMAL(20, 2) NOT NULL,
    trade_date DATE NOT NULL,
    trade_time TIME,
    notes TEXT,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_trade_date (trade_date),
    INDEX idx_crypto_id (crypto_id)
);

-- Password Reset Tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reset_token VARCHAR(255) UNIQUE NOT NULL,
    token_expiry TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (reset_token)
);

-- User Preferences/Settings
CREATE TABLE IF NOT EXISTS user_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    theme ENUM('light', 'dark') DEFAULT 'light',
    email_alerts BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    language VARCHAR(5) DEFAULT 'en',
    date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
);

-- Security Logs Table
CREATE TABLE IF NOT EXISTS security_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    event_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    event_data JSON,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_date_created (date_created)
);

-- Bot Trading Strategies Table
CREATE TABLE IF NOT EXISTS bot_trading_strategies (
    strategy_id INT AUTO_INCREMENT PRIMARY KEY,
    strategy_name VARCHAR(100) NOT NULL,
    strategy_description TEXT,
    strategy_type ENUM('trend_following', 'mean_reversion', 'breakout', 'scalping', 'arbitrage') NOT NULL,
    parameters JSON NOT NULL,
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_strategy_type (strategy_type),
    INDEX idx_active (is_active)
);

-- User Bot Instances Table
CREATE TABLE IF NOT EXISTS user_bot_instances (
    bot_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    strategy_id INT NOT NULL,
    bot_name VARCHAR(100) NOT NULL,
    crypto_symbol VARCHAR(10) NOT NULL,
    status ENUM('active', 'paused', 'stopped') DEFAULT 'paused',
    configuration JSON NOT NULL,
    allocated_balance DECIMAL(20, 2) DEFAULT 0,
    max_daily_trades INT DEFAULT 10,
    max_position_size DECIMAL(20, 2) DEFAULT 1000,
    stop_loss_percentage DECIMAL(5, 2) DEFAULT 5.00,
    take_profit_percentage DECIMAL(5, 2) DEFAULT 10.00,
    is_backtesting BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_run TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (strategy_id) REFERENCES bot_trading_strategies(strategy_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_strategy_id (strategy_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_user_bot_name (user_id, bot_name)
);

-- Bot Execution Logs Table
CREATE TABLE IF NOT EXISTS bot_execution_logs (
    execution_id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    trade_type ENUM('buy', 'sell') NOT NULL,
    crypto_symbol VARCHAR(10) NOT NULL,
    quantity DECIMAL(20, 8) NOT NULL,
    price DECIMAL(20, 2) NOT NULL,
    total_value DECIMAL(20, 2) NOT NULL,
    reason VARCHAR(255),
    profit_loss DECIMAL(20, 2) DEFAULT 0,
    execution_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    FOREIGN KEY (bot_id) REFERENCES user_bot_instances(bot_id) ON DELETE CASCADE,
    INDEX idx_bot_id (bot_id),
    INDEX idx_execution_time (execution_time),
    INDEX idx_status (status)
);

-- Bot Performance Metrics Table
CREATE TABLE IF NOT EXISTS bot_performance_metrics (
    metric_id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    date DATE NOT NULL,
    total_trades INT DEFAULT 0,
    winning_trades INT DEFAULT 0,
    losing_trades INT DEFAULT 0,
    total_profit_loss DECIMAL(20, 2) DEFAULT 0,
    win_rate DECIMAL(5, 2) DEFAULT 0,
    average_profit DECIMAL(20, 2) DEFAULT 0,
    average_loss DECIMAL(20, 2) DEFAULT 0,
    max_drawdown DECIMAL(20, 2) DEFAULT 0,
    sharpe_ratio DECIMAL(10, 4) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES user_bot_instances(bot_id) ON DELETE CASCADE,
    INDEX idx_bot_id (bot_id),
    INDEX idx_date (date),
    UNIQUE KEY unique_bot_date (bot_id, date)
);

-- Referral Settings Table
CREATE TABLE IF NOT EXISTS referral_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_bonus_percentage DECIMAL(5, 2) DEFAULT 10.00,
    referee_bonus_amount DECIMAL(10, 2) DEFAULT 5.00,
    min_trade_volume DECIMAL(10, 2) DEFAULT 100.00,
    max_referral_earnings DECIMAL(10, 2) DEFAULT 1000.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Referral Transactions Table
CREATE TABLE IF NOT EXISTS referral_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referee_id INT NOT NULL,
    bonus_type ENUM('referrer_percentage', 'referee_welcome') NOT NULL,
    bonus_amount DECIMAL(10, 2) NOT NULL,
    trade_volume DECIMAL(20, 2) DEFAULT 0,
    status ENUM('pending', 'credited', 'cancelled') DEFAULT 'pending',
    credited_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (referee_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_referrer_id (referrer_id),
    INDEX idx_referee_id (referee_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Referral Clicks Table
CREATE TABLE IF NOT EXISTS referral_clicks (
    click_id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referral_code VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    converted BOOLEAN DEFAULT FALSE,
    converted_at TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_referrer_id (referrer_id),
    INDEX idx_referral_code (referral_code),
    INDEX idx_converted (converted),
    INDEX idx_clicked_at (clicked_at)
);
