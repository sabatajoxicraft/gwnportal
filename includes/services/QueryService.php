<?php
/**
 * QueryService - Centralized Database Query Patterns
 * 
 * Consolidates repeated database query patterns used throughout the application.
 * This eliminates code duplication and makes queries consistent across the codebase.
 * 
 * Usage: QueryService::getAccommodationDetails($id)
 * Instead of inline: $stmt = $conn->prepare("SELECT ... FROM accommodations WHERE id = ?");
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
        $stmt = $conn->prepare("
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
        $stmt = $conn->prepare("
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
        $stmt = $conn->prepare("
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
            $stmt = $conn->prepare("
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
            $stmt = $conn->prepare("
                SELECT id, name, owner_id
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
            $stmt = $conn->prepare("
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
            $stmt = $conn->prepare("
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

        $stmt = $conn->prepare($query);
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
        $stmt = $conn->prepare("
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

        $stmt = $conn->prepare($query);
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

        $stmt = $conn->prepare($query);
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
        $stmt = $conn->prepare("
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

?>
