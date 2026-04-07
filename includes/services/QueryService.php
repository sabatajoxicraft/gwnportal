<?php
/**
 * QueryService - Centralized Database Query Patterns
 * 
 * Consolidates repeated database query patterns used throughout the application.
 * This eliminates code duplication and makes queries consistent across the codebase.
 * 
 * Usage: QueryService::getAccommodationDetails($id)
 * Instead of inline: $stmt = safeQueryPrepare($conn, "SELECT ... FROM accommodations WHERE id = ?");
 */

class QueryService {

    /**
     * Get accommodation details with owner information
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @return array|null Accommodation data or null if not found
     */
    public static function getAccommodationDetails($conn, $accommodationId) {
        $stmt = safeQueryPrepare($conn, "
            SELECT 
                a.id,
                a.name,
                a.owner_id,
                u.username AS owner_username,
                u.first_name AS owner_first_name,
                u.last_name AS owner_last_name,
                a.created_at,
                a.updated_at
            FROM accommodations a
            LEFT JOIN users u ON a.owner_id = u.id
            WHERE a.id = ?
        ");
        
        if (!$stmt) {
            error_log("QueryService::getAccommodationDetails - Prepare error: " . $conn->error);
            return null;
        }
        
        $stmt->bind_param("i", $accommodationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $accommodation = $result->fetch_assoc();
        $stmt->close();
        
        return $accommodation;
    }

    /**
     * Get user with their role information
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return array|null User data with role details or null if not found
     */
    public static function getUserWithRole($conn, $userId) {
        $stmt = safeQueryPrepare($conn, "
            SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.id_number,
                u.phone_number,
                u.whatsapp_number,
                u.preferred_communication,
                u.profile_photo,
                u.role_id,
                r.name AS role_name,
                u.status,
                u.created_at,
                u.updated_at,
                u.password_reset_required
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        
        if (!$stmt) {
            error_log("QueryService::getUserWithRole - Prepare error: " . $conn->error);
            return null;
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }

    /**
     * Get user by username with role
     * 
     * @param mysqli $conn Database connection
     * @param string $username Username to search for
     * @return array|null User data or null if not found
     */
    public static function getUserByUsername($conn, $username) {
        $stmt = safeQueryPrepare($conn, "
            SELECT 
                u.id,
                u.username,
                u.password,
                u.email,
                u.first_name,
                u.last_name,
                u.role_id,
                r.name AS role_name,
                u.status,
                u.password_reset_required
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
        ");
        
        if (!$stmt) {
            error_log("QueryService::getUserByUsername - Prepare error: " . $conn->error);
            return null;
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }

    /**
     * Get accommodations accessible by a user based on their role
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @param string $userRole User's role (auto-fetched if null)
     * @return array Array of accommodation IDs/data
     */
    public static function getUserAccommodations($conn, $userId, $userRole = null) {
        // If role not provided, fetch it
        if ($userRole === null) {
            $user = self::getUserWithRole($conn, $userId);
            if (!$user) {
                return [];
            }
            $userRole = $user['role_name'];
        }

        // Admin can see all accommodations
        if ($userRole === ROLE_ADMIN) {
            $stmt = safeQueryPrepare($conn, "
                SELECT id, name, owner_id
                FROM accommodations
                ORDER BY name
            ");
            if (!$stmt) {
                return [];
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $accommodations = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $accommodations;
        }

        // Owner can see their own accommodations
        if ($userRole === ROLE_OWNER) {
            $stmt = safeQueryPrepare($conn, "
                SELECT 
                    id, 
                    name, 
                    owner_id,
                    COALESCE(
                        NULLIF(CONCAT_WS(', ',
                            NULLIF(TRIM(address_line1), ''),
                            NULLIF(TRIM(address_line2), ''),
                            NULLIF(TRIM(city), ''),
                            NULLIF(TRIM(province), ''),
                            NULLIF(TRIM(postal_code), '')
                        ), ''),
                        CASE
                            WHEN NULLIF(TRIM(map_url), '') IS NOT NULL THEN 'Map location available'
                            ELSE 'Not set'
                        END
                    ) AS address
                FROM accommodations
                WHERE owner_id = ?
                ORDER BY name
            ");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $accommodations = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $accommodations;
        }

        // Manager can see assigned accommodations
        if ($userRole === ROLE_MANAGER) {
            $stmt = safeQueryPrepare($conn, "
                SELECT DISTINCT a.id, a.name, a.owner_id
                FROM accommodations a
                INNER JOIN user_accommodation ua ON a.id = ua.accommodation_id
                WHERE ua.user_id = ?
                ORDER BY a.name
            ");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $accommodations = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $accommodations;
        }

        // Student can see their assigned accommodation
        if ($userRole === ROLE_STUDENT) {
            $stmt = safeQueryPrepare($conn, "
                SELECT a.id, a.name, a.owner_id
                FROM accommodations a
                INNER JOIN students s ON a.id = s.accommodation_id
                WHERE s.user_id = ?
            ");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $accommodations = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $accommodations;
        }

        return [];
    }

    /**
     * Get accommodation students with optional filtering
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @param array $filter Optional filters: ['status' => 'active', 'search' => 'name']
     * @return array Array of students
     */
    public static function getAccommodationStudents($conn, $accommodationId, $filter = []) {
        $query = "
            SELECT 
                s.id,
                s.user_id,
                s.accommodation_id,
                s.room_number,
                s.status,
                s.created_at,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                u.phone_number,
                u.id_number
            FROM students s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.accommodation_id = ?
        ";

        $params = [$accommodationId];
        $types = "i";

        if (!empty($filter['status'])) {
            $query .= " AND s.status = ?";
            $params[] = $filter['status'];
            $types .= "s";
        }

        if (!empty($filter['search'])) {
            $searchTerm = "%" . $filter['search'] . "%";
            $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }

        $query .= " ORDER BY s.room_number, u.last_name";

        $stmt = safeQueryPrepare($conn, $query);
        if (!$stmt) {
            error_log("QueryService::getAccommodationStudents - Prepare error: " . $conn->error);
            return [];
        }

        if (count($params) > 1) {
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param($types, $params[0]);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $students;
    }

    /**
     * Get student info with accommodation details
     * 
     * @param mysqli $conn Database connection
     * @param int $studentUserId Student's user ID
     * @return array|null Student data or null if not found
     */
    public static function getStudentInfo($conn, $studentUserId) {
        $stmt = safeQueryPrepare($conn, "
            SELECT 
                s.id,
                s.user_id,
                s.accommodation_id,
                s.room_number,
                s.status,
                s.created_at,
                s.updated_at,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                u.phone_number,
                u.id_number,
                a.name AS accommodation_name,
                a.owner_id
            FROM students s
            INNER JOIN users u ON s.user_id = u.id
            INNER JOIN accommodations a ON s.accommodation_id = a.id
            WHERE s.user_id = ?
        ");
        
        if (!$stmt) {
            error_log("QueryService::getStudentInfo - Prepare error: " . $conn->error);
            return null;
        }
        
        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
        
        return $student;
    }

    /**
     * Build the WHERE clause, params, and types for student list queries.
     * Used internally by getStudentList() and countStudentList().
     *
     * Supported criteria keys:
     *   accommodation_id (int)   – filter to a single accommodation
     *   status  (string)         – 'all'|'active'|'pending'|'inactive'|'archived'
     *   search  (string)         – name / email / id_number / student record ID
     *   device_status (string)   – 'all'|'has_devices'|'needs_approval'
     *
     * @param array $criteria
     * @return array [string $whereClause, array $params, string $types]
     */
    private static function buildStudentWhere(array $criteria): array {
        $conditions = ['1=1'];
        $params     = [];
        $types      = '';

        if (!empty($criteria['accommodation_id'])) {
            $conditions[] = 's.accommodation_id = ?';
            $params[]     = (int)$criteria['accommodation_id'];
            $types       .= 'i';
        }

        $status = $criteria['status'] ?? 'all';
        if ($status === 'active') {
            $conditions[] = "s.status = 'active'";
        } elseif ($status === 'pending') {
            $conditions[] = "s.status = 'pending'";
        } elseif ($status === 'inactive') {
            $conditions[] = "s.status = 'inactive'";
        } elseif ($status === 'archived') {
            $conditions[] = "s.status = 'archived'";
        } else {
            // 'all' = everything except archived
            $conditions[] = "s.status != 'archived'";
        }

        if (!empty($criteria['search'])) {
            $searchTerm   = '%' . $criteria['search'] . '%';
            $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR u.id_number LIKE ? OR CAST(s.id AS CHAR) LIKE ?)";
            for ($i = 0; $i < 6; $i++) {
                $params[] = $searchTerm;
                $types   .= 's';
            }
        }

        $deviceStatus = $criteria['device_status'] ?? 'all';
        if ($deviceStatus === 'has_devices') {
            $conditions[] = '(SELECT COUNT(*) FROM user_devices ud_f WHERE ud_f.user_id = u.id) > 0';
        } elseif ($deviceStatus === 'needs_approval') {
            // Devices submitted via student self-service request (linked_via = 'request')
            $conditions[] = "(SELECT COUNT(*) FROM user_devices ud_f WHERE ud_f.user_id = u.id AND ud_f.linked_via = 'request') > 0";
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params, $types];
    }

    /**
     * Get a paginated, filtered, sorted student list.
     *
     * Supported criteria keys (in addition to buildStudentWhere keys):
     *   sort          (string) – 'newest'|'oldest'|'name_asc'|'name_desc'
     *   current_month (string) – e.g. "March 2026" for per-month voucher sub-count
     *
     * @param mysqli $conn
     * @param array  $criteria
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public static function getStudentList($conn, array $criteria = [], int $limit = 50, int $offset = 0): array {
        $allowedSorts = [
            'newest'    => 's.created_at DESC',
            'oldest'    => 's.created_at ASC',
            'name_asc'  => 'u.first_name ASC, u.last_name ASC',
            'name_desc' => 'u.first_name DESC, u.last_name DESC',
        ];

        $sortKey = (isset($criteria['sort']) && array_key_exists($criteria['sort'], $allowedSorts))
            ? $criteria['sort'] : 'newest';
        $orderBy = $allowedSorts[$sortKey];

        $currentMonth = $criteria['current_month'] ?? '';

        [$whereClause, $filterParams, $filterTypes] = self::buildStudentWhere($criteria);

        $sql = "SELECT s.id, s.status, s.created_at, u.id AS user_id,
                    u.first_name, u.last_name, u.email, u.phone_number,
                    u.whatsapp_number, u.preferred_communication, u.id_number,
                    a.id AS accommodation_id, a.name AS accommodation_name,
                    COUNT(DISTINCT ud.id) AS device_count,
                    (SELECT COUNT(*) FROM voucher_logs vl
                        WHERE vl.user_id = u.id AND vl.is_active = 1 AND vl.voucher_month = ?) AS active_vouchers_this_month,
                    (SELECT COUNT(*) FROM voucher_logs vl2 WHERE vl2.user_id = u.id) AS total_voucher_count
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN accommodations a ON s.accommodation_id = a.id
                LEFT JOIN user_devices ud ON ud.user_id = u.id
                $whereClause
                GROUP BY s.id, s.status, s.created_at, u.id, u.first_name, u.last_name,
                         u.email, u.phone_number, u.whatsapp_number, u.preferred_communication,
                         u.id_number, a.id, a.name
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) {
            error_log('QueryService::getStudentList - Prepare error: ' . $conn->error);
            return [];
        }

        $allParams = array_merge([$currentMonth], $filterParams, [$limit, $offset]);
        $allTypes  = 's' . $filterTypes . 'ii';
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $students;
    }

    /**
     * Count students matching the given criteria (mirrors getStudentList filters).
     *
     * @param mysqli $conn
     * @param array  $criteria  Same keys as buildStudentWhere
     * @return int
     */
    public static function countStudentList($conn, array $criteria = []): int {
        [$whereClause, $filterParams, $filterTypes] = self::buildStudentWhere($criteria);

        $sql = "SELECT COUNT(*) AS total
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN accommodations a ON s.accommodation_id = a.id
                $whereClause";

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) {
            error_log('QueryService::countStudentList - Prepare error: ' . $conn->error);
            return 0;
        }

        if (!empty($filterParams)) {
            $stmt->bind_param($filterTypes, ...$filterParams);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }

    /**
     * Search users by criteria
     * 
     * @param mysqli $conn Database connection
     * @param array $criteria Search criteria: ['role' => 'admin', 'status' => 'active', 'search' => 'name']
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array Array of users matching criteria
     */
    public static function searchUsers($conn, $criteria = [], $limit = 50, $offset = 0) {
        $query = "
            SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.role_id,
                r.name AS role_name,
                u.status,
                u.created_at
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE 1=1
        ";

        $params = [];
        $types = "";

        if (!empty($criteria['role'])) {
            $query .= " AND r.name = ?";
            $params[] = $criteria['role'];
            $types .= "s";
        }

        if (!empty($criteria['status'])) {
            $query .= " AND u.status = ?";
            $params[] = $criteria['status'];
            $types .= "s";
        }

        if (!empty($criteria['search'])) {
            $searchTerm = "%" . $criteria['search'] . "%";
            $query .= " AND (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ssss";
        }

        $query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = safeQueryPrepare($conn, $query);
        if (!$stmt) {
            error_log("QueryService::searchUsers - Prepare error: " . $conn->error);
            return [];
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $users;
    }

    /**
     * Count search results
     * 
     * @param mysqli $conn Database connection
     * @param array $criteria Search criteria
     * @return int Total count matching criteria
     */
    public static function countSearchResults($conn, $criteria = []) {
        $query = "SELECT COUNT(*) as count FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE 1=1";

        $params = [];
        $types = "";

        if (!empty($criteria['role'])) {
            $query .= " AND r.name = ?";
            $params[] = $criteria['role'];
            $types .= "s";
        }

        if (!empty($criteria['status'])) {
            $query .= " AND u.status = ?";
            $params[] = $criteria['status'];
            $types .= "s";
        }

        if (!empty($criteria['search'])) {
            $searchTerm = "%" . $criteria['search'] . "%";
            $query .= " AND (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ssss";
        }

        $stmt = safeQueryPrepare($conn, $query);
        if (!$stmt) {
            error_log("QueryService::countSearchResults - Prepare error: " . $conn->error);
            return 0;
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)($row['count'] ?? 0);
    }

    /**
     * Get onboarding code with creation details
     * 
     * @param mysqli $conn Database connection
     * @param string $code Code to lookup
     * @return array|null Code data or null if not found
     */
    public static function getOnboardingCode($conn, $code) {
        $stmt = safeQueryPrepare($conn, "
            SELECT 
                oc.id,
                oc.code,
                oc.created_by,
                oc.accommodation_id,
                oc.used_by,
                oc.status,
                oc.role_id,
                oc.created_at,
                oc.expires_at,
                oc.used_at,
                u_creator.username AS creator_username,
                u_user.username AS used_by_username,
                a.name AS accommodation_name,
                r.name AS role_name
            FROM onboarding_codes oc
            LEFT JOIN users u_creator ON oc.created_by = u_creator.id
            LEFT JOIN users u_user ON oc.used_by = u_user.id
            LEFT JOIN accommodations a ON oc.accommodation_id = a.id
            LEFT JOIN roles r ON oc.role_id = r.id
            WHERE oc.code = ?
        ");
        
        if (!$stmt) {
            error_log("QueryService::getOnboardingCode - Prepare error: " . $conn->error);
            return null;
        }
        
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $codeData = $result->fetch_assoc();
        $stmt->close();
        
        return $codeData;
    }

}

