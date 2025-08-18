<?php
// Function to connect to the main database
function connectToDatabase() {
    $config = require_once 'config/database.php';
    
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
