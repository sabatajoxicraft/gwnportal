<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/python_interface.php';

// Require manager login
requireManagerLogin();

$userId = $_SESSION['user_id'] ?? 0;
$accommodation_id = $_SESSION['accommodation_id'] ?? 0;
$conn = getDbConnection();

// Get student count
$sql_count = "SELECT COUNT(*) as count FROM students WHERE accommodation_id = ? AND status = 'active'";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $accommodation_id);
$stmt_count->execute();
$student_count = $stmt_count->get_result()->fetch_assoc()['count'];

// Initialize variables
$success = false;
$results = [];
$is_processing = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = $_POST['month'] ?? '';
    
    if (empty($month)) {
        $error = 'Please select a month';
    } else {
        $is_processing = true;
        
        // Send vouchers to students
        $result = sendAccommodationVouchers($accommodation_id, $month);
        
        $success_count = $result['success_count'];
        $failure_count = $result['failure_count'];
        $results = $result['results'];
        
        $success = true; // At least we attempted to send vouchers
    }
}

// Generate month options for the form
$current_month = date('F Y');
$next_month = date('F Y', strtotime('+1 month'));
$month_options = [$current_month, $next_month];

$pageTitle = "Send Vouchers";
require_once '../includes/components/header.php';
require_once '../includes/components/navigation.php';
?>
<div class="container mt-4">
    <?php require_once '../includes/components/accommodation-switcher.php'; ?>
    <?php displayFlashMessage(); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Send Monthly Vouchers</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-info">
                <h4 class="alert-heading">Vouchers Sent!</h4>
                <p>Successfully sent <?= $success_count ?> vouchers. <?= $failure_count ?> failed.</p>
            </div>
            
            <?php if (!empty($results)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Sending Results</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <tr>
                                            <td><?= $result['name'] ?></td>
                                            <td>
                                                <?php if ($result['success']): ?>
                                                    <span class="badge bg-success">Success</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="text-center mb-4">
                <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
            </div>
            
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <?php if ($student_count == 0): ?>
                        <div class="alert alert-warning">
                            <h4 class="alert-heading">No Active Students</h4>
                            <p>You don't have any active students to send vouchers to. Activate students or create new onboarding codes first.</p>
                            <div class="mt-3">
                                <a href="students.php" class="btn btn-primary me-2">Manage Students</a>
                                <a href="create-code.php" class="btn btn-success">Create Onboarding Code</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="post" action="" id="send-vouchers-form">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>
                            
                            <div class="mb-4 text-center">
                                <div class="alert alert-info">
                                    <p><i class="bi bi-info-circle"></i> You are about to send vouchers to <strong><?= $student_count ?></strong> active student(s) at <?= $_SESSION['accommodation_name'] ?>.</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="month" class="form-label">Select Month</label>
                                <select class="form-select" id="month" name="month" required>
                                    <option value="">-- Select Month --</option>
                                    <?php foreach ($month_options as $option): ?>
                                        <option value="<?= $option ?>"><?= $option ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the month for which you want to send vouchers.</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="send-button">
                                    <i class="bi bi-send"></i> Send Vouchers
                                </button>
                            </div>
                            
                            <div class="mt-3 text-center" id="loading" style="display: none;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Sending vouchers... This may take a few moments.</p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Show loading indicator on form submission
        document.getElementById('send-vouchers-form')?.addEventListener('submit', function() {
            document.getElementById('send-button').disabled = true;
            document.getElementById('loading').style.display = 'block';
        });
    </script>

<?php require_once '../includes/components/footer.php'; ?>
