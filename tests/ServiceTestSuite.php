<?php
/**
 * Service Test Suite - Unit Tests for All Services
 * 
 * Tests all services to ensure proper functionality.
 * Run: php tests/ServiceTestSuite.php
 */

class ServiceTestResult {
    public $passed = 0;
    public $failed = 0;
    public $errors = [];
}

class ServiceTestSuite {

    private static $result;
    private static $testConn;

    /**
     * Run all service tests
     * 
     * @return ServiceTestResult Test results
     */
    public static function runAllTests() {
        self::$result = new ServiceTestResult();
        
        // Initialize test database connection
        self::initTestDatabase();
        
        if (!self::$testConn) {
            self::addError('Failed to connect to test database');
            return self::$result;
        }

        echo "Running Service Test Suite\n";
        echo str_repeat("=", 60) . "\n\n";

        // Run service tests
        self::testUserService();
        self::testAccommodationService();
        self::testCodeService();
        self::testStudentService();
        self::testDeviceManagementService();
        self::testFormValidator();
        self::testQueryService();
        self::testActivityLogger();
        self::testPermissionHelper();
        self::testResponse();

        // Print results
        self::printResults();

        return self::$result;
    }

    private static function initTestDatabase() {
        // Use test database
        self::$testConn = new mysqli('localhost', 'root', '', 'gwn_wifi_system_test');
        
        if (self::$testConn->connect_error) {
            // Try to create test database from main
            $mainConn = new mysqli('localhost', 'root', '', 'gwn_wifi_system');
            if ($mainConn->connect_error) {
                self::addError("Cannot connect to test database");
                return;
            }
            
            // Create test database
            $mainConn->query("CREATE DATABASE IF NOT EXISTS gwn_wifi_system_test");
            
            // Load schema
            $schema = file_get_contents(__DIR__ . '/../db/schema.sql');
            $mainConn->multi_query($schema);
            while ($mainConn->more_results()) {
                $mainConn->next_result();
            }
            
            self::$testConn = new mysqli('localhost', 'root', '', 'gwn_wifi_system_test');
        }
    }

    private static function testUserService() {
        echo "Testing UserService...\n";
        
        // Test: Create user
        $userData = [
            'username' => 'testuser1',
            'email' => 'test1@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 4  // Student
        ];
        
        $user = UserService::createUser(self::$testConn, $userData);
        self::assertTrue($user !== false, "Create user");
        
        // Test: Get user
        $retrieved = UserService::getUser(self::$testConn, $user['id']);
        self::assertTrue($retrieved !== false, "Get user");
        
        // Test: Username exists check
        $exists = UserService::usernameExists(self::$testConn, 'testuser1');
        self::assertTrue($exists, "Username exists check");
        
        // Test: Authenticate
        $auth = UserService::authenticate(self::$testConn, 'testuser1', 'TestPassword123!');
        self::assertTrue($auth !== false, "User authentication");
        
        // Test: Wrong password
        $wrongAuth = UserService::authenticate(self::$testConn, 'testuser1', 'WrongPassword');
        self::assertFalse($wrongAuth, "Wrong password fails");
        
        // Test: Change password
        $changed = UserService::changePassword(self::$testConn, $user['id'], 'NewPassword456!');
        self::assertTrue($changed, "Change password");
        
        // Test: Set status
        $status = UserService::setStatus(self::$testConn, $user['id'], 'active');
        self::assertTrue($status, "Set user status");
    }

    private static function testAccommodationService() {
        echo "Testing AccommodationService...\n";
        
        // Create owner user first
        $owner = UserService::createUser(self::$testConn, [
            'username' => 'owner1',
            'email' => 'owner1@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 2  // Owner
        ]);
        
        // Test: Create accommodation
        $accom = AccommodationService::createAccommodation(
            self::$testConn,
            $owner['id'],
            'Test Accommodation'
        );
        self::assertTrue($accom !== false, "Create accommodation");
        
        // Test: Get accommodation
        $retrieved = AccommodationService::getAccommodation(self::$testConn, $accom['id']);
        self::assertTrue($retrieved !== false, "Get accommodation");
        
        // Test: Check ownership
        $isOwner = AccommodationService::isOwner(self::$testConn, $accom['id'], $owner['id']);
        self::assertTrue($isOwner, "Check accommodation ownership");
        
        // Create manager user
        $manager = UserService::createUser(self::$testConn, [
            'username' => 'manager1',
            'email' => 'manager1@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 3  // Manager
        ]);
        
        // Test: Assign manager
        $assigned = AccommodationService::assignManager(self::$testConn, $manager['id'], $accom['id']);
        self::assertTrue($assigned, "Assign manager to accommodation");
        
        // Test: Check manager assignment
        $isManager = AccommodationService::isManager(self::$testConn, $accom['id'], $manager['id']);
        self::assertTrue($isManager, "Check manager assignment");
        
        // Test: Get managers
        $managers = AccommodationService::getManagers(self::$testConn, $accom['id']);
        self::assertTrue(count($managers) > 0, "Get accommodation managers");
    }

    private static function testCodeService() {
        echo "Testing CodeService...\n";
        
        // Need accommodation for code
        $owner = UserService::createUser(self::$testConn, [
            'username' => 'codeowner',
            'email' => 'codeowner@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 2
        ]);
        
        $accom = AccommodationService::createAccommodation(self::$testConn, $owner['id'], 'Code Test');
        
        // Test: Generate code
        $code = CodeService::generateCode(
            self::$testConn,
            $owner['id'],
            $accom['id'],
            4,  // Student role
            7   // 7 days expiry
        );
        self::assertTrue($code !== false, "Generate onboarding code");
        
        // Test: Validate code
        $validated = CodeService::validateCode(self::$testConn, $code['code']);
        self::assertTrue($validated !== false, "Validate code");
        
        // Test: Use code
        $student = UserService::createUser(self::$testConn, [
            'username' => 'student1',
            'email' => 'student1@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 4
        ]);
        
        $used = CodeService::validateAndUseCode(self::$testConn, $code['code'], $student['id']);
        self::assertTrue($used, "Use onboarding code");
        
        // Test: Code marked as used
        $check = CodeService::validateCode(self::$testConn, $code['code']);
        self::assertFalse($check, "Used code validation fails");
    }

    private static function testStudentService() {
        echo "Testing StudentService...\n";
        
        // Create accommodation and student
        $owner = UserService::createUser(self::$testConn, [
            'username' => 'studentowner',
            'email' => 'studentowner@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 2
        ]);
        
        $accom = AccommodationService::createAccommodation(self::$testConn, $owner['id'], 'Student Test');
        
        $student = UserService::createUser(self::$testConn, [
            'username' => 'student2',
            'email' => 'student2@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 4
        ]);
        
        // Test: Register student
        $registered = StudentService::registerStudent(
            self::$testConn,
            $student['id'],
            $accom['id'],
            '101'
        );
        self::assertTrue($registered !== false, "Register student");
        
        // Test: Get student record
        $record = StudentService::getStudentRecord(self::$testConn, $student['id']);
        self::assertTrue($record !== false, "Get student record");
        
        // Test: Check is student
        $isStudent = StudentService::isStudent(self::$testConn, $student['id']);
        self::assertTrue($isStudent, "Check student status");
        
        // Test: Update room
        $roomUpdated = StudentService::updateRoomAssignment(self::$testConn, $student['id'], '201');
        self::assertTrue($roomUpdated, "Update room assignment");
        
        // Test: Set status
        $statusSet = StudentService::setStatus(self::$testConn, $student['id'], 'active');
        self::assertTrue($statusSet, "Set student status");
    }

    private static function testDeviceManagementService() {
        echo "Testing DeviceManagementService...\n";
        
        // Create student for device
        $student = UserService::createUser(self::$testConn, [
            'username' => 'deviceuser',
            'email' => 'deviceuser@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 4
        ]);
        
        // Test: Register device
        $device = DeviceManagementService::registerDevice(
            self::$testConn,
            $student['id'],
            'Laptop',
            'AA:BB:CC:DD:EE:FF'
        );
        self::assertTrue($device !== false, "Register device");
        
        // Test: Get device
        $retrieved = DeviceManagementService::getDevice(self::$testConn, $device['id']);
        self::assertTrue($retrieved !== false, "Get device");
        
        // Test: Get device by MAC
        $byMac = DeviceManagementService::getDeviceByMac(self::$testConn, 'AA:BB:CC:DD:EE:FF');
        self::assertTrue($byMac !== false, "Get device by MAC");
        
        // Test: Check MAC exists
        $exists = DeviceManagementService::macAddressExists(self::$testConn, 'AA:BB:CC:DD:EE:FF');
        self::assertTrue($exists, "MAC address exists check");
        
        // Test: Get user devices
        $devices = DeviceManagementService::getUserDevices(self::$testConn, $student['id']);
        self::assertTrue(count($devices) > 0, "Get user devices");
        
        // Test: Device count
        $count = DeviceManagementService::getDeviceCount(self::$testConn, $student['id']);
        self::assertTrue($count >= 1, "Get device count");
    }

    private static function testFormValidator() {
        echo "Testing FormValidator...\n";
        
        $validator = new FormValidator();
        
        // Test: Valid email
        $valid = $validator->validateEmail('test@example.com');
        self::assertTrue($valid, "Valid email");
        
        // Test: Invalid email
        $invalid = $validator->validateEmail('invalid-email');
        self::assertFalse($invalid, "Invalid email");
        
        // Test: Valid phone
        $validPhone = $validator->validatePhoneNumber('+27123456789');
        self::assertTrue($validPhone, "Valid phone");
        
        // Test: Invalid phone
        $invalidPhone = $validator->validatePhoneNumber('123456');
        self::assertFalse($invalidPhone, "Invalid phone");
        
        // Test: Valid MAC address
        $validMac = $validator->validateMacAddress('AA:BB:CC:DD:EE:FF');
        self::assertTrue($validMac, "Valid MAC address");
        
        // Test: Normalize MAC
        $normalized = $validator->normalizeMacAddress('aabbccddeeff');
        self::assertTrue($normalized === 'AA:BB:CC:DD:EE:FF', "Normalize MAC");
        
        // Test: Password strength
        $strongPassword = $validator->validatePassword('StrongPass123!');
        self::assertTrue($strongPassword, "Strong password");
        
        // Test: Error tracking
        $validator->addError('test_field', 'Test error');
        self::assertTrue($validator->hasError('test_field'), "Error tracking");
    }

    private static function testQueryService() {
        echo "Testing QueryService...\n";
        
        // Create test data
        $owner = UserService::createUser(self::$testConn, [
            'username' => 'queryowner',
            'email' => 'queryowner@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 2
        ]);
        
        $accom = AccommodationService::createAccommodation(self::$testConn, $owner['id'], 'Query Test');
        
        // Test: Get accommodation details
        $details = QueryService::getAccommodationDetails(self::$testConn, $accom['id']);
        self::assertTrue($details !== false, "Get accommodation details");
        
        // Test: Get user with role
        $userRole = QueryService::getUserWithRole(self::$testConn, $owner['id']);
        self::assertTrue($userRole !== false, "Get user with role");
        
        // Test: Get user by username
        $byUsername = QueryService::getUserByUsername(self::$testConn, 'queryowner');
        self::assertTrue($byUsername !== false, "Get user by username");
        
        // Test: Get user accommodations
        $userAccoms = QueryService::getUserAccommodations(self::$testConn, $owner['id'], 2);
        self::assertTrue(is_array($userAccoms), "Get user accommodations");
    }

    private static function testActivityLogger() {
        echo "Testing ActivityLogger...\n";
        
        // Create test user
        $user = UserService::createUser(self::$testConn, [
            'username' => 'loguser',
            'email' => 'loguser@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 4
        ]);
        
        // Mock session for logging
        $_SESSION['user_id'] = $user['id'];
        
        // Test: Log action
        $logged = ActivityLogger::logAction($user['id'], 'test_action', ['test' => 'data'], '127.0.0.1');
        self::assertTrue($logged, "Log action");
        
        // Test: Log device action
        $deviceLogged = ActivityLogger::logDeviceAction($user['id'], 'test', 1, ['device_id' => 1]);
        self::assertTrue($deviceLogged, "Log device action");
        
        // Test: Log auth event
        $authLogged = ActivityLogger::logAuthEvent($user['id'], 'login', ['ip' => '127.0.0.1']);
        self::assertTrue($authLogged, "Log auth event");
    }

    private static function testPermissionHelper() {
        echo "Testing PermissionHelper...\n";
        
        // Create users with different roles
        $admin = UserService::createUser(self::$testConn, [
            'username' => 'permadmin',
            'email' => 'permadmin@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 1
        ]);
        
        $owner = UserService::createUser(self::$testConn, [
            'username' => 'permowner',
            'email' => 'permowner@example.com',
            'password' => 'TestPassword123!',
            'role_id' => 2
        ]);
        
        // Test: Role checks
        self::assertTrue(PermissionHelper::isAdmin($admin['id']), "Admin check");
        self::assertTrue(PermissionHelper::isOwner($owner['id']), "Owner check");
        self::assertFalse(PermissionHelper::isAdmin($owner['id']), "Non-admin check");
        
        // Test: Privilege checks
        self::assertTrue(
            PermissionHelper::hasPrivilege(ROLE_STUDENT, $admin['id']),
            "Admin has student privilege"
        );
    }

    private static function testResponse() {
        echo "Testing Response utility...\n";
        
        // We can't actually test HTTP responses, but we can verify methods exist
        self::assertTrue(method_exists('Response', 'success'), "Response::success exists");
        self::assertTrue(method_exists('Response', 'error'), "Response::error exists");
        self::assertTrue(method_exists('Response', 'validationError'), "Response::validationError exists");
        self::assertTrue(method_exists('Response', 'forbidden'), "Response::forbidden exists");
    }

    // Test assertion methods
    private static function assertTrue($condition, $testName) {
        if ($condition) {
            self::$result->passed++;
            echo "  ✓ $testName\n";
        } else {
            self::$result->failed++;
            self::addError($testName);
            echo "  ✗ $testName\n";
        }
    }

    private static function assertFalse($condition, $testName) {
        self::assertTrue(!$condition, $testName);
    }

    private static function addError($message) {
        self::$result->errors[] = $message;
    }

    private static function printResults() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Test Results\n";
        echo str_repeat("=", 60) . "\n";
        echo "Passed: " . self::$result->passed . "\n";
        echo "Failed: " . self::$result->failed . "\n";
        echo "Total:  " . (self::$result->passed + self::$result->failed) . "\n";
        
        if (!empty(self::$result->errors)) {
            echo "\nFailed Tests:\n";
            foreach (self::$result->errors as $error) {
                echo "  - " . htmlspecialchars($error) . "\n";
            }
        }
        
        echo "\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
    require_once __DIR__ . '/../includes/config.php';
    ServiceTestSuite::runAllTests();
}

?>
