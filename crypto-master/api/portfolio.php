<?php
/**
 * Portfolio Management API
 * Handles adding, updating, and deleting portfolio holdings
 */

require_once '../includes/config.php';

// Check if user is authenticated
if (!is_user_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$user_id = get_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : null;
    
    // Validate CSRF token for all actions
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security token validation failed.']);
        exit;
    }
    
    if ($action === 'add_holding') {
        add_holding();
    } elseif ($action === 'update_holding') {
        update_holding();
    } elseif ($action === 'remove_holding') {
        remove_holding();
    } elseif ($action === 'get_holdings') {
        get_holdings();
    } elseif ($action === 'get_holding_details') {
        get_holding_details();
    } elseif ($action === 'get_portfolio_summary') {
        get_portfolio_summary();
    }
}

/**
 * Add new cryptocurrency holding to portfolio
 */
function add_holding() {
    global $conn, $user_id;
    
    $crypto_id = sanitize_input($_POST['crypto_id'] ?? '');
    $crypto_symbol = sanitize_input($_POST['crypto_symbol'] ?? '');
    $crypto_name = sanitize_input($_POST['crypto_name'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $entry_price = floatval($_POST['entry_price'] ?? 0);
    $entry_date = sanitize_input($_POST['entry_date'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($crypto_id) || empty($crypto_symbol)) {
        $errors[] = 'Invalid cryptocurrency';
    }
    
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than 0';
    }
    
    if ($entry_price <= 0) {
        $errors[] = 'Entry price must be greater than 0';
    }
    
    if (empty($entry_date) || !strtotime($entry_date)) {
        $errors[] = 'Invalid entry date';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    // Check if user already has this crypto
    $sql = "SELECT holding_id FROM portfolio_holdings WHERE user_id = ? AND crypto_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $crypto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You already have this cryptocurrency in your portfolio']);
        exit;
    }
    
    // Insert holding
    $sql = "INSERT INTO portfolio_holdings 
            (user_id, crypto_id, crypto_symbol, crypto_name, quantity, entry_price, entry_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssddss", $user_id, $crypto_id, $crypto_symbol, $crypto_name, $quantity, $entry_price, $entry_date, $notes);
    
    if ($stmt->execute()) {
        log_api_activity('/portfolio/add_holding', 'POST', $user_id, 200);
        
        echo json_encode([
            'success' => true,
            'message' => 'Holding added successfully',
            'holding_id' => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add holding']);
    }
}

/**
 * Update existing portfolio holding
 */
function update_holding() {
    global $conn, $user_id;
    
    $holding_id = intval($_POST['holding_id'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);
    $entry_price = floatval($_POST['entry_price'] ?? 0);
    $entry_date = sanitize_input($_POST['entry_date'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    
    if ($holding_id <= 0) {
        $errors[] = 'Invalid holding';
    }
    
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than 0';
    }
    
    if ($entry_price <= 0) {
        $errors[] = 'Entry price must be greater than 0';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    // Verify holding belongs to user
    $sql = "SELECT holding_id FROM portfolio_holdings WHERE holding_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $holding_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Holding not found']);
        exit;
    }
    
    // Update holding
    $sql = "UPDATE portfolio_holdings 
            SET quantity = ?, entry_price = ?, entry_date = ?, notes = ?, date_updated = NOW() 
            WHERE holding_id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ddssii", $quantity, $entry_price, $entry_date, $notes, $holding_id, $user_id);
    
    if ($stmt->execute()) {
        log_api_activity('/portfolio/update_holding', 'POST', $user_id, 200);
        
        echo json_encode([
            'success' => true,
            'message' => 'Holding updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update holding']);
    }
}

/**
 * Remove holding from portfolio
 */
function remove_holding() {
    global $conn, $user_id;
    
    $holding_id = intval($_POST['holding_id'] ?? 0);
    
    if ($holding_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid holding']);
        exit;
    }
    
    // Verify holding belongs to user
    $sql = "SELECT holding_id FROM portfolio_holdings WHERE holding_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $holding_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Holding not found']);
        exit;
    }
    
    // Delete holding
    $sql = "DELETE FROM portfolio_holdings WHERE holding_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $holding_id, $user_id);
    
    if ($stmt->execute()) {
        log_api_activity('/portfolio/remove_holding', 'POST', $user_id, 200);
        
        echo json_encode([
            'success' => true,
            'message' => 'Holding removed successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to remove holding']);
    }
}

/**
 * Get all holdings for user
 */
function get_holdings() {
    global $conn, $user_id;
    
    $sql = "SELECT holding_id, crypto_id, crypto_symbol, crypto_name, quantity, entry_price, entry_date, notes, date_added 
            FROM portfolio_holdings 
            WHERE user_id = ? 
            ORDER BY date_added DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $holdings = [];
    while ($row = $result->fetch_assoc()) {
        $holdings[] = $row;
    }
    
    log_api_activity('/portfolio/get_holdings', 'POST', $user_id, 200);
    
    echo json_encode([
        'success' => true,
        'holdings' => $holdings,
        'count' => count($holdings)
    ]);
}

/**
 * Get single holding details for editing
 */
function get_holding_details() {
    global $conn, $user_id;
    
    $holding_id = intval($_POST['holding_id'] ?? 0);
    
    if ($holding_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid holding ID']);
        exit;
    }
    
    $sql = "SELECT holding_id, crypto_id, crypto_symbol, crypto_name, quantity, entry_price, entry_date, notes 
            FROM portfolio_holdings 
            WHERE holding_id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $holding_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Holding not found']);
        exit;
    }
    
    $holding = $result->fetch_assoc();
    
    log_api_activity('/portfolio/get_holding_details', 'POST', $user_id, 200);
    
    echo json_encode([
        'success' => true,
        'holding' => $holding
    ]);
}

/**
 * Get portfolio summary (requires current prices from external API)
 */
function get_portfolio_summary() {
    global $conn, $user_id;
    
    $sql = "SELECT crypto_id, crypto_symbol, crypto_name, SUM(quantity) as total_quantity, 
            AVG(entry_price) as avg_entry_price, SUM(quantity * entry_price) as total_invested
            FROM portfolio_holdings 
            WHERE user_id = ? 
            GROUP BY crypto_id, crypto_symbol, crypto_name
            ORDER BY total_invested DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $summary = [];
    $total_invested = 0;
    
    while ($row = $result->fetch_assoc()) {
        $summary[] = $row;
        $total_invested += $row['total_invested'];
    }
    
    log_api_activity('/portfolio/get_portfolio_summary', 'POST', $user_id, 200);
    
    echo json_encode([
        'success' => true,
        'portfolio' => $summary,
        'total_invested' => $total_invested,
        'crypto_count' => count($summary),
        'total_holdings' => array_sum(array_column($summary, 'total_quantity'))
    ]);
}

?>
