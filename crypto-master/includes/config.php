<?php
/**
 * Authentication Configuration & Database Connection
 * Database connection, session management, security utilities
 */

// Load environment variables from .env file
function load_env($file = '.env') {
    if (!file_exists($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load environment variables
load_env(__DIR__ . '/../.env');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'crypto_trading');

// Email Configuration - SendGrid (FREE - 100 emails/day)
// Sign up at: https://sendgrid.com/free/
define('FROM_EMAIL', getenv('FROM_EMAIL') ?: 'noreply@example.com');
define('FROM_NAME', getenv('FROM_NAME') ?: 'CryptoTrade Platform');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@example.com');
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.sendgrid.net');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'apikey');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('EMAIL_DEBUG', filter_var(getenv('EMAIL_DEBUG'), FILTER_VALIDATE_BOOLEAN));

// Security Configuration
define('PASSWORD_MIN_LENGTH', 12); // Increased from 8 to 12 for better security
define('SESSION_TIMEOUT', 3600); // 1 hour
define('TOKEN_EXPIRY', 3600); // 1 hour for reset tokens
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Base URLs
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/crypto-master/');
define('API_URL', SITE_URL . 'api/');
define('ALLOWED_ORIGINS', getenv('ALLOWED_ORIGINS') ?: 'http://localhost:8000');

// Create Database Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    die(json_encode([
        'success' => false, 
        'message' => 'Service temporarily unavailable. Please try again later.'
    ]));
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Session Configuration
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => '/',
    'domain' => '',
    'secure' => false, // Set to true in production with HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set secure CORS headers
 */
function set_cors_headers() {
    $allowed_origins = explode(',', ALLOWED_ORIGINS);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Set security headers
 */
function set_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\';');
}

/**
 * Regenerate session ID for security
 */
function regenerate_session() {
    session_regenerate_id(true);
}

/**
 * Check if user is authenticated
 */
function is_user_authenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require admin access - redirect if not admin
 */
function require_admin() {
    if (!is_user_authenticated()) {
        header('Location: admin-login.php');
        exit;
    }
    
    if (!is_admin()) {
        http_response_code(403);
        die('Access Denied: Admin privileges required');
    }
}

/**
 * Log admin activity
 */
function log_admin_activity($action, $target_type = null, $target_id = null, $details = null) {
    global $conn;
    
    if (!is_admin()) {
        return false;
    }
    
    $admin_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $admin_id, $action, $target_type, $target_id, $details, $ip_address);
    
    return $stmt->execute();
}

/**
 * Get current authenticated user ID
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current authenticated user email
 */
function get_user_email() {
    return $_SESSION['user_email'] ?? null;
}

/**
 * Hash password using bcrypt
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random token
 */
function generate_token($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    global $conn;
    return $conn->real_escape_string(trim(strip_tags($data)));
}

/**
 * Log security event (login, logout, failed attempts, etc.)
 */
function log_security_event($user_id, $event_type, $details = null) {
    global $conn;
    
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'Unknown';
    
    // Convert array to JSON if needed, or use string as-is
    if (is_array($details)) {
        $details_json = json_encode($details);
    } else {
        $details_json = $details;
    }
    
    $sql = "INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, event_data) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $event_type, $ip_address, $user_agent, $details_json);
    return $stmt->execute();
}

/**
 * Check rate limiting (prevent brute force)
 */
function check_rate_limit($identifier) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
            FROM login_attempts 
            WHERE identifier = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['attempts'] >= MAX_LOGIN_ATTEMPTS) {
        return false; // Rate limited
    }
    
    return true;
}

/**
 * Record failed login attempt
 */
function record_failed_attempt($identifier) {
    global $conn;
    
    $sql = "INSERT INTO login_attempts (identifier, attempt_time) VALUES (?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
}

/**
 * Clear login attempts after successful login
 */
function clear_login_attempts($identifier) {
    global $conn;
    
    $sql = "DELETE FROM login_attempts WHERE identifier = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $identifier);
    $stmt->execute();
}

/**
 * Send email (using PHP mail or SMTP)
 */
function send_email($to_email, $subject, $message, $is_html = true) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: " . ($is_html ? "text/html" : "text/plain") . "; charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_EMAIL . "\r\n";
    $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

/**
 * Log API activity
 */
function log_api_activity($endpoint, $method, $user_id = null, $status = 200, $response = null) {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $timestamp = date('Y-m-d H:i:s');
    $response_json = $response ? json_encode($response) : null;
    
    $sql = "INSERT INTO api_logs (endpoint, method, user_id, status, ip_address, response, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiisss", $endpoint, $method, $user_id, $status, $ip_address, $response_json, $timestamp);
    return $stmt->execute();
}

?>
