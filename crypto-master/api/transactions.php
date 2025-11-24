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

switch ($action) {
    case 'get_transactions':
        getTransactions($conn, $user_id);
        break;
    
    case 'get_stats':
        getTransactionStats($conn, $user_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getTransactions($conn, $user_id) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $tab = $_GET['tab'] ?? 'all';
    $type_filter = $_GET['type'] ?? 'all';
    $status_filter = $_GET['status'] ?? 'all';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Build unified query combining all transaction sources
    $transactions = [];
    
    // Get wallet transactions
    if ($tab === 'all' || $tab === 'wallet') {
        $wallet_txs = getWalletTransactions($conn, $user_id, $type_filter, $status_filter, $date_from, $date_to);
        $transactions = array_merge($transactions, $wallet_txs);
    }
    
    // Get crypto deposits
    if ($tab === 'all' || $tab === 'wallet') {
        $deposits = getCryptoDeposits($conn, $user_id, $status_filter, $date_from, $date_to);
        $transactions = array_merge($transactions, $deposits);
    }
    
    // Get crypto withdrawals
    if ($tab === 'all' || $tab === 'wallet') {
        $withdrawals = getCryptoWithdrawals($conn, $user_id, $status_filter, $date_from, $date_to);
        $transactions = array_merge($transactions, $withdrawals);
    }
    
    // Get bot trades
    if ($tab === 'all' || $tab === 'bots' || $tab === 'trading') {
        $bot_trades = getBotTrades($conn, $user_id, $status_filter, $date_from, $date_to);
        $transactions = array_merge($transactions, $bot_trades);
    }
    
    // Sort by date descending
    usort($transactions, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Apply type filter if needed
    if ($type_filter !== 'all') {
        $transactions = array_filter($transactions, function($tx) use ($type_filter) {
            return $tx['type'] === $type_filter;
        });
        $transactions = array_values($transactions);
    }
    
    // Calculate pagination
    $total = count($transactions);
    $total_pages = ceil($total / $limit);
    
    // Get page slice
    $transactions = array_slice($transactions, $offset, $limit);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total,
            'per_page' => $limit
        ]
    ]);
}

function getWalletTransactions($conn, $user_id, $type_filter, $status_filter, $date_from, $date_to) {
    $sql = "SELECT 
                transaction_id,
                type,
                amount,
                currency,
                balance_after,
                description,
                created_at,
                'completed' as status
            FROM wallet_transactions 
            WHERE user_id = ?";
    
    $params = [$user_id];
    $types = "i";
    
    if ($date_from) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if ($date_to) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'id' => 'wt_' . $row['transaction_id'],
            'type' => $row['type'],
            'amount' => floatval($row['amount']),
            'balance_after' => floatval($row['balance_after']),
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'status' => 'completed'
        ];
    }
    
    return $transactions;
}

function getCryptoDeposits($conn, $user_id, $status_filter, $date_from, $date_to) {
    $sql = "SELECT 
                deposit_id,
                crypto_symbol,
                crypto_name,
                amount as crypto_amount,
                usd_value,
                status,
                deposit_date as created_at,
                transaction_hash
            FROM crypto_deposits 
            WHERE user_id = ?";
    
    $params = [$user_id];
    $types = "i";
    
    if ($status_filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status_filter === 'completed' ? 'completed' : $status_filter;
        $types .= "s";
    }
    
    if ($date_from) {
        $sql .= " AND DATE(deposit_date) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if ($date_to) {
        $sql .= " AND DATE(deposit_date) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql .= " ORDER BY deposit_date DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        // Get current balance (approximate)
        $balance = getUserBalance($conn, $user_id);
        
        $transactions[] = [
            'id' => 'cd_' . $row['deposit_id'],
            'type' => 'deposit',
            'amount' => floatval($row['usd_value']),
            'balance_after' => $balance,
            'description' => "Crypto deposit: {$row['crypto_amount']} {$row['crypto_symbol']}",
            'created_at' => $row['created_at'],
            'status' => $row['status']
        ];
    }
    
    return $transactions;
}

function getCryptoWithdrawals($conn, $user_id, $status_filter, $date_from, $date_to) {
    // Check if crypto_withdrawals table exists
    $check = $conn->query("SHOW TABLES LIKE 'crypto_withdrawals'");
    if ($check->num_rows === 0) {
        return [];
    }
    
    $sql = "SELECT 
                withdrawal_id,
                crypto_symbol,
                amount as crypto_amount,
                usd_value,
                status,
                created_at
            FROM crypto_withdrawals 
            WHERE user_id = ?";
    
    $params = [$user_id];
    $types = "i";
    
    if ($status_filter !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if ($date_from) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if ($date_to) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $balance = getUserBalance($conn, $user_id);
        
        $transactions[] = [
            'id' => 'cw_' . $row['withdrawal_id'],
            'type' => 'withdrawal',
            'amount' => -floatval($row['usd_value']),
            'balance_after' => $balance,
            'description' => "Crypto withdrawal: {$row['crypto_amount']} {$row['crypto_symbol']}",
            'created_at' => $row['created_at'],
            'status' => $row['status']
        ];
    }
    
    return $transactions;
}

function getBotTrades($conn, $user_id, $status_filter, $date_from, $date_to) {
    // Check if bot_trades table exists
    $check = $conn->query("SHOW TABLES LIKE 'bot_trades'");
    if ($check->num_rows === 0) {
        return [];
    }
    
    $sql = "SELECT 
                bt.trade_id,
                bt.trade_type,
                bt.crypto_symbol,
                bt.crypto_amount,
                bt.usd_amount,
                bt.profit_loss,
                bt.status,
                bt.entry_time,
                bt.exit_time,
                tb.bot_name
            FROM bot_trades bt
            JOIN trading_bots tb ON bt.bot_id = tb.bot_id
            WHERE bt.user_id = ?";
    
    $params = [$user_id];
    $types = "i";
    
    if ($status_filter !== 'all') {
        $status_map = ['completed' => 'closed', 'pending' => 'open'];
        $bot_status = $status_map[$status_filter] ?? $status_filter;
        $sql .= " AND bt.status = ?";
        $params[] = $bot_status;
        $types .= "s";
    }
    
    if ($date_from) {
        $sql .= " AND DATE(bt.entry_time) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if ($date_to) {
        $sql .= " AND DATE(bt.entry_time) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    $sql .= " ORDER BY bt.entry_time DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $balance = getUserBalance($conn, $user_id);
        
        $amount = $row['status'] === 'closed' 
            ? floatval($row['profit_loss']) 
            : -floatval($row['usd_amount']);
        
        $description = "Bot trade: {$row['trade_type']} {$row['crypto_amount']} {$row['crypto_symbol']} ({$row['bot_name']})";
        
        $status_map = ['open' => 'pending', 'closed' => 'completed', 'cancelled' => 'failed'];
        $status = $status_map[$row['status']] ?? $row['status'];
        
        $transactions[] = [
            'id' => 'bt_' . $row['trade_id'],
            'type' => 'bot-trade',
            'amount' => $amount,
            'balance_after' => $balance,
            'description' => $description,
            'created_at' => $row['exit_time'] ?? $row['entry_time'],
            'status' => $status
        ];
    }
    
    return $transactions;
}

function getTransactionStats($conn, $user_id) {
    $stats = [
        'total_transactions' => 0,
        'total_deposits' => 0,
        'deposit_count' => 0,
        'total_withdrawals' => 0,
        'withdrawal_count' => 0,
        'bot_pl' => 0,
        'bot_trade_count' => 0
    ];
    
    // Wallet transactions count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM wallet_transactions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['total_transactions'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Crypto deposits
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(usd_value) as total 
                           FROM crypto_deposits 
                           WHERE user_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $deposit_stats = $stmt->get_result()->fetch_assoc();
    $stats['deposit_count'] = $deposit_stats['count'] ?? 0;
    $stats['total_deposits'] = $deposit_stats['total'] ?? 0;
    $stats['total_transactions'] += $stats['deposit_count'];
    
    // Crypto withdrawals
    $check = $conn->query("SHOW TABLES LIKE 'crypto_withdrawals'");
    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(usd_value) as total 
                               FROM crypto_withdrawals 
                               WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $withdrawal_stats = $stmt->get_result()->fetch_assoc();
        $stats['withdrawal_count'] = $withdrawal_stats['count'] ?? 0;
        $stats['total_withdrawals'] = $withdrawal_stats['total'] ?? 0;
        $stats['total_transactions'] += $stats['withdrawal_count'];
    }
    
    // Bot trades
    $check = $conn->query("SHOW TABLES LIKE 'bot_trades'");
    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(profit_loss) as total_pl 
                               FROM bot_trades 
                               WHERE user_id = ? AND status = 'closed'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $bot_stats = $stmt->get_result()->fetch_assoc();
        $stats['bot_trade_count'] = $bot_stats['count'] ?? 0;
        $stats['bot_pl'] = $bot_stats['total_pl'] ?? 0;
        $stats['total_transactions'] += $stats['bot_trade_count'];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

function getUserBalance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND currency = 'USD'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return floatval($result->fetch_assoc()['balance']);
    }
    
    return 0;
}

$conn->close();
?>
