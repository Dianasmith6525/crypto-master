<?php
/**
 * Trading Bot Management API
 * Handles bot creation, status updates, and statistics
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_strategies':
        getAvailableStrategies();
        break;
    
    case 'get_user_bots':
        getUserBots($user_id);
        break;
    
    case 'create_bot':
        createBot($user_id);
        break;
    
    case 'update_bot_status':
        updateBotStatus($user_id);
        break;
    
    case 'delete_bot':
        deleteBot($user_id);
        break;
    
    case 'get_stats':
        getBotStats($user_id);
        break;
    
    case 'get_bot_details':
        getBotDetails($user_id);
        break;
    
    case 'get_execution_logs':
        getExecutionLogs($user_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all available trading strategies
 */
function getAvailableStrategies() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT strategy_id, strategy_name, strategy_description, strategy_type, risk_level
        FROM bot_trading_strategies
        WHERE is_active = 1
        ORDER BY risk_level, strategy_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $strategies = [];
    while ($row = $result->fetch_assoc()) {
        $strategies[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $strategies
    ]);
}

/**
 * Get user's bots with performance data
 */
function getUserBots($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            s.strategy_name,
            s.strategy_type,
            s.risk_level,
            COUNT(DISTINCT el.execution_id) as total_trades,
            SUM(CASE WHEN el.profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(el.profit_loss) as total_profit,
            CASE 
                WHEN COUNT(DISTINCT el.execution_id) > 0 
                THEN ROUND(SUM(CASE WHEN el.profit_loss > 0 THEN 1 ELSE 0 END) / COUNT(DISTINCT el.execution_id) * 100, 2)
                ELSE 0
            END as win_rate
        FROM user_bot_instances b
        JOIN bot_trading_strategies s ON b.strategy_id = s.strategy_id
        LEFT JOIN bot_execution_logs el ON b.bot_id = el.bot_id AND el.status = 'success'
        WHERE b.user_id = ?
        GROUP BY b.bot_id
        ORDER BY b.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No bots found']);
        return;
    }
    
    $bots = [];
    while ($row = $result->fetch_assoc()) {
        $bots[] = [
            'bot_id' => $row['bot_id'],
            'bot_name' => $row['bot_name'],
            'crypto_symbol' => $row['crypto_symbol'],
            'status' => $row['status'],
            'strategy_name' => $row['strategy_name'],
            'strategy_type' => $row['strategy_type'],
            'risk_level' => $row['risk_level'],
            'allocated_balance' => (float)$row['allocated_balance'],
            'total_trades' => (int)$row['total_trades'],
            'winning_trades' => (int)$row['winning_trades'],
            'total_profit' => (float)($row['total_profit'] ?? 0),
            'win_rate' => (float)$row['win_rate'],
            'created_at' => $row['created_at'],
            'last_run' => $row['last_run']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $bots
    ]);
}

/**
 * Create a new trading bot
 */
function createBot($user_id) {
    global $conn;
    
    $bot_name = $_POST['bot_name'] ?? '';
    $strategy_id = $_POST['strategy_id'] ?? 0;
    $crypto_symbol = $_POST['crypto_symbol'] ?? '';
    $allocated_balance = $_POST['allocated_balance'] ?? 0;
    $max_daily_trades = $_POST['max_daily_trades'] ?? 10;
    $stop_loss_percentage = $_POST['stop_loss_percentage'] ?? 5.0;
    $take_profit_percentage = $_POST['take_profit_percentage'] ?? 10.0;
    
    // Validation
    if (empty($bot_name) || empty($crypto_symbol) || $strategy_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    if ($allocated_balance < 100) {
        echo json_encode(['success' => false, 'message' => 'Minimum balance is $100']);
        return;
    }
    
    // Check if bot name already exists for this user
    $stmt = $conn->prepare("SELECT bot_id FROM user_bot_instances WHERE user_id = ? AND bot_name = ?");
    $stmt->bind_param("is", $user_id, $bot_name);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Bot name already exists']);
        return;
    }
    
    // Get strategy parameters
    $stmt = $conn->prepare("SELECT parameters FROM bot_trading_strategies WHERE strategy_id = ?");
    $stmt->bind_param("i", $strategy_id);
    $stmt->execute();
    $strategy = $stmt->get_result()->fetch_assoc();
    
    if (!$strategy) {
        echo json_encode(['success' => false, 'message' => 'Invalid strategy']);
        return;
    }
    
    // Create bot configuration
    $configuration = json_encode([
        'stop_loss' => $stop_loss_percentage,
        'take_profit' => $take_profit_percentage,
        'max_daily_trades' => $max_daily_trades,
        'strategy_params' => json_decode($strategy['parameters'], true)
    ]);
    
    // Insert bot
    $stmt = $conn->prepare("
        INSERT INTO user_bot_instances (
            user_id, strategy_id, bot_name, crypto_symbol, configuration,
            allocated_balance, max_daily_trades, max_position_size,
            stop_loss_percentage, take_profit_percentage, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paused')
    ");
    
    $max_position_size = $allocated_balance * 0.5; // Max 50% per trade
    
    $stmt->bind_param(
        "iisssdiddd",
        $user_id,
        $strategy_id,
        $bot_name,
        $crypto_symbol,
        $configuration,
        $allocated_balance,
        $max_daily_trades,
        $max_position_size,
        $stop_loss_percentage,
        $take_profit_percentage
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Bot created successfully',
            'bot_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create bot: ' . $conn->error]);
    }
}

/**
 * Update bot status (active, paused, stopped)
 */
function updateBotStatus($user_id) {
    global $conn;
    
    $bot_id = $_POST['bot_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if (!in_array($status, ['active', 'paused', 'stopped'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    // Verify bot belongs to user
    $stmt = $conn->prepare("SELECT bot_id FROM user_bot_instances WHERE bot_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bot_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bot not found']);
        return;
    }
    
    // Update status
    $stmt = $conn->prepare("UPDATE user_bot_instances SET status = ? WHERE bot_id = ?");
    $stmt->bind_param("si", $status, $bot_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Bot status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

/**
 * Delete a trading bot
 */
function deleteBot($user_id) {
    global $conn;
    
    $bot_id = $_POST['bot_id'] ?? 0;
    
    // Verify bot belongs to user
    $stmt = $conn->prepare("SELECT bot_id FROM user_bot_instances WHERE bot_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bot_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bot not found']);
        return;
    }
    
    // Delete bot (cascades to execution logs and performance metrics)
    $stmt = $conn->prepare("DELETE FROM user_bot_instances WHERE bot_id = ?");
    $stmt->bind_param("i", $bot_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Bot deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete bot']);
    }
}

/**
 * Get overall bot statistics
 */
function getBotStats($user_id) {
    global $conn;
    
    // Active bots count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM user_bot_instances 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $active_bots = $stmt->get_result()->fetch_assoc()['count'];
    
    // Today's trades
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM bot_execution_logs el
        JOIN user_bot_instances b ON el.bot_id = b.bot_id
        WHERE b.user_id = ? AND DATE(el.execution_time) = ? AND el.status = 'success'
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $total_trades_today = $stmt->get_result()->fetch_assoc()['count'];
    
    // Today's P/L
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(el.profit_loss), 0) as total
        FROM bot_execution_logs el
        JOIN user_bot_instances b ON el.bot_id = b.bot_id
        WHERE b.user_id = ? AND DATE(el.execution_time) = ? AND el.status = 'success'
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $today_profit_loss = $stmt->get_result()->fetch_assoc()['total'];
    
    // Overall win rate
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_trades,
            SUM(CASE WHEN el.profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades
        FROM bot_execution_logs el
        JOIN user_bot_instances b ON el.bot_id = b.bot_id
        WHERE b.user_id = ? AND el.status = 'success'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $total_trades = $result['total_trades'] ?? 0;
    $winning_trades = $result['winning_trades'] ?? 0;
    $win_rate = $total_trades > 0 ? round(($winning_trades / $total_trades) * 100, 2) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'active_bots' => (int)$active_bots,
            'total_trades_today' => (int)$total_trades_today,
            'today_profit_loss' => (float)$today_profit_loss,
            'win_rate' => (float)$win_rate
        ]
    ]);
}

/**
 * Get detailed bot information
 */
function getBotDetails($user_id) {
    global $conn;
    
    $bot_id = $_GET['bot_id'] ?? 0;
    
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            s.strategy_name,
            s.strategy_description,
            s.strategy_type,
            s.risk_level
        FROM user_bot_instances b
        JOIN bot_trading_strategies s ON b.strategy_id = s.strategy_id
        WHERE b.bot_id = ? AND b.user_id = ?
    ");
    $stmt->bind_param("ii", $bot_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bot not found']);
        return;
    }
    
    $bot = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => $bot
    ]);
}

/**
 * Get bot execution logs
 */
function getExecutionLogs($user_id) {
    global $conn;
    
    $bot_id = $_GET['bot_id'] ?? 0;
    $limit = $_GET['limit'] ?? 50;
    
    // Verify bot belongs to user
    $stmt = $conn->prepare("SELECT bot_id FROM user_bot_instances WHERE bot_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $bot_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bot not found']);
        return;
    }
    
    // Get execution logs
    $stmt = $conn->prepare("
        SELECT *
        FROM bot_execution_logs
        WHERE bot_id = ?
        ORDER BY execution_time DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $bot_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'execution_id' => $row['execution_id'],
            'trade_type' => $row['trade_type'],
            'crypto_symbol' => $row['crypto_symbol'],
            'quantity' => (float)$row['quantity'],
            'price' => (float)$row['price'],
            'total_value' => (float)$row['total_value'],
            'profit_loss' => (float)$row['profit_loss'],
            'reason' => $row['reason'],
            'status' => $row['status'],
            'execution_time' => $row['execution_time'],
            'error_message' => $row['error_message']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);
}
?>
