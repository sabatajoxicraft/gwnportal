<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

$success = '';
$error = '';

// Process settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    // Handle different setting forms based on section
    $section = $_POST['section'] ?? '';
    
    if ($section === 'app') {
        // Update application settings
        $app_name = trim($_POST['app_name'] ?? APP_NAME);
        $code_expiry_days = (int)($_POST['code_expiry_days'] ?? CODE_EXPIRY_DAYS);
        
        if (empty($app_name)) {
            $error = 'Application name cannot be empty';
        } else {
            // Update .env file
            $env_file = __DIR__ . '/../../../.env';
            if (file_exists($env_file)) {
                $env_content = file_get_contents($env_file);
                
                // Replace APP_NAME
                $env_content = preg_replace('/APP_NAME=.*/', 'APP_NAME=' . $app_name, $env_content);
                
                // Replace CODE_EXPIRY_DAYS
                $env_content = preg_replace('/CODE_EXPIRY_DAYS=.*/', 'CODE_EXPIRY_DAYS=' . $code_expiry_days, $env_content);
                
                if (file_put_contents($env_file, $env_content)) {
                    logActivity($conn ?? getDbConnection(), $_SESSION['user_id'], 'update_settings', "Updated app settings: APP_NAME={$app_name}, CODE_EXPIRY_DAYS={$code_expiry_days}", $_SERVER['REMOTE_ADDR']);
                    $success = 'Application settings updated successfully. Changes will take effect after page reload.';
                } else {
                    $error = 'Failed to update application settings. Please check file permissions.';
                }
            } else {
                $error = '.env file not found';
            }
        }
    } elseif ($section === 'database') {
        // Database backup
        $backup_filename = 'wifi_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = __DIR__ . '/../../../backups/';
        
        // Create backup directory if it doesn't exist
        if (!file_exists($backup_path)) {
            mkdir($backup_path, 0755, true);
        }
        
        // Command to backup database
        $command = sprintf(
            'mysqldump --user=%s --password=%s %s > %s',
            escapeshellarg(DB_USERNAME),
            escapeshellarg(DB_PASSWORD),
            escapeshellarg(DB_NAME),
            escapeshellarg($backup_path . $backup_filename)
        );
        
        // Execute command
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            logActivity(getDbConnection(), $_SESSION['user_id'], 'database_backup', "Created DB backup: {$backup_filename}", $_SERVER['REMOTE_ADDR']);
            $success = 'Database backup created successfully: ' . $backup_filename;
        } else {
            $error = 'Failed to create database backup. Error code: ' . $return_var;
        }
    } elseif ($section === 'security') {
        // Update admin password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters long';
        } else {
            $conn = getDbConnection();
            $admin_id = $_SESSION['user_id'];
            
            // Verify current password
            $stmt = safeQueryPrepare($conn, "SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!password_verify($current_password, $user['password'])) {
                $error = 'Current password is incorrect';
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = safeQueryPrepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($stmt->execute()) {
                    logActivity($conn, $_SESSION['user_id'], 'admin_password_change', "Admin changed their password", $_SERVER['REMOTE_ADDR']);
                    $success = 'Password updated successfully';
                } else {
                    $error = 'Failed to update password: ' . $conn->error;
                }
            }
        }
    }
}

// Fetch current settings
$app_name = APP_NAME;
$code_expiry_days = CODE_EXPIRY_DAYS;

// Get system info
$php_version = phpversion();
$mysql_version = getDbConnection()->get_server_info();
$server_info = $_SERVER['SERVER_SOFTWARE'];
$document_root = $_SERVER['DOCUMENT_ROOT'];

// Set page title
$pageTitle = "System Settings";
$activePage = "settings";

// Include header
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>System Settings</h2>
    </div>
    
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="list-group">
                <a href="#app-settings" class="list-group-item list-group-item-action active" data-bs-toggle="list">Application Settings</a>
                <a href="#database-settings" class="list-group-item list-group-item-action" data-bs-toggle="list">Database Management</a>
                <a href="#security-settings" class="list-group-item list-group-item-action" data-bs-toggle="list">Security Settings</a>
                <a href="#system-info" class="list-group-item list-group-item-action" data-bs-toggle="list">System Information</a>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="tab-content">
                <!-- Application Settings -->
                <div class="tab-pane fade show active" id="app-settings">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Application Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="section" value="app">
                                
                                <div class="mb-3">
                                    <label for="app_name" class="form-label">Application Name</label>
                                    <input type="text" class="form-control" id="app_name" name="app_name" value="<?= htmlspecialchars($app_name) ?>" required>
                                    <div class="form-text">This name appears in the navbar and throughout the application.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="code_expiry_days" class="form-label">Code Expiry Days</label>
                                    <input type="number" class="form-control" id="code_expiry_days" name="code_expiry_days" value="<?= $code_expiry_days ?>" min="1" max="365" required>
                                    <div class="form-text">Number of days before onboarding codes expire.</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Database Management -->
                <div class="tab-pane fade" id="database-settings">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Database Management</h5>
                        </div>
                        <div class="card-body">
                            <h6>Backup Database</h6>
                            <p>Create a backup of the current database. This may take a while for large databases.</p>
                            
                            <form method="post" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="section" value="database">
                                <button type="submit" class="btn btn-primary">Create Backup</button>
                            </form>
                            
                            <hr>
                            
                            <h6>Recent Backups</h6>
                            <?php
                            $backup_path = __DIR__ . '/../../../backups/';
                            $backups = [];
                            
                            if (file_exists($backup_path)) {
                                $files = scandir($backup_path);
                                foreach ($files as $file) {
                                    if ($file !== '.' && $file !== '..' && strpos($file, '.sql') !== false) {
                                        $backups[] = [
                                            'name' => $file,
                                            'size' => filesize($backup_path . $file),
                                            'date' => filemtime($backup_path . $file)
                                        ];
                                    }
                                }
                                
                                // Sort by date, newest first
                                usort($backups, function($a, $b) {
                                    return $b['date'] - $a['date'];
                                });
                                
                                // Show only the most recent backups
                                $backups = array_slice($backups, 0, 5);
                            }
                            
                            if (count($backups) > 0):
                            ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td><?= $backup['name'] ?></td>
                                                <td><?= formatFileSize($backup['size']) ?></td>
                                                <td><?= date('Y-m-d H:i:s', $backup['date']) ?></td>
                                                <td>
                                                    <a href="<?= BASE_URL ?>/admin/download-backup.php?file=<?= $backup['name'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-download"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                                <p class="text-muted">No backups found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings -->
                <div class="tab-pane fade" id="security-settings">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <h6>Change Admin Password</h6>
                            <form method="post" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="section" value="security">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Password</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="tab-pane fade" id="system-info">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">System Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <th>PHP Version</th>
                                            <td><?= $php_version ?></td>
                                        </tr>
                                        <tr>
                                            <th>MySQL Version</th>
                                            <td><?= $mysql_version ?></td>
                                        </tr>
                                        <tr>
                                            <th>Web Server</th>
                                            <td><?= $server_info ?></td>
                                        </tr>
                                        <tr>
                                            <th>Document Root</th>
                                            <td><?= $document_root ?></td>
                                        </tr>
                                        <tr>
                                            <th>Server Time</th>
                                            <td><?= date('Y-m-d H:i:s') ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes > 1024) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

require_once '../../includes/components/footer.php';
?>
