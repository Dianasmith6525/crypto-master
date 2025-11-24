<?php
/**
 * User Registration Handler
 */

require_once '../includes/config.php';

// Handle AJAX requests
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : null;
    
    if ($action === 'register') {
        register_user();
    } elseif ($action === 'verify_email') {
        verify_user_email();
    } elseif ($action === 'check_email_exists') {
        check_email_exists();
    }
}

/**
 * Register new user
 */
function register_user() {
    global $conn;
    
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'errors' => ['Invalid security token. Please refresh and try again.']]);
        exit;
    }
    
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $agree_terms = isset($_POST['agree_terms']) ? (bool)$_POST['agree_terms'] : false;
    
    // Validation
    $errors = [];
    
    if (empty($email) || !validate_email($email)) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    $password_errors = validate_password($password);
    if (!empty($password_errors)) {
        $errors = array_merge($errors, $password_errors);
    }
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (!$agree_terms) {
        $errors[] = 'You must agree to the terms and conditions';
    }
    
    // Check if email already exists
    $sql = "SELECT user_id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $errors[] = 'Email address already registered';
    }

    // Get referral code from POST data
    $referral_code = sanitize_input($_POST['referral_code'] ?? '');

    // Validate referral code if provided
    $referred_by = null;
    if (!empty($referral_code)) {
        $sql = "SELECT user_id FROM users WHERE referral_code = ? AND status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $referral_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = 'Invalid referral code';
        } else {
            $referrer = $result->fetch_assoc();
            $referred_by = $referrer['user_id'];
        }
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    // Hash password
    $password_hash = hash_password($password);
    
    // Generate email verification token
    $verification_token = generate_token();
    $verification_sent_at = date('Y-m-d H:i:s');
    
    // Generate unique referral code
    $referral_code_generated = generate_referral_code();

    // Insert user into database
    $sql = "INSERT INTO users (email, password_hash, full_name, email_verification_token, verification_sent_at, referral_code, referred_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $email, $password_hash, $full_name, $verification_token, $verification_sent_at, $referral_code_generated, $referred_by);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        exit;
    }
    
    $user_id = $conn->insert_id;
    
    // Create user settings record
    $sql = "INSERT INTO user_settings (user_id) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Send welcome email with email notifier
    require_once __DIR__ . '/../includes/email.php';
    $emailer = new EmailNotifier();
    $emailer->sendWelcomeEmail($email, $full_name);
    
    // Log the registration
    log_security_event($user_id, 'registration', ['email' => $email]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Welcome to CryptoTrade Platform.',
        'user_id' => $user_id
    ]);
}

/**
 * Verify email address
 */
function verify_user_email() {
    global $conn;
    
    $token = sanitize_input($_POST['token'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($token) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid verification request']);
        exit;
    }
    
    // Find user with token
    $sql = "SELECT user_id, email_verification_token, verification_sent_at FROM users 
            WHERE email = ? AND email_verification_token = ? AND email_verified = FALSE";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification token']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];
    
    // Check if token expired (24 hours)
    $sent_time = strtotime($user['verification_sent_at']);
    if (time() - $sent_time > 86400) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Verification token has expired']);
        exit;
    }
    
    // Update user as verified
    $sql = "UPDATE users SET email_verified = TRUE, email_verification_token = NULL 
            WHERE user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        log_security_event($user_id, 'email_verified', ['email' => $email]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Email verified successfully! You can now login.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Verification failed. Please try again.']);
    }
}

/**
 * Check if email exists (for real-time validation)
 */
function check_email_exists() {
    global $conn;
    
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($email) || !validate_email($email)) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    $sql = "SELECT user_id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode(['exists' => $result->num_rows > 0]);
}

?>
