<?php
require 'includes/config.php';
require 'includes/email.php';

// Test account details
$email = 'test@example.com';
$password = 'Test123456';
$full_name = 'Test User';
$phone = '+1234567890';
$country = 'United States';

// Check if test account already exists
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "Test account already exists!\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    exit;
}

// Create test account
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (email, password_hash, full_name, phone, country, email_verified, status) VALUES (?, ?, ?, ?, ?, 1, 'active')");
$stmt->bind_param("sssss", $email, $password_hash, $full_name, $phone, $country);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;
    
    // Send welcome email
    $emailNotifier = new EmailNotifier();
    $emailNotifier->sendWelcomeEmail($email, $full_name);
    
    // Log security event
    $event_data = json_encode([
        'ip' => '127.0.0.1',
        'user_agent' => 'CLI Test Script'
    ]);
    log_security_event($user_id, 'registration', $event_data);
    
    echo "✓ Test account created successfully!\n\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "\nLogin at: http://localhost:8000/login.html\n";
} else {
    echo "Error creating test account: " . $conn->error . "\n";
}

$stmt->close();
$conn->close();
?>
