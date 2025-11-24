<?php
require 'includes/config.php';

$result = $conn->query("DESCRIBE security_logs");
echo "security_logs table structure:\n\n";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

$conn->close();
?>
