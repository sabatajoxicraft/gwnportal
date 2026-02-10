<?php
require_once 'config.php';

/**
 * Execute a Python command from the main.py script
 * 
 * @param string $command The command to execute
 * @param array $params Additional parameters
 * @return array The output and return code from the command
 */
function executePythonCommand($command, $params = []) {
    // Prepare the command string with parameters
    $param_string = '';
    foreach ($params as $key => $value) {
        $param_string .= " --$key " . escapeshellarg($value);
    }
    
    $python_cmd = "python " . PYTHON_SCRIPT_PATH . " $command$param_string";
    
    // Execute the command
    $output = [];
    $return_var = 0;
    exec($python_cmd, $output, $return_var);
    
    return [
        'output' => $output,
        'return_code' => $return_var
    ];
}

/**
 * Send a voucher to a student
 * 
 * @param int $student_id The student ID
 * @param string $month The month for the voucher (e.g., "January 2023")
 * @return bool True if sent successfully, false otherwise
 */
function sendStudentVoucher($student_id, $month) {
    $conn = getDbConnection();
    
    // Get student details with user info and accommodation name
    $sql = "SELECT s.id, s.user_id, u.first_name, u.last_name, u.phone_number, u.whatsapp_number, u.preferred_communication,
                   a.name AS accommodation_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN accommodations a ON s.accommodation_id = a.id
            WHERE s.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    if (!$student) {
        return false;
    }
    
    // Call the Python script to generate and send the voucher
    $result = executePythonCommand('send_single_voucher', [
        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
        'month' => $month,
        'phone' => $student['preferred_communication'] === 'SMS' ? $student['phone_number'] : $student['whatsapp_number'],
        'method' => $student['preferred_communication'],
        'accommodation' => $student['accommodation_name']
    ]);
    
    // Check if voucher was sent successfully
    if ($result['return_code'] === 0 && !empty($result['output'])) {
        // Parse the voucher code from the output
        $voucher_code = '';
        foreach ($result['output'] as $line) {
            if (preg_match('/Voucher code: ([A-Z0-9]+)/', $line, $matches)) {
                $voucher_code = $matches[1];
                break;
            }
        }
        
        // Log the voucher using user_id
        if (!empty($voucher_code)) {
            $sql = "INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at) 
                    VALUES (?, ?, ?, ?, 'sent', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $student['user_id'], $voucher_code, $month, $student['preferred_communication']);
            $stmt->execute();
            
            return true;
        }
    }
    
    return false;
}

/**
 * Send vouchers to all active students in an accommodation
 * 
 * @param int $manager_id The manager ID
 * @param string $month The month for vouchers (e.g., "January 2023")
 * @return array Results with success count, failure count, and student details
 */
function sendAccommodationVouchers($accommodation_id, $month) {
    $conn = getDbConnection();
    
    // Get all active students for this accommodation with names
    $sql = "SELECT s.id, u.first_name, u.last_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.accommodation_id = ? AND s.status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $accommodation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $success_count = 0;
    $failure_count = 0;
    $results = [];
    
    while ($student = $result->fetch_assoc()) {
        $sent = sendStudentVoucher($student['id'], $month);
        
        $results[] = [
            'student_id' => $student['id'],
            'name' => $student['first_name'] . ' ' . $student['last_name'],
            'success' => $sent
        ];
        
        if ($sent) {
            $success_count++;
        } else {
            $failure_count++;
        }
    }
    
    return [
        'success_count' => $success_count,
        'failure_count' => $failure_count,
        'results' => $results
    ];
}
?>
