<?php
/**
 * DeviceManagementService - WiFi Device Management
 * 
 * Handles WiFi device registration, blocking, unblocking, and lifecycle management.
 * 
 * Usage: DeviceManagementService::registerDevice($conn, $userId, $deviceType, $mac);
 */

class DeviceManagementService {

    /**
     * Register a new device for user
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID (student)
     * @param string $deviceType Device type (e.g., 'Laptop', 'Phone', 'Tablet')
     * @param string $macAddress MAC address
     * @return array|false Array with device info, or false on failure
     */
    public static function registerDevice($conn, $userId, $deviceType, $macAddress) {
        if (empty($userId) || empty($deviceType) || empty($macAddress)) {
            error_log("DeviceManagementService::registerDevice - Missing required field");
            return false;
        }

        // Validate MAC address format
        if (!FormValidator::validateMacAddress($macAddress)) {
            error_log("DeviceManagementService::registerDevice - Invalid MAC format: $macAddress");
            return false;
        }

        // Normalize MAC address
        $normalizedMac = FormValidator::normalizeMacAddress($macAddress);

        // Check for duplicate MAC
        if (self::macAddressExists($conn, $normalizedMac)) {
            error_log("DeviceManagementService::registerDevice - MAC already registered: $normalizedMac");
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO user_devices (user_id, device_type, mac_address)
            VALUES (?, ?, ?)
        ");

        if (!$stmt) {
            error_log("DeviceManagementService::registerDevice - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("iss", $userId, $deviceType, $normalizedMac);
        
        if (!$stmt->execute()) {
            error_log("DeviceManagementService::registerDevice - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $deviceId = $stmt->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'device_id' => $deviceId,
            'user_id' => $userId,
            'device_type' => $deviceType,
            'mac_address' => $normalizedMac
        ];
    }

    /**
     * Get user's devices
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return array Array of devices
     */
    public static function getUserDevices($conn, $userId) {
        if (empty($userId)) {
            return [];
        }

        $stmt = $conn->prepare("
            SELECT 
                id, user_id, device_type, mac_address, created_at
            FROM user_devices
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");

        if (!$stmt) {
            error_log("DeviceManagementService::getUserDevices - Prepare error: " . $conn->error);
            return [];
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $devices = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $devices;
    }

    /**
     * Get device details
     * 
     * @param mysqli $conn Database connection
     * @param int $deviceId Device ID
     * @return array|null Device data or null if not found
     */
    public static function getDevice($conn, $deviceId) {
        if (empty($deviceId)) {
            return null;
        }

        $stmt = $conn->prepare("
            SELECT 
                id, user_id, device_type, mac_address, created_at
            FROM user_devices
            WHERE id = ?
        ");

        if (!$stmt) {
            error_log("DeviceManagementService::getDevice - Prepare error: " . $conn->error);
            return null;
        }

        $stmt->bind_param("i", $deviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        $device = $result->fetch_assoc();
        $stmt->close();

        return $device;
    }

    /**
     * Get device by MAC address
     * 
     * @param mysqli $conn Database connection
     * @param string $macAddress MAC address to lookup
     * @return array|null Device data or null if not found
     */
    public static function getDeviceByMac($conn, $macAddress) {
        if (empty($macAddress)) {
            return null;
        }

        $normalizedMac = FormValidator::normalizeMacAddress($macAddress);

        $stmt = $conn->prepare("
            SELECT id, user_id, device_type, mac_address, created_at
            FROM user_devices
            WHERE mac_address = ?
        ");

        if (!$stmt) {
            error_log("DeviceManagementService::getDeviceByMac - Prepare error: " . $conn->error);
            return null;
        }

        $stmt->bind_param("s", $normalizedMac);
        $stmt->execute();
        $result = $stmt->get_result();
        $device = $result->fetch_assoc();
        $stmt->close();

        return $device;
    }

    /**
     * Check if MAC address is already registered
     * 
     * @param mysqli $conn Database connection
     * @param string $macAddress MAC address to check
     * @return bool True if registered, false otherwise
     */
    public static function macAddressExists($conn, $macAddress) {
        if (empty($macAddress)) {
            return false;
        }

        $normalizedMac = FormValidator::normalizeMacAddress($macAddress);

        $stmt = $conn->prepare("
            SELECT id FROM user_devices WHERE mac_address = ?
        ");

        if (!$stmt) {
            error_log("DeviceManagementService::macAddressExists - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("s", $normalizedMac);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Delete device
     * 
     * @param mysqli $conn Database connection
     * @param int $deviceId Device ID
     * @return bool Success
     */
    public static function deleteDevice($conn, $deviceId) {
        if (empty($deviceId)) {
            return false;
        }

        $stmt = $conn->prepare("DELETE FROM user_devices WHERE id = ?");

        if (!$stmt) {
            error_log("DeviceManagementService::deleteDevice - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $deviceId);
        
        if (!$stmt->execute()) {
            error_log("DeviceManagementService::deleteDevice - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Update device info
     * 
     * @param mysqli $conn Database connection
     * @param int $deviceId Device ID
     * @param array $updateData Fields to update (device_type)
     * @return bool Success
     */
    public static function updateDevice($conn, $deviceId, $updateData) {
        if (empty($deviceId) || empty($updateData)) {
            return false;
        }

        $allowedFields = ['device_type'];
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

        $query = "UPDATE user_devices SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $params[] = $deviceId;
        $types .= "i";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("DeviceManagementService::updateDevice - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            error_log("DeviceManagementService::updateDevice - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Check if device belongs to user
     * 
     * @param mysqli $conn Database connection
     * @param int $deviceId Device ID
     * @param int $userId User ID
     * @return bool True if device belongs to user, false otherwise
     */
    public static function deviceBelongsToUser($conn, $deviceId, $userId) {
        if (empty($deviceId) || empty($userId)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT id FROM user_devices
            WHERE id = ? AND user_id = ?
        ");

        if (!$stmt) {
            error_log("DeviceManagementService::deviceBelongsToUser - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ii", $deviceId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $belongs = $result->num_rows > 0;
        $stmt->close();

        return $belongs;
    }

    /**
     * Get device count for user
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return int Device count
     */
    public static function getDeviceCount($conn, $userId) {
        if (empty($userId)) {
            return 0;
        }

        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM user_devices WHERE user_id = ?
        ");

        if (!$stmt) {
            error_log("DeviceManagementService::getDeviceCount - Prepare error: " . $conn->error);
            return 0;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)($row['count'] ?? 0);
    }

}

?>
