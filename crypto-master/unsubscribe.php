<?php
/**
 * Newsletter Unsubscribe Page
 */

session_start();

$host = 'localhost';
$db = 'crypto_platform';
$user = 'root';
$password = '';

$message = '';
$success = false;
$email = '';

try {
    $conn = new mysqli($host, $user, $password, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }
        
        // Update subscription status to unsubscribed
        $sql = "UPDATE newsletters SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $message = "You have been successfully unsubscribed from our newsletter.";
            $success = true;
        } else {
            $message = "Email not found in our newsletter list.";
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    $message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe from Newsletter</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .unsubscribe-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .unsubscribe-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        .unsubscribe-card h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .unsubscribe-card p {
            color: #666;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn-unsubscribe {
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: transform 0.3s;
        }
        .btn-unsubscribe:hover {
            transform: translateY(-2px);
        }
        .success-message {
            color: #4CAF50;
            padding: 15px;
            background: #f0f7f0;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error-message {
            color: #f59e0b;
            padding: 15px;
            background: #fef3f0;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="unsubscribe-container">
        <div class="unsubscribe-card">
            <h2>Unsubscribe from Newsletter</h2>
            
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="<?php echo $success ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php if ($success): ?>
                    <p>We're sorry to see you go. You can resubscribe anytime by visiting our website.</p>
                    <a href="index.html" class="btn-unsubscribe" style="text-decoration: none; display: inline-block;">Back to Home</a>
                <?php endif; ?>
            <?php else: ?>
                <p>We'd be sorry to see you go! Enter your email address to unsubscribe from our newsletter.</p>
                <form method="POST">
                    <div class="form-group">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" required placeholder="your@email.com">
                    </div>
                    <button type="submit" class="btn-unsubscribe">Unsubscribe</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
