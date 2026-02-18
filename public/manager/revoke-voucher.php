<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';

requireManagerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Invalid request method.', 'danger');
}

requireCsrfToken();

$voucher_log_id = $_POST['voucher_log_id'] ?? ($_POST['voucher_id'] ?? 0);
$revoke_reason = trim($_POST['revoke_reason'] ?? '');
$user_id = $_SESSION['user_id'] ?? 0;
$accommodation_id = $_SESSION['accommodation_id'] ?? 0;

// Validate input
if (empty($voucher_log_id) || empty($revoke_reason)) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Voucher ID and reason are required.', 'danger');
}

$conn = getDbConnection();

// Verify voucher belongs to manager's accommodation and can be revoked
$sql = "SELECT vl.*, s.accommodation_id 
        FROM voucher_logs vl
        JOIN users u ON vl.user_id = u.id
        JOIN students s ON u.id = s.user_id
        WHERE vl.id = ? AND s.accommodation_id = ?";

$stmt = safeQueryPrepare($conn, $sql);
$stmt->bind_param("ii", $voucher_log_id, $accommodation_id);
$stmt->execute();
$voucher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$voucher) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Voucher not found or access denied.', 'danger');
}

// Check if already revoked
if (isset($voucher['is_active']) && $voucher['is_active'] == 0) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'This voucher has already been revoked.', 'warning');
}

// Check if voucher was sent
if ($voucher['status'] !== 'sent') {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Only sent vouchers can be revoked.', 'warning');
}

// Revoke the voucher (deletes on GWN Cloud and marks inactive)
$success = revokeStudentVoucher($voucher_log_id, $user_id, $revoke_reason);

if ($success) {
    // Log activity
    logActivity($conn, $user_id, 'voucher_revoked', 'Revoked voucher: ' . $voucher['voucher_code'] . ' - Reason: ' . $revoke_reason);
    
    redirect(BASE_URL . '/manager/voucher-details.php?id=' . $voucher_log_id, 'Voucher revoked successfully.', 'success');
} else {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Failed to revoke voucher. Please try again.', 'danger');
}
