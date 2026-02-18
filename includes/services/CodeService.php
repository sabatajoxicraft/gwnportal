<?php
/**
 * CodeService - Invitation/Onboarding Code Management
 * 
 * Handles creation, validation, and tracking of onboarding invitation codes
 * used for user registration.
 * 
 * Usage: CodeService::generateCode($conn, $createdBy, $accommodationId, $roleId);
 */

class CodeService {

    /**
     * Generate a new invitation code
     * 
     * @param mysqli $conn Database connection
     * @param int $createdBy User ID of creator
     * @param int $accommodationId Target accommodation
     * @param int $roleId Target role ID
     * @param int $expirationDays Days until expiration (default: 7)
     * @return array|false Array with code and details, or false on failure
     */
    public static function generateCode($conn, $createdBy, $accommodationId, $roleId, $expirationDays = 7) {
        if (empty($createdBy) || empty($accommodationId) || empty($roleId)) {
            error_log("CodeService::generateCode - Missing required field");
            return false;
        }

        // Generate unique code: format XXXX-XXXX-XXXX-XX
        $code = self::generateUniqueCode($conn);
        if (!$code) {
            return false;
        }

        $expirationDate = date('Y-m-d H:i:s', strtotime("+$expirationDays days"));

        $stmt = $conn->prepare("
            INSERT INTO onboarding_codes (
                code, created_by, accommodation_id, role_id, expires_at, status
            ) VALUES (?, ?, ?, ?, ?, 'unused')
        ");

        if (!$stmt) {
            error_log("CodeService::generateCode - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ssiss", $code, $createdBy, $accommodationId, $roleId, $expirationDate);
        
        if (!$stmt->execute()) {
            error_log("CodeService::generateCode - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $codeId = $stmt->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'code_id' => $codeId,
            'code' => $code,
            'expires_at' => $expirationDate,
            'role_id' => $roleId
        ];
    }

    /**
     * Validate and use an invitation code (mark as used)
     * 
     * @param mysqli $conn Database connection
     * @param string $code Code to validate
     * @param int $userId User ID using the code
     * @return array|false Array with code details if valid, false otherwise
     */
    public static function validateAndUseCode($conn, $code, $userId) {
        if (empty($code) || empty($userId)) {
            return false;
        }

        // Get code details
        $codeData = QueryService::getOnboardingCode($conn, $code);
        
        if (!$codeData) {
            error_log("CodeService::validateAndUseCode - Code not found: $code");
            return false;
        }

        // Check if already used
        if ($codeData['status'] === 'used') {
            error_log("CodeService::validateAndUseCode - Code already used: $code");
            return false;
        }

        // Check if expired
        if ($codeData['status'] === 'expired' || (strtotime($codeData['expires_at']) < time())) {
            error_log("CodeService::validateAndUseCode - Code expired: $code");
            return false;
        }

        // Mark code as used
        $stmt = $conn->prepare("
            UPDATE onboarding_codes
            SET status = 'used', used_by = ?, used_at = NOW()
            WHERE code = ?
        ");

        if (!$stmt) {
            error_log("CodeService::validateAndUseCode - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("is", $userId, $code);
        
        if (!$stmt->execute()) {
            error_log("CodeService::validateAndUseCode - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();

        return $codeData;
    }

    /**
     * Check code validity (without using it)
     * 
     * @param mysqli $conn Database connection
     * @param string $code Code to check
     * @return array|false Code data if valid, false otherwise
     */
    public static function validateCode($conn, $code) {
        if (empty($code)) {
            return false;
        }

        $codeData = QueryService::getOnboardingCode($conn, $code);
        
        if (!$codeData) {
            return false;
        }

        // Check if already used
        if ($codeData['status'] === 'used') {
            return false;
        }

        // Check if expired
        if ($codeData['status'] === 'expired' || (strtotime($codeData['expires_at']) < time())) {
            return false;
        }

        return $codeData;
    }

    /**
     * Revoke an invitation code (mark as expired)
     * 
     * @param mysqli $conn Database connection
     * @param int $codeId Code ID to revoke
     * @return bool Success
     */
    public static function revokeCode($conn, $codeId) {
        if (empty($codeId)) {
            return false;
        }

        $stmt = $conn->prepare("
            UPDATE onboarding_codes
            SET status = 'expired'
            WHERE id = ? AND status = 'unused'
        ");

        if (!$stmt) {
            error_log("CodeService::revokeCode - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $codeId);
        
        if (!$stmt->execute()) {
            error_log("CodeService::revokeCode - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Get codes for accommodation
     * 
     * @param mysqli $conn Database connection
     * @param int $accommodationId Accommodation ID
     * @param string $status Optional filter: 'unused', 'used', 'expired'
     * @return array Array of codes
     */
    public static function getAccommodationCodes($conn, $accommodationId, $status = null) {
        if (empty($accommodationId)) {
            return [];
        }

        $query = "
            SELECT 
                oc.*,
                u_creator.username AS creator_username,
                u_user.username AS used_by_username,
                r.name AS role_name
            FROM onboarding_codes oc
            LEFT JOIN users u_creator ON oc.created_by = u_creator.id
            LEFT JOIN users u_user ON oc.used_by = u_user.id
            LEFT JOIN roles r ON oc.role_id = r.id
            WHERE oc.accommodation_id = ?
        ";

        $params = [$accommodationId];
        $types = "i";

        if ($status !== null) {
            $query .= " AND oc.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $query .= " ORDER BY oc.created_at DESC";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("CodeService::getAccommodationCodes - Prepare error: " . $conn->error);
            return [];
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $codes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $codes;
    }

    /**
     * Delete old expired codes (cleanup)
     * 
     * @param mysqli $conn Database connection
     * @param int $daysOld Delete codes expired more than N days ago (default: 30)
     * @return int Number of deleted codes
     */
    public static function cleanupExpiredCodes($conn, $daysOld = 30) {
        $stmt = $conn->prepare("
            DELETE FROM onboarding_codes
            WHERE status = 'expired'
            AND expires_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");

        if (!$stmt) {
            error_log("CodeService::cleanupExpiredCodes - Prepare error: " . $conn->error);
            return 0;
        }

        $stmt->bind_param("i", $daysOld);
        
        if (!$stmt->execute()) {
            error_log("CodeService::cleanupExpiredCodes - Execute error: " . $stmt->error);
            $stmt->close();
            return 0;
        }

        $count = $stmt->affected_rows;
        $stmt->close();

        return $count;
    }

    /**
     * Generate a unique invitation code
     * Format: XXXX-XXXX-XXXX-XX (uppercase alphanumeric)
     * 
     * @param mysqli $conn Database connection
     * @return string|false Unique code or false if generation fails
     */
    private static function generateUniqueCode($conn) {
        // Max attempts to generate unique code
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = self::generateCodeString();

            // Check if code already exists
            $stmt = $conn->prepare("SELECT id FROM onboarding_codes WHERE code = ?");
            if (!$stmt) {
                continue;
            }

            $stmt->bind_param("s", $code);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();

            if (!$exists) {
                return $code; // Found unique code
            }
        }

        // Failed to generate unique code
        error_log("CodeService::generateUniqueCode - Failed to generate unique code after $maxAttempts attempts");
        return false;
    }

    /**
     * Generate code string format
     * 
     * @return string Code in format XXXX-XXXX-XXXX-XX
     */
    private static function generateCodeString() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        // Format: 4-4-4-2 characters
        $lengths = [4, 4, 4, 2];
        
        foreach ($lengths as $length) {
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
            $code .= '-';
        }

        // Remove trailing dash
        return rtrim($code, '-');
    }

}

?>
