<?php
/**
 * User Login Handler
 */

require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : null;
    
    if ($action === 'login') {
        login_user();
    } elseif ($action === 'logout') {
        logout_user();
    } elseif ($action === 'forgot_password') {
        forgot_password();
    } elseif ($action === 'reset_password') {
        reset_password();
    }
}

/**
 * Authenticate user login
 */
function login_user() {
    global $conn;
    
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
        exit;
    }
    
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']) ? (bool)$_POST['remember_me'] : false;
    
    // Validation
    if (empty($email) || !validate_email($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password is required']);
        exit;
    }
    
    // Check rate limiting
    if (!check_rate_limit($email)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again in 15 minutes.']);
        exit;
    }
    
    // Find user by email
    $sql = "SELECT user_id, password_hash, email_verified, full_name, status FROM users WHERE email = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        record_failed_attempt($email);
        log_security_event(null, 'failed_login', ['email' => $email, 'reason' => 'user_not_found']);
        
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];
    
    // Check account status
    if ($user['status'] !== 'active') {
        record_failed_attempt($email);
        log_security_event($user_id, 'failed_login', ['reason' => 'account_' . $user['status']]);
        
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Your account is ' . $user['status']]);
        exit;
    }
    
    // Check if email is verified
    if (!$user['email_verified']) {
        record_failed_attempt($email);
        log_security_event($user_id, 'failed_login', ['reason' => 'email_not_verified']);
        
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Please verify your email before logging in',
            'email_not_verified' => true
        ]);
        exit;
    }
    
    // Verify password
    if (!verify_password($password, $user['password_hash'])) {
        record_failed_attempt($email);
        log_security_event($user_id, 'failed_login', ['reason' => 'wrong_password']);
        
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }
    
    // Clear login attempts
    clear_login_attempts($email);
    
    // Regenerate session
    regenerate_session();
    
    // Set session variables
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['login_time'] = time();
    
    // Update last login
    $sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Send login notification email
    require_once __DIR__ . '/../includes/email.php';
    $emailer = new EmailNotifier();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $emailer->sendLoginNotification($email, $user['full_name'], $ip_address, $user_agent);
    
    // Set remember me cookie (14 days)
    if ($remember_me) {
        $remember_token = generate_token();
        setcookie('remember_me', $remember_token, time() + (14 * 24 * 60 * 60), '/');
    }
    
    log_security_event($user_id, 'login_success', ['email' => $email]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user_id' => $user_id,
        'user_name' => $user['full_name'],
        'user_email' => $email
    ]);
}

/**
 * Logout user
 */
function logout_user() {
    $user_id = get_user_id();
    
    if ($user_id) {
        log_security_event($user_id, 'logout', ['email' => get_user_email()]);
    }
    
    // Destroy session
    session_destroy();
    
    // Clear remember me cookie
    setcookie('remember_me', '', time() - 3600, '/');
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

/**
 * Send password reset email
 */
function forgot_password() {
    global $conn;
    
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
        exit;
    }
    
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($email) || !validate_email($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Find user
    $sql = "SELECT user_id, full_name FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Don't reveal if email exists for security
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with this email, a password reset link will be sent'
        ]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];
    $full_name = $user['full_name'];
    
    // Generate reset token
    $reset_token = generate_token();
    $token_expiry = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY);
    
    // Store token
    $sql = "INSERT INTO password_reset_tokens (user_id, reset_token, token_expiry) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $reset_token, $token_expiry);
    $stmt->execute();
    
    // Send reset email
    $reset_link = SITE_URL . "reset-password.php?token=" . $reset_token . "&email=" . urlencode($email);
    
    $subject = "Password Reset - Crypto Trading Platform";
    $message = "
        <html>
            <head>
                <title>Password Reset</title>
            </head>
            <body>
                <h2>Password Reset Request</h2>
                <p>Dear $full_name,</p>
                <p>We received a request to reset your password. Click the link below to create a new password:</p>
                <p><a href='$reset_link' style='background-color: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                <p>Or copy this link: $reset_link</p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, ignore this email.</p>
                <p>Best regards,<br>Crypto Trading Platform Team</p>
            </body>
        </html>
    ";
    
    send_email($email, $subject, $message, true);
    
    log_security_event($user_id, 'password_reset_requested', ['email' => $email]);
    
    echo json_encode([
        'success' => true,
        'message' => 'If an account exists with this email, a password reset link will be sent'
    ]);
}

/**
 * Reset password with token
 */
function reset_password() {
    global $conn;
    
    $token = sanitize_input($_POST['token'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($token) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid reset request']);
        exit;
    }
    
    if ($new_password !== $confirm_password) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    $password_errors = validate_password($new_password);
    if (!empty($password_errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $password_errors]);
        exit;
    }
    
    // Verify token
    $sql = "SELECT user_id FROM password_reset_tokens 
            WHERE reset_token = ? AND token_expiry > NOW() AND used = FALSE";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
        exit;
    }
    
    $token_data = $result->fetch_assoc();
    $user_id = $token_data['user_id'];
    
    // Update password
    $password_hash = hash_password($new_password);
    
    $sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $password_hash, $user_id);
    
    if ($stmt->execute()) {
        // Mark token as used
        $sql = "UPDATE password_reset_tokens SET used = TRUE WHERE reset_token = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        
        log_security_event($user_id, 'password_reset_success', ['email' => $email]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Password reset failed. Please try again.']);
    }
}

?>
