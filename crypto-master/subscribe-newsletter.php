<?php
/**
 * Newsletter Subscription Handler
 * Stores email addresses in database and sends confirmation email
 */

header('Content-Type: application/json');

// Database configuration
$host = 'localhost';
$db = 'crypto_platform';
$user = 'root';
$password = '';

try {
    $conn = new mysqli($host, $user, $password, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }
    
    // Get email from POST
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address");
    }
    
    // Check if email already subscribed
    $check_sql = "SELECT id FROM newsletters WHERE email = ? AND status = 'subscribed'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('s', $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This email is already subscribed to our newsletter'
        ]);
        exit;
    }
    
    // Generate unique token for confirmation
    $token = bin2hex(random_bytes(32));
    $created_at = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Insert or update subscription
    $sql = "INSERT INTO newsletters (email, token, status, created_at, expires_at) 
            VALUES (?, ?, 'pending', ?, ?)
            ON DUPLICATE KEY UPDATE 
            token = ?, status = 'pending', created_at = ?, expires_at = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssss', $email, $token, $created_at, $expires_at, $token, $created_at, $expires_at);
    
    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $stmt->error);
    }
    
    // Send confirmation email
    $confirmation_link = "https://yoursite.com/confirm-subscription.php?token=" . $token;
    $subject = "Confirm Your Newsletter Subscription";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; }
            .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
            .footer { background: #333; color: white; padding: 15px; text-align: center; border-radius: 0 0 5px 5px; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Welcome to CryptoTrade Newsletter!</h2>
            </div>
            <div class='content'>
                <p>Hi,</p>
                <p>Thank you for signing up to our newsletter. Please confirm your subscription by clicking the button below:</p>
                <a href='" . $confirmation_link . "' class='button'>Confirm Subscription</a>
                <p>Or copy this link:</p>
                <p style='word-break: break-all; color: #666;'>" . $confirmation_link . "</p>
                <p>This link will expire in 7 days.</p>
                <p>If you did not sign up for this newsletter, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 CryptoTrade. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: newsletter@yoursite.com" . "\r\n";
    
    // Note: Configure your email settings below
    $from_email = 'newsletter@yoursite.com'; // Update this
    $headers = "From: " . $from_email . "\r\n";
    $headers .= "Reply-To: support@yoursite.com" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    
    // Send email (uncomment when email is configured)
    // mail($email, $subject, $message, $headers);
    
    // For development, just log success
    error_log("Newsletter subscription for: " . $email);
    
    echo json_encode([
        'success' => true,
        'message' => 'Please check your email to confirm your subscription'
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
