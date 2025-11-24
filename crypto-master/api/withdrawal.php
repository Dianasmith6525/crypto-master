<?php
/**
 * Withdrawal API
 * Handles cryptocurrency withdrawal requests and approvals
 */

header('Content-Type: application/json');
require_once '../includes/db.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Router
switch ($action) {
    case 'create':
        createWithdrawal($conn, $user_id);
        break;
    case 'list':
        getWithdrawals($conn, $user_id);
        break;
    case 'approve':
        approveWithdrawal($conn, $user_id);
        break;
    case 'reject':
        rejectWithdrawal($conn, $user_id);
        break;
    case 'cancel':
        cancelWithdrawal($conn, $user_id);
        break;
    case 'balance':
        getWalletBalance($conn, $user_id);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();

/**
 * Create withdrawal request
 */
function createWithdrawal($conn, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $crypto_symbol = strtoupper($data['crypto_symbol'] ?? '');
    $crypto_amount = floatval($data['crypto_amount'] ?? 0);
    $withdrawal_address = trim($data['withdrawal_address'] ?? '');
    
    // Validation
    if (empty($crypto_symbol) || $crypto_amount <= 0 || empty($withdrawal_address)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Validate crypto symbol
    $valid_cryptos = ['BTC', 'ETH', 'USDT', 'BNB', 'USDC', 'XRP', 'ADA', 'SOL', 'DOT', 'DOGE'];
    if (!in_array($crypto_symbol, $valid_cryptos)) {
        echo json_encode(['success' => false, 'message' => 'Invalid cryptocurrency']);
        return;
    }
    
    // Validate address format (basic validation)
    if (strlen($withdrawal_address) < 20 || strlen($withdrawal_address) > 120) {
        echo json_encode(['success' => false, 'message' => 'Invalid wallet address format']);
        return;
    }
    
    // Check user balance
    $wallet_check = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND crypto_symbol = ?");
    $wallet_check->bind_param("is", $user_id, $crypto_symbol);
    $wallet_check->execute();
    $wallet_result = $wallet_check->get_result();
    
    if ($wallet_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => "No {$crypto_symbol} wallet found"]);
        return;
    }
    
    $wallet = $wallet_result->fetch_assoc();
    if ($wallet['balance'] < $crypto_amount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
        return;
    }
    
    // Get current price in USD
    $price_usd = getCurrentPrice($crypto_symbol);
    $usd_amount = $crypto_amount * $price_usd;
    
    // Calculate fee (1% withdrawal fee)
    $fee_percentage = 0.01;
    $network_fee = $crypto_amount * $fee_percentage;
    $final_amount = $crypto_amount - $network_fee;
    
    $conn->begin_transaction();
    
    try {
        // Create withdrawal request
        $stmt = $conn->prepare("
            INSERT INTO crypto_withdrawals 
            (user_id, crypto_symbol, crypto_amount, usd_amount, network_fee, withdrawal_address, status, approval_status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')
        ");
        $stmt->bind_param("isddds", $user_id, $crypto_symbol, $final_amount, $usd_amount, $network_fee, $withdrawal_address);
        $stmt->execute();
        $withdrawal_id = $conn->insert_id;
        
        // Deduct from wallet (hold in pending)
        $stmt = $conn->prepare("UPDATE user_wallets SET balance = balance - ? WHERE user_id = ? AND crypto_symbol = ?");
        $stmt->bind_param("dis", $crypto_amount, $user_id, $crypto_symbol);
        $stmt->execute();
        
        // Record transaction
        $description = "Withdrawal request: {$crypto_amount} {$crypto_symbol}";
        $stmt = $conn->prepare("
            INSERT INTO wallet_transactions 
            (user_id, type, crypto_symbol, crypto_amount, usd_amount, description, status)
            VALUES (?, 'withdrawal', ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("isdds", $user_id, $crypto_symbol, $crypto_amount, $usd_amount, $description);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully',
            'withdrawal_id' => $withdrawal_id,
            'final_amount' => $final_amount,
            'network_fee' => $network_fee,
            'status' => 'Pending admin approval'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to create withdrawal request']);
    }
}

/**
 * Get user withdrawals
 */
function getWithdrawals($conn, $user_id) {
    $status = $_GET['status'] ?? 'all';
    
    $query = "
        SELECT 
            withdrawal_id,
            crypto_symbol,
            crypto_amount,
            usd_amount,
            network_fee,
            withdrawal_address,
            status,
            approval_status,
            admin_notes,
            withdrawal_date,
            processed_date
        FROM crypto_withdrawals 
        WHERE user_id = ?
    ";
    
    if ($status !== 'all') {
        $query .= " AND status = ?";
    }
    
    $query .= " ORDER BY withdrawal_date DESC LIMIT 50";
    
    $stmt = $conn->prepare($query);
    if ($status !== 'all') {
        $stmt->bind_param("is", $user_id, $status);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    while ($row = $result->fetch_assoc()) {
        $withdrawals[] = [
            'id' => $row['withdrawal_id'],
            'crypto' => $row['crypto_symbol'],
            'amount' => floatval($row['crypto_amount']),
            'usd_amount' => floatval($row['usd_amount']),
            'fee' => floatval($row['network_fee']),
            'address' => $row['withdrawal_address'],
            'status' => $row['status'],
            'approval_status' => $row['approval_status'],
            'admin_notes' => $row['admin_notes'],
            'date' => $row['withdrawal_date'],
            'processed_date' => $row['processed_date']
        ];
    }
    
    echo json_encode(['success' => true, 'withdrawals' => $withdrawals]);
}

/**
 * Approve withdrawal (admin only)
 */
function approveWithdrawal($conn, $user_id) {
    // Check admin status
    $admin_check = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $admin_check->bind_param("i", $user_id);
    $admin_check->execute();
    $admin_result = $admin_check->get_result()->fetch_assoc();
    
    if (!$admin_result || !$admin_result['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $withdrawal_id = intval($data['withdrawal_id'] ?? 0);
    $tx_hash = trim($data['tx_hash'] ?? '');
    $admin_notes = trim($data['admin_notes'] ?? 'Approved');
    
    if ($withdrawal_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Update withdrawal status
        $stmt = $conn->prepare("
            UPDATE crypto_withdrawals 
            SET status = 'completed', 
                approval_status = 'approved',
                admin_notes = ?,
                tx_hash = ?,
                approved_by = ?,
                processed_date = NOW()
            WHERE withdrawal_id = ? AND status = 'pending'
        ");
        $stmt->bind_param("ssii", $admin_notes, $tx_hash, $user_id, $withdrawal_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Withdrawal not found or already processed');
        }
        
        // Update wallet transaction
        $stmt = $conn->prepare("
            UPDATE wallet_transactions 
            SET status = 'completed' 
            WHERE type = 'withdrawal' AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute();
        
        $conn->commit();
        
        // TODO: Send email notification to user
        
        echo json_encode(['success' => true, 'message' => 'Withdrawal approved successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Reject withdrawal (admin only)
 */
function rejectWithdrawal($conn, $user_id) {
    // Check admin status
    $admin_check = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $admin_check->bind_param("i", $user_id);
    $admin_check->execute();
    $admin_result = $admin_check->get_result()->fetch_assoc();
    
    if (!$admin_result || !$admin_result['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $withdrawal_id = intval($data['withdrawal_id'] ?? 0);
    $admin_notes = trim($data['admin_notes'] ?? 'Rejected');
    
    if ($withdrawal_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        return;
    }
    
    // Get withdrawal details
    $stmt = $conn->prepare("
        SELECT user_id, crypto_symbol, crypto_amount 
        FROM crypto_withdrawals 
        WHERE withdrawal_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("i", $withdrawal_id);
    $stmt->execute();
    $withdrawal = $stmt->get_result()->fetch_assoc();
    
    if (!$withdrawal) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found or already processed']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Update withdrawal status
        $stmt = $conn->prepare("
            UPDATE crypto_withdrawals 
            SET status = 'rejected', 
                approval_status = 'rejected',
                admin_notes = ?,
                approved_by = ?,
                processed_date = NOW()
            WHERE withdrawal_id = ?
        ");
        $stmt->bind_param("sii", $admin_notes, $user_id, $withdrawal_id);
        $stmt->execute();
        
        // Refund to wallet
        $stmt = $conn->prepare("
            UPDATE user_wallets 
            SET balance = balance + ? 
            WHERE user_id = ? AND crypto_symbol = ?
        ");
        $stmt->bind_param("dis", $withdrawal['crypto_amount'], $withdrawal['user_id'], $withdrawal['crypto_symbol']);
        $stmt->execute();
        
        // Update wallet transaction
        $stmt = $conn->prepare("
            UPDATE wallet_transactions 
            SET status = 'rejected' 
            WHERE type = 'withdrawal' AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute();
        
        $conn->commit();
        
        // TODO: Send email notification to user
        
        echo json_encode(['success' => true, 'message' => 'Withdrawal rejected and funds refunded']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Cancel withdrawal request (user)
 */
function cancelWithdrawal($conn, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $withdrawal_id = intval($data['withdrawal_id'] ?? 0);
    
    if ($withdrawal_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        return;
    }
    
    // Get withdrawal details
    $stmt = $conn->prepare("
        SELECT user_id, crypto_symbol, crypto_amount 
        FROM crypto_withdrawals 
        WHERE withdrawal_id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $withdrawal_id, $user_id);
    $stmt->execute();
    $withdrawal = $stmt->get_result()->fetch_assoc();
    
    if (!$withdrawal) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found or cannot be cancelled']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Update withdrawal status
        $stmt = $conn->prepare("
            UPDATE crypto_withdrawals 
            SET status = 'cancelled', 
                approval_status = 'cancelled',
                processed_date = NOW()
            WHERE withdrawal_id = ?
        ");
        $stmt->bind_param("i", $withdrawal_id);
        $stmt->execute();
        
        // Refund to wallet
        $stmt = $conn->prepare("
            UPDATE user_wallets 
            SET balance = balance + ? 
            WHERE user_id = ? AND crypto_symbol = ?
        ");
        $stmt->bind_param("dis", $withdrawal['crypto_amount'], $user_id, $withdrawal['crypto_symbol']);
        $stmt->execute();
        
        // Update wallet transaction
        $stmt = $conn->prepare("
            UPDATE wallet_transactions 
            SET status = 'cancelled' 
            WHERE type = 'withdrawal' AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Withdrawal cancelled and funds refunded']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get wallet balance
 */
function getWalletBalance($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT crypto_symbol, balance 
        FROM user_wallets 
        WHERE user_id = ?
        ORDER BY balance DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $balances = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['balance'] > 0) {
            $balances[] = [
                'crypto' => $row['crypto_symbol'],
                'balance' => floatval($row['balance'])
            ];
        }
    }
    
    echo json_encode(['success' => true, 'balances' => $balances]);
}

/**
 * Get current crypto price from CoinGecko
 */
function getCurrentPrice($crypto_symbol) {
    $coin_ids = [
        'BTC' => 'bitcoin',
        'ETH' => 'ethereum',
        'USDT' => 'tether',
        'BNB' => 'binancecoin',
        'USDC' => 'usd-coin',
        'XRP' => 'ripple',
        'ADA' => 'cardano',
        'SOL' => 'solana',
        'DOT' => 'polkadot',
        'DOGE' => 'dogecoin'
    ];
    
    $coin_id = $coin_ids[$crypto_symbol] ?? 'bitcoin';
    $url = "https://api.coingecko.com/api/v3/simple/price?ids={$coin_id}&vs_currencies=usd";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        return $data[$coin_id]['usd'] ?? 0;
    }
    
    return 0;
}
