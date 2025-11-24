<?php
require 'includes/config.php';

$result = $conn->query("SELECT user_id, username, email, date_created FROM users");

if ($result->num_rows > 0) {
    echo "Users in database:\n\n";
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row['user_id'] . "\n";
        echo "Username: " . $row['username'] . "\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Created: " . $row['date_created'] . "\n";
        echo "---\n";
    }
} else {
    echo "No users found\n";
}

$conn->close();
?>
