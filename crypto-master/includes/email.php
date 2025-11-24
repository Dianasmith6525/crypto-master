<?php
/**
 * Email Notification System
 * Handles all email notifications for the crypto trading platform
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailNotifier {
    private $from_email;
    private $from_name;
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    
    public function __construct() {
        $this->from_email = FROM_EMAIL;
        $this->from_name = "CryptoTrade Platform";
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_user = SMTP_USERNAME;
        $this->smtp_pass = SMTP_PASSWORD;
    }
    
    /**
     * Send welcome email after registration
     */
    public function sendWelcomeEmail($user_email, $user_name) {
        $subject = "Welcome to CryptoTrade Platform! 🎉";
        
        $message = $this->getEmailTemplate([
            'title' => 'Welcome to CryptoTrade!',
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>Thank you for joining CryptoTrade Platform! Your account has been successfully created.</p>
                <p>You now have access to:</p>
                <ul style='text-align: left; margin: 20px auto; max-width: 400px;'>
                    <li>📊 Real-time Portfolio Tracking</li>
                    <li>⭐ Custom Watchlists</li>
                    <li>🔔 Price Alerts</li>
                    <li>📈 Advanced Charts & Analytics</li>
                    <li>📰 Latest Crypto News</li>
                    <li>🔒 Two-Factor Authentication</li>
                </ul>
                <p>Start building your crypto portfolio today!</p>
            ",
            'cta_text' => 'Go to Dashboard',
            'cta_link' => $this->getBaseUrl() . '/dashboard.html',
            'footer' => 'If you did not create this account, please contact our support team immediately.'
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send login notification
     */
    public function sendLoginNotification($user_email, $user_name, $ip_address, $user_agent) {
        $subject = "New Login to Your Account";
        
        $browser = $this->getBrowserFromUserAgent($user_agent);
        $time = date('F j, Y g:i A');
        
        $message = $this->getEmailTemplate([
            'title' => 'New Login Detected',
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>We detected a new login to your CryptoTrade account.</p>
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Time:</strong> $time</p>
                    <p style='margin: 5px 0;'><strong>IP Address:</strong> $ip_address</p>
                    <p style='margin: 5px 0;'><strong>Browser:</strong> $browser</p>
                </div>
                <p>If this was you, no action is needed.</p>
                <p style='color: #f44336;'><strong>If this wasn't you, please secure your account immediately by changing your password and enabling 2FA.</strong></p>
            ",
            'cta_text' => 'Secure My Account',
            'cta_link' => $this->getBaseUrl() . '/two-factor-auth.html',
            'footer' => 'This is an automated security notification.'
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send price alert notification
     */
    public function sendPriceAlert($user_email, $user_name, $crypto_name, $crypto_symbol, $target_price, $current_price, $condition) {
        $subject = "🔔 Price Alert: $crypto_symbol Reached Your Target!";
        
        $emoji = $condition === 'above' ? '📈' : '📉';
        $color = $condition === 'above' ? '#4caf50' : '#f44336';
        
        $message = $this->getEmailTemplate([
            'title' => "$emoji Price Alert Triggered",
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>Your price alert for <strong>$crypto_name ($crypto_symbol)</strong> has been triggered!</p>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;'>
                    <h2 style='margin: 0 0 10px 0; color: white;'>$crypto_name</h2>
                    <p style='font-size: 36px; margin: 10px 0; font-weight: bold;'>$$current_price</p>
                    <p style='margin: 10px 0; opacity: 0.9;'>Target: $$target_price ($condition)</p>
                </div>
                <p>The current price has moved <strong style='color: $color;'>" . strtoupper($condition) . "</strong> your target price.</p>
                <p>Consider reviewing your trading strategy!</p>
            ",
            'cta_text' => 'View in Dashboard',
            'cta_link' => $this->getBaseUrl() . '/dashboard.html',
            'footer' => 'Manage your alerts from the Alerts page.'
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send trade confirmation
     */
    public function sendTradeConfirmation($user_email, $user_name, $trade_type, $crypto_symbol, $amount, $price, $total) {
        $subject = "Trade Confirmation: $trade_type $crypto_symbol";
        
        $action_color = $trade_type === 'buy' ? '#4caf50' : '#f44336';
        $action_emoji = $trade_type === 'buy' ? '💰' : '💸';
        
        $message = $this->getEmailTemplate([
            'title' => "$action_emoji Trade Executed",
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>Your <strong style='color: $action_color;'>" . strtoupper($trade_type) . "</strong> order has been executed successfully.</p>
                <div style='background: #f8f9fa; padding: 25px; border-radius: 12px; margin: 20px 0; border-left: 4px solid $action_color;'>
                    <h3 style='margin: 0 0 15px 0; color: #333;'>Trade Details</h3>
                    <p style='margin: 8px 0;'><strong>Type:</strong> " . strtoupper($trade_type) . "</p>
                    <p style='margin: 8px 0;'><strong>Cryptocurrency:</strong> $crypto_symbol</p>
                    <p style='margin: 8px 0;'><strong>Amount:</strong> $amount $crypto_symbol</p>
                    <p style='margin: 8px 0;'><strong>Price per unit:</strong> $$price</p>
                    <p style='margin: 8px 0; font-size: 18px; padding-top: 10px; border-top: 2px solid #ddd;'><strong>Total:</strong> <span style='color: $action_color;'>$$total</span></p>
                </div>
                <p>Your portfolio has been updated automatically.</p>
            ",
            'cta_text' => 'View Portfolio',
            'cta_link' => $this->getBaseUrl() . '/dashboard.html',
            'footer' => 'Trade Date: ' . date('F j, Y g:i A')
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send 2FA setup confirmation
     */
    public function send2FAEnabled($user_email, $user_name) {
        $subject = "🔒 Two-Factor Authentication Enabled";
        
        $message = $this->getEmailTemplate([
            'title' => 'Security Enhanced!',
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>Two-Factor Authentication (2FA) has been successfully enabled on your account.</p>
                <div style='background: #4caf50; color: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 18px;'>✓ Your account is now more secure!</p>
                </div>
                <p>You'll now need to enter a 6-digit code from your authenticator app each time you log in.</p>
                <p><strong>Important:</strong> Make sure you've saved your backup codes in a safe place. You'll need them if you lose access to your authenticator app.</p>
                <p style='color: #f44336;'><strong>If you didn't enable 2FA, please contact support immediately.</strong></p>
            ",
            'cta_text' => 'View Security Settings',
            'cta_link' => $this->getBaseUrl() . '/two-factor-auth.html',
            'footer' => 'This is an important security notification.'
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send 2FA disabled notification
     */
    public function send2FADisabled($user_email, $user_name) {
        $subject = "⚠️ Two-Factor Authentication Disabled";
        
        $message = $this->getEmailTemplate([
            'title' => 'Security Alert',
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>Two-Factor Authentication (2FA) has been disabled on your account.</p>
                <div style='background: #ff9800; color: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 18px;'>⚠️ Your account security has been reduced</p>
                </div>
                <p>We recommend keeping 2FA enabled for maximum security.</p>
                <p style='color: #f44336;'><strong>If you didn't disable 2FA, please secure your account immediately.</strong></p>
            ",
            'cta_text' => 'Re-enable 2FA',
            'cta_link' => $this->getBaseUrl() . '/two-factor-auth.html',
            'footer' => 'This is an important security notification.'
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($user_email, $user_name, $reset_token) {
        $subject = "Password Reset Request";
        
        $reset_link = $this->getBaseUrl() . "/reset-password.html?token=$reset_token";
        
        $message = $this->getEmailTemplate([
            'title' => 'Reset Your Password',
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>We received a request to reset your password for your CryptoTrade account.</p>
                <p>Click the button below to create a new password:</p>
                <div style='background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                    <p style='margin: 0; color: #856404;'><strong>⏰ This link expires in 1 hour</strong></p>
                </div>
                <p style='color: #666; font-size: 14px;'>If you didn't request a password reset, you can safely ignore this email. Your password will not be changed.</p>
            ",
            'cta_text' => 'Reset Password',
            'cta_link' => $reset_link,
            'footer' => 'For security reasons, this link will expire in 1 hour.'
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send password changed confirmation
     */
    public function sendPasswordChanged($user_email, $user_name) {
        $subject = "Password Changed Successfully";
        
        $message = $this->getEmailTemplate([
            'title' => 'Password Updated',
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>Your password has been changed successfully.</p>
                <div style='background: #4caf50; color: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 18px;'>✓ Password changed at " . date('F j, Y g:i A') . "</p>
                </div>
                <p>You can now use your new password to log in to your account.</p>
                <p style='color: #f44336;'><strong>If you didn't change your password, please contact support immediately.</strong></p>
            ",
            'cta_text' => 'Log In Now',
            'cta_link' => $this->getBaseUrl() . '/login.html',
            'footer' => 'This is an important security notification.'
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send weekly portfolio summary
     */
    public function sendWeeklySummary($user_email, $user_name, $total_value, $weekly_change, $weekly_change_percent, $best_performer, $worst_performer) {
        $subject = "📊 Your Weekly Portfolio Summary";
        
        $change_color = $weekly_change >= 0 ? '#4caf50' : '#f44336';
        $change_emoji = $weekly_change >= 0 ? '📈' : '📉';
        $change_sign = $weekly_change >= 0 ? '+' : '';
        
        $message = $this->getEmailTemplate([
            'title' => '📊 Weekly Summary',
            'greeting' => "Hi $user_name,",
            'body' => "
                <p>Here's your weekly portfolio performance summary:</p>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px 0; color: white; opacity: 0.9;'>Total Portfolio Value</h3>
                    <p style='font-size: 42px; margin: 10px 0; font-weight: bold;'>$$total_value</p>
                    <p style='margin: 10px 0; font-size: 20px;'>
                        <span style='color: $change_color;'>$change_emoji $change_sign$$weekly_change ($change_sign$weekly_change_percent%)</span>
                    </p>
                </div>
                <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0;'>
                    <div style='background: #e8f5e9; padding: 20px; border-radius: 8px;'>
                        <p style='margin: 0; color: #2e7d32; font-weight: bold;'>Best Performer</p>
                        <p style='margin: 5px 0 0 0; font-size: 18px; color: #1b5e20;'>$best_performer</p>
                    </div>
                    <div style='background: #ffebee; padding: 20px; border-radius: 8px;'>
                        <p style='margin: 0; color: #c62828; font-weight: bold;'>Worst Performer</p>
                        <p style='margin: 5px 0 0 0; font-size: 18px; color: #b71c1c;'>$worst_performer</p>
                    </div>
                </div>
                <p>Keep up the great work managing your crypto portfolio!</p>
            ",
            'cta_text' => 'View Full Analytics',
            'cta_link' => $this->getBaseUrl() . '/analytics.html',
            'footer' => 'Week of ' . date('F j, Y')
        ]);
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Get professional email template
     */
    private function getEmailTemplate($data) {
        $title = $data['title'] ?? 'CryptoTrade Platform';
        $greeting = $data['greeting'] ?? 'Hi there,';
        $body = $data['body'] ?? '';
        $cta_text = $data['cta_text'] ?? 'Visit Dashboard';
        $cta_link = $data['cta_link'] ?? $this->getBaseUrl();
        $footer = $data['footer'] ?? '';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; }
                .content p { margin: 15px 0; }
                .cta-button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; padding: 15px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                .cta-button:hover { opacity: 0.9; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; border-top: 1px solid #e0e0e0; }
                .footer p { margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>$title</h1>
                </div>
                <div class='content'>
                    <p>$greeting</p>
                    $body
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$cta_link' class='cta-button'>$cta_text</a>
                    </div>
                </div>
                <div class='footer'>
                    " . ($footer ? "<p><strong>$footer</strong></p>" : "") . "
                    <p>&copy; " . date('Y') . " CryptoTrade Platform. All rights reserved.</p>
                    <p>This is an automated email. Please do not reply to this message.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Send email via SMTP using PHPMailer
     */
    private function sendEmail($to, $subject, $message) {
        // For development: Log emails instead of sending
        if (defined('EMAIL_DEBUG') && EMAIL_DEBUG === true) {
            $this->logEmail($to, $subject, $message);
            return true;
        }
        
        // Send real email via SMTP
        try {
            $mail = new PHPMailer(true);
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_user;
            $mail->Password = $this->smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            
            // Email settings
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Log email for development/debugging
     */
    private function logEmail($to, $subject, $message) {
        $log_dir = __DIR__ . '/../logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        
        $log_file = $log_dir . '/emails.log';
        $log_entry = "\n\n" . str_repeat('=', 80) . "\n";
        $log_entry .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $log_entry .= "To: $to\n";
        $log_entry .= "Subject: $subject\n";
        $log_entry .= str_repeat('-', 80) . "\n";
        $log_entry .= strip_tags($message) . "\n";
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        return "$protocol://$host";
    }
    
    /**
     * Extract browser name from user agent
     */
    private function getBrowserFromUserAgent($user_agent) {
        if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
        if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
        if (strpos($user_agent, 'Safari') !== false) return 'Safari';
        if (strpos($user_agent, 'Edge') !== false) return 'Edge';
        if (strpos($user_agent, 'Opera') !== false) return 'Opera';
        return 'Unknown Browser';
    }
}
