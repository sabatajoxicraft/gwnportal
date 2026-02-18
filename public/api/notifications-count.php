<?php
/**
 * API Endpoint: Get Unread Notification Count
 * Returns JSON with unread notification count for current user
 */

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;

// Get unread count
$count = getUnreadNotificationCount($userId);

// Return JSON response
echo json_encode([
    'success' => true,
    'count' => $count
]);
