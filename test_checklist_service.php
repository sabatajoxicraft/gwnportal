<?php
/**
 * Test script for ProfileChecklistService
 * Run: docker exec gwn-portal-app php /var/www/html/test_checklist_service.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/services/ProfileChecklistService.php';

echo "=== ProfileChecklistService Tests ===\n\n";

$conn = getDbConnection();

// Test 1: Get checklist for admin (user_id = 1)
echo "Test 1: Get checklist for admin (user_id = 1)\n";
echo "-------------------------------------------\n";
$adminChecklist = ProfileChecklistService::getChecklistForUser($conn, 1);
echo "Admin has " . count($adminChecklist) . " checklist items:\n";
foreach ($adminChecklist as $item) {
    $status = $item['completed'] ? '✓' : '○';
    $optional = $item['optional'] ? ' (optional)' : '';
    echo "  $status {$item['label']}$optional\n";
}
echo "\n";

// Test 2: Get completion percentage for admin
echo "Test 2: Get completion percentage for admin\n";
echo "-------------------------------------------\n";
$adminPercentage = ProfileChecklistService::getCompletionPercentage($conn, 1);
echo "Admin completion: {$adminPercentage}%\n\n";

// Test 3: Get incomplete count for admin
echo "Test 3: Get incomplete count for admin\n";
echo "-------------------------------------------\n";
$adminIncomplete = ProfileChecklistService::getIncompleteCount($conn, 1);
echo "Admin has {$adminIncomplete} incomplete required tasks\n\n";

// Test 4: Get checklist for owner (user_id = 2)
echo "Test 4: Get checklist for owner (user_id = 2)\n";
echo "-------------------------------------------\n";
$ownerChecklist = ProfileChecklistService::getChecklistForUser($conn, 2);
echo "Owner has " . count($ownerChecklist) . " checklist items:\n";
foreach ($ownerChecklist as $item) {
    $status = $item['completed'] ? '✓' : '○';
    $optional = $item['optional'] ? ' (optional)' : '';
    echo "  $status {$item['label']}$optional\n";
}
$ownerPercentage = ProfileChecklistService::getCompletionPercentage($conn, 2);
echo "Owner completion: {$ownerPercentage}%\n\n";

// Test 5: Get checklist for manager (user_id = 3)
echo "Test 5: Get checklist for manager (user_id = 3)\n";
echo "-------------------------------------------\n";
$managerChecklist = ProfileChecklistService::getChecklistForUser($conn, 3);
echo "Manager has " . count($managerChecklist) . " checklist items:\n";
foreach ($managerChecklist as $item) {
    $status = $item['completed'] ? '✓' : '○';
    $optional = $item['optional'] ? ' (optional)' : '';
    echo "  $status {$item['label']}$optional\n";
}
$managerPercentage = ProfileChecklistService::getCompletionPercentage($conn, 3);
echo "Manager completion: {$managerPercentage}%\n\n";

// Test 6: Get checklist for student (user_id = 7)
echo "Test 6: Get checklist for student (user_id = 7)\n";
echo "-------------------------------------------\n";
$studentChecklist = ProfileChecklistService::getChecklistForUser($conn, 7);
echo "Student has " . count($studentChecklist) . " checklist items:\n";
foreach ($studentChecklist as $item) {
    $status = $item['completed'] ? '✓' : '○';
    $optional = $item['optional'] ? ' (optional)' : '';
    echo "  $status {$item['label']}$optional\n";
}
$studentPercentage = ProfileChecklistService::getCompletionPercentage($conn, 7);
$studentIncomplete = ProfileChecklistService::getIncompleteCount($conn, 7);
echo "Student completion: {$studentPercentage}%\n";
echo "Student has {$studentIncomplete} incomplete required tasks\n\n";

// Test 7: Auto-check tasks for student
echo "Test 7: Auto-check tasks for student (user_id = 7)\n";
echo "-------------------------------------------\n";
$autoCompleted = ProfileChecklistService::autoCheckTasks($conn, 7);
echo "Auto-completed {$autoCompleted} tasks\n";
$newPercentage = ProfileChecklistService::getCompletionPercentage($conn, 7);
echo "New completion: {$newPercentage}%\n\n";

// Test 8: Mark a task as complete manually
echo "Test 8: Mark task complete manually (admin.test_notifications)\n";
echo "-------------------------------------------\n";
$success = ProfileChecklistService::markComplete($conn, 1, 'admin.test_notifications');
echo $success ? "✓ Task marked complete\n" : "✗ Failed to mark task complete\n";
$newAdminPercentage = ProfileChecklistService::getCompletionPercentage($conn, 1);
echo "New admin completion: {$newAdminPercentage}%\n\n";

// Test 9: Widget dismissal state
echo "Test 9: Widget dismissal state\n";
echo "-------------------------------------------\n";
$isDismissed = ProfileChecklistService::isWidgetDismissed($conn, 1);
echo "Widget dismissed: " . ($isDismissed ? 'Yes' : 'No') . "\n";
ProfileChecklistService::setWidgetDismissed($conn, 1, true);
$isDismissed = ProfileChecklistService::isWidgetDismissed($conn, 1);
echo "After setting to true: " . ($isDismissed ? 'Yes' : 'No') . "\n";
ProfileChecklistService::setWidgetDismissed($conn, 1, false);
$isDismissed = ProfileChecklistService::isWidgetDismissed($conn, 1);
echo "After setting to false: " . ($isDismissed ? 'Yes' : 'No') . "\n\n";

// Test 10: Get incomplete items
echo "Test 10: Get incomplete items for owner\n";
echo "-------------------------------------------\n";
$incompleteItems = ProfileChecklistService::getIncompleteItems($conn, 2);
echo "Owner has " . count($incompleteItems) . " incomplete items:\n";
foreach ($incompleteItems as $item) {
    $optional = $item['optional'] ? ' (optional)' : '';
    echo "  - {$item['label']}$optional\n";
}

echo "\n=== All Tests Completed ===\n";
