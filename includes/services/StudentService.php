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

        $stmt = $conn->prepare("
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

        $stmt = $conn->prepare("
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

        $stmt = $conn->prepare("
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

        $stmt = $conn->prepare("
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

        $stmt = $conn->prepare("
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

        $stmt = $conn->prepare("DELETE FROM students WHERE user_id = ?");

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

}

?>
