<?php
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
