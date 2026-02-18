<?php
/**
 * API Endpoint: Mark Notification as Read
 * Marks single notification or all notifications as read
 */

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;

// Check if marking all as read
if (isset($_POST['mark_all']) && $_POST['mark_all'] == '1') {
    $success = markAllNotificationsAsRead($userId);
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'All notifications marked as read' : 'Failed to mark all as read'
    ]);
    exit;
}

// Mark single notification as read
if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing notification_id']);
    exit;
}

$notificationId = (int)$_POST['notification_id'];

// Mark as read
$success = markNotificationAsRead($notificationId, $userId);

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read'
]);
