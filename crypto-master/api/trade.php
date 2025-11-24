<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Place a trade
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = $input['action'] ?? '';
    
    if ($action === 'place_trade') {
        $crypto = $input['crypto'] ?? '';
        $type = $input['type'] ?? ''; // buy or sell
        $usd_amount = floatval($input['usd_amount'] ?? 0);
        $crypto_amount = floatval($input['crypto_amount'] ?? 0);
        $price = floatval($input['price'] ?? 0);
        
        // Validate inputs
        if (empty($crypto) || empty($type) || $usd_amount < 10 || $crypto_amount <= 0 || $price <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid trade parameters']);
            exit;
        }
        
        // Calculate fee (0.1%)
        $fee = $usd_amount * 0.001;
        $total = $type === 'buy' ? ($usd_amount + $fee) : ($usd_amount - $fee);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            if ($type === 'buy') {
                // Check USD balance (simplified - in production, you'd have a wallet table)
                // For now, assume user has sufficient balance
                
                // Insert transaction
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, crypto, type, amount, price, usd_value, fee, status, created_at) VALUES (?, ?, 'buy', ?, ?, ?, ?, 'completed', NOW())");
                $stmt->bind_param("isdddd", $user_id, $crypto, $crypto_amount, $price, $usd_amount, $fee);
                $stmt->execute();
                
                // Update portfolio
                $stmt = $conn->prepare("SELECT id, amount FROM portfolios WHERE user_id = ? AND crypto = ?");
                $stmt->bind_param("is", $user_id, $crypto);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing portfolio
                    $row = $result->fetch_assoc();
                    $new_amount = $row['amount'] + $crypto_amount;
                    $stmt = $conn->prepare("UPDATE portfolios SET amount = ?, last_updated = NOW() WHERE id = ?");
                    $stmt->bind_param("di", $new_amount, $row['id']);
                    $stmt->execute();
                } else {
                    // Create new portfolio entry
                    $stmt = $conn->prepare("INSERT INTO portfolios (user_id, crypto, amount, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->bind_param("isd", $user_id, $crypto, $crypto_amount);
                    $stmt->execute();
                }
                
                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => "Successfully bought $crypto_amount " . strtoupper($crypto) . " for $$usd_amount"
                ]);
                
            } else { // sell
                // Check crypto balance
                $stmt = $conn->prepare("SELECT amount FROM portfolios WHERE user_id = ? AND crypto = ?");
                $stmt->bind_param("is", $user_id, $crypto);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    throw new Exception("You don't have any " . strtoupper($crypto) . " to sell");
                }
                
                $row = $result->fetch_assoc();
                if ($row['amount'] < $crypto_amount) {
                    throw new Exception("Insufficient " . strtoupper($crypto) . " balance. Available: " . $row['amount']);
                }
                
                // Insert transaction
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, crypto, type, amount, price, usd_value, fee, status, created_at) VALUES (?, ?, 'sell', ?, ?, ?, ?, 'completed', NOW())");
                $stmt->bind_param("isdddd", $user_id, $crypto, $crypto_amount, $price, $usd_amount, $fee);
                $stmt->execute();
                
                // Update portfolio
                $new_amount = $row['amount'] - $crypto_amount;
                if ($new_amount > 0) {
                    $stmt = $conn->prepare("UPDATE portfolios SET amount = ?, last_updated = NOW() WHERE user_id = ? AND crypto = ?");
                    $stmt->bind_param("dis", $new_amount, $user_id, $crypto);
                } else {
                    $stmt = $conn->prepare("DELETE FROM portfolios WHERE user_id = ? AND crypto = ?");
                    $stmt->bind_param("is", $user_id, $crypto);
                }
                $stmt->execute();
                
                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => "Successfully sold $crypto_amount " . strtoupper($crypto) . " for $$usd_amount"
                ]);
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
} else if ($method === 'GET') {
    // Get recent trades
    $action = $_GET['action'] ?? '';
    
    if ($action === 'recent_trades') {
        $stmt = $conn->prepare("SELECT crypto, type, amount, price, usd_value, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $trades = [];
        while ($row = $result->fetch_assoc()) {
            $trades[] = [
                'crypto' => $row['crypto'],
                'type' => $row['type'],
                'crypto_amount' => $row['amount'],
                'price' => $row['price'],
                'usd_value' => $row['usd_value'],
                'date' => date('M d, H:i', strtotime($row['created_at']))
            ];
        }
        
        echo json_encode(['success' => true, 'trades' => $trades]);
    }
}
?>
