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
    '../includes/session-config.php' => '../includes/session-config.php'
];
foreach ($files as $name => $relPath) {
    $path = realpath(__DIR__ . '/' . $relPath);
    echo "<p>$name: " . ($path && file_exists($path) ? '✅ exists' : '❌ MISSING') . "</p>";
}
