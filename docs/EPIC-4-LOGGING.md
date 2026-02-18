# EPIC 4: Activity Logging Enhancement - Implementation Guide

> Complete logging infrastructure for tracking all user actions, page views, API calls, and errors.

## Overview

EPIC 4 has implemented comprehensive logging across three dimensions:

1. **Request Logging** - Page views, API calls, performance metrics
2. **Activity Logging** - Business logic events (device registration, student enrollment, etc.)
3. **Error Logging** - Database errors, validation errors, application failures

## Components

### RequestLogger Service

Tracks all HTTP requests, page views, and API calls with performance metrics.

**Initialization:**

```php
require_once 'includes/config.php';
RequestLogger::init();  // Call at application start
```

**Methods:**

```php
// Log page view
RequestLogger::logPageView(
    $userId,
    '/public/dashboard.php',
    ['search_query' => 'students', 'filter' => 'active']
);

// Log API call
RequestLogger::logApiCall(
    $userId,
    '/api/accommodations/1/students',
    'GET',
    200,
    ['count' => 15]
);

// Get page view statistics
$stats = RequestLogger::getPageViewStats('/public/dashboard.php', 'today');
// Returns: ['views' => 42, 'unique_users' => 8, 'unique_ips' => 5]

// Get most viewed pages
$pages = RequestLogger::getMostViewedPages(10, 'week');

// Get user activity summary
$summary = RequestLogger::getUserActivitySummary($userId, 'month');
// Returns: ['total_actions' => 156, 'action_types' => 8, 'active_days' => 12, 'last_activity' => '2024-01-20 14:32:00']
```

### ActivityDashboardWidget Utility

Renders dashboard components showing activity data.

**Display Recent Activity Log:**

```php
<?php
require_once 'includes/config.php';
require_once 'includes/page-template.php';
?>

<div class="card">
    <div class="card-header">
        <h5>Recent Activity</h5>
    </div>
    <div class="card-body">
        <?= ActivityDashboardWidget::renderRecentActivityLog(15) ?>
    </div>
</div>
```

**Display Activity Summary:**

```php
<?= ActivityDashboardWidget::renderActivitySummary($_SESSION['user_id'], 'today') ?>
```

**Display Page View Statistics:**

```php
<?= ActivityDashboardWidget::renderPageViewStats(10, 'week') ?>
```

**Display Accommodation Activity:**

```php
<?= ActivityDashboardWidget::renderAccommodationActivityLog($accommodationId, 20) ?>
```

**Display User Timeline:**

```php
<?= ActivityDashboardWidget::renderUserActivityTimeline($userId, 25) ?>
```

### ActivityLogger Service (Extended)

Logs business logic events with proper categorization.

**Usage:**

```php
// Log device registration
ActivityLogger::logDeviceAction(
    $_SESSION['user_id'],
    'register',
    $deviceId,
    ['mac_address' => $mac, 'device_type' => 'Laptop']
);

// Log student enrollment
ActivityLogger::logStudentAction(
    $_SESSION['user_id'],
    $studentId,
    'enrolled',
    ['accommodation_id' => 1, 'room' => '201']
);

// Log permission changes
ActivityLogger::logPermissionChange(
    $_SESSION['user_id'],
    $managerId,
    'assign_manager',
    ['accommodation_id' => 1]
);
```

### DatabaseErrorLogger Service

Logs all errors to database for query and analysis.

**Initialization:**

```php
require_once 'includes/config.php';

// Initialize error logging to database
DatabaseErrorLogger::init($conn);
DatabaseErrorLogger::setEnabled(true);
```

**Logging Errors:**

```php
// Log database error
DatabaseErrorLogger::log(
    'Database',
    'Query failed: User creation',
    DatabaseErrorLogger::SEVERITY_ERROR,
    ['query' => 'INSERT INTO users...', 'user_id' => 123]
);

// Log HTTP error
DatabaseErrorLogger::logHttpError(500, 'Internal Server Error', ['endpoint' => '/api/users']);

// Get error statistics
$stats = DatabaseErrorLogger::getErrorStats('today');
// Returns: ['total_errors' => 12, 'critical_count' => 2, 'error_count' => 8, ...]

// Get unresolved errors
$unresolved = DatabaseErrorLogger::getRecentErrors(10);

// Mark error as resolved
DatabaseErrorLogger::markResolved($errorId, $adminUserId, 'Fixed password hashing bug');
```

## Database Tables

### activity_logs

Stores all activity events.

```sql
CREATE TABLE activity_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255),
    entity_type VARCHAR(50),      -- 'device', 'student', 'code', etc.
    entity_id INT,
    description TEXT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP
);
```

### error_logs

Stores error events for tracking and resolution.

```sql
CREATE TABLE error_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    error_type VARCHAR(100),
    severity ENUM('info', 'warning', 'error', 'critical'),
    message TEXT,
    context JSON,
    stack_trace LONGTEXT,
    user_id INT,
    ip_address VARCHAR(45),
    url VARCHAR(512),
    method VARCHAR(10),
    resolved BOOLEAN,
    resolved_at TIMESTAMP,
    resolved_by INT,
    created_at TIMESTAMP
);
```

### \_migrations

Tracks applied database migrations.

```sql
CREATE TABLE _migrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    migration_name VARCHAR(255) UNIQUE,
    applied_at TIMESTAMP,
    batch INT,
    status ENUM('success', 'failed', 'pending', 'skipped')
);
```

## Usage Patterns

### Pattern 1: Track Device Registration

```php
<?php
// In device registration handler
require_once 'includes/config.php';
require_once 'includes/page-template.php';

// Validate device
if (!DeviceManagementService::macAddressExists($conn, $_POST['mac'])) {
    // Register device
    $device = DeviceManagementService::registerDevice(
        $conn,
        $_SESSION['user_id'],
        $_POST['device_type'],
        $_POST['mac']
    );

    // Log the action
    ActivityLogger::logDeviceAction(
        $_SESSION['user_id'],
        'registered',
        $device['id'],
        ['device_type' => $_POST['device_type'], 'mac' => $_POST['mac']]
    );

    // Log to request logger for analytics
    RequestLogger::logApiCall(
        $_SESSION['user_id'],
        '/api/devices/register',
        'POST',
        201,
        ['device_id' => $device['id']]
    );

    Response::success('Device registered', $device);
}
?>
```

### Pattern 2: Dashboard Activity Widget

```php
<?php
require_once 'includes/config.php';
require_once 'includes/page-template.php';

// Initialize request logging
RequestLogger::init();
RequestLogger::logPageView($_SESSION['user_id'], 'dashboard.php');
?>

<div class="container">
    <div class="row">
        <div class="col-md-8">
            <!-- Activity Summary Card -->
            <?= ActivityDashboardWidget::renderActivitySummary($_SESSION['user_id'], 'today') ?>

            <!-- Recent Activity Log -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?= ActivityDashboardWidget::renderRecentActivityLog(20) ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Page View Stats -->
            <div class="card">
                <div class="card-header">
                    <h5>Popular Pages (This Week)</h5>
                </div>
                <div class="card-body">
                    <?= ActivityDashboardWidget::renderPageViewStats(5, 'week') ?>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Pattern 3: Error Tracking & Resolution

```php
<?php
require_once 'includes/config.php';

// Initialize error logging
DatabaseErrorLogger::init($conn);

try {
    $result = UserService::createUser($conn, $userData);
    if (!$result) {
        throw new Exception('User creation failed');
    }
} catch (Exception $e) {
    // Log error to database
    $errorId = DatabaseErrorLogger::log(
        'User Creation',
        $e->getMessage(),
        DatabaseErrorLogger::SEVERITY_ERROR,
        ['user_data' => $userData]
    );

    // Admin can later query and resolve
    error_log("Error logged with ID: $errorId");
    Response::serverError('Failed to create user');
}
?>
```

### Pattern 4: Admin Error Management

```php
<?php
require_once 'includes/config.php';
require_once 'includes/page-template.php';

PermissionHelper::requireRole(ROLE_ADMIN);

// Get error statistics
$stats = DatabaseErrorLogger::getErrorStats('today');

// Get unresolved errors
$unresolved = DatabaseErrorLogger::getRecentErrors(20);

// View errors by type
if ($_GET['type'] ?? false) {
    $errors = DatabaseErrorLogger::getErrorsByType($_GET['type'], 30);

    // Render errors with resolution interface
    foreach ($errors as $error) {
        echo '<div class="alert alert-' . $error['severity'] . '">';
        echo htmlspecialchars($error['message']);
        if (!$error['resolved']) {
            echo '<form method="POST" action="/admin/resolve-error.php">';
            echo '<textarea name="notes" placeholder="Resolution notes"></textarea>';
            echo '<button type="submit" value="' . $error['id'] . '">Mark Resolved</button>';
            echo '</form>';
        }
        echo '</div>';
    }
}
?>
```

## Migration

Apply the logging infrastructure migration:

```bash
# Run migration manually
mysql -u root -p gwn_wifi_system < db/migrations/2024_01_20_add_logging_infrastructure.sql

# Or use MigrationService in PHP
<?php
require_once 'includes/config.php';

MigrationService::init($conn);

if (!MigrationService::isMigrationApplied('2024_01_20_add_logging_infrastructure')) {
    $sql = file_get_contents('db/migrations/2024_01_20_add_logging_infrastructure.sql');
    $conn->multi_query($sql);

    while ($conn->more_results()) {
        $conn->next_result();
    }

    MigrationService::recordMigration('2024_01_20_add_logging_infrastructure');
}
?>
```

## Best Practices

1. **Always log business actions**: Device registration, code usage, student enrollment, etc.
2. **Use appropriate log levels**: info, warning, error, critical based on severity
3. **Include context**: Always provide enough detail in `details` JSON for debugging
4. **Clean up old logs**: Call `ActivityLogger::clearOldLogs()` or `DatabaseErrorLogger::cleanupOldErrors()` periodically
5. **Respect privacy**: Never log passwords, tokens, or sensitive data in details
6. **Monitor unresolved errors**: Review `DatabaseErrorLogger::getUnresolvedErrorsCount()` regularly

## Monitoring

### Page Performance

```php
$page_stats = RequestLogger::getPageViewStats('/public/codes.php', 'week');
echo "This week: {$page_stats['views']} views, {$page_stats['unique_users']} unique users";
```

### Error Tracking

```php
$critical_errors = DatabaseErrorLogger::getErrorsByType('Database');
if (count($critical_errors) > 5) {
    // Alert admin
}
```

### User Activity

```php
$activity = RequestLogger::getUserActivitySummary($userId, 'month');
echo "User was active on {$activity['active_days']} days this month";
```

## Related Documentation

- [CODE-INDEX.md](CODE-INDEX.md) - Complete service reference
- [Security Documentation](docs/security.md) - Logging security considerations
- [Database Schema](docs/database.md) - Table relationships

---

**EPIC 4 Complete** - Activity logging infrastructure fully implemented and ready for use across all pages.
