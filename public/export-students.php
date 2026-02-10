<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require manager login
requireManagerLogin();

$manager_id = $_SESSION['manager_id'];
$conn = getDbConnection();

// Handle export
if (isset($_GET['format']) && $_GET['format'] == 'csv') {
    // Get all students for this manager
    $sql = "SELECT s.*, 
           (SELECT COUNT(*) FROM voucher_logs WHERE student_id = s.id) as voucher_count,
           (SELECT voucher_month FROM voucher_logs WHERE student_id = s.id ORDER BY sent_at DESC LIMIT 1) as last_voucher_month
           FROM students s 
           WHERE accommodation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $manager_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    
    // Prepare CSV file
    $filename = 'students_export_' . date('Y-m-d') . '.csv';
    $output = fopen('php://output', 'w');
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    // CSV header row
    $header = [
        'ID', 'First Name', 'Last Name', 'Email', 'Phone Number', 'WhatsApp Number',
        'Preferred Communication', 'Status', 'Phone MAC Address', 'Laptop MAC Address',
        'Registration Date', 'Voucher Count', 'Last Voucher Month'
    ];
    fputcsv($output, $header);
    
    // Add student data rows
    foreach ($students as $student) {
        $row = [
            $student['id'],
            $student['first_name'],
            $student['last_name'],
            $student['email'],
            $student['phone_number'],
            $student['whatsapp_number'],
            $student['preferred_communication'],
            $student['status'],
            $student['phone_mac_address'],
            $student['laptop_mac_address'],
            $student['created_at'],
            $student['voucher_count'],
            $student['last_voucher_month']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Get student count
$sql_count = "SELECT COUNT(*) as count FROM students WHERE accommodation_id = ?";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $manager_id);
$stmt_count->execute();
$student_count = $stmt_count->get_result()->fetch_assoc()['count'];

// Get status counts
$sql_status = "SELECT 
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
    FROM students WHERE accommodation_id = ?";
$stmt_status = $conn->prepare($sql_status);
$stmt_status->bind_param("i", $manager_id);
$stmt_status->execute();
$status_counts = $stmt_status->get_result()->fetch_assoc();
?>
<?php
$pageTitle = "Export Students";
require_once '../includes/components/header.php';
?>
<!-- Rest of your HTML content -->

    <div class="container mt-4">
        <?php displayFlashMessage(); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Export Student Data</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Export Options</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($student_count > 0): ?>
                            <p>You can export your student data in the following formats:</p>
                            <div class="d-grid gap-2">
                                <a href="?format=csv" class="btn btn-primary">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> Export as CSV
                                </a>
                            </div>
                            <div class="alert alert-info mt-3">
                                <p><i class="bi bi-info-circle"></i> The export includes all student information including contact details and device MAC addresses.</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p>You don't have any students to export yet.</p>
                                <a href="create-code.php" class="btn btn-primary mt-2">Generate Onboarding Code</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Student Summary</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Students
                                <span class="badge bg-primary rounded-pill"><?= $student_count ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Active Students
                                <span class="badge bg-success rounded-pill"><?= $status_counts['active'] ?? 0 ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Pending Students
                                <span class="badge bg-warning rounded-pill"><?= $status_counts['pending'] ?? 0 ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Inactive Students
                                <span class="badge bg-danger rounded-pill"><?= $status_counts['inactive'] ?? 0 ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once '../includes/components/footer.php'; ?>
