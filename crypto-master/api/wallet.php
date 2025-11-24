<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

set_security_headers();
set_cors_headers();

// Check authentication
if (!is_user_authenticated()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = get_user_id();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

switch ($action) {
    case 'get_balance':
        getBalance($conn, $user_id);
        break;
        
    case 'get_deposit_address':
        getDepositAddress($conn, $user_id);
        break;
        
    case 'create_deposit':
        createDeposit($conn, $user_id);
        break;
        
    case 'get_deposits':
        getDeposits($conn, $user_id);
        break;
        
    case 'get_transactions':
        getTransactions($conn, $user_id);
        break;
        
    case 'confirm_deposit':
        confirmDeposit($conn, $user_id);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getBalance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT balance, currency, updated_at FROM user_wallets WHERE user_id = ? AND currency = 'USD'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'balance' => floatval($row['balance']),
            'currency' => $row['currency'],
            'formatted' => '$' . number_format($row['balance'], 2),
            'last_updated' => $row['updated_at']
        ]);
    } else {
        // Create wallet if not exists
        $stmt = $conn->prepare("INSERT INTO user_wallets (user_id, balance, currency) VALUES (?, 0.00, 'USD')");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'balance' => 0.00,
            'currency' => 'USD',
            'formatted' => '$0.00'
        ]);
    }
}

function getDepositAddress($conn, $user_id) {
    $crypto = $_POST['crypto'] ?? 'BTC';
    $network = $_POST['network'] ?? 'Bitcoin';
    
    // Check if address exists
    $stmt = $conn->prepare("SELECT address, qr_code FROM deposit_addresses WHERE user_id = ? AND crypto_symbol = ? AND network = ? AND is_active = TRUE");
    $stmt->bind_param("iss", $user_id, $crypto, $network);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'address' => $row['address'],
            'qr_code' => $row['qr_code'],
            'crypto' => $crypto,
            'network' => $network
        ]);
    } else {
        // Generate new address (in production, integrate with actual blockchain API)
        $address = generateDepositAddress($crypto, $network);
        $qr_code = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($address);
        
        $stmt = $conn->prepare("INSERT INTO deposit_addresses (user_id, crypto_symbol, network, address, qr_code) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $crypto, $network, $address, $qr_code);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'address' => $address,
            'qr_code' => $qr_code,
            'crypto' => $crypto,
            'network' => $network
        ]);
    }
}

function generateDepositAddress($crypto, $network) {
    // Generate a unique address (this is a placeholder - in production use actual blockchain API)
    $prefixes = [
        'BTC' => '1',
        'ETH' => '0x',
        'USDT' => '0x',
        'BNB' => 'bnb',
        'USDC' => '0x',
        'XRP' => 'r',
        'ADA' => 'addr1',
        'SOL' => 'So1',
        'DOT' => '1',
        'DOGE' => 'D'
    ];
    
    $prefix = $prefixes[$crypto] ?? '0x';
    $length = ($crypto == 'ETH' || $crypto == 'USDT' || $crypto == 'USDC') ? 40 : 32;
    
    return $prefix . bin2hex(random_bytes($length / 2));
}

function createDeposit($conn, $user_id) {
    $crypto = $_POST['crypto'] ?? '';
    $crypto_name = $_POST['crypto_name'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $tx_hash = $_POST['tx_hash'] ?? '';
    $network = $_POST['network'] ?? 'Bitcoin';
    
    if (empty($crypto) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid deposit details']);
        return;
    }
    
    // Get current crypto price (using a simple API call - in production use reliable API)
    $price_url = "https://api.coingecko.com/api/v3/simple/price?ids=" . strtolower($crypto_name) . "&vs_currencies=usd";
    $price_data = @file_get_contents($price_url);
    $price = 0;
    
    if ($price_data) {
        $price_json = json_decode($price_data, true);
        $price = $price_json[strtolower($crypto_name)]['usd'] ?? 0;
    }
    
    $usd_value = $amount * $price;
    
    // Get deposit address
    $stmt = $conn->prepare("SELECT address FROM deposit_addresses WHERE user_id = ? AND crypto_symbol = ? AND network = ? LIMIT 1");
    $stmt->bind_param("iss", $user_id, $crypto, $network);
    $stmt->execute();
    $result = $stmt->get_result();
    $address = $result->fetch_assoc()['address'] ?? 'Unknown';
    
    // Create deposit record
    $stmt = $conn->prepare("INSERT INTO crypto_deposits (user_id, crypto_symbol, crypto_name, amount, usd_value, deposit_address, transaction_hash, network, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("issddsss", $user_id, $crypto, $crypto_name, $amount, $usd_value, $address, $tx_hash, $network);
    
    if ($stmt->execute()) {
        $deposit_id = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Deposit submitted successfully. Waiting for confirmations.',
            'deposit_id' => $deposit_id,
            'amount' => $amount,
            'crypto' => $crypto,
            'usd_value' => $usd_value,
            'status' => 'pending'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create deposit']);
    }
}

function getDeposits($conn, $user_id) {
    $limit = intval($_GET['limit'] ?? 10);
    $status = $_GET['status'] ?? 'all';
    
    $sql = "SELECT * FROM crypto_deposits WHERE user_id = ?";
    $params = [$user_id];
    $types = "i";
    
    if ($status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY deposit_date DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $deposits = [];
    while ($row = $result->fetch_assoc()) {
        $deposits[] = $row;
    }
    
    echo json_encode(['success' => true, 'deposits' => $deposits]);
}

function getTransactions($conn, $user_id) {
    $limit = intval($_GET['limit'] ?? 20);
    
    $stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    echo json_encode(['success' => true, 'transactions' => $transactions]);
}

function confirmDeposit($conn, $user_id) {
    // This would be called by a webhook or cron job in production
    $deposit_id = intval($_POST['deposit_id'] ?? 0);
    
    // Get deposit details
    $stmt = $conn->prepare("SELECT * FROM crypto_deposits WHERE deposit_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $deposit_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Get current balance
        $stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND currency = 'USD' FOR UPDATE");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $wallet = $stmt->get_result()->fetch_assoc();
        $balance_before = floatval($wallet['balance'] ?? 0);
        $balance_after = $balance_before + $row['usd_value'];
        
        // Update wallet balance
        $stmt = $conn->prepare("UPDATE user_wallets SET balance = ? WHERE user_id = ? AND currency = 'USD'");
        $stmt->bind_param("di", $balance_after, $user_id);
        $stmt->execute();
        
        // Update deposit status
        $stmt = $conn->prepare("UPDATE crypto_deposits SET status = 'completed', confirmed_at = NOW() WHERE deposit_id = ?");
        $stmt->bind_param("i", $deposit_id);
        $stmt->execute();
        
        // Create transaction record
        $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, currency, balance_before, balance_after, reference_id, reference_type, description) VALUES (?, 'deposit', ?, 'USD', ?, ?, ?, 'crypto_deposit', ?)");
        $description = "Crypto deposit: {$row['amount']} {$row['crypto_symbol']}";
        $stmt->bind_param("iddis", $user_id, $row['usd_value'], $balance_before, $balance_after, $deposit_id, $description);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Deposit confirmed and balance updated',
            'new_balance' => $balance_after
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Deposit not found']);
    }
}
?>
