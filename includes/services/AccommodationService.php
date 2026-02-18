<?php
/**
 * AccommodationService - Accommodation Management Operations
 * 
 * Handles all accommodation-related business logic:
 * - Create, update, delete accommodations
 * - Manage manager assignments
 * - Get accommodation details with access control
 * 
 * Usage: AccommodationService::createAccommodation($conn, $ownerId, $name);
 */

class AccommodationService {

    /**
     * Create new accommodation
     * 
     * @param mysqli $conn Database connection
     * @param int $ownerId Owner user ID
     * @param string $name Accommodation name
     * @return array|false Array with ['success' => true, 'accommodation_id' => X] or false
     */
    public static function createAccommodation($conn, $ownerId, $name) {
        if (empty($ownerId) || empty($name)) {
            error_log("AccommodationService::createAccommodation - Missing required field");
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO accommodations (name, owner_id)
            VALUES (?, ?)
        ");

        if (!$stmt) {
            error_log("AccommodationService::createAccommodation - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("si", $name, $ownerId);
        
        if (!$stmt->execute()) {
            error_log("AccommodationService::createAccommodation - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $accommodationId = $stmt->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'accommodation_id' => $accommodationId,
            'name' => $name,
            'owner_id' => $ownerId
        ];
    }

    /**
     * Update accommodation details
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @param array $updateData Fields to update (name)
     * @return bool Success
     */
    public static function updateAccommodation($conn, $accommodationId, $updateData) {
        if (empty($accommodationId) || empty($updateData)) {
            return false;
        }

        $allowedFields = ['name'];
        $updateFields = [];
        $params = [];
        $types = "";

        foreach ($updateData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = ?";
                $params[] = $value;
                $types .= "s";
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $query = "UPDATE accommodations SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $params[] = $accommodationId;
        $types .= "i";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("AccommodationService::updateAccommodation - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            error_log("AccommodationService::updateAccommodation - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Delete accommodation
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @return bool Success
     */
    public static function deleteAccommodation($conn, $accommodationId) {
        if (empty($accommodationId)) {
            return false;
        }

        $stmt = $conn->prepare("DELETE FROM accommodations WHERE id = ?");

        if (!$stmt) {
            error_log("AccommodationService::deleteAccommodation - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $accommodationId);
        
        if (!$stmt->execute()) {
            error_log("AccommodationService::deleteAccommodation - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Get accommodation details
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @return array|null Accommodation data or null
     */
    public static function getAccommodation($conn, $accommodationId) {
        return QueryService::getAccommodationDetails($conn, $accommodationId);
    }

    /**
     * Assign manager to accommodation
     * 
     * @param mysqli $conn Database connection
     * @param int $managerId Manager user ID
     * @param int $accommodationId Accommodation ID
     * @return bool Success
     */
    public static function assignManager($conn, $managerId, $accommodationId) {
        if (empty($managerId) || empty($accommodationId)) {
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO user_accommodation (user_id, accommodation_id)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE user_id=user_id
        ");

        if (!$stmt) {
            error_log("AccommodationService::assignManager - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ii", $managerId, $accommodationId);
        
        if (!$stmt->execute()) {
            error_log("AccommodationService::assignManager - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Remove manager from accommodation
     * 
     * @param mysqli $conn Database connection
     * @param int $managerId Manager user ID
     * @param int $accommodationId Accommodation ID
     * @return bool Success
     */
    public static function removeManager($conn, $managerId, $accommodationId) {
        if (empty($managerId) || empty($accommodationId)) {
            return false;
        }

        $stmt = $conn->prepare("
            DELETE FROM user_accommodation
            WHERE user_id = ? AND accommodation_id = ?
        ");

        if (!$stmt) {
            error_log("AccommodationService::removeManager - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ii", $managerId, $accommodationId);
        
        if (!$stmt->execute()) {
            error_log("AccommodationService::removeManager - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Get accommodation managers
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @return array Array of manager users
     */
    public static function getManagers($conn, $accommodationId) {
        if (empty($accommodationId)) {
            return [];
        }

        $stmt = $conn->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.last_name
            FROM users u
            INNER JOIN user_accommodation ua ON u.id = ua.user_id
            WHERE ua.accommodation_id = ?
            ORDER BY u.first_name, u.last_name
        ");

        if (!$stmt) {
            error_log("AccommodationService::getManagers - Prepare error: " . $conn->error);
            return [];
        }

        $stmt->bind_param("i", $accommodationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $managers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $managers;
    }

    /**
     * Check if accommodation belongs to owner
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @param int $ownerId Owner user ID
     * @return bool True if owner, false otherwise
     */
    public static function isOwner($conn, $accommodationId, $ownerId) {
        if (empty($accommodationId) || empty($ownerId)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT id FROM accommodations
            WHERE id = ? AND owner_id = ?
        ");

        if (!$stmt) {
            error_log("AccommodationService::isOwner - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ii", $accommodationId, $ownerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $isOwner = $result->num_rows > 0;
        $stmt->close();

        return $isOwner;
    }

    /**
     * Check if user is manager for accommodation
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @param int $managerId Manager user ID
     * @return bool True if manager, false otherwise
     */
    public static function isManager($conn, $accommodationId, $managerId) {
        if (empty($accommodationId) || empty($managerId)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT id FROM user_accommodation
            WHERE user_id = ? AND accommodation_id = ?
        ");

        if (!$stmt) {
            error_log("AccommodationService::isManager - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ii", $managerId, $accommodationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $isManager = $result->num_rows > 0;
        $stmt->close();

        return $isManager;
    }

    /**
     * Get accommodation for student
     * 
     * @param mysqli $conn Database connection
     * @param int $studentUserId Student user ID
     * @return array|null Accommodation data or null
     */
    public static function getStudentAccommodation($conn, $studentUserId) {
        if (empty($studentUserId)) {
            return null;
        }

        $stmt = $conn->prepare("
            SELECT 
                a.id,
                a.name,
                a.owner_id,
                s.room_number,
                s.status
            FROM accommodations a
            INNER JOIN students s ON a.id = s.accommodation_id
            WHERE s.user_id = ?
        ");

        if (!$stmt) {
            error_log("AccommodationService::getStudentAccommodation - Prepare error: " . $conn->error);
            return null;
        }

        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $accommodation = $result->fetch_assoc();
        $stmt->close();

        return $accommodation;
    }

}

?>
