<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireManagerLogin();

// DEPRECATED: Bulk voucher selection is no longer supported.
// Students now self-request 1 voucher/month. Redirecting to voucher history.
$_SESSION['flash'] = [
    'type' => 'warning',
    'message' => '<strong>Notice:</strong> Bulk voucher selection has been deprecated. Students now self-request their monthly vouchers. You can still <a href="' . BASE_URL . '/send-voucher.php" class="alert-link">send individual vouchers</a> or view history below.'
];
header('Location: ' . BASE_URL . '/manager/voucher-history.php');
exit;
