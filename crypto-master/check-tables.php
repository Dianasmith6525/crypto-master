<?php
require 'includes/config.php';

$result = $conn->query("SHOW TABLES");
echo "Tables in crypto_trading database:\n\n";
while($row = $result->fetch_array()) {
    echo "✓ " . $row[0] . "\n";
}

$conn->close();
?>
