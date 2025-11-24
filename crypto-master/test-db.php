<?php
// Test database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

$host = 'localhost';
$user = 'root';
$pass = 'Olami6525$';
$db = 'crypto_trading';

echo "<p>Attempting to connect to MySQL...</p>";
echo "<p>Host: $host</p>";
echo "<p>Database: $db</p>";
echo "<p>User: $user</p>";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        die("<p style='color:red;'>Connection failed: " . $conn->connect_error . "</p>");
    }
    
    echo "<p style='color:green;'><strong>✓ Connection successful!</strong></p>";
    
    // Test query
    $result = $conn->query("SHOW TABLES");
    echo "<h3>Tables in database:</h3><ul>";
    while ($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
    
    // Test users table
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    echo "<p>Users in database: " . $row['count'] . "</p>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
