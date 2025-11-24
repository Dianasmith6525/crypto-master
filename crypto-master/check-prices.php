<?php
/**
 * Check Prices and Trigger Alerts
 * Runs periodically to check crypto prices against alerts
 * Can be called via cron job or manually
 */

header('Content-Type: application/json');

// Database configuration
$host = 'localhost';
$db = 'crypto_platform';
$user = 'root';
$password = '';

$alerts_triggered = 0;
$alerts_checked = 0;

try {
    $conn = new mysqli($host, $user, $password, $db);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Get all active alerts grouped by crypto
    $sql = "SELECT DISTINCT crypto_id FROM price_alerts WHERE status = 'active'";
    $result = $conn->query($sql);
    
    $cryptos_to_check = [];
    while ($row = $result->fetch_assoc()) {
        $cryptos_to_check[] = $row['crypto_id'];
    }
    
    if (empty($cryptos_to_check)) {
        echo json_encode([
            'success' => true,
            'message' => 'No active alerts to check',
            'alerts_checked' => 0,
            'alerts_triggered' => 0
        ]);
        exit;
    }
    
    // Fetch current prices from CoinGecko for all cryptos
    $crypto_ids_str = implode(',', $cryptos_to_check);
    $api_url = "https://api.coingecko.com/api/v3/simple/price?ids=" . $crypto_ids_str . "&vs_currencies=usd";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CryptoTrade-AlertSystem');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception("Failed to fetch prices from CoinGecko API");
    }
    
    $prices = json_decode($response, true);
    
    if (empty($prices)) {
        throw new Exception("No price data received from API");
    }
    
    // Map CoinGecko IDs to our crypto IDs
    $coingecko_mapping = [
        'bitcoin' => 'bitcoin',
        'ethereum' => 'ethereum',
        'litecoin' => 'litecoin',
        'ripple' => 'ripple'
    ];
    
    // Check each active alert
    $check_alerts_sql = "SELECT id, user_id, crypto_id, alert_type, price_threshold 
                         FROM price_alerts 
                         WHERE status = 'active'
                         ORDER BY user_id, crypto_id";
    
    $check_result = $conn->query($check_alerts_sql);
    
    while ($alert = $check_result->fetch_assoc()) {
        $alerts_checked++;
        $alert_id = $alert['id'];
        $user_id = $alert['user_id'];
        $crypto_id = $alert['crypto_id'];
        $alert_type = $alert['alert_type'];
        $threshold = $alert['price_threshold'];
        
        // Get current price for this crypto
        if (!isset($prices[$crypto_id]['usd'])) {
            continue; // Skip if price not available
        }
        
        $current_price = $prices[$crypto_id]['usd'];
        
        // Check if alert condition is met
        $condition_met = false;
        if ($alert_type === 'above' && $current_price >= $threshold) {
            $condition_met = true;
        } elseif ($alert_type === 'below' && $current_price <= $threshold) {
            $condition_met = true;
        }
        
        if ($condition_met) {
            // Update alert status to triggered
            $update_sql = "UPDATE price_alerts 
                          SET status = 'triggered', triggered_at = NOW() 
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('i', $alert_id);
            $update_stmt->execute();
            
            // Get user email
            $user_sql = "SELECT email FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param('i', $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_email = $user_data['email'];
            
            // Get alert details
            $alert_sql = "SELECT crypto_name FROM price_alerts WHERE id = ?";
            $alert_stmt = $conn->prepare($alert_sql);
            $alert_stmt->bind_param('i', $alert_id);
            $alert_stmt->execute();
            $alert_result = $alert_stmt->get_result();
            $alert_data = $alert_result->fetch_assoc();
            $crypto_name = $alert_data['crypto_name'];
            
            // Send notification email
            $subject = "Price Alert Triggered: " . $crypto_name;
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; }
                    .alert-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; }
                    .price { font-size: 24px; font-weight: bold; color: #667eea; }
                    .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Price Alert Triggered!</h2>
                    </div>
                    <div class='content'>
                        <p>Hi,</p>
                        <div class='alert-box'>
                            <p>Your price alert for <strong>" . htmlspecialchars($crypto_name) . "</strong> has been triggered!</p>
                            <p><strong>Alert Type:</strong> Price went <strong>" . strtoupper($alert_type) . "</strong> " . number_format($threshold, 2) . " USD</p>
                            <p><strong>Current Price:</strong> <span class='price'>\$" . number_format($current_price, 2) . "</span></p>
                        </div>
                        <p>Check your portfolio and make trading decisions now:</p>
                        <a href='https://yoursite.com/dashboard.html' class='button'>View Dashboard</a>
                        <p>This alert has been marked as triggered in your account.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
            $headers .= "From: alerts@yoursite.com" . "\r\n";
            
            // Note: Uncomment to enable email sending
            // mail($user_email, $subject, $message, $headers);
            
            $alerts_triggered++;
            
            error_log("Alert triggered for user $user_id: $crypto_name at \$$current_price");
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Price check completed',
        'alerts_checked' => $alerts_checked,
        'alerts_triggered' => $alerts_triggered
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'alerts_checked' => $alerts_checked,
        'alerts_triggered' => $alerts_triggered
    ]);
}
?>
