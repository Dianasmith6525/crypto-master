<?php
/**
 * Referral System API
 * Handles all referral-related operations
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_stats':
        getReferralStats($user_id);
        break;
    
    case 'get_referrals':
        getReferralsList($user_id);
        break;
    
    case 'get_earnings':
        getEarningsHistory($user_id);
        break;
    
    case 'generate_link':
        generateSharingLink($user_id);
        break;
    
    case 'track_click':
        trackReferralClick();
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get referral statistics for dashboard
 */
function getReferralStats($user_id) {
    global $conn;
    
    // Get user's referral code and link
    $stmt = $conn->prepare("SELECT referral_code, total_referral_earnings FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user || !$user['referral_code']) {
        echo json_encode(['success' => false, 'message' => 'Referral code not found']);
        return;
    }
    
    $referral_code = $user['referral_code'];
    $referral_link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . 
                     '/register.html?ref=' . $referral_code;
    
    // Count total referrals
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE referred_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_referrals = $stmt->get_result()->fetch_assoc()['total'];
    
    // Total earnings (credited)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(bonus_amount), 0) as total 
        FROM referral_transactions 
        WHERE referrer_id = ? AND status = 'credited'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_earnings = $stmt->get_result()->fetch_assoc()['total'];
    
    // Pending earnings
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(bonus_amount), 0) as total 
        FROM referral_transactions 
        WHERE referrer_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending_earnings = $stmt->get_result()->fetch_assoc()['total'];
    
    // Total clicks
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM referral_clicks WHERE referrer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_clicks = $stmt->get_result()->fetch_assoc()['total'];
    
    // Conversion rate
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM referral_clicks 
        WHERE referrer_id = ? AND converted = 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $converted_clicks = $stmt->get_result()->fetch_assoc()['total'];
    
    $conversion_rate = $total_clicks > 0 ? ($converted_clicks / $total_clicks) * 100 : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'referral_code' => $referral_code,
            'referral_link' => $referral_link,
            'total_referrals' => (int)$total_referrals,
            'total_earnings' => (float)$total_earnings,
            'pending_earnings' => (float)$pending_earnings,
            'total_clicks' => (int)$total_clicks,
            'conversion_rate' => round($conversion_rate, 2)
        ]
    ]);
}

/**
 * Get list of referred users
 */
function getReferralsList($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            u.full_name as name,
            u.email,
            u.date_created as join_date,
            COUNT(DISTINCT th.trade_id) as total_trades,
            COALESCE(SUM(th.total_value), 0) as total_volume,
            COALESCE(SUM(rt.bonus_amount), 0) as total_earnings
        FROM users u
        LEFT JOIN trade_history th ON u.user_id = th.user_id
        LEFT JOIN referral_transactions rt ON u.user_id = rt.referee_id AND rt.referrer_id = ?
        WHERE u.referred_by = ?
        GROUP BY u.user_id
        ORDER BY u.date_created DESC
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $referrals = [];
    while ($row = $result->fetch_assoc()) {
        $referrals[] = [
            'name' => $row['name'] ?? 'Unknown',
            'email' => $row['email'],
            'join_date' => $row['join_date'],
            'total_trades' => (int)$row['total_trades'],
            'total_volume' => (float)$row['total_volume'],
            'total_earnings' => (float)$row['total_earnings']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $referrals
    ]);
}

/**
 * Get earnings history
 */
function getEarningsHistory($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            rt.*,
            u.full_name as referee_name,
            u.email as referee_email
        FROM referral_transactions rt
        JOIN users u ON rt.referee_id = u.user_id
        WHERE rt.referrer_id = ?
        ORDER BY rt.created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $earnings = [];
    while ($row = $result->fetch_assoc()) {
        $earnings[] = [
            'transaction_id' => $row['transaction_id'],
            'referee_name' => $row['referee_name'] ?? 'Unknown',
            'referee_email' => $row['referee_email'],
            'bonus_type' => $row['bonus_type'],
            'bonus_amount' => (float)$row['bonus_amount'],
            'trade_volume' => (float)$row['trade_volume'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'credited_at' => $row['credited_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $earnings
    ]);
}

/**
 * Generate platform-specific sharing link
 */
function generateSharingLink($user_id) {
    global $conn;
    
    $platform = $_POST['platform'] ?? '';
    
    // Get referral code
    $stmt = $conn->prepare("SELECT referral_code FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    $referral_link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . 
                     '/register.html?ref=' . $user['referral_code'];
    
    $message = "Join CryptoTrade Pro and get $5 bonus! Use my referral link:";
    
    $sharing_urls = [
        'twitter' => 'https://twitter.com/intent/tweet?text=' . urlencode($message) . '&url=' . urlencode($referral_link),
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($referral_link),
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($referral_link),
        'whatsapp' => 'https://wa.me/?text=' . urlencode($message . ' ' . $referral_link),
        'telegram' => 'https://t.me/share/url?url=' . urlencode($referral_link) . '&text=' . urlencode($message),
        'email' => 'mailto:?subject=' . urlencode('Join CryptoTrade Pro') . '&body=' . urlencode($message . ' ' . $referral_link)
    ];
    
    if (isset($sharing_urls[$platform])) {
        echo json_encode([
            'success' => true,
            'selected_link' => $sharing_urls[$platform]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid platform']);
    }
}

/**
 * Track referral click
 */
function trackReferralClick() {
    global $conn;
    
    $referral_code = $_POST['referral_code'] ?? $_GET['ref'] ?? '';
    
    if (empty($referral_code)) {
        echo json_encode(['success' => false, 'message' => 'No referral code provided']);
        return;
    }
    
    // Get referrer user ID
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE referral_code = ?");
    $stmt->bind_param("s", $referral_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $referrer = $result->fetch_assoc();
    
    if (!$referrer) {
        echo json_encode(['success' => false, 'message' => 'Invalid referral code']);
        return;
    }
    
    $referrer_id = $referrer['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Insert click record
    $stmt = $conn->prepare("
        INSERT INTO referral_clicks (referrer_id, referral_code, ip_address, user_agent) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $referrer_id, $referral_code, $ip_address, $user_agent);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Click tracked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to track click']);
    }
}
?>
