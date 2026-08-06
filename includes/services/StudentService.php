<?php
/**
 * StudentService - Student Management Operations
 * 
 * Handles student-specific operations:
 * - Register student to accommodation
 * - Assign/update room information
 * - Change student status
 * - Student lookup
 * 
 * Usage: StudentService::registerStudent($conn, $userId, $accommodationId, $roomNumber);
 */

class StudentService {
    private const VALID_STATUSES = ['active', 'pending', 'inactive'];

    /**
     * Register student to accommodation
     * 
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @param int $accommodationId Target accommodation
     * @param string $roomNumber Room number/identifier
     * @return array|false Array with success info, or false on failure
     */
    public static function registerStudent($conn, $userId, $accommodationId, $roomNumber) {
        if (empty($userId) || empty($accommodationId) || empty($roomNumber)) {
            error_log("StudentService::registerStudent - Missing required field");
            return false;
        }

        // Check if student already registered elsewhere
        $existing = self::getStudentRecord($conn, $userId);
        if ($existing) {
            error_log("StudentService::registerStudent - Student already registered to another accommodation");
            return false;
        }

        $status = 'pending';

        $stmt = safeQueryPrepare($conn, "
            INSERT INTO students (user_id, accommodation_id, room_number, status)
            VALUES (?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("StudentService::registerStudent - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("iiss", $userId, $accommodationId, $roomNumber, $status);
        
        if (!$stmt->execute()) {
            error_log("StudentService::registerStudent - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();

        return [
            'success' => true,
            'user_id' => $userId,
            'accommodation_id' => $accommodationId,
            'room_number' => $roomNumber
        ];
    }

    /**
     * Get student record
     * 
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @return array|null Student data or null if not found
     */
    public static function getStudentRecord($conn, $userId) {
        if (empty($userId)) {
            return null;
        }

        $stmt = safeQueryPrepare($conn, "
            SELECT id, user_id, accommodation_id, room_number, status, created_at, updated_at
            FROM students
            WHERE user_id = ?
        ");

        if (!$stmt) {
            error_log("StudentService::getStudentRecord - Prepare error: " . $conn->error);
            return null;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        return $student;
    }

    /**
     * Update student room assignment
     * 
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @param string $newRoomNumber New room number
     * @return bool Success
     */
    public static function updateRoomAssignment($conn, $userId, $newRoomNumber) {
        if (empty($userId) || empty($newRoomNumber)) {
            return false;
        }

        $stmt = safeQueryPrepare($conn, "
            UPDATE students
            SET room_number = ?
            WHERE user_id = ?
        ");

        if (!$stmt) {
            error_log("StudentService::updateRoomAssignment - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("si", $newRoomNumber, $userId);
        
        if (!$stmt->execute()) {
            error_log("StudentService::updateRoomAssignment - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Change student status
     * 
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @param string $newStatus New status: active, pending, inactive
     * @return bool Success
     */
    public static function setStatus($conn, $userId, $newStatus) {
        $validStatuses = ['active', 'pending', 'inactive'];
        
        if (empty($userId) || !in_array($newStatus, $validStatuses)) {
            return false;
        }

        $stmt = safeQueryPrepare($conn, "
            UPDATE students
            SET status = ?
            WHERE user_id = ?
        ");

        if (!$stmt) {
            error_log("StudentService::setStatus - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("si", $newStatus, $userId);
        
        if (!$stmt->execute()) {
            error_log("StudentService::setStatus - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Activate student (set status to active)
     * 
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @return bool Success
     */
    public static function activateStudent($conn, $userId) {
        return self::setStatus($conn, $userId, 'active');
    }

    /**
     * Deactivate student (set status to inactive)
     * 
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @return bool Success
     */
    public static function deactivateStudent($conn, $userId) {
        return self::setStatus($conn, $userId, 'inactive');
    }

    /**
     * Get student with full details
     * 
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @return array|null Full student info including accommodation and user details
     */
    public static function getStudentWithDetails($conn, $userId) {
        return QueryService::getStudentInfo($conn, $userId);
    }

    /**
     * Check if user is a student
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID to check
     * @return bool True if student, false otherwise
     */
    public static function isStudent($conn, $userId) {
        if (empty($userId)) {
            return false;
        }

        $stmt = safeQueryPrepare($conn, "
            SELECT id FROM students WHERE user_id = ?
        ");

        if (!$stmt) {
            error_log("StudentService::isStudent - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $isStudent = $result->num_rows > 0;
        $stmt->close();

        return $isStudent;
    }

    /**
     * Unregister student (remove from accommodation)
     * 
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @return bool Success
     */
    public static function unregisterStudent($conn, $userId) {
        if (empty($userId)) {
            return false;
        }

        $stmt = safeQueryPrepare($conn, "DELETE FROM students WHERE user_id = ?");

        if (!$stmt) {
            error_log("StudentService::unregisterStudent - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            error_log("StudentService::unregisterStudent - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Get students by status
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @param string $status Status filter: 'active', 'pending', 'inactive'
     * @return array Array of students
     */
    public static function getStudentsByStatus($conn, $accommodationId, $status = null) {
        if (empty($accommodationId)) {
            return [];
        }

        $filter = [];
        if ($status !== null) {
            $filter['status'] = $status;
        }

        return QueryService::getAccommodationStudents($conn, $accommodationId, $filter);
    }

    /**
     * Ensure a student user has a student record for a specific accommodation.
     * Uses upsert semantics keyed by students.user_id.
     *
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @param int $accommodationId Accommodation ID
     * @param string $status Student status (active|pending|inactive)
     * @return bool Success
     */
    public static function ensureStudentRecord($conn, int $userId, int $accommodationId, string $status = 'active'): bool {
        if ($userId <= 0 || $accommodationId <= 0) {
            error_log("StudentService::ensureStudentRecord - Invalid user/accommodation values");
            return false;
        }

        if (!in_array($status, self::VALID_STATUSES, true)) {
            error_log("StudentService::ensureStudentRecord - Invalid status '{$status}'");
            return false;
        }

        $stmt = safeQueryPrepare($conn, "
            INSERT INTO students (user_id, accommodation_id, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                accommodation_id = VALUES(accommodation_id),
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP
        ");

        if (!$stmt) {
            error_log("StudentService::ensureStudentRecord - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("iis", $userId, $accommodationId, $status);
        $ok = $stmt->execute();
        if (!$ok) {
            error_log("StudentService::ensureStudentRecord - Execute error: " . $stmt->error);
        }
        $stmt->close();
        return $ok;
    }

    /**
     * Get the primary accommodation assignment for a user.
     * Students are single-accommodation users in the students table, so we use
     * the minimum assigned accommodation from user_accommodation when needed.
     *
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return int|null Accommodation ID or null if not assigned
     */
    public static function getPrimaryAccommodationIdForUser($conn, int $userId): ?int {
        if ($userId <= 0) {
            return null;
        }

        $stmt = safeQueryPrepare($conn, "
            SELECT MIN(ua.accommodation_id) AS accommodation_id
            FROM user_accommodation ua
            WHERE ua.user_id = ?
        ");

        if (!$stmt) {
            error_log("StudentService::getPrimaryAccommodationIdForUser - Prepare error: " . $conn->error);
            return null;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $accommodationId = isset($row['accommodation_id']) ? (int)$row['accommodation_id'] : 0;
        return $accommodationId > 0 ? $accommodationId : null;
    }

    /**
     * Ensure a student record exists for a student user based on their
     * user_accommodation assignment.
     *
     * @param mysqli $conn Database connection
     * @param int $userId Student user ID
     * @param string $status Default status for newly repaired records
     * @return int Student table ID (0 when not available or failed)
     */
    public static function ensureStudentRecordFromAssignment($conn, int $userId, string $status = 'active'): int {
        if ($userId <= 0) {
            return 0;
        }

        $existingStmt = safeQueryPrepare($conn, "SELECT id FROM students WHERE user_id = ? LIMIT 1");
        if (!$existingStmt) {
            error_log("StudentService::ensureStudentRecordFromAssignment - Prepare error: " . $conn->error);
            return 0;
        }

        $existingStmt->bind_param("i", $userId);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();

        if ($existing) {
            return (int)$existing['id'];
        }

        $accommodationId = self::getPrimaryAccommodationIdForUser($conn, $userId);
        if (!$accommodationId) {
            return 0;
        }

        if (!self::ensureStudentRecord($conn, $userId, $accommodationId, $status)) {
            return 0;
        }

        $idStmt = safeQueryPrepare($conn, "SELECT id FROM students WHERE user_id = ? LIMIT 1");
        if (!$idStmt) {
            error_log("StudentService::ensureStudentRecordFromAssignment - ID lookup prepare error: " . $conn->error);
            return 0;
        }
        $idStmt->bind_param("i", $userId);
        $idStmt->execute();
        $row = $idStmt->get_result()->fetch_assoc();
        $idStmt->close();

        return $row ? (int)$row['id'] : 0;
    }

    /**
     * Backfill missing students rows for student-role users that already have
     * user_accommodation assignments.
     *
     * @param mysqli $conn Database connection
     * @param int|null $accommodationId Optional accommodation scope
     * @param string $status Default status for inserted rows
     * @return int Number of inserted rows
     */
    public static function backfillMissingStudentRecords($conn, ?int $accommodationId = null, string $status = 'active'): int {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            error_log("StudentService::backfillMissingStudentRecords - Invalid status '{$status}'");
            return 0;
        }

        $sql = "
            INSERT INTO students (user_id, accommodation_id, status)
            SELECT
                u.id AS user_id,
                ua_primary.accommodation_id,
                ? AS status
            FROM users u
            JOIN roles r ON r.id = u.role_id AND r.name = 'student'
            JOIN (
                SELECT ua.user_id, MIN(ua.accommodation_id) AS accommodation_id
                FROM user_accommodation ua
                GROUP BY ua.user_id
            ) ua_primary ON ua_primary.user_id = u.id
            LEFT JOIN students s ON s.user_id = u.id
            WHERE s.user_id IS NULL
        ";

        $types = 's';
        $params = [$status];

        if (!empty($accommodationId) && $accommodationId > 0) {
            $sql .= " AND ua_primary.accommodation_id = ?";
            $types .= 'i';
            $params[] = $accommodationId;
        }

        $stmt = safeQueryPrepare($conn, $sql);
        if (!$stmt) {
            error_log("StudentService::backfillMissingStudentRecords - Prepare error: " . $conn->error);
            return 0;
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            error_log("StudentService::backfillMissingStudentRecords - Execute error: " . $stmt->error);
            $stmt->close();
            return 0;
        }

        $inserted = $stmt->affected_rows;
        $stmt->close();
        return max(0, (int)$inserted);
    }

}
