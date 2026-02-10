<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/python_interface.php';

// Require manager login
requireManagerLogin();

$accommodation_id = $_SESSION['accommodation_id'] ?? $_SESSION['manager_id'] ?? 0;
$conn = getDbConnection();

// Get student ID from query string
$student_id = $_GET['id'] ?? 0;

// Verify student belongs to this manager and fetch details
$stmt = $conn->prepare("SELECT s.id, s.status, s.user_id, u.first_name, u.last_name, u.email, u.phone_number, u.whatsapp_number, u.preferred_communication
                        FROM students s
                        JOIN users u ON s.user_id = u.id
                        WHERE s.id = ? AND s.accommodation_id = ?");
$stmt->bind_param("ii", $student_id, $accommodation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect(BASE_URL . '/students.php', 'Student not found or does not belong to your accommodation.', 'danger');
}

$student = $result->fetch_assoc();

// Initialize variables
$success = false;
$error = null;
$voucher_result = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $month = $_POST['month'] ?? '';
    
    if (empty($month)) {
        $error = 'Please select a month';
    } elseif ($student['status'] !== 'active') {
        $error = 'Student must be active to receive vouchers. Please activate the student first.';
    } else {
        // Send voucher to student
        $sent = sendStudentVoucher($student_id, $month);
        
        if ($sent) {
            // Get the latest voucher for this student
            $stmt_voucher = $conn->prepare("SELECT * FROM voucher_logs WHERE user_id = ? ORDER BY sent_at DESC LIMIT 1");
            $stmt_voucher->bind_param("i", $student['user_id']);
            $stmt_voucher->execute();
            $voucher_result = $stmt_voucher->get_result()->fetch_assoc();
            
            $success = true;
        } else {
            $error = 'Failed to send voucher. Please try again later.';
        }
    }
}

// Generate month options for the form
$current_month = date('F Y');
$next_month = date('F Y', strtotime('+1 month'));
$month_options = [$current_month, $next_month];
?>
<?php
$pageTitle = "Send Voucher";
require_once '../includes/components/header.php';
?>
    <div class="container mt-4">
        <?php displayFlashMessage(); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Send Voucher to Student</h2>
            <a href="student-details.php?id=<?= $student_id ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Student Details
            </a>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($success): ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="mb-3">Voucher Sent Successfully!</h3>
                            
                            <div class="alert alert-info">
                                <p class="mb-1"><strong>Student:</strong> <?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?></p>
                                <p class="mb-1"><strong>Month:</strong> <?= htmlspecialchars($voucher_result['voucher_month']) ?></p>
                                <p class="mb-1"><strong>Voucher Code:</strong> <span class="font-monospace"><?= htmlspecialchars($voucher_result['voucher_code']) ?></span></p>
                                <p class="mb-1"><strong>Sent via:</strong> <?= $voucher_result['sent_via'] ?></p>
                                <p class="mb-0"><strong>Sent at:</strong> <?= date('M j, Y H:i:s', strtotime($voucher_result['sent_at'])) ?></p>
                            </div>
                            
                            <div class="mt-4">
                                <a href="student-details.php?id=<?= $student_id ?>" class="btn btn-primary">Back to Student Details</a>
                                <a href="students.php" class="btn btn-outline-secondary">View All Students</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Send Voucher</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>
                            
                            <div class="alert alert-info">
                                <p><strong>Student:</strong> <?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?></p>
                                <p><strong>Preferred communication:</strong> <?= $student['preferred_communication'] ?></p>
                                <p class="mb-0"><strong>Contact:</strong> 
                                    <?= $student['preferred_communication'] === 'SMS' 
                                        ? htmlspecialchars($student['phone_number']) 
                                        : htmlspecialchars($student['whatsapp_number']) ?>
                                </p>
                            </div>
                            
                            <?php if ($student['status'] !== 'active'): ?>
                                <div class="alert alert-warning">
                                    <h5 class="alert-heading">Student Not Active</h5>
                                    <p class="mb-0">This student is currently marked as <strong><?= ucfirst($student['status']) ?></strong>. 
                                    You must activate the student before sending vouchers.</p>
                                    <div class="mt-3">
                                        <a href="students.php?action=activate&id=<?= $student_id ?>" class="btn btn-success btn-sm">
                                            <i class="bi bi-check-circle"></i> Activate Student
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="post" action="" id="send-voucher-form">
                                    <?php echo csrfField(); ?>
                                    <div class="mb-3">
                                        <label for="month" class="form-label">Select Month</label>
                                        <select class="form-select" id="month" name="month" required>
                                            <option value="">-- Select Month --</option>
                                            <?php foreach ($month_options as $option): ?>
                                                <option value="<?= $option ?>"><?= $option ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Select the month for which you want to send a voucher.</div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success" id="send-button">
                                            <i class="bi bi-send"></i> Send Voucher
                                        </button>
                                    </div>
                                    
                                    <div class="mt-3 text-center" id="loading" style="display: none;">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Sending voucher... This may take a few moments.</p>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Show loading indicator on form submission
        document.getElementById('send-voucher-form')?.addEventListener('submit', function() {
            document.getElementById('send-button').disabled = true;
            document.getElementById('loading').style.display = 'block';
        });
    </script>

<?php require_once '../includes/components/footer.php'; ?>
