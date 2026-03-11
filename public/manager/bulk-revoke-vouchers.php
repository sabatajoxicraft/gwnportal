<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';

requireManagerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Invalid request method.', 'danger');
}

requireCsrfToken();

$voucher_ids_raw = $_POST['voucher_ids'] ?? '';
$revoke_reason = trim($_POST['revoke_reason'] ?? '');
$user_id = $_SESSION['user_id'] ?? 0;
$accommodation_id = $_SESSION['accommodation_id'] ?? 0;

if (empty($voucher_ids_raw) || empty($revoke_reason)) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Voucher IDs and reason are required.', 'danger');
}

// Parse and validate voucher IDs
$voucher_ids = array_filter(array_map('intval', explode(',', $voucher_ids_raw)));
if (empty($voucher_ids)) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'No valid voucher IDs provided.', 'danger');
}

// Limit batch size for safety
if (count($voucher_ids) > 50) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Cannot revoke more than 50 vouchers at once.', 'danger');
}

$conn = getDbConnection();
$revoked = 0;
$failed = 0;

foreach ($voucher_ids as $vid) {
    // Verify each voucher belongs to this manager's accommodation and is active
    $sql = "SELECT vl.id, vl.voucher_code, vl.gwn_voucher_id, s.accommodation_id 
            FROM voucher_logs vl
            JOIN users u ON vl.user_id = u.id
            JOIN students s ON u.id = s.user_id
            WHERE vl.id = ? AND s.accommodation_id = ? AND vl.status = 'sent'
              AND (vl.is_active = 1 OR vl.is_active IS NULL)";
    $stmt = safeQueryPrepare($conn, $sql);
    $stmt->bind_param("ii", $vid, $accommodation_id);
    $stmt->execute();
    $voucher = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$voucher) {
        $failed++;
        continue;
    }

    // Use the existing revokeVoucher function
    $success = revokeVoucher($vid, $revoke_reason, $user_id);
    if ($success) {
        // Also delete from GWN Cloud if gwn_voucher_id exists
        if (!empty($voucher['gwn_voucher_id'])) {
            $networkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
            if (!empty($networkId)) {
                gwnDeleteVoucher((int)$voucher['gwn_voucher_id'], $networkId);
            }
        }
        logActivity($conn, $user_id, 'voucher_bulk_revoked', 'Bulk revoked voucher: ' . $voucher['voucher_code'] . ' - Reason: ' . $revoke_reason);
        $revoked++;
    } else {
        $failed++;
    }
}

$message = "Bulk revoke complete: {$revoked} revoked";
if ($failed > 0) {
    $message .= ", {$failed} failed";
}

redirect(BASE_URL . '/manager/voucher-history.php', $message, $revoked > 0 ? 'success' : 'danger');
