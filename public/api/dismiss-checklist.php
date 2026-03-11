<?php
/**
 * API Endpoint: Dismiss Profile Checklist Widget
 * Updates user_preferences.checklist_widget_dismissed flag
 */

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/services/ProfileChecklistService.php';

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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$dismiss = $input['dismiss'] ?? true;

// Update dismissal state
$success = ProfileChecklistService::setWidgetDismissed($conn, $userId, $dismiss);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Checklist widget dismissed successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update dismissal state'
    ]);
}

exit;
