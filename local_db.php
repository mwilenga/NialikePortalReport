<?php
// Local database configuration for user management
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_USER', 'root');
define('LOCAL_DB_PASS', '');
define('LOCAL_DB_NAME', 'nialike_users');
define('LOCAL_DB_PORT', '3306');

// Function to connect to local database
function connectToUserDB() {
    $conn = new mysqli(LOCAL_DB_HOST, LOCAL_DB_USER, LOCAL_DB_PASS, LOCAL_DB_NAME, LOCAL_DB_PORT);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>
