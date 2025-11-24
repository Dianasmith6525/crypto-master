<?php
/**
 * Admin Setup Script
 * Adds role column to users table and creates first admin user
 */

require_once 'includes/config.php';

echo "<h2>Admin System Setup</h2>";

// Step 1: Add role column to users table
echo "<h3>Step 1: Adding role column to users table...</h3>";

$sql = "ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER status";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✓ Role column added successfully</p>";
} else {
    if (strpos($conn->error, "Duplicate column name") !== false) {
        echo "<p style='color: orange;'>⚠ Role column already exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
    }
}

// Step 2: Add admin activity log table
echo "<h3>Step 2: Creating admin activity log table...</h3>";

$sql = "CREATE TABLE IF NOT EXISTS admin_activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✓ Admin activity log table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

// Step 3: Create platform statistics table for tracking
echo "<h3>Step 3: Creating platform statistics table...</h3>";

$sql = "CREATE TABLE IF NOT EXISTS platform_stats (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL UNIQUE,
    total_users INT DEFAULT 0,
    active_users INT DEFAULT 0,
    new_users INT DEFAULT 0,
    total_portfolio_value DECIMAL(20, 2) DEFAULT 0,
    total_trades INT DEFAULT 0,
    total_alerts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stat_date (stat_date)
)";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✓ Platform statistics table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

// Step 4: Prompt to create admin user
echo "<h3>Step 3: Create Admin User</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $email = sanitize_input($_POST['admin_email']);
    $password = $_POST['admin_password'];
    $full_name = sanitize_input($_POST['admin_name']);
    
    // Validate
    if (empty($email) || empty($password) || empty($full_name)) {
        echo "<p style='color: red;'>✗ All fields are required</p>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<p style='color: red;'>✗ Invalid email address</p>";
    } elseif (strlen($password) < 12) {
        echo "<p style='color: red;'>✗ Password must be at least 12 characters</p>";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<p style='color: red;'>✗ Email already exists</p>";
        } else {
            // Create admin user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (email, password_hash, full_name, role, status, email_verified) VALUES (?, ?, ?, 'admin', 'active', TRUE)");
            $stmt->bind_param("sss", $email, $password_hash, $full_name);
            
            if ($stmt->execute()) {
                echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
                echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;'>";
                echo "<strong>Admin Credentials:</strong><br>";
                echo "Email: <code>$email</code><br>";
                echo "Name: <code>$full_name</code><br>";
                echo "Role: <code>admin</code><br><br>";
                echo "<a href='admin-login.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Admin Panel</a>";
                echo "</div>";
            } else {
                echo "<p style='color: red;'>✗ Error creating admin user: " . $conn->error . "</p>";
            }
        }
    }
} else {
    // Show form
    echo "<form method='POST' style='max-width: 500px; background: #f9f9f9; padding: 20px; border-radius: 5px;'>";
    echo "<div style='margin-bottom: 15px;'>";
    echo "<label style='display: block; margin-bottom: 5px; font-weight: bold;'>Full Name:</label>";
    echo "<input type='text' name='admin_name' required style='width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;'>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 15px;'>";
    echo "<label style='display: block; margin-bottom: 5px; font-weight: bold;'>Email:</label>";
    echo "<input type='email' name='admin_email' required style='width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;'>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 15px;'>";
    echo "<label style='display: block; margin-bottom: 5px; font-weight: bold;'>Password (min 12 characters):</label>";
    echo "<input type='password' name='admin_password' required minlength='12' style='width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;'>";
    echo "</div>";
    
    echo "<button type='submit' name='create_admin' style='background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>Create Admin User</button>";
    echo "</form>";
}

echo "<hr style='margin: 30px 0;'>";
echo "<p><strong>Existing Admin Users:</strong></p>";

// List existing admins
$result = $conn->query("SELECT user_id, email, full_name, date_created FROM users WHERE role = 'admin' ORDER BY date_created DESC");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Name</th><th>Email</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . $row['date_created'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>No admin users found. Create one above.</p>";
}

?>
<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: #fff;
    }
    h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    h3 { color: #667eea; margin-top: 20px; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
</style>
