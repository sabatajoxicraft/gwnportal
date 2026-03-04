<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    $conn = new mysqli('gwn-db', 'root', 'rootpassword', 'gwn_wifi_system_test');
    if ($conn->connect_error) {
        echo "Connection failed: " . $conn->connect_error . PHP_EOL;
        exit(1);
    }
    echo "Test DB connection: OK" . PHP_EOL;
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
