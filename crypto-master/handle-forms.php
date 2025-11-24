<?php
/**
 * Form Handler for Cryptocurrency Platform
 * Handles email subscriptions, contact forms, and signups
 */

require_once 'includes/config.php';
require_once 'includes/email.php';

header('Content-Type: application/json');
set_cors_headers();
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Function to validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to send email
function send_email($to, $subject, $message, $from = FROM_EMAIL) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . FROM_NAME . " <" . $from . ">\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Handle subscription form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'subscribe') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security token validation failed. Please refresh the page.']);
        exit;
    }
    
    $email = sanitize_input($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    if (!is_valid_email($email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Prepare email to admin
    $admin_subject = "New Newsletter Subscription - " . $email;
    $admin_message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
            .footer { text-align: center; color: #999; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Newsletter Subscription</h2>
            </div>
            <div class='content'>
                <p><strong>Email:</strong> " . $email . "</p>
                <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p>A new user has subscribed to the newsletter.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from Crypto Trading Platform</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Prepare confirmation email to user
    $user_subject = "Welcome to Our Newsletter!";
    $user_message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
            .btn { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; display: inline-block; border-radius: 5px; margin: 10px 0; }
            .footer { text-align: center; color: #999; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Thank You for Subscribing!</h2>
            </div>
            <div class='content'>
                <p>Hi there,</p>
                <p>Thank you for subscribing to our cryptocurrency newsletter. You'll now receive weekly updates about Bitcoin, blockchain technology, and trading tips.</p>
                <p>Check out our latest content and features:</p>
                <a href='https://cryptotrading.com/blog.html' class='btn'>Read Latest News</a>
            </div>
            <div class='footer'>
                <p>&copy; 2025 Crypto Trading Platform. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Send emails
    $admin_sent = send_email(ADMIN_EMAIL, $admin_subject, $admin_message);
    $user_sent = send_email($email, $user_subject, $user_message, FROM_EMAIL);
    
    if ($admin_sent && $user_sent) {
        echo json_encode(['success' => true, 'message' => 'Thank you for subscribing! Check your email for confirmation.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Subscription failed. Please try again later.']);
    }
    exit;
}

// Handle contact form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'contact') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security token validation failed. Please refresh the page.']);
        exit;
    }
    
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $subject = sanitize_input($_POST['subject'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');
    
    $errors = [];
    
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email) || !is_valid_email($email)) $errors[] = 'Valid email is required';
    if (empty($subject)) $errors[] = 'Subject is required';
    if (empty($message)) $errors[] = 'Message is required';
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    // Prepare email to admin
    $admin_subject = "New Contact Form Submission - " . $subject;
    $admin_message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
            .footer { text-align: center; color: #999; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Contact Form Submission</h2>
            </div>
            <div class='content'>
                <p><strong>Name:</strong> " . $name . "</p>
                <p><strong>Email:</strong> " . $email . "</p>
                <p><strong>Subject:</strong> " . $subject . "</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br($message) . "</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from Crypto Trading Platform</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Prepare confirmation email to user
    $user_subject = "We Received Your Message";
    $user_message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
            .footer { text-align: center; color: #999; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Thank You for Contacting Us</h2>
            </div>
            <div class='content'>
                <p>Hi " . $name . ",</p>
                <p>We have received your message and will get back to you within 24 hours.</p>
                <p><strong>Your Message:</strong></p>
                <p>" . nl2br($message) . "</p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 Crypto Trading Platform. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Send emails
    $admin_sent = send_email(ADMIN_EMAIL, $admin_subject, $admin_message);
    $user_sent = send_email($email, $user_subject, $user_message, FROM_EMAIL);
    
    if ($admin_sent && $user_sent) {
        echo json_encode(['success' => true, 'message' => 'Thank you for your message! We will respond within 24 hours.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again later.']);
    }
    exit;
}

// If no valid action
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
