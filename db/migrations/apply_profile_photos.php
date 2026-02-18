<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';

echo "Starting profile photo migration...\n";

$conn = getDbConnection();

try {
    // Read and execute the SQL migration
    $sql = file_get_contents(__DIR__ . '/add_profile_photos.sql');
    
    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        echo "Executing: " . substr($statement, 0, 80) . "...\n";
        
        if (!$conn->query($statement)) {
            throw new Exception("Error executing statement: " . $conn->error);
        }
    }
    
    echo "✓ Migration completed successfully!\n";
    echo "✓ Added profile_photo column to users table\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
