<?php
/**
 * Admin Withdrawals API
 * Admin-specific endpoints for withdrawal management
 */

header('Content-Type: application/json');
require_once '../includes/db.php';

session_start();

// Check if user is logged in and is admin
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

// Check admin status
$admin_check = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$admin_check->bind_param("i", $user_id);
$admin_check->execute();
$admin_result = $admin_check->get_result()->fetch_assoc();

if (!$admin_result || !$admin_result['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// Router
switch ($action) {
    case 'list':
        listWithdrawals($conn);
        break;
    case 'stats':
        getStats($conn);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();

/**
 * List all withdrawals with user info
 */
function listWithdrawals($conn) {
    $status = $_GET['status'] ?? 'all';
    
    $query = "
        SELECT 
            w.withdrawal_id,
            w.user_id,
            w.crypto_symbol,
            w.crypto_amount,
            w.usd_amount,
            w.network_fee,
            w.withdrawal_address,
            w.status,
            w.approval_status,
            w.admin_notes,
            w.tx_hash,
            w.withdrawal_date,
            w.processed_date,
            u.username,
            u.email
        FROM crypto_withdrawals w
        JOIN users u ON w.user_id = u.id
    ";
    
    if ($status !== 'all') {
        $query .= " WHERE w.status = ?";
    }
    
    $query .= " ORDER BY w.withdrawal_date DESC LIMIT 100";
    
    if ($status !== 'all') {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $status);
    } else {
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $withdrawals = [];
    while ($row = $result->fetch_assoc()) {
        $withdrawals[] = [
            'id' => $row['withdrawal_id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'crypto' => $row['crypto_symbol'],
            'amount' => floatval($row['crypto_amount']),
            'usd_amount' => floatval($row['usd_amount']),
            'fee' => floatval($row['network_fee']),
            'address' => $row['withdrawal_address'],
            'status' => $row['status'],
            'approval_status' => $row['approval_status'],
            'admin_notes' => $row['admin_notes'],
            'tx_hash' => $row['tx_hash'],
            'date' => $row['withdrawal_date'],
            'processed_date' => $row['processed_date']
        ];
    }
    
    echo json_encode(['success' => true, 'withdrawals' => $withdrawals]);
}

/**
 * Get withdrawal statistics
 */
function getStats($conn) {
    // Pending count
    $pending_result = $conn->query("SELECT COUNT(*) as count FROM crypto_withdrawals WHERE status = 'pending'");
    $pending_count = $pending_result->fetch_assoc()['count'];
    
    // Approved today
    $approved_result = $conn->query("
        SELECT COUNT(*) as count 
        FROM crypto_withdrawals 
        WHERE status = 'completed' 
        AND DATE(processed_date) = CURDATE()
    ");
    $approved_today = $approved_result->fetch_assoc()['count'];
    
    // Rejected today
    $rejected_result = $conn->query("
        SELECT COUNT(*) as count 
        FROM crypto_withdrawals 
        WHERE status = 'rejected' 
        AND DATE(processed_date) = CURDATE()
    ");
    $rejected_today = $rejected_result->fetch_assoc()['count'];
    
    // Total volume
    $volume_result = $conn->query("
        SELECT SUM(usd_amount) as total 
        FROM crypto_withdrawals 
        WHERE status = 'completed'
    ");
    $total_volume = $volume_result->fetch_assoc()['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'pending_count' => $pending_count,
            'approved_today' => $approved_today,
            'rejected_today' => $rejected_today,
            'total_volume' => floatval($total_volume)
        ]
    ]);
}
