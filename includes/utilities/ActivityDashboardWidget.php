<?php
/**
 * Activity Dashboard Widget - Display Activity Summaries
 * 
 * Renders activity dashboard components showing logs, statistics, and summaries.
 * Used in admin/owner/manager dashboards.
 */

class ActivityDashboardWidget {

    /**
     * Render activity summary card
     * 
     * Shows user activity at a glance for dashboard
     * 
     * @param int $userId User ID or null for current user
     * @param string $period Period: today, week, month, all
     * @return string HTML
     */
    public static function renderActivitySummary($userId = null, $period = 'today') {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        
        if (!$userId) {
            return '';
        }

        $summary = RequestLogger::getUserActivitySummary($userId, $period);

        if (empty($summary)) {
            return '<div class="alert alert-info">No activity recorded</div>';
        }

        $html = '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h5 class="card-title">Activity Summary</h5>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<div class="row">';

        $html .= '<div class="col-md-6">';
        $html .= '<p class="text-muted mb-1">Total Actions</p>';
        $html .= '<h3>' . $summary['total_actions'] . '</h3>';
        $html .= '</div>';

        $html .= '<div class="col-md-6">';
        $html .= '<p class="text-muted mb-1">Active Days</p>';
        $html .= '<h3>' . $summary['active_days'] . '</h3>';
        $html .= '</div>';

        if ($summary['last_activity']) {
            $html .= '<div class="col-md-12 mt-3 pt-3 border-top">';
            $html .= '<p class="text-muted mb-1">Last Activity</p>';
            $html .= '<p>' . date('M d, Y H:i', strtotime($summary['last_activity'])) . '</p>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render recent activity log
     * 
     * @param int $limit Number of activities to show
     * @param int $userId User ID or null for all users
     * @return string HTML
     */
    public static function renderRecentActivityLog($limit = 15, $userId = null) {
        global $conn;
        
        if (!$conn) {
            return '';
        }

        $userFilter = $userId ? "AND user_id = $userId" : '';

        $result = $conn->query("
            SELECT 
                al.id,
                al.user_id,
                u.username,
                u.email,
                al.action,
                al.entity_type,
                al.details,
                al.ip_address,
                al.created_at
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1 $userFilter
            ORDER BY al.created_at DESC
            LIMIT $limit
        ");

        if (!$result || $result->num_rows === 0) {
            return '<div class="alert alert-info">No activity recorded</div>';
        }

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-sm table-hover">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>User</th>';
        $html .= '<th>Action</th>';
        $html .= '<th>Type</th>';
        $html .= '<th>Time</th>';
        $html .= '<th>IP Address</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        while ($row = $result->fetch_assoc()) {
            $userDisplay = $row['username'] ?? ($row['email'] ?? 'Anonymous');
            $actionDisplay = self::formatAction($row['action']);
            $timeDisplay = self::formatTime($row['created_at']);
            $typeDisplay = self::formatEntityType($row['entity_type']);

            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($userDisplay) . '</strong></td>';
            $html .= '<td>' . htmlspecialchars($actionDisplay) . '</td>';
            $html .= '<td><span class="badge bg-secondary">' . htmlspecialchars($typeDisplay) . '</span></td>';
            $html .= '<td><small class="text-muted">' . $timeDisplay . '</small></td>';
            $html .= '<td><code>' . htmlspecialchars($row['ip_address']) . '</code></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render accommodation activity log
     * 
     * @param int $accommodationId Accommodation ID
     * @param int $limit Number of activities to show
     * @return string HTML
     */
    public static function renderAccommodationActivityLog($accommodationId, $limit = 20) {
        global $conn;
        
        if (!$conn) {
            return '';
        }

        $accommodationId = intval($accommodationId);

        $result = $conn->query("
            SELECT 
                al.id,
                al.user_id,
                u.username,
                u.email,
                al.action,
                al.entity_type,
                al.entity_id,
                al.details,
                al.created_at
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE (
                al.entity_type = 'accommodation' AND al.entity_id = $accommodationId
                OR al.action LIKE '%accommodation%'
            )
            ORDER BY al.created_at DESC
            LIMIT $limit
        ");

        if (!$result || $result->num_rows === 0) {
            return '<div class="alert alert-info">No activity for this accommodation</div>';
        }

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-sm table-hover">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>User</th>';
        $html .= '<th>Action</th>';
        $html .= '<th>Time</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        while ($row = $result->fetch_assoc()) {
            $userDisplay = $row['username'] ?? ($row['email'] ?? 'System');
            $actionDisplay = self::formatAction($row['action']);
            $timeDisplay = self::formatTimeAgo($row['created_at']);

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($userDisplay) . '</td>';
            $html .= '<td>' . htmlspecialchars($actionDisplay) . '</td>';
            $html .= '<td><small class="text-muted">' . $timeDisplay . '</small></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render page view statistics
     * 
     * @param int $limit Number of pages to show
     * @param string $period Period: today, week, month, all
     * @return string HTML
     */
    public static function renderPageViewStats($limit = 10, $period = 'today') {
        $pages = RequestLogger::getMostViewedPages($limit, $period);

        if (empty($pages)) {
            return '<div class="alert alert-info">No page views recorded</div>';
        }

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-sm">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>Page</th>';
        $html .= '<th class="text-end">Views</th>';
        $html .= '<th class="text-end">Unique Users</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($pages as $page) {
            $pageDisplay = str_replace('page_view_', '', $page['action']);
            $pageDisplay = str_replace('_', '/', $pageDisplay);

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($pageDisplay) . '</td>';
            $html .= '<td class="text-end"><span class="badge bg-primary">' . $page['views'] . '</span></td>';
            $html .= '<td class="text-end">' . $page['unique_users'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render user activity timeline
     * 
     * @param int $userId User ID
     * @param int $limit Number of activities
     * @return string HTML
     */
    public static function renderUserActivityTimeline($userId, $limit = 25) {
        global $conn;
        
        if (!$conn) {
            return '';
        }

        $userId = intval($userId);

        $result = $conn->query("
            SELECT 
                al.action,
                al.entity_type,
                al.description,
                al.created_at
            FROM activity_logs al
            WHERE al.user_id = $userId
            ORDER BY al.created_at DESC
            LIMIT $limit
        ");

        if (!$result || $result->num_rows === 0) {
            return '<div class="alert alert-info">No activity recorded for this user</div>';
        }

        $html = '<div class="timeline">';

        while ($row = $result->fetch_assoc()) {
            $timeDisplay = self::formatTimeAgo($row['created_at']);
            $actionDisplay = self::formatAction($row['action']);

            $html .= '<div class="timeline-item">';
            $html .= '<div class="timeline-time">' . $timeDisplay . '</div>';
            $html .= '<div class="timeline-content">';
            $html .= '<p>' . htmlspecialchars($actionDisplay) . '</p>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format action name for display
     * 
     * @param string $action Raw action name
     * @return string Formatted action
     */
    private static function formatAction($action) {
        return ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Format entity type for display
     * 
     * @param string $type Raw entity type
     * @return string Formatted type
     */
    private static function formatEntityType($type) {
        $types = [
            'page_view' => 'Page View',
            'api_call' => 'API Call',
            'device' => 'Device',
            'voucher' => 'Voucher',
            'student' => 'Student',
            'accommodation' => 'Accommodation',
            'user' => 'User',
            'code' => 'Code'
        ];

        return $types[$type] ?? ucfirst($type);
    }

    /**
     * Format timestamp for display
     * 
     * @param string $timestamp Database timestamp
     * @return string Formatted time
     */
    private static function formatTime($timestamp) {
        return date('M d, Y H:i', strtotime($timestamp));
    }

    /**
     * Format timestamp as "time ago" (e.g., "2 hours ago")
     * 
     * @param string $timestamp Database timestamp
     * @return string Formatted time ago
     */
    private static function formatTimeAgo($timestamp) {
        $time = time() - strtotime($timestamp);

        if ($time < 60) {
            return $time . ' seconds ago';
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($time < 2592000) {
            $days = floor($time / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M d, Y', strtotime($timestamp));
        }
    }

}

?>
