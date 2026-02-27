<?php
// Diagnostic file - delete after debugging
echo "<h2>Server Diagnostics</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";

echo "<h3>Required Extensions</h3>";
echo "<p>mysqli: " . (extension_loaded('mysqli') ? '✅ loaded' : '❌ MISSING') . "</p>";
echo "<p>pdo_mysql: " . (extension_loaded('pdo_mysql') ? '✅ loaded' : '❌ MISSING') . "</p>";
echo "<p>mod_rewrite: " . (in_array('mod_rewrite', apache_get_modules() ?? []) ? '✅' : '⚠️ unknown') . "</p>";

echo "<h3>File Check</h3>";
// Adjusted paths relative to public/ folder
$files = [
    'index.php' => 'index.php', 
    '../includes/config.php' => '../includes/config.php', 
    '../includes/db.php' => '../includes/db.php', 
    '../includes/session-config.php' => '../includes/session-config.php',
    '../db/schema.sql' => '../db/schema.sql'
];
foreach ($files as $name => $relPath) {
    $path = realpath(__DIR__ . '/' . $relPath);
    echo "<p>$name: " . ($path && file_exists($path) ? '✅ exists' : '❌ MISSING') . "</p>";
}

echo "<h3>Database Connection Check</h3>";
// Load env.production manually to test credentials
$envFile = realpath(__DIR__ . '/../env.production');
if ($envFile) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($n, $v) = explode('=', $line, 2);
        putenv(trim($n)."=".trim($v));
    }
}
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db = getenv('DB_NAME') ?: 'gwn_wifi_system';

echo "<p>Attempting to connect to <strong>$host</strong> as <strong>$user</strong>...</p>";
try {
    $c = new mysqli($host, $user, $pass);
    if ($c->connect_error) {
        echo "<p style='color:red'>❌ Connection failed: " . $c->connect_error . "</p>";
    } else {
        echo "<p style='color:green'>✅ Connected to MySQL server!</p>";
        if ($c->select_db($db)) {
             echo "<p style='color:green'>✅ Database '$db' selected successfully!</p>";
             
             // Check if tables exist
             $res = $c->query("SHOW TABLES");
             echo "<h3>Tables in database:</h3><ul>";
             if ($res && $res->num_rows > 0) {
                 while($row = $res->fetch_array()) {
                     echo "<li>" . $row[0] . "</li>";
                 }
             } else {
                 echo "<li>No tables found!</li>";
             }
             echo "</ul>";

             // Check specifically for roles table like index.php does
             $resRoles = $c->query("SHOW TABLES LIKE 'roles'");
             echo "<p>Checking for 'roles' table specifically: " . ($resRoles->num_rows > 0 ? "✅ Found" : "❌ NOT FOUND") . "</p>";
             
        } else {
             echo "<p style='color:orange'>⚠️ Connected, but database '$db' does not exist.</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Exception: " . $e->getMessage() . "</p>";
}
