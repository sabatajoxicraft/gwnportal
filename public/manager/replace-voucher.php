<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';
require_once '../../includes/services/VoucherService.php';

requireManagerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Invalid request method.', 'danger');
}

requireCsrfToken();

$voucher_log_id = (int)($_POST['voucher_log_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;
$accommodation_id = $_SESSION['accommodation_id'] ?? 0;

if (empty($voucher_log_id)) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Voucher ID is required.', 'danger');
}

$conn = getDbConnection();

// Verify the voucher belongs to this manager's accommodation
$sql = "SELECT vl.*, s.accommodation_id, s.id as student_id
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

if (isset($voucher['is_active']) && !$voucher['is_active']) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Cannot replace an already revoked voucher.', 'warning');
}

// Perform the replacement
$voucherService = new VoucherService();
$result = $voucherService->replaceVoucher($voucher_log_id, $user_id);

if ($result) {
    logActivity($conn, $user_id, 'voucher_replaced', 
        'Replaced voucher ' . $voucher['voucher_code'] . ' with ' . ($result['voucher_code'] ?? 'new voucher') . 
        ' (fixed device limit to ' . GWN_ALLOWED_DEVICES . ')');
    
    $returnUrl = isset($_POST['return_to_student']) 
        ? BASE_URL . '/student-details.php?id=' . $voucher['student_id']
        : BASE_URL . '/manager/voucher-history.php';
    
    redirect($returnUrl, 
        'Voucher replaced successfully. New code: ' . ($result['voucher_code'] ?? 'sent') . ' with ' . GWN_ALLOWED_DEVICES . ' device limit.', 
        'success');
} else {
    redirect(BASE_URL . '/manager/voucher-history.php', 
        'Failed to replace voucher. The old voucher may have been revoked but the new one could not be created. Please try sending a new voucher manually.', 
        'danger');
}
