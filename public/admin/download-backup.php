<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Get the requested file
$filename = $_GET['file'] ?? '';

if (empty($filename) || !preg_match('/^[a-zA-Z0-9_\.-]+$/', $filename)) {
    die('Invalid backup filename');
}

// Full path to the file
$backup_dir = __DIR__ . '/../../../backups';
$file_path = $backup_dir . '/' . $filename;

// Verify file exists and is within the backups directory
if (!file_exists($file_path) || !is_file($file_path) || dirname($file_path) !== $backup_dir) {
    die('Backup file not found');
}

// Set appropriate headers for file download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . basename($file_path));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Clean output buffer
ob_clean();
flush();

// Read and output file
readfile($file_path);
exit;
?>
