<?php
require 'includes/config.php';

echo "Creating login_attempts table...\n";

$sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    INDEX idx_identifier_time (identifier, attempt_time)
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ login_attempts table created successfully!\n";
    
    // Verify it exists
    $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($result->num_rows > 0) {
        echo "✓ Table verified in database\n";
    }
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

$conn->close();
?>
