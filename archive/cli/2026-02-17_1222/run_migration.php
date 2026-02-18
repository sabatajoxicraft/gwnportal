<?php
require_once __DIR__ . '/includes/db.php';

$sql = file_get_contents(__DIR__ . '/db/migrations/create_user_preferences.sql');

if ($conn->query($sql) === TRUE) {
    echo "Table user_preferences created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}
$conn->close();
?>
