<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';

// Require manager login
requireManagerLogin();

// Verify CSRF
if (!csrfValidate()) {
    redirect(BASE_URL . '/students.php', 'Invalid request', 'danger');
}

$conn = getDbConnection();
$action = $_POST['action'] ?? '';
$device_id = (int)($_POST['device_id'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? 0);
$student_id = (int)($_POST['student_id'] ?? 0);
$mac_address = $_POST['mac_address'] ?? '';
$accommodation_id = $_SESSION['accommodation_id'] ?? $_SESSION['manager_id'] ?? 0;

if ($device_id <= 0 || $user_id <= 0) {
    redirect(BASE_URL . '/students.php', 'Invalid parameters', 'danger');
}

// Verify student belongs to this manager's accommodation
$verify_stmt = safeQueryPrepare($conn, "SELECT id FROM students WHERE id = ? AND user_id = ? AND accommodation_id = ?");
$verify_stmt->bind_param("iii", $student_id, $user_id, $accommodation_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    redirect(BASE_URL . '/students.php', 'Access denied: Student does not belong to your accommodation', 'danger');
}

$redirectUrl = BASE_URL . '/student-details.php?id=' . $student_id;
$currentUserId = $_SESSION['user_id'] ?? 0;
$clientService = new ClientService();

switch ($action) {
    case 'block':
        $reason = $_POST['reason'] ?? '';
        
        // Update local database
        $stmt = safeQueryPrepare($conn, "UPDATE user_devices 
                                    SET is_blocked = 1, 
                                        blocked_at = NOW(), 
                                        blocked_by = ?,
                                        blocked_reason = ?
                                    WHERE id = ? AND user_id = ?");
        $stmt->bind_param("isii", $currentUserId, $reason, $device_id, $user_id);
        
        if ($stmt->execute()) {
            // Log to device_block_log
            $log_stmt = safeQueryPrepare($conn, "INSERT INTO device_block_log 
                                            (device_id, user_id, mac_address, action, reason, performed_by) 
                                            VALUES (?, ?, ?, 'block', ?, ?)");
            $log_stmt->bind_param("iissi", $device_id, $user_id, $mac_address, $reason, $currentUserId);
            $log_stmt->execute();
            
            // Block on GWN Cloud
            $gwnResult = $clientService->setClientBlockStatus($mac_address, null, 1);
            
            logActivity($conn, $currentUserId, 'device_blocked', "Manager blocked device $mac_address for user $user_id. Reason: $reason", $_SERVER['REMOTE_ADDR']);
            
            redirect($redirectUrl, 'Device blocked successfully', 'success');
        } else {
            redirect($redirectUrl, 'Failed to block device', 'danger');
        }
        break;
        
    case 'unblock':
        // Update local database
        $stmt = safeQueryPrepare($conn, "UPDATE user_devices 
                                    SET is_blocked = 0, 
                                        unblocked_at = NOW(), 
                                        unblocked_by = ?,
                                        blocked_reason = NULL
                                    WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $currentUserId, $device_id, $user_id);
        
        if ($stmt->execute()) {
            // Log to device_block_log
            $log_stmt = safeQueryPrepare($conn, "INSERT INTO device_block_log 
                                            (device_id, user_id, mac_address, action, performed_by) 
                                            VALUES (?, ?, ?, 'unblock', ?)");
            $log_stmt->bind_param("iisi", $device_id, $user_id, $mac_address, $currentUserId);
            $log_stmt->execute();
            
            // Unblock on GWN Cloud
            $gwnResult = $clientService->setClientBlockStatus($mac_address, null, 0);
            
            logActivity($conn, $currentUserId, 'device_unblocked', "Manager unblocked device $mac_address for user $user_id", $_SERVER['REMOTE_ADDR']);
            
            redirect($redirectUrl, 'Device unblocked successfully', 'success');
        } else {
            redirect($redirectUrl, 'Failed to unblock device', 'danger');
        }
        break;
        
    case 'rename':
        $device_name = $_POST['device_name'] ?? '';
        
        $stmt = safeQueryPrepare($conn, "UPDATE user_devices 
                                    SET device_name = ?
                                    WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $device_name, $device_id, $user_id);
        
        if ($stmt->execute()) {
            // Update name on GWN Cloud
            $clientService->editClient($mac_address, $device_name);
            
            logActivity($conn, $currentUserId, 'device_renamed', "Manager renamed device $mac_address to '$device_name' for user $user_id", $_SERVER['REMOTE_ADDR']);
            
            redirect($redirectUrl, 'Device renamed successfully', 'success');
        } else {
            redirect($redirectUrl, 'Failed to rename device', 'danger');
        }
        break;
        
    default:
        redirect($redirectUrl, 'Invalid action', 'danger');
}
