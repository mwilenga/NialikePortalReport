<?php
define('DB_HOST', '161.97.132.146');
define('DB_USER', 'nialike');
define('DB_PASS', 'nialikeP@$$w0rd');
define('DB_NAME', 'nialike');
define('DB_PORT', '3306');

/**
 * Test database connection
 * @return array Result of the connection test
 */
function testDatabaseConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $conn->connect_error
            ];
        }
        return [
            'success' => true,
            'message' => 'Database connection successful'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Connect to the database
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 */
function connectToDatabase() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            throw new Exception($conn->connect_error);
        }
        return $conn;
    } catch (Exception $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}
