<?php
/**
 * Admin API
 * Handles admin operations: user management, statistics, etc.
 */

require_once '../includes/config.php';

// Require admin access
require_admin();

header('Content-Type: application/json');

$admin_id = get_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : null;
    
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security token validation failed.']);
        exit;
    }
    
    switch ($action) {
        case 'update_user_status':
            update_user_status();
            break;
        case 'delete_user':
            delete_user();
            break;
        case 'reset_user_password':
            reset_user_password();
            break;
        case 'get_user_details':
            get_user_details();
            break;
        case 'get_platform_stats':
            get_platform_stats();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

/**
 * Update user status (active, suspended, inactive)
 */
function update_user_status() {
    global $conn, $admin_id;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $status = sanitize_input($_POST['status'] ?? '');
    
    if ($user_id <= 0 || !in_array($status, ['active', 'inactive', 'suspended'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    // Verify user exists and is not admin
    $stmt = $conn->prepare("SELECT email, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    if ($user['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot modify admin users']);
        exit;
    }
    
    // Update status
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $status, $user_id);
    
    if ($stmt->execute()) {
        log_admin_activity('update_user_status', 'user', $user_id, "Changed status to: $status");
        
        echo json_encode([
            'success' => true,
            'message' => "User status updated to " . ucfirst($status)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
    }
}

/**
 * Delete user account
 */
function delete_user() {
    global $conn, $admin_id;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
    
    // Verify user exists and is not admin
    $stmt = $conn->prepare("SELECT email, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    if ($user['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot delete admin users']);
        exit;
    }
    
    // Delete user (cascade will delete related data)
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        log_admin_activity('delete_user', 'user', $user_id, "Deleted user: {$user['email']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
}

/**
 * Get detailed user information
 */
function get_user_details() {
    global $conn;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
    
    // Get user data
    $stmt = $conn->prepare("SELECT user_id, email, full_name, phone, country, date_created, last_login, status, email_verified, two_factor_enabled, referral_code, referred_by, total_referral_earnings FROM users WHERE user_id = ? AND role = 'user'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Get portfolio count
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(quantity * entry_price) as total_value FROM portfolio_holdings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $portfolio = $stmt->get_result()->fetch_assoc();
    $user['portfolio_holdings'] = $portfolio['count'];
    $user['portfolio_value'] = $portfolio['total_value'] ?? 0;
    
    // Get alerts count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM price_alerts WHERE user_id = ? AND is_active = TRUE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user['active_alerts'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get trades count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM trade_history WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user['total_trades'] = $stmt->get_result()->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}

/**
 * Get platform-wide statistics
 */
function get_platform_stats() {
    global $conn;
    
    $stats = [];
    
    // User statistics
    $stats['total_users'] = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'")->fetch_assoc()['total'];
    $stats['active_users'] = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND status = 'active'")->fetch_assoc()['total'];
    $stats['verified_users'] = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user' AND email_verified = TRUE")->fetch_assoc()['total'];
    
    // Portfolio statistics
    $result = $conn->query("SELECT SUM(quantity * entry_price) as total_value, COUNT(DISTINCT user_id) as holders FROM portfolio_holdings");
    $portfolio_stats = $result->fetch_assoc();
    $stats['total_portfolio_value'] = $portfolio_stats['total_value'] ?? 0;
    $stats['users_with_portfolio'] = $portfolio_stats['holders'];
    
    // Alert statistics
    $stats['total_alerts'] = $conn->query("SELECT COUNT(*) as total FROM price_alerts WHERE is_active = TRUE")->fetch_assoc()['total'];
    $stats['triggered_alerts'] = $conn->query("SELECT COUNT(*) as total FROM price_alerts WHERE is_triggered = TRUE")->fetch_assoc()['total'];
    
    // Trading statistics
    $stats['total_trades'] = $conn->query("SELECT COUNT(*) as total FROM trade_history")->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

/**
 * Reset user password
 */
function reset_user_password() {
    global $conn, $admin_id;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';
    
    if ($user_id <= 0 || strlen($new_password) < 12) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters or password too short']);
        exit;
    }
    
    // Verify user exists and is not admin
    $stmt = $conn->prepare("SELECT email, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    if ($user['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot reset admin passwords']);
        exit;
    }
    
    // Update password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->bind_param("si", $password_hash, $user_id);
    
    if ($stmt->execute()) {
        log_admin_activity('reset_password', 'user', $user_id, "Password reset for: {$user['email']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    }
}

?>
