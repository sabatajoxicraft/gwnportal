<?php
/**
 * ReportService
 *
 * Centralised query methods for all admin report types.
 * Covers the 5 M2-T8 report types plus the 3 legacy types migrated from reports.php.
 * Used by public/admin/reports.php (display) and public/admin/export-reports.php (CSV).
 */
class ReportService {

    // =========================================================================
    // M2-T8 report methods
    // =========================================================================

    /**
     * Monthly voucher usage grouped by month and accommodation.
     */
    public static function getMonthlyVoucherUsage(
        mysqli $conn,
        string $start_date,
        string $end_date,
        string $accommodation_id = 'all'
    ): array {
        $start = $start_date . ' 00:00:00';
        $end   = $end_date   . ' 23:59:59';
        $where = $accommodation_id !== 'all' ? "AND s.accommodation_id = ?" : "";

        $sql = "SELECT
                    DATE_FORMAT(vl.sent_at, '%Y-%m') AS voucher_month,
                    a.name AS accommodation_name,
                    COUNT(*) AS total_sent,
                    SUM(CASE WHEN vl.status IN ('sent','used') AND IFNULL(vl.is_active, 1) = 1 THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN vl.status = 'used' THEN 1 ELSE 0 END) AS used_count,
                    SUM(CASE WHEN IFNULL(vl.is_active, 1) = 0 THEN 1 ELSE 0 END) AS revoked_count
                FROM voucher_logs vl
                JOIN users u ON vl.user_id = u.id
                JOIN students s ON s.user_id = u.id
                JOIN accommodations a ON a.id = s.accommodation_id
                WHERE vl.sent_at BETWEEN ? AND ? $where
                GROUP BY DATE_FORMAT(vl.sent_at, '%Y-%m'), a.id
                ORDER BY voucher_month DESC, a.name";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) return [];
        if ($accommodation_id !== 'all') {
            $stmt->bind_param("ssi", $start, $end, $accommodation_id);
        } else {
            $stmt->bind_param("ss", $start, $end);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Student enrollment counts grouped by accommodation.
     */
    public static function getStudentEnrollmentByAccommodation(
        mysqli $conn,
        string $start_date,
        string $end_date,
        string $accommodation_id = 'all'
    ): array {
        $start = $start_date . ' 00:00:00';
        $end   = $end_date   . ' 23:59:59';
        $where = $accommodation_id !== 'all' ? "AND a.id = ?" : "";

        $sql = "SELECT
                    a.name AS accommodation_name,
                    COUNT(s.id) AS total_enrolled,
                    SUM(CASE WHEN s.status = 'active' AND u.status = 'active' THEN 1 ELSE 0 END) AS active_students,
                    SUM(CASE WHEN s.status = 'inactive' OR u.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_students,
                    SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) AS pending_students,
                    MIN(s.created_at) AS first_enrollment,
                    MAX(s.created_at) AS last_enrollment
                FROM students s
                JOIN users u ON u.id = s.user_id
                JOIN accommodations a ON a.id = s.accommodation_id
                WHERE s.created_at BETWEEN ? AND ? $where
                GROUP BY a.id
                ORDER BY a.name";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) return [];
        if ($accommodation_id !== 'all') {
            $stmt->bind_param("ssi", $start, $end, $accommodation_id);
        } else {
            $stmt->bind_param("ss", $start, $end);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Device authorization summary grouped by accommodation.
     */
    public static function getDeviceAuthorizationSummary(
        mysqli $conn,
        string $start_date,
        string $end_date,
        string $accommodation_id = 'all'
    ): array {
        $start = $start_date . ' 00:00:00';
        $end   = $end_date   . ' 23:59:59';
        $where = $accommodation_id !== 'all' ? "AND s.accommodation_id = ?" : "";

        $sql = "SELECT
                    COALESCE(a.name, '(No Accommodation)') AS accommodation_name,
                    COUNT(ud.id) AS total_devices,
                    SUM(CASE WHEN IFNULL(ud.is_blocked, 0) = 0 THEN 1 ELSE 0 END) AS active_devices,
                    SUM(CASE WHEN IFNULL(ud.is_blocked, 0) = 1 THEN 1 ELSE 0 END) AS blocked_devices,
                    COUNT(DISTINCT ud.user_id) AS users_with_devices,
                    SUM(CASE WHEN ud.device_type = 'laptop' THEN 1 ELSE 0 END) AS laptops,
                    SUM(CASE WHEN ud.device_type = 'phone' THEN 1 ELSE 0 END) AS phones,
                    SUM(CASE WHEN ud.device_type NOT IN ('laptop', 'phone') THEN 1 ELSE 0 END) AS other_devices
                FROM user_devices ud
                JOIN users u ON u.id = ud.user_id
                LEFT JOIN students s ON s.user_id = ud.user_id
                LEFT JOIN accommodations a ON a.id = s.accommodation_id
                WHERE ud.created_at BETWEEN ? AND ? $where
                GROUP BY a.id
                ORDER BY accommodation_name";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) return [];
        if ($accommodation_id !== 'all') {
            $stmt->bind_param("ssi", $start, $end, $accommodation_id);
        } else {
            $stmt->bind_param("ss", $start, $end);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Manager activity summary grouped by manager and accommodation.
     * Converts the local date range to UTC via ActivityLogHelper for activity_log filtering.
     */
    public static function getManagerActivity(
        mysqli $conn,
        string $start_date,
        string $end_date,
        string $accommodation_id = 'all'
    ): array {
        require_once __DIR__ . '/../helpers/ActivityLogHelper.php';
        $utcRange = ActivityLogHelper::localDateRangeToUtc($start_date, $end_date);
        $utc_from = $utcRange['utc_from'] ?? ($start_date . ' 00:00:00');
        $utc_to   = $utcRange['utc_to']   ?? ($end_date   . ' 23:59:59');

        $where = $accommodation_id !== 'all' ? "AND ua.accommodation_id = ?" : "";

        $sql = "SELECT
                    CONCAT(u.first_name, ' ', u.last_name) AS manager_name,
                    u.email,
                    u.username,
                    COALESCE(a.name, '(No Accommodation)') AS accommodation_name,
                    COUNT(al.id) AS total_actions,
                    SUM(CASE WHEN al.action LIKE 'auth_login%' THEN 1 ELSE 0 END) AS logins,
                    SUM(CASE WHEN al.action LIKE 'voucher_%' THEN 1 ELSE 0 END) AS voucher_actions,
                    SUM(CASE WHEN al.action LIKE 'student_%' THEN 1 ELSE 0 END) AS student_actions,
                    SUM(CASE WHEN al.action LIKE 'device_%' THEN 1 ELSE 0 END) AS device_actions,
                    MIN(al.timestamp) AS first_activity,
                    MAX(al.timestamp) AS last_activity
                FROM users u
                JOIN roles r ON r.id = u.role_id AND r.name = 'manager'
                LEFT JOIN user_accommodation ua ON ua.user_id = u.id
                LEFT JOIN accommodations a ON a.id = ua.accommodation_id
                LEFT JOIN activity_log al ON al.user_id = u.id
                    AND al.timestamp >= ? AND al.timestamp < ?
                WHERE 1=1 $where
                GROUP BY u.id, a.id
                ORDER BY a.name, manager_name";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) return [];
        if ($accommodation_id !== 'all') {
            $stmt->bind_param("ssi", $utc_from, $utc_to, $accommodation_id);
        } else {
            $stmt->bind_param("ss", $utc_from, $utc_to);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * System audit log entries with user / action / date filtering.
     * Timestamps stored as UTC are converted via ActivityLogHelper.
     *
     * @param int $limit  Row cap (use a high value or 0 for export, 2000 for HTML display).
     */
    public static function getSystemAuditLog(
        mysqli $conn,
        string $start_date,
        string $end_date,
        string $filter_action = 'all',
        int    $filter_user   = 0,
        int    $limit         = 2000
    ): array {
        require_once __DIR__ . '/../helpers/ActivityLogHelper.php';
        $utcRange = ActivityLogHelper::localDateRangeToUtc($start_date, $end_date);
        $utc_from = $utcRange['utc_from'] ?? ($start_date . ' 00:00:00');
        $utc_to   = $utcRange['utc_to']   ?? ($end_date   . ' 23:59:59');

        $limitClause = $limit > 0 ? "LIMIT ?" : "";

        $sql = "SELECT
                    al.id,
                    al.action,
                    al.details,
                    al.ip_address,
                    al.timestamp,
                    CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                    u.username,
                    r.name AS role_name
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE (? = 0 OR al.user_id = ?)
                  AND (? = 'all' OR al.action LIKE CONCAT('%', ?, '%'))
                  AND al.timestamp >= ?
                  AND al.timestamp < ?
                ORDER BY al.timestamp DESC
                $limitClause";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) return [];
        if ($limit > 0) {
            $stmt->bind_param("iissssi",
                $filter_user, $filter_user,
                $filter_action, $filter_action,
                $utc_from, $utc_to,
                $limit
            );
        } else {
            $stmt->bind_param("iissss",
                $filter_user, $filter_user,
                $filter_action, $filter_action,
                $utc_from, $utc_to
            );
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // =========================================================================
    // Legacy report methods (preserved from original reports.php)
    // =========================================================================

    /**
     * Users registered in the given date range.
     */
    public static function getUserActivity(
        mysqli $conn,
        string $start_date,
        string $end_date
    ): array {
        $sql = "SELECT
                    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                    u.email,
                    r.name AS role_name,
                    u.status,
                    u.created_at
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.created_at BETWEEN ? AND ?
                ORDER BY u.created_at DESC";
        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) return [];
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Current active user counts per accommodation.
     */
    public static function getAccommodationUsage(
        mysqli $conn,
        string $accommodation_id = 'all'
    ): array {
        $where = $accommodation_id !== 'all' ? "AND a.id = ?" : "";

        $sql = "SELECT
                    a.name AS accommodation_name,
                    (COALESCE(s.student_count, 0) + COALESCE(m.manager_count, 0)) AS total_users,
                    COALESCE(s.student_count, 0) AS student_count,
                    COALESCE(m.manager_count, 0) AS manager_count
                FROM accommodations a
                LEFT JOIN (
                    SELECT s.accommodation_id, COUNT(DISTINCT s.user_id) AS student_count
                    FROM students s
                    JOIN users u ON u.id = s.user_id
                    JOIN roles r ON r.id = u.role_id
                    WHERE s.status = 'active' AND u.status = 'active' AND r.name = 'student'
                    GROUP BY s.accommodation_id
                ) s ON s.accommodation_id = a.id
                LEFT JOIN (
                    SELECT ua.accommodation_id, COUNT(DISTINCT ua.user_id) AS manager_count
                    FROM user_accommodation ua
                    JOIN users u ON u.id = ua.user_id
                    JOIN roles r ON r.id = u.role_id
                    WHERE u.status = 'active' AND r.name = 'manager'
                    GROUP BY ua.accommodation_id
                ) m ON m.accommodation_id = a.id
                WHERE 1=1 $where
                ORDER BY a.name";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) return [];
        if ($accommodation_id !== 'all') {
            $stmt->bind_param("i", $accommodation_id);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Onboarding code summary grouped by accommodation.
     */
    public static function getOnboardingCodes(
        mysqli $conn,
        string $start_date,
        string $end_date,
        string $accommodation_id = 'all'
    ): array {
        $where = $accommodation_id !== 'all' ? "AND oc.accommodation_id = ?" : "";

        $sql = "SELECT
                    a.name AS accommodation_name,
                    COUNT(oc.id) AS total_codes,
                    SUM(CASE WHEN oc.status = 'unused'  THEN 1 ELSE 0 END) AS unused_codes,
                    SUM(CASE WHEN oc.status = 'used'    THEN 1 ELSE 0 END) AS used_codes,
                    SUM(CASE WHEN oc.status = 'expired' THEN 1 ELSE 0 END) AS expired_codes,
                    MIN(oc.created_at) AS first_code_date,
                    MAX(oc.created_at) AS last_code_date
                FROM onboarding_codes oc
                JOIN accommodations a ON oc.accommodation_id = a.id
                WHERE oc.created_at BETWEEN ? AND ? $where
                GROUP BY a.id
                ORDER BY a.name";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) return [];
        if ($accommodation_id !== 'all') {
            $stmt->bind_param("ssi", $start_date, $end_date, $accommodation_id);
        } else {
            $stmt->bind_param("ss", $start_date, $end_date);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // =========================================================================
    // Metadata helpers used by both the HTML view and the CSV exporter
    // =========================================================================

    /**
     * Column definitions — ['label' => ..., 'key' => ...] — for table rendering and CSV headers.
     */
    public static function getReportColumns(string $report_type): array {
        switch ($report_type) {
            case 'monthly_voucher_usage':
                return [
                    ['label' => 'Month',         'key' => 'voucher_month'],
                    ['label' => 'Accommodation', 'key' => 'accommodation_name'],
                    ['label' => 'Total Sent',    'key' => 'total_sent'],
                    ['label' => 'Active',        'key' => 'active_count'],
                    ['label' => 'Used',          'key' => 'used_count'],
                    ['label' => 'Revoked',       'key' => 'revoked_count'],
                ];
            case 'student_enrollment':
                return [
                    ['label' => 'Accommodation',    'key' => 'accommodation_name'],
                    ['label' => 'Total Enrolled',   'key' => 'total_enrolled'],
                    ['label' => 'Active',           'key' => 'active_students'],
                    ['label' => 'Inactive',         'key' => 'inactive_students'],
                    ['label' => 'Pending',          'key' => 'pending_students'],
                    ['label' => 'First Enrollment', 'key' => 'first_enrollment'],
                    ['label' => 'Last Enrollment',  'key' => 'last_enrollment'],
                ];
            case 'device_authorization':
                return [
                    ['label' => 'Accommodation',    'key' => 'accommodation_name'],
                    ['label' => 'Total Devices',    'key' => 'total_devices'],
                    ['label' => 'Active',           'key' => 'active_devices'],
                    ['label' => 'Blocked',          'key' => 'blocked_devices'],
                    ['label' => 'Users w/ Devices', 'key' => 'users_with_devices'],
                    ['label' => 'Laptops',          'key' => 'laptops'],
                    ['label' => 'Phones',           'key' => 'phones'],
                    ['label' => 'Other',            'key' => 'other_devices'],
                ];
            case 'manager_activity':
                return [
                    ['label' => 'Manager',         'key' => 'manager_name'],
                    ['label' => 'Email',           'key' => 'email'],
                    ['label' => 'Accommodation',   'key' => 'accommodation_name'],
                    ['label' => 'Total Actions',   'key' => 'total_actions'],
                    ['label' => 'Logins',          'key' => 'logins'],
                    ['label' => 'Voucher Actions', 'key' => 'voucher_actions'],
                    ['label' => 'Student Actions', 'key' => 'student_actions'],
                    ['label' => 'Device Actions',  'key' => 'device_actions'],
                    ['label' => 'First Activity',  'key' => 'first_activity'],
                    ['label' => 'Last Activity',   'key' => 'last_activity'],
                ];
            case 'system_audit_log':
                return [
                    ['label' => 'ID',         'key' => 'id'],
                    ['label' => 'Timestamp',  'key' => 'timestamp'],
                    ['label' => 'User',       'key' => 'user_name'],
                    ['label' => 'Username',   'key' => 'username'],
                    ['label' => 'Role',       'key' => 'role_name'],
                    ['label' => 'Action',     'key' => 'action'],
                    ['label' => 'IP Address', 'key' => 'ip_address'],
                    ['label' => 'Details',    'key' => 'details'],
                ];
            case 'user_activity':
                return [
                    ['label' => 'Name',    'key' => 'full_name'],
                    ['label' => 'Email',   'key' => 'email'],
                    ['label' => 'Role',    'key' => 'role_name'],
                    ['label' => 'Status',  'key' => 'status'],
                    ['label' => 'Created', 'key' => 'created_at'],
                ];
            case 'accommodation_usage':
                return [
                    ['label' => 'Accommodation', 'key' => 'accommodation_name'],
                    ['label' => 'Total Users',   'key' => 'total_users'],
                    ['label' => 'Students',      'key' => 'student_count'],
                    ['label' => 'Managers',      'key' => 'manager_count'],
                ];
            case 'onboarding_codes':
                return [
                    ['label' => 'Accommodation', 'key' => 'accommodation_name'],
                    ['label' => 'Total Codes',   'key' => 'total_codes'],
                    ['label' => 'Unused',        'key' => 'unused_codes'],
                    ['label' => 'Used',          'key' => 'used_codes'],
                    ['label' => 'Expired',       'key' => 'expired_codes'],
                    ['label' => 'First Code',    'key' => 'first_code_date'],
                    ['label' => 'Last Code',     'key' => 'last_code_date'],
                ];
            default:
                return [];
        }
    }

    /** Human-readable title for a given report type. */
    public static function getReportTitle(string $report_type): string {
        $titles = [
            'monthly_voucher_usage' => 'Monthly Voucher Usage',
            'student_enrollment'    => 'Student Enrollment by Accommodation',
            'device_authorization'  => 'Device Authorization Summary',
            'manager_activity'      => 'Manager Activity Report',
            'system_audit_log'      => 'System Audit Log',
            'user_activity'         => 'User Activity',
            'accommodation_usage'   => 'Accommodation Usage',
            'onboarding_codes'      => 'Onboarding Codes',
        ];
        return $titles[$report_type] ?? ucwords(str_replace('_', ' ', $report_type));
    }

    /** Bootstrap icon class for a given report type. */
    public static function getReportIcon(string $report_type): string {
        $icons = [
            'monthly_voucher_usage' => 'bi-calendar-check',
            'student_enrollment'    => 'bi-mortarboard',
            'device_authorization'  => 'bi-laptop',
            'manager_activity'      => 'bi-person-badge',
            'system_audit_log'      => 'bi-shield-lock',
            'user_activity'         => 'bi-people',
            'accommodation_usage'   => 'bi-building',
            'onboarding_codes'      => 'bi-ticket-perforated',
        ];
        return $icons[$report_type] ?? 'bi-file-earmark-bar-graph';
    }

    /** Whether a report type uses an accommodation filter. */
    public static function hasAccommodationFilter(string $report_type): bool {
        return in_array($report_type, [
            'monthly_voucher_usage',
            'student_enrollment',
            'device_authorization',
            'manager_activity',
            'accommodation_usage',
            'onboarding_codes',
        ], true);
    }

    /** Whether a report type uses a date range filter. */
    public static function hasDateFilter(string $report_type): bool {
        return $report_type !== 'accommodation_usage';
    }
}
