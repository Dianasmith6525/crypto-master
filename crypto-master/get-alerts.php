<?php
/**
 * Get User Alerts
 * Retrieves all alerts for authenticated user
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
    
    $user_id = $_SESSION['user_id'];
    $filter = htmlspecialchars(strip_tags($_GET['filter'] ?? 'active'), ENT_QUOTES, 'UTF-8');
    
    // Build query based on filter
    if ($filter === 'triggered') {
        $sql = "SELECT id, crypto_id, crypto_name, alert_type, price_threshold, status, 
                        created_at, triggered_at 
                FROM price_alerts 
                WHERE user_id = ? AND status = 'triggered'
                ORDER BY triggered_at DESC";
    } else if ($filter === 'all') {
        $sql = "SELECT id, crypto_id, crypto_name, alert_type, price_threshold, status, 
                        created_at, triggered_at 
                FROM price_alerts 
                WHERE user_id = ? 
                ORDER BY created_at DESC";
    } else {
        $sql = "SELECT id, crypto_id, crypto_name, alert_type, price_threshold, status, 
                        created_at, triggered_at 
                FROM price_alerts 
                WHERE user_id = ? AND status = 'active'
                ORDER BY created_at DESC";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alerts = [];
    while ($row = $result->fetch_assoc()) {
        $alerts[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'alerts' => $alerts,
        'count' => count($alerts)
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
