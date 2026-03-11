<?php
/**
 * ProfileChecklistService - Profile Completion Checklist Management
 * 
 * Handles all profile checklist operations:
 * - Retrieval of user checklist items
 * - Completion percentage calculation
 * - Marking tasks as complete
 * - Auto-detection of completed tasks
 * - Widget dismissal state
 * 
 * Usage: ProfileChecklistService::getChecklistForUser($conn, $userId);
 */

class ProfileChecklistService {

    /**
     * Role-specific checklist definitions with display labels and action links
     */
    private static $CHECKLIST_DEFINITIONS = [
        'admin' => [
            'admin.create_super_admin' => ['label' => 'Create Super Admin Account', 'link' => null],
            'admin.configure_system' => ['label' => 'Configure System Settings', 'link' => null],
            'admin.review_security' => ['label' => 'Review Security Settings', 'link' => null, 'optional' => true],
            'admin.test_notifications' => ['label' => 'Test Email & SMS Notifications', 'link' => null, 'optional' => true],
        ],
        'owner' => [
            'owner.complete_profile' => ['label' => 'Complete Your Profile', 'link' => '/public/profile.php'],
            'owner.create_accommodation' => ['label' => 'Create Your First Accommodation', 'link' => '/public/create-accommodation.php'],
            'owner.assign_manager' => ['label' => 'Assign a Manager', 'link' => '/public/accommodations.php'],
            'owner.upload_profile_photo' => ['label' => 'Upload Profile Photo', 'link' => '/public/profile.php', 'optional' => true],
            'owner.configure_notifications' => ['label' => 'Configure Notification Preferences', 'link' => '/public/notifications.php', 'optional' => true],
        ],
        'manager' => [
            'manager.complete_profile' => ['label' => 'Complete Your Profile', 'link' => '/public/profile.php'],
            'manager.view_accommodation' => ['label' => 'View Your Accommodation', 'link' => '/public/dashboard.php'],
            'manager.generate_student_code' => ['label' => 'Generate First Student Code', 'link' => '/public/codes.php'],
            'manager.send_first_voucher' => ['label' => 'Send Your First Voucher', 'link' => '/public/send-vouchers.php'],
            'manager.upload_profile_photo' => ['label' => 'Upload Profile Photo', 'link' => '/public/profile.php', 'optional' => true],
            'manager.configure_notifications' => ['label' => 'Configure Notification Preferences', 'link' => '/public/notifications.php', 'optional' => true],
        ],
        'student' => [
            'student.complete_onboarding' => ['label' => 'Complete Onboarding Process', 'link' => '/public/onboard.php'],
            'student.complete_profile' => ['label' => 'Complete Your Profile', 'link' => '/public/profile.php'],
            'student.request_voucher' => ['label' => 'Request Your First WiFi Voucher', 'link' => '/public/student/request-voucher.php'],
            'student.connect_device' => ['label' => 'Connect Your First Device', 'link' => '/public/student/devices.php'],
            'student.upload_profile_photo' => ['label' => 'Upload Profile Photo', 'link' => '/public/profile.php', 'optional' => true],
            'student.configure_notifications' => ['label' => 'Configure Notification Preferences', 'link' => '/public/notifications.php', 'optional' => true],
            'student.read_help_docs' => ['label' => 'Read Help Documentation', 'link' => '/public/help.php', 'optional' => true],
        ],
    ];

    /**
     * Get role name from role_id
     */
    private static function getRoleNameById($roleId) {
        $roleMap = [1 => 'admin', 2 => 'owner', 3 => 'manager', 4 => 'student'];
        return $roleMap[$roleId] ?? null;
    }

    /**
     * Get checklist items for a specific user
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return array Array of checklist items with status, label, link, completion timestamp
     */
    public static function getChecklistForUser($conn, $userId) {
        // Get user role
        $stmt = safeQueryPrepare($conn, "SELECT role_id FROM users WHERE id = ?");
        if (!$stmt) return [];
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) return [];
        
        $roleName = self::getRoleNameById($user['role_id']);
        if (!$roleName || !isset(self::$CHECKLIST_DEFINITIONS[$roleName])) return [];

        // Get user's checklist completion status from database
        $stmt = safeQueryPrepare($conn, "
            SELECT checklist_key, completed, completed_at 
            FROM profile_checklist 
            WHERE user_id = ?
        ");
        
        if (!$stmt) return [];
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $completionStatus = [];
        while ($row = $result->fetch_assoc()) {
            $completionStatus[$row['checklist_key']] = [
                'completed' => (bool)$row['completed'],
                'completed_at' => $row['completed_at']
            ];
        }
        $stmt->close();

        // Build checklist with definitions
        $checklist = [];
        foreach (self::$CHECKLIST_DEFINITIONS[$roleName] as $key => $definition) {
            $status = $completionStatus[$key] ?? ['completed' => false, 'completed_at' => null];
            $checklist[] = [
                'key' => $key,
                'label' => $definition['label'],
                'link' => $definition['link'],
                'optional' => $definition['optional'] ?? false,
                'completed' => $status['completed'],
                'completed_at' => $status['completed_at']
            ];
        }

        return $checklist;
    }

    /**
     * Get completion percentage for a user (excludes optional tasks)
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return float Percentage (0-100)
     */
    public static function getCompletionPercentage($conn, $userId) {
        $checklist = self::getChecklistForUser($conn, $userId);
        
        if (empty($checklist)) return 100.0; // No checklist means 100% complete
        
        // Filter out optional tasks for percentage calculation
        $requiredTasks = array_filter($checklist, function($item) {
            return !$item['optional'];
        });
        
        if (empty($requiredTasks)) return 100.0;
        
        $totalRequired = count($requiredTasks);
        $completedRequired = count(array_filter($requiredTasks, function($item) {
            return $item['completed'];
        }));
        
        return round(($completedRequired / $totalRequired) * 100, 1);
    }

    /**
     * Get count of incomplete required tasks
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return int Number of incomplete required tasks
     */
    public static function getIncompleteCount($conn, $userId) {
        $checklist = self::getChecklistForUser($conn, $userId);
        
        $requiredTasks = array_filter($checklist, function($item) {
            return !$item['optional'];
        });
        
        $incompleteTasks = array_filter($requiredTasks, function($item) {
            return !$item['completed'];
        });
        
        return count($incompleteTasks);
    }

    /**
     * Mark a checklist item as complete
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @param string $checklistKey Checklist key (e.g., 'owner.create_accommodation')
     * @return bool Success status
     */
    public static function markComplete($conn, $userId, $checklistKey) {
        $stmt = safeQueryPrepare($conn, "
            UPDATE profile_checklist 
            SET completed = TRUE, completed_at = NOW(), updated_at = NOW()
            WHERE user_id = ? AND checklist_key = ? AND completed = FALSE
        ");
        
        if (!$stmt) return false;
        
        $stmt->bind_param("is", $userId, $checklistKey);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Auto-detect and mark completed tasks based on database state
     * Called after user performs actions (create accommodation, request voucher, etc.)
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return int Number of tasks auto-completed
     */
    public static function autoCheckTasks($conn, $userId) {
        $autoCompleted = 0;

        // Get user role
        $stmt = safeQueryPrepare($conn, "SELECT role_id FROM users WHERE id = ?");
        if (!$stmt) return 0;
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) return 0;

        // Check profile completion (all roles)
        $stmt = safeQueryPrepare($conn, "
            SELECT first_name, last_name, email FROM users WHERE id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $profile = $result->fetch_assoc();
            $stmt->close();
            
            if ($profile && !empty($profile['first_name']) && !empty($profile['last_name']) && !empty($profile['email'])) {
                $roleName = self::getRoleNameById($user['role_id']);
                $key = $roleName . '.complete_profile';
                if (self::markComplete($conn, $userId, $key)) $autoCompleted++;
            }
        }

        // Owner-specific checks
        if ($user['role_id'] == 2) {
            // Check accommodation creation
            $stmt = safeQueryPrepare($conn, "SELECT id FROM accommodations WHERE owner_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if (self::markComplete($conn, $userId, 'owner.create_accommodation')) $autoCompleted++;
                }
                $stmt->close();
            }

            // Check manager assignment
            $stmt = safeQueryPrepare($conn, "SELECT id FROM user_accommodation WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if (self::markComplete($conn, $userId, 'owner.assign_manager')) $autoCompleted++;
                }
                $stmt->close();
            }
        }

        // Manager-specific checks
        if ($user['role_id'] == 3) {
            // Check view accommodation (assigned to accommodation)
            $stmt = safeQueryPrepare($conn, "SELECT id FROM user_accommodation WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if (self::markComplete($conn, $userId, 'manager.view_accommodation')) $autoCompleted++;
                }
                $stmt->close();
            }

            // Check code generation
            $stmt = safeQueryPrepare($conn, "SELECT id FROM onboarding_codes WHERE manager_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if (self::markComplete($conn, $userId, 'manager.generate_student_code')) $autoCompleted++;
                }
                $stmt->close();
            }

            // Check voucher sending
            $stmt = safeQueryPrepare($conn, "SELECT id FROM voucher_logs WHERE created_by_user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if (self::markComplete($conn, $userId, 'manager.send_first_voucher')) $autoCompleted++;
                }
                $stmt->close();
            }
        }

        // Student-specific checks
        if ($user['role_id'] == 4) {
            // Check onboarding completion
            $stmt = safeQueryPrepare($conn, "SELECT id FROM students WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if (self::markComplete($conn, $userId, 'student.complete_onboarding')) $autoCompleted++;
                }
                $stmt->close();
            }

            // Check voucher request
            $stmt = safeQueryPrepare($conn, "SELECT id FROM voucher_logs WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if (self::markComplete($conn, $userId, 'student.request_voucher')) $autoCompleted++;
                }
                $stmt->close();
            }

            // Check device connection
            $stmt = safeQueryPrepare($conn, "SELECT id FROM user_devices WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    if (self::markComplete($conn, $userId, 'student.connect_device')) $autoCompleted++;
                }
                $stmt->close();
            }
        }

        return $autoCompleted;
    }

    /**
     * Check if user has dismissed the checklist widget
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return bool True if dismissed
     */
    public static function isWidgetDismissed($conn, $userId) {
        $stmt = safeQueryPrepare($conn, "
            SELECT checklist_widget_dismissed 
            FROM user_preferences 
            WHERE user_id = ?
        ");
        
        if (!$stmt) return false;
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        // If no record exists, create one with default values
        if (!$row) {
            self::ensureUserPreferences($conn, $userId);
            return false; // New record defaults to not dismissed
        }
        
        return $row && $row['checklist_widget_dismissed'] == 1;
    }

    /**
     * Set widget dismissed state
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @param bool $dismissed Dismissed state
     * @return bool Success status
     */
    public static function setWidgetDismissed($conn, $userId, $dismissed = true) {
        // Ensure user_preferences record exists
        self::ensureUserPreferences($conn, $userId);
        
        $dismissedInt = $dismissed ? 1 : 0;
        
        $stmt = safeQueryPrepare($conn, "
            UPDATE user_preferences 
            SET checklist_widget_dismissed = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        
        if (!$stmt) return false;
        
        $stmt->bind_param("ii", $dismissedInt, $userId);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Ensure user_preferences record exists for a user
     * Creates default record if not exists
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return bool Success status
     */
    private static function ensureUserPreferences($conn, $userId) {
        $stmt = safeQueryPrepare($conn, "
            INSERT IGNORE INTO user_preferences (user_id) 
            VALUES (?)
        ");
        
        if (!$stmt) return false;
        
        $stmt->bind_param("i", $userId);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Get incomplete checklist items (for widget display)
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return array Array of incomplete checklist items
     */
    public static function getIncompleteItems($conn, $userId) {
        $checklist = self::getChecklistForUser($conn, $userId);
        
        return array_filter($checklist, function($item) {
            return !$item['completed'];
        });
    }
}
