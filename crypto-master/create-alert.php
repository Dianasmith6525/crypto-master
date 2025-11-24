<?php
/**
 * Create Price Alert
 * Stores new price alert for authenticated user
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
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }
    
    // Get POST data
    $user_id = $_SESSION['user_id'];
    $crypto_id = htmlspecialchars(strip_tags($_POST['crypto_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $crypto_name = htmlspecialchars(strip_tags($_POST['crypto_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $alert_type = htmlspecialchars(strip_tags($_POST['alert_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $price_threshold = floatval($_POST['price_threshold'] ?? 0);
    
    // Validate inputs
    if (empty($crypto_id) || empty($crypto_name) || empty($alert_type) || $price_threshold <= 0) {
        throw new Exception("Missing or invalid required fields");
    }
    
    if (!in_array($alert_type, ['above', 'below'])) {
        throw new Exception("Invalid alert type");
    }
    
    // Check for duplicate alert
    $check_sql = "SELECT id FROM price_alerts 
                  WHERE user_id = ? AND crypto_id = ? AND alert_type = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('iss', $user_id, $crypto_id, $alert_type);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception("You already have an active " . $alert_type . " alert for " . $crypto_name);
    }
    
    // Insert new alert
    $insert_sql = "INSERT INTO price_alerts (user_id, crypto_id, crypto_name, alert_type, price_threshold, status, created_at)
                   VALUES (?, ?, ?, ?, ?, 'active', NOW())";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param('isssd', $user_id, $crypto_id, $crypto_name, $alert_type, $price_threshold);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to create alert");
    }
    
    $alert_id = $conn->insert_id;
    
    echo json_encode([
        'success' => true,
        'message' => 'Alert created successfully',
        'alert_id' => $alert_id
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
