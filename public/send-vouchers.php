<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/python_interface.php';

// Require manager login
requireManagerLogin();

// DEPRECATED: Bulk voucher sending is no longer supported.
// Students now self-request 1 voucher/month. Use individual send-voucher.php instead.
$_SESSION['flash'] = [
    'type' => 'warning',
    'message' => '<strong>Notice:</strong> Bulk voucher sending has been deprecated. Students now self-request their monthly vouchers. You can still <a href="' . BASE_URL . '/send-voucher.php" class="alert-link">send individual vouchers</a> or view <a href="' . BASE_URL . '/manager/voucher-history.php" class="alert-link">voucher history</a>.'
];
header('Location: ' . BASE_URL . '/send-voucher.php');
exit;
