<?php

/**
 * Database connection functions
 */

// Initialize the database connection
$conn = null;

/**
 * Get database connection
 * @return mysqli Database connection object
 */
function getDbConnection() {
    global $conn;
    
    // If connection already exists, return it
    if ($conn !== null) {
        return $conn;
    }
    
    // Get database credentials from config
    $db_host = DB_HOST;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
    $db_name = DB_NAME;
    
    // Create new connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set character set to UTF-8
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Establish initial connection
$conn = getDbConnection();
?>
