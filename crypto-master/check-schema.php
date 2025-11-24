<?php
require 'includes/config.php';

// Get table structure
$result = $conn->query("DESCRIBE users");

echo "Columns in users table:\n\n";
while($row = $result->fetch_assoc()) {
    echo "Column: " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Count users
$count = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc();
echo "\nTotal users: " . $count['total'] . "\n";

$conn->close();
?>
