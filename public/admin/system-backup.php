<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

$error = '';
$success = '';

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_backup') {
        // Create backup directory if it doesn't exist
        $backup_dir = __DIR__ . '/../../../backups';
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_type = $_POST['backup_type'] ?? 'db';
        $timestamp = date('Y-m-d_H-i-s');
        
        // Database backup
        if ($backup_type === 'db' || $backup_type === 'full') {
            $db_backup_file = "$backup_dir/db_backup_$timestamp.sql";
            
            $command = sprintf(
                'mysqldump --user=%s --password=%s %s > %s',
                escapeshellarg(DB_USERNAME),
                escapeshellarg(DB_PASSWORD),
                escapeshellarg(DB_NAME),
                escapeshellarg($db_backup_file)
            );
            
            exec($command, $output, $return_var);
            
            if ($return_var !== 0) {
                $error = 'Database backup failed. Check PHP error log for details.';
                error_log("Database backup command failed: $command");
            } else {
                $success = 'Database backup created successfully: ' . basename($db_backup_file);
            }
        }
        
        // Files backup
        if ($backup_type === 'files' || $backup_type === 'full') {
            $exclude_dirs = ['backups', 'node_modules', 'vendor'];
            $exclude_params = '';
            
            foreach ($exclude_dirs as $dir) {
                $exclude_params .= " --exclude='$dir'";
            }
            
            $source_dir = realpath(__DIR__ . '/../../../');
            $files_backup_file = "$backup_dir/files_backup_$timestamp.tar.gz";
            
            $command = "tar -czf $files_backup_file $exclude_params -C $source_dir .";
            
            exec($command, $output, $return_var);
            
            if ($return_var !== 0) {
                $error .= ($error ? ' ' : '') . 'Files backup failed. Check PHP error log for details.';
                error_log("Files backup command failed: $command");
            } else {
                $success .= ($success ? ' ' : '') . 'Files backup created successfully: ' . basename($files_backup_file);
            }
        }
    } elseif ($action === 'delete_backup') {
        // Delete a backup file
        $filename = $_POST['filename'] ?? '';
        
        if (!empty($filename) && preg_match('/^[a-zA-Z0-9_\.-]+$/', $filename)) {
            $backup_dir = __DIR__ . '/../../../backups';
            $file_path = $backup_dir . '/' . $filename;
            
            if (file_exists($file_path) && is_file($file_path)) {
                if (unlink($file_path)) {
                    $success = 'Backup file deleted successfully: ' . $filename;
                } else {
                    $error = 'Failed to delete backup file. Check file permissions.';
                }
            } else {
                $error = 'Backup file not found.';
            }
        } else {
            $error = 'Invalid backup filename.';
        }
    }
}

// Get list of backup files
$backups = [];
$backup_dir = __DIR__ . '/../../../backups';

if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $file_path = $backup_dir . '/' . $file;
            
            if (is_file($file_path)) {
                $backups[] = [
                    'name' => $file,
                    'size' => filesize($file_path),
                    'date' => filemtime($file_path),
                    'type' => (strpos($file, 'db_backup') !== false) ? 'database' : 'files'
                ];
            }
        }
    }
    
    // Sort by date, newest first
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Set page title
$pageTitle = "System Backup";
$activePage = "system";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
?>

<div class="container mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Create Backup</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="create_backup">
                        
                        <div class="mb-3">
                            <label class="form-label">Backup Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="backup_type" id="db_backup" value="db" checked>
                                <label class="form-check-label" for="db_backup">
                                    Database Only
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="backup_type" id="files_backup" value="files">
                                <label class="form-check-label" for="files_backup">
                                    Files Only
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="backup_type" id="full_backup" value="full">
                                <label class="form-check-label" for="full_backup">
                                    Full Backup (Database + Files)
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cloud-arrow-up me-2"></i> Create Backup
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Backup Status</h5>
                </div>
                <div class="card-body">
                    <p><strong>Last backup:</strong> 
                    <?php 
                    if (!empty($backups)) {
                        echo date('M j, Y H:i:s', $backups[0]['date']);
                    } else {
                        echo 'Never';
                    }
                    ?>
                    </p>
                    
                    <p><strong>Total backups:</strong> <?= count($backups) ?></p>
                    
                    <p><strong>Storage used:</strong> 
                    <?php
                    $total_size = 0;
                    foreach ($backups as $backup) {
                        $total_size += $backup['size'];
                    }
                    echo formatFileSize($total_size);
                    ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Backup Files</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($backups) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Filename</th>
                                        <th>Type</th>
                                        <th>Size</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($backup['name']) ?></td>
                                            <td>
                                                <?php if ($backup['type'] === 'database'): ?>
                                                    <span class="badge bg-primary">Database</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Files</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatFileSize($backup['size']) ?></td>
                                            <td><?= date('Y-m-d H:i:s', $backup['date']) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="download-backup.php?file=<?= urlencode($backup['name']) ?>" class="btn btn-outline-primary">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= md5($backup['name']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?= md5($backup['name']) ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete this backup file?</p>
                                                                <p><strong><?= htmlspecialchars($backup['name']) ?></strong></p>
                                                                <p class="text-danger">This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="post">
                                                                    <input type="hidden" name="action" value="delete_backup">
                                                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($backup['name']) ?>">
                                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-archive text-muted" style="font-size: 3rem;"></i>
                            <p class="mt-3">No backup files found.</p>
                            <p class="text-muted">Use the form on the left to create your first backup.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Backup Information</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i> About Backups</h5>
                        <p>Regular backups are essential to prevent data loss. Consider implementing the following backup strategy:</p>
                        <ul>
                            <li>Create daily database backups</li>
                            <li>Create weekly full backups</li>
                            <li>Store backups in multiple locations</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i> Important</h5>
                        <p>Backup files created here are stored within the web application directory. For better security:</p>
                        <ol>
                            <li>Download backup files and store them securely</li>
                            <li>Delete backup files from the server after downloading</li>
                            <li>Consider setting up automated offsite backups</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to format file sizes
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Include footer
require_once '../../includes/components/footer.php';
?>
