<?php
/**
 * Two-Factor Authentication API
 * Handles 2FA setup, verification, and backup codes
 */

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once 'vendor/autoload.php'; // For Google Authenticator library

use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    if ($method === 'GET') {
        if ($action === 'status') {
            // Get 2FA status
            get2FAStatus($conn, $user_id);
        } else if ($action === 'setup') {
            // Generate QR code for setup
            setup2FA($conn, $user_id);
        }
    } else if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($action === 'enable') {
            // Enable 2FA with verification
            enable2FA($conn, $user_id, $input);
        } else if ($action === 'verify') {
            // Verify 2FA code during login
            verify2FACode($conn, $user_id, $input);
        } else if ($action === 'disable') {
            // Disable 2FA
            disable2FA($conn, $user_id, $input);
        } else if ($action === 'regenerate_backup') {
            // Regenerate backup codes
            regenerateBackupCodes($conn, $user_id);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Get 2FA status for user
 */
function get2FAStatus($conn, $user_id) {
    $query = "SELECT two_factor_enabled FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'enabled' => (bool)$user['two_factor_enabled']
    ]);
}

/**
 * Setup 2FA - Generate secret and QR code
 */
function setup2FA($conn, $user_id) {
    // Get user email
    $query = "SELECT email FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Generate secret (using simple base32 encoding)
    $secret = generateSecret();
    
    // Store temporary secret in session (not in DB until verified)
    $_SESSION['temp_2fa_secret'] = $secret;
    
    // Generate QR code URL
    $qrCodeUrl = getQRCodeUrl('CryptoTrade', $user['email'], $secret);
    
    echo json_encode([
        'success' => true,
        'secret' => $secret,
        'qr_code_url' => $qrCodeUrl,
        'backup_codes' => generateBackupCodes()
    ]);
}

/**
 * Enable 2FA after verification
 */
function enable2FA($conn, $user_id, $input) {
    if (!isset($_SESSION['temp_2fa_secret'])) {
        throw new Exception('No 2FA setup in progress');
    }
    
    $code = $input['code'] ?? '';
    $backupCodes = $input['backup_codes'] ?? [];
    
    // Verify the code
    $secret = $_SESSION['temp_2fa_secret'];
    if (!verifyCode($secret, $code)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid verification code'
        ]);
        return;
    }
    
    // Save to database
    $backupCodesJson = json_encode($backupCodes);
    $query = "UPDATE users SET 
              two_factor_enabled = TRUE,
              two_factor_secret = ?,
              backup_codes = ?
              WHERE user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $secret, $backupCodesJson, $user_id);
    $stmt->execute();
    
    // Get user info for email
    $userQuery = "SELECT email, full_name FROM users WHERE user_id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userData = $userResult->fetch_assoc();
    
    // Send 2FA enabled notification
    require_once __DIR__ . '/../includes/email.php';
    $emailer = new EmailNotifier();
    $emailer->send2FAEnabled($userData['email'], $userData['full_name']);
    
    // Clear temp secret
    unset($_SESSION['temp_2fa_secret']);
    
    echo json_encode([
        'success' => true,
        'message' => '2FA enabled successfully'
    ]);
}

/**
 * Verify 2FA code
 */
function verify2FACode($conn, $user_id, $input) {
    $code = $input['code'] ?? '';
    
    // Get user's secret
    $query = "SELECT two_factor_secret, backup_codes FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Try regular code first
    if (verifyCode($user['two_factor_secret'], $code)) {
        echo json_encode([
            'success' => true,
            'message' => 'Code verified'
        ]);
        return;
    }
    
    // Try backup codes
    $backupCodes = json_decode($user['backup_codes'], true) ?? [];
    $codeIndex = array_search($code, $backupCodes);
    
    if ($codeIndex !== false) {
        // Remove used backup code
        unset($backupCodes[$codeIndex]);
        $backupCodes = array_values($backupCodes);
        
        $backupCodesJson = json_encode($backupCodes);
        $updateQuery = "UPDATE users SET backup_codes = ? WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("si", $backupCodesJson, $user_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup code verified',
            'remaining_codes' => count($backupCodes)
        ]);
        return;
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Invalid code'
    ]);
}

/**
 * Disable 2FA
 */
function disable2FA($conn, $user_id, $input) {
    $password = $input['password'] ?? '';
    
    // Verify password
    $query = "SELECT password_hash FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Incorrect password'
        ]);
        return;
    }
    
    // Disable 2FA
    $updateQuery = "UPDATE users SET 
                    two_factor_enabled = FALSE,
                    two_factor_secret = NULL,
                    backup_codes = NULL
                    WHERE user_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Get user info for email
    $userQuery = "SELECT email, full_name FROM users WHERE user_id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userData = $userResult->fetch_assoc();
    
    // Send 2FA disabled notification
    require_once __DIR__ . '/../includes/email.php';
    $emailer = new EmailNotifier();
    $emailer->send2FADisabled($userData['email'], $userData['full_name']);
    
    echo json_encode([
        'success' => true,
        'message' => '2FA disabled successfully'
    ]);
}

/**
 * Regenerate backup codes
 */
function regenerateBackupCodes($conn, $user_id) {
    $backupCodes = generateBackupCodes();
    $backupCodesJson = json_encode($backupCodes);
    
    $query = "UPDATE users SET backup_codes = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $backupCodesJson, $user_id);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'backup_codes' => $backupCodes
    ]);
}

/**
 * Generate a random secret key (Base32)
 */
function generateSecret() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 32; $i++) {
        $secret .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $secret;
}

/**
 * Generate backup codes
 */
function generateBackupCodes() {
    $codes = [];
    for ($i = 0; $i < 10; $i++) {
        $codes[] = sprintf('%04d-%04d-%04d', 
            random_int(0, 9999),
            random_int(0, 9999),
            random_int(0, 9999)
        );
    }
    return $codes;
}

/**
 * Verify TOTP code
 */
function verifyCode($secret, $code) {
    // Simple TOTP implementation
    $timestamp = floor(time() / 30);
    
    // Check current and adjacent time windows (to allow for time drift)
    for ($i = -1; $i <= 1; $i++) {
        $calculatedCode = calculateTOTP($secret, $timestamp + $i);
        if ($code === $calculatedCode) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calculate TOTP code
 */
function calculateTOTP($secret, $timestamp) {
    $key = base32Decode($secret);
    $time = pack('N*', 0) . pack('N*', $timestamp);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Base32 decode
 */
function base32Decode($secret) {
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32charsFlipped = array_flip(str_split($base32chars));
    
    $paddingCharCount = substr_count($secret, '=');
    $allowedValues = [6, 4, 3, 1, 0];
    if (!in_array($paddingCharCount, $allowedValues)) {
        return false;
    }
    
    for ($i = 0; $i < 4; ++$i) {
        if ($paddingCharCount == $allowedValues[$i] &&
            substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])) {
            return false;
        }
    }
    
    $secret = str_replace('=', '', $secret);
    $secret = str_split($secret);
    $binaryString = '';
    
    for ($i = 0; $i < count($secret); $i = $i + 8) {
        $x = '';
        if (!in_array($secret[$i], $base32charsFlipped)) {
            return false;
        }
        for ($j = 0; $j < 8; ++$j) {
            $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
        }
        $eightBits = str_split($x, 8);
        for ($z = 0; $z < count($eightBits); ++$z) {
            $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
        }
    }
    
    return $binaryString;
}

/**
 * Get QR code URL for Google Authenticator
 */
function getQRCodeUrl($issuer, $accountName, $secret) {
    $urlencoded = urlencode('otpauth://totp/' . $issuer . ':' . $accountName . '?secret=' . $secret . '&issuer=' . $issuer);
    return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . $urlencoded;
}

$conn->close();
?>