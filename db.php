<?php
// Load database configuration from secure location
$config = require dirname(__DIR__) . '/config/database.php';

// Define constants for backward compatibility
define('DB_HOST', $config['host']);
define('DB_USER', $config['username']);
define('DB_PASS', $config['password']);
define('DB_NAME', $config['database']);
define('DB_PORT', $config['port']);

// Function to connect to the main database
function connectToDatabase() {
    $config = require dirname(__DIR__) . '/config/database.php';
    
    $conn = new mysqli(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['database'],
        $config['port']
    );
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Unable to connect to the database. Please try again later.");
    }
    
    return $conn;
}
