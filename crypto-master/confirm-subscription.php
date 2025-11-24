<?php
/**
 * Newsletter Subscription Confirmation
 * Validates token and confirms subscription
 */

session_start();

$host = 'localhost';
$db = 'crypto_platform';
$user = 'root';
$password = '';

$message = '';
$success = false;

try {
    $conn = new mysqli($host, $user, $password, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    $token = htmlspecialchars(strip_tags($_GET['token'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    if (empty($token)) {
        throw new Exception("Invalid confirmation link");
    }
    
    // Find subscription with valid token
    $sql = "SELECT id, email, expires_at FROM newsletters WHERE token = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid or expired confirmation link");
    }
    
    $row = $result->fetch_assoc();
    $expires_at = strtotime($row['expires_at']);
    
    if ($expires_at < time()) {
        throw new Exception("This confirmation link has expired. Please sign up again.");
    }
    
    // Update subscription status
    $update_sql = "UPDATE newsletters SET status = 'subscribed', confirmed_at = NOW() WHERE token = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('s', $token);
    $update_stmt->execute();
    
    $message = "Thank you! Your subscription has been confirmed. You will now receive our weekly newsletter.";
    $success = true;
    
    $conn->close();
    
} catch (Exception $e) {
    $message = $e->getMessage();
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success ? 'Subscription Confirmed' : 'Confirmation Failed'; ?></title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .confirmation-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .confirmation-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            text-align: center;
        }
        .success-icon {
            color: #4CAF50;
            font-size: 60px;
            margin-bottom: 20px;
        }
        .error-icon {
            color: #f59e0b;
            font-size: 60px;
            margin-bottom: 20px;
        }
        .confirmation-card h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .confirmation-card p {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            transition: transform 0.3s;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <?php if ($success): ?>
                <div class="success-icon">✓</div>
                <h2>Subscription Confirmed!</h2>
            <?php else: ?>
                <div class="error-icon">⚠</div>
                <h2>Confirmation Failed</h2>
            <?php endif; ?>
            
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="index.html" class="btn-home">Back to Home</a>
        </div>
    </div>
</body>
</html>
