<?php
require_once 'includes/config.php';

// Create wallet and deposit tables
$tables = [
    "CREATE TABLE IF NOT EXISTS user_wallets (
        wallet_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        balance DECIMAL(20, 8) DEFAULT 0.00000000,
        currency VARCHAR(10) DEFAULT 'USD',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_currency (user_id, currency)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS crypto_deposits (
        deposit_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        crypto_symbol VARCHAR(20) NOT NULL,
        crypto_name VARCHAR(100) NOT NULL,
        amount DECIMAL(20, 8) NOT NULL,
        usd_value DECIMAL(20, 2) NOT NULL,
        deposit_address VARCHAR(255) NOT NULL,
        transaction_hash VARCHAR(255),
        status ENUM('pending', 'confirmed', 'completed', 'failed') DEFAULT 'pending',
        confirmations INT DEFAULT 0,
        required_confirmations INT DEFAULT 3,
        network VARCHAR(50),
        deposit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        confirmed_at TIMESTAMP NULL,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_user_status (user_id, status),
        INDEX idx_tx_hash (transaction_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS crypto_withdrawals (
        withdrawal_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        crypto_symbol VARCHAR(20) NOT NULL,
        crypto_name VARCHAR(100) NOT NULL,
        amount DECIMAL(20, 8) NOT NULL,
        usd_value DECIMAL(20, 2) NOT NULL,
        withdrawal_address VARCHAR(255) NOT NULL,
        transaction_hash VARCHAR(255),
        status ENUM('pending', 'processing', 'completed', 'cancelled', 'failed') DEFAULT 'pending',
        network VARCHAR(50),
        fee DECIMAL(20, 8) DEFAULT 0,
        withdrawal_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_user_status (user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS deposit_addresses (
        address_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        crypto_symbol VARCHAR(20) NOT NULL,
        network VARCHAR(50) NOT NULL,
        address VARCHAR(255) NOT NULL,
        qr_code TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_crypto_network (user_id, crypto_symbol, network),
        INDEX idx_address (address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS wallet_transactions (
        transaction_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        type ENUM('deposit', 'withdrawal', 'trade', 'fee', 'bonus', 'transfer') NOT NULL,
        amount DECIMAL(20, 8) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        balance_before DECIMAL(20, 8) NOT NULL,
        balance_after DECIMAL(20, 8) NOT NULL,
        reference_id INT,
        reference_type VARCHAR(50),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, created_at),
        INDEX idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Wallet Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .success { color: #4CAF50; padding: 10px; background: #e8f5e9; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0; }
        .info { color: #666; padding: 10px; background: #e3f2fd; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>💰 Wallet System Setup</h1>";

$errors = 0;
$success = 0;

foreach ($tables as $sql) {
    preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $matches);
    $table_name = $matches[1];
    
    echo "<div class='info'>Creating table: <strong>$table_name</strong></div>";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✓ Table '$table_name' created successfully</div>";
        $success++;
    } else {
        echo "<div class='error'>✗ Error creating table '$table_name': " . $conn->error . "</div>";
        $errors++;
    }
}

// Create default wallets for existing users
echo "<div class='info'>Creating default USD wallets for existing users...</div>";
$result = $conn->query("SELECT user_id FROM users WHERE role = 'user'");
$wallet_count = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $check = $conn->query("SELECT wallet_id FROM user_wallets WHERE user_id = $user_id AND currency = 'USD'");
        
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO user_wallets (user_id, balance, currency) VALUES (?, 0.00, 'USD')");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $wallet_count++;
            }
        }
    }
    echo "<div class='success'>✓ Created $wallet_count default wallets</div>";
}

echo "<br><h2>📊 Setup Summary</h2>";
echo "<div class='success'><strong>Success:</strong> $success tables created</div>";
if ($errors > 0) {
    echo "<div class='error'><strong>Errors:</strong> $errors</div>";
}

echo "<br><h2>🎯 Next Steps</h2>
<div class='info'>
<ol>
    <li>Wallet tables are ready</li>
    <li>Users can now deposit cryptocurrency</li>
    <li>Balances will be tracked in USD</li>
    <li>Transaction history will be maintained</li>
</ol>
</div>

<div style='margin-top: 20px; text-align: center;'>
    <a href='dashboard.php' style='display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Dashboard</a>
</div>

</div>
</body>
</html>";

$conn->close();
?>
