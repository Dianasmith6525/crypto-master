<?php
/**
 * Database Setup Script
 * Creates necessary tables for the crypto platform
 */

$host = 'localhost';
$user = 'root';
$password = '';
$db = 'crypto_platform';

// Connect to MySQL server (without database first)
$conn = new mysqli($host, $user, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$create_db = "CREATE DATABASE IF NOT EXISTS " . $db;
if ($conn->query($create_db) === TRUE) {
    echo "Database created successfully or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($db);

// Create users table
$users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    date_of_birth DATE,
    country VARCHAR(100),
    verification_token VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    INDEX idx_email (email),
    INDEX idx_verification_token (verification_token)
)";

if ($conn->query($users_table) === TRUE) {
    echo "Users table created successfully.<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// Create portfolios table
$portfolios_table = "CREATE TABLE IF NOT EXISTS portfolios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_name VARCHAR(100) NOT NULL,
    crypto_symbol VARCHAR(20) NOT NULL,
    amount DECIMAL(18, 8) NOT NULL,
    purchase_price DECIMAL(18, 2) NOT NULL,
    purchase_date DATETIME NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_crypto_id (crypto_id),
    UNIQUE KEY unique_holding (user_id, crypto_id, purchase_date)
)";

if ($conn->query($portfolios_table) === TRUE) {
    echo "Portfolios table created successfully.<br>";
} else {
    echo "Error creating portfolios table: " . $conn->error . "<br>";
}

// Create watchlist table
$watchlist_table = "CREATE TABLE IF NOT EXISTS watchlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_name VARCHAR(100) NOT NULL,
    crypto_symbol VARCHAR(20) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    UNIQUE KEY unique_watchlist (user_id, crypto_id)
)";

if ($conn->query($watchlist_table) === TRUE) {
    echo "Watchlist table created successfully.<br>";
} else {
    echo "Error creating watchlist table: " . $conn->error . "<br>";
}

// Create price alerts table
$alerts_table = "CREATE TABLE IF NOT EXISTS price_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_name VARCHAR(100) NOT NULL,
    alert_type ENUM('above', 'below') NOT NULL,
    price_threshold DECIMAL(18, 2) NOT NULL,
    status ENUM('active', 'triggered', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    triggered_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_crypto_id (crypto_id),
    INDEX idx_status (status)
)";

if ($conn->query($alerts_table) === TRUE) {
    echo "Price Alerts table created successfully.<br>";
} else {
    echo "Error creating alerts table: " . $conn->error . "<br>";
}

// Create newsletters table
$newsletters_table = "CREATE TABLE IF NOT EXISTS newsletters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    token VARCHAR(255) UNIQUE,
    status ENUM('pending', 'subscribed', 'unsubscribed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    unsubscribed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    last_email_sent TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_token (token)
)";

if ($conn->query($newsletters_table) === TRUE) {
    echo "Newsletters table created successfully.<br>";
} else {
    echo "Error creating newsletters table: " . $conn->error . "<br>";
}

// Create transactions table for trading history
$transactions_table = "CREATE TABLE IF NOT EXISTS transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    transaction_type ENUM('buy', 'sell') NOT NULL,
    crypto_id VARCHAR(50) NOT NULL,
    crypto_name VARCHAR(100) NOT NULL,
    amount DECIMAL(18, 8) NOT NULL,
    price_per_unit DECIMAL(18, 2) NOT NULL,
    total_value DECIMAL(18, 2) NOT NULL,
    transaction_date DATETIME NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_date (transaction_date)
)";

if ($conn->query($transactions_table) === TRUE) {
    echo "Transactions table created successfully.<br>";
} else {
    echo "Error creating transactions table: " . $conn->error . "<br>";
}

echo "<br><strong>✓ Database setup completed successfully!</strong><br>";
echo "<a href='javascript:window.history.back();'>Go Back</a>";

$conn->close();
?>
