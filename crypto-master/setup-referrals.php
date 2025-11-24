<?php
/**
 * Referral System Setup Script
 * Initializes referral settings and default configurations
 */

require_once 'includes/config.php';

echo "<h1>Referral System Setup</h1>";

// Check if referral settings already exist
$sql = "SELECT COUNT(*) as count FROM referral_settings";
$result = $conn->query($sql);
$settings_exist = $result->fetch_assoc()['count'] > 0;

if ($settings_exist) {
    echo "<p style='color: orange;'>Referral settings already exist. Skipping initialization.</p>";
} else {
    // Insert default referral settings
    $sql = "INSERT INTO referral_settings (
                referrer_bonus_percentage,
                referee_bonus_amount,
                min_trade_volume,
                max_referral_earnings,
                is_active
            ) VALUES (10.00, 5.00, 100.00, 1000.00, 1)";

    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Referral settings initialized successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to initialize referral settings: " . $conn->error . "</p>";
    }
}

// Check if any users need referral codes
$sql = "SELECT user_id, full_name FROM users WHERE referral_code IS NULL";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h3>Generating Referral Codes for Existing Users</h3>";

    while ($user = $result->fetch_assoc()) {
        $referral_code = generate_referral_code();

        // Make sure the code is unique
        $check_sql = "SELECT user_id FROM users WHERE referral_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $referral_code);
        $check_stmt->execute();

        while ($check_stmt->get_result()->num_rows > 0) {
            $referral_code = generate_referral_code();
            $check_stmt->bind_param("s", $referral_code);
            $check_stmt->execute();
        }

        $update_sql = "UPDATE users SET referral_code = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $referral_code, $user['user_id']);

        if ($update_stmt->execute()) {
            echo "<p style='color: green;'>✓ Generated referral code for " . htmlspecialchars($user['full_name']) . ": " . $referral_code . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to generate referral code for " . htmlspecialchars($user['full_name']) . "</p>";
        }
    }
} else {
    echo "<p style='color: green;'>✓ All users already have referral codes</p>";
}

echo "<h3>Setup Complete</h3>";
echo "<p>The referral system has been successfully initialized!</p>";
echo "<p><a href='referrals.html'>Go to Referral Dashboard</a></p>";
echo "<p><a href='dashboard-pro.html'>Go to Main Dashboard</a></p>";

/**
 * Generate a unique referral code
 */
function generate_referral_code($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }

    return $code;
}
?>
