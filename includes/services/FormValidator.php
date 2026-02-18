<?php
/**
 * FormValidator Service - Centralized Form Validation Rules
 * 
 * Consolidates validation for emails, phone numbers, South African ID numbers, MAC addresses, etc.
 * Eliminates duplicate validation logic across the application.
 * 
 * Usage: FormValidator::validateEmail($email) or FormValidator::validateForm('user', $data)
 */

class FormValidator {

    private static $errors = [];

    /**
     * Validate email address format
     * 
     * @param string $email Email to validate
     * @return bool True if valid format, false otherwise
     */
    public static function validateEmail($email) {
        if (empty($email)) {
            return false;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate South African ID number (13 digits)
     * 
     * @param string $idNumber ID number to validate
     * @return bool True if valid format, false otherwise
     */
    public static function validateSouthAfricanId($idNumber) {
        if (empty($idNumber)) {
            return false;
        }

        // Remove any non-digit characters
        $idNumber = preg_replace('/[^0-9]/', '', $idNumber);

        // Must be exactly 13 digits
        if (strlen($idNumber) !== 13) {
            return false;
        }

        // Format: YYMMDDGGGGSSCAZ
        // GG = gender (0-4 female, 5-9 male)
        // SS = series
        // CA = checksums (usually 00 or 08)
        // Z = citizen indicator (0=citizen, 1=non-citizen)

        // Validate using Luhn algorithm
        $sum = 0;
        $weights = [1, 2, 3, 4, 5, 6, 7, 8, 9, 1, 2, 3, 4];

        for ($i = 0; $i < 13; $i++) {
            $digit = (int)$idNumber[$i];
            $weighted = $digit * $weights[$i];

            // If weighted value is 2 digits, add them together
            if ($weighted > 9) {
                $weighted = intdiv($weighted, 10) + ($weighted % 10);
            }

            $sum += $weighted;
        }

        // Valid if sum is divisible by 10
        return ($sum % 10) === 0;
    }

    /**
     * Validate phone number format (South African)
     * Accepts formats: 027XXXXXXXX, +27 7 XXXXXXXX, (027) XXXXXXXX, etc.
     * 
     * @param string $phone Phone number to validate
     * @return bool True if valid format, false otherwise
     */
    public static function validatePhoneNumber($phone) {
        if (empty($phone)) {
            return false;
        }

        // Remove common separators and spaces
        $clean = preg_replace('/[\s\-\(\)\.]+/', '', $phone);

        // Check if it starts with 27 or +27
        if (strpos($clean, '+27') === 0) {
            $clean = '27' . substr($clean, 3);
        }

        // Must start with 27 and have 11 digits total (27 + 9 digit number)
        if (!preg_match('/^27[0-9]{9}$/', $clean)) {
            return false;
        }

        return true;
    }

    /**
     * Validate MAC address format (48-bit address)
     * Accepts: AA:BB:CC:DD:EE:FF, AA-BB-CC-DD-EE-FF, AABBCCDDEEFF
     * 
     * @param string $mac MAC address to validate
     * @return bool True if valid format, false otherwise
     */
    public static function validateMacAddress($mac) {
        if (empty($mac)) {
            return false;
        }

        // Standard formats
        $patterns = [
            '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',  // AA:BB:CC:DD:EE:FF or AA-BB-CC-DD-EE-FF
            '/^([0-9A-Fa-f]{2}){6}$/',                        // AABBCCDDEEFF
            '/^([0-9A-Fa-f]{4}\.){2}([0-9A-Fa-f]{4})$/',      // AABB.CCDD.EEFF
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $mac)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize MAC address to standard format (lowercase with colons)
     * 
     * @param string $mac MAC address to normalize
     * @return string Normalized MAC address
     */
    public static function normalizeMacAddress($mac) {
        if (empty($mac)) {
            return '';
        }

        // Remove all separators
        $clean = strtolower(preg_replace('/[:-.]/', '', $mac));

        // Validate it's a valid 12-digit hex string
        if (!preg_match('/^[0-9a-f]{12}$/', $clean)) {
            return '';
        }

        // Return in standard AA:BB:CC:DD:EE:FF format
        return implode(':', str_split($clean, 2));
    }

    /**
     * Validate username format
     * Alphanumeric, underscores, hyphens, 3-50 characters
     * 
     * @param string $username Username to validate
     * @return bool True if valid format, false otherwise
     */
    public static function validateUsername($username) {
        if (empty($username)) {
            return false;
        }

        // 3-50 characters, alphanumeric + underscore + hyphen
        return preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $username) === 1;
    }

    /**
     * Validate password strength
     * Requires: minimum 8 characters, at least one uppercase, one lowercase, one digit
     * 
     * @param string $password Password to validate
     * @return bool True if meets requirements, false otherwise
     */
    public static function validatePassword($password) {
        if (empty($password) || strlen($password) < 8) {
            return false;
        }

        // At least one uppercase
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // At least one lowercase
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        // At least one digit
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Validate user creation/update form
     * 
     * @param array $data Form data to validate
     * @param bool $isUpdate True if updating (allows some fields to be optional)
     * @return bool True if all validations pass, false otherwise
     */
    public static function validateUserForm($data = [], $isUpdate = false) {
        self::$errors = [];

        // Required fields
        $requiredFields = ['username', 'email', 'first_name', 'last_name', 'role_id'];
        if (!$isUpdate) {
            $requiredFields[] = 'password';
        }

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                self::$errors[$field] = "This field is required";
            }
        }

        // Validate username format
        if (!empty($data['username']) && !self::validateUsername($data['username'])) {
            self::$errors['username'] = "Username must be 3-50 characters (alphanumeric, _ and - only)";
        }

        // Validate email
        if (!empty($data['email']) && !self::validateEmail($data['email'])) {
            self::$errors['email'] = "Invalid email address format";
        }

        // Validate password if provided or required
        if (!empty($data['password'])) {
            if (!self::validatePassword($data['password'])) {
                self::$errors['password'] = "Password must be at least 8 characters with uppercase, lowercase, and digit";
            }
        } elseif (!$isUpdate && empty(self::$errors['password'])) {
            self::$errors['password'] = "Password is required";
        }

        // Password confirmation
        if (!empty($data['password']) && (empty($data['password_confirm']) || $data['password'] !== $data['password_confirm'])) {
            self::$errors['password_confirm'] = "Passwords do not match";
        }

        // Validate ID number if provided
        if (!empty($data['id_number']) && !self::validateSouthAfricanId($data['id_number'])) {
            self::$errors['id_number'] = "Invalid South African ID number";
        }

        // Validate phone if provided
        if (!empty($data['phone_number']) && !self::validatePhoneNumber($data['phone_number'])) {
            self::$errors['phone_number'] = "Invalid phone number format";
        }

        // Validate role ID
        if (!empty($data['role_id']) && !is_numeric($data['role_id'])) {
            self::$errors['role_id'] = "Invalid role selection";
        }

        return empty(self::$errors);
    }

    /**
     * Validate accommodation form
     * 
     * @param array $data Form data to validate
     * @return bool True if all validations pass, false otherwise
     */
    public static function validateAccommodationForm($data = []) {
        self::$errors = [];

        // Required fields
        if (empty($data['name'])) {
            self::$errors['name'] = "Accommodation name is required";
        }

        if (empty($data['owner_id'])) {
            self::$errors['owner_id'] = "Owner selection is required";
        } elseif (!is_numeric($data['owner_id'])) {
            self::$errors['owner_id'] = "Invalid owner ID";
        }

        return empty(self::$errors);
    }

    /**
     * Validate student assignment form
     * 
     * @param array $data Form data to validate
     * @return bool True if all validations pass, false otherwise
     */
    public static function validateStudentAssignmentForm($data = []) {
        self::$errors = [];

        if (empty($data['student_id'])) {
            self::$errors['student_id'] = "Student selection is required";
        }

        if (empty($data['accommodation_id'])) {
            self::$errors['accommodation_id'] = "Accommodation is required";
        }

        if (empty($data['room_number'])) {
            self::$errors['room_number'] = "Room number is required";
        }

        return empty(self::$errors);
    }

    /**
     * Get validation errors
     * 
     * @return array Array of field => error message
     */
    public static function getErrors() {
        return self::$errors;
    }

    /**
     * Get single field error
     * 
     * @param string $field Field name
     * @return string|null Error message or null if no error
     */
    public static function getError($field) {
        return self::$errors[$field] ?? null;
    }

    /**
     * Check if field has error
     * 
     * @param string $field Field name
     * @return bool True if has error, false otherwise
     */
    public static function hasError($field) {
        return isset(self::$errors[$field]);
    }

    /**
     * Clear all errors
     */
    public static function clearErrors() {
        self::$errors = [];
    }

    /**
     * Add custom error for a field
     * 
     * @param string $field Field name
     * @param string $message Error message
     */
    public static function addError($field, $message) {
        self::$errors[$field] = $message;
    }

}

?>
