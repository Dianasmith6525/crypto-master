<?php
require 'includes/config.php';

// Get the registered user's email
$result = $conn->query("SELECT email FROM users LIMIT 1");
$user = $result->fetch_assoc();

echo "Registered user email: " . $user['email'] . "\n";
echo "\nYou can login at: http://localhost:8000/login.html\n";
echo "Use the email above and the password you set during registration.\n";

$conn->close();
?>
