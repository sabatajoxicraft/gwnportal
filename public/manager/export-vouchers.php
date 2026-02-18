<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireManagerLogin();

$accommodation_id = $_SESSION['accommodation_id'] ?? 0;
$conn = getDbConnection();

// Get filters from query string (same as voucher-history.php)
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$student_search = $_GET['student_search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$month_filter = $_GET['month_filter'] ?? '';

// Build query
$where_clauses = ["s.accommodation_id = ?"];
$params = [$accommodation_id];
$param_types = "i";

if (!empty($date_from)) {
    $where_clauses[] = "vl.sent_at >= ?";
    $params[] = $date_from . ' 00:00:00';
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_clauses[] = "vl.sent_at <= ?";
    $params[] = $date_to . ' 23:59:59';
    $param_types .= "s";
}

if (!empty($student_search)) {
    $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$student_search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "vl.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($month_filter)) {
    $where_clauses[] = "vl.voucher_month = ?";
    $params[] = $month_filter;
    $param_types .= "s";
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch all vouchers matching filters
$sql = "SELECT vl.id, vl.voucher_code, vl.voucher_month, vl.sent_via, vl.status, vl.sent_at, vl.is_active,
               u.first_name, u.last_name, u.email,
               CONCAT(u.first_name, ' ', u.last_name) as student_name,
               a.name as accommodation_name
        FROM voucher_logs vl
        JOIN users u ON vl.user_id = u.id
        JOIN students s ON u.id = s.user_id
        JOIN accommodations a ON s.accommodation_id = a.id
        WHERE $where_sql
        ORDER BY vl.sent_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$vouchers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate CSV
$filename = 'vouchers_export_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
$headers = [
    'Student Name',
    'Email',
    'Voucher Code',
    'Month',
    'Sent Via',
    'Status',
    'Sent Date',
    'Accommodation',
    'Active'
];
fputcsv($output, $headers);

// Write data rows
foreach ($vouchers as $voucher) {
    $is_revoked = isset($voucher['is_active']) && $voucher['is_active'] == 0;
    $status = $is_revoked ? 'Revoked' : ucfirst($voucher['status']);
    
    $row = [
        $voucher['student_name'],
        $voucher['email'],
        $voucher['voucher_code'],
        $voucher['voucher_month'],
        $voucher['sent_via'],
        $status,
        $voucher['sent_at'] ? date('Y-m-d H:i:s', strtotime($voucher['sent_at'])) : 'Not sent',
        $voucher['accommodation_name'],
        $is_revoked ? 'No' : 'Yes'
    ];
    
    fputcsv($output, $row);
}

fclose($output);
exit;
