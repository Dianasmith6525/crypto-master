<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            addToWatchlist($conn, $user_id);
            break;
        
        case 'remove':
            removeFromWatchlist($conn, $user_id);
            break;
        
        case 'get_all':
            getWatchlist($conn, $user_id);
            break;
        
        case 'check':
            checkIfInWatchlist($conn, $user_id);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Watchlist Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

function addToWatchlist($conn, $user_id) {
    $crypto_id = $_POST['crypto_id'] ?? '';
    $crypto_symbol = $_POST['crypto_symbol'] ?? '';
    $crypto_name = $_POST['crypto_name'] ?? '';
    
    if (empty($crypto_id) || empty($crypto_symbol) || empty($crypto_name)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Check if already in watchlist
    $check_stmt = $conn->prepare("SELECT watchlist_id FROM watchlist WHERE user_id = ? AND crypto_id = ?");
    $check_stmt->bind_param("is", $user_id, $crypto_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already in watchlist']);
        return;
    }
    
    // Add to watchlist
    $stmt = $conn->prepare("INSERT INTO watchlist (user_id, crypto_id, crypto_symbol, crypto_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $crypto_id, $crypto_symbol, $crypto_name);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $crypto_name . ' added to watchlist',
            'watchlist_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add to watchlist']);
    }
}

function removeFromWatchlist($conn, $user_id) {
    $crypto_id = $_POST['crypto_id'] ?? $_GET['crypto_id'] ?? '';
    
    if (empty($crypto_id)) {
        echo json_encode(['success' => false, 'message' => 'Crypto ID required']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM watchlist WHERE user_id = ? AND crypto_id = ?");
    $stmt->bind_param("is", $user_id, $crypto_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Removed from watchlist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found in watchlist']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove from watchlist']);
    }
}

function getWatchlist($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT 
            watchlist_id,
            crypto_id,
            crypto_symbol,
            crypto_name,
            date_added
        FROM watchlist 
        WHERE user_id = ?
        ORDER BY date_added DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $watchlist = [];
    while ($row = $result->fetch_assoc()) {
        $watchlist[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'watchlist' => $watchlist,
        'count' => count($watchlist)
    ]);
}

function checkIfInWatchlist($conn, $user_id) {
    $crypto_id = $_GET['crypto_id'] ?? '';
    
    if (empty($crypto_id)) {
        echo json_encode(['success' => false, 'message' => 'Crypto ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT watchlist_id FROM watchlist WHERE user_id = ? AND crypto_id = ?");
    $stmt->bind_param("is", $user_id, $crypto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode([
        'success' => true,
        'in_watchlist' => $result->num_rows > 0
    ]);
}
?>
