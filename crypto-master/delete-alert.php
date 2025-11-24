<?php
/**
 * Delete Price Alert
 * Removes an alert for authenticated user
 */

session_start();
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database configuration
$host = 'localhost';
$db = 'crypto_platform';
$user = 'root';
$password = '';

try {
    $conn = new mysqli($host, $user, $password, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Get alert ID
    $alert_id = intval($_POST['alert_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if ($alert_id <= 0) {
        throw new Exception("Invalid alert ID");
    }
    
    // Verify alert belongs to user
    $check_sql = "SELECT id FROM price_alerts WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('ii', $alert_id, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        throw new Exception("Alert not found");
    }
    
    // Delete the alert
    $delete_sql = "DELETE FROM price_alerts WHERE id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('ii', $alert_id, $user_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception("Failed to delete alert");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Alert deleted successfully'
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
