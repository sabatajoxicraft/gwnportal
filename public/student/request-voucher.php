<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';

$pageTitle = "Request WiFi Voucher";
$activePage = "student-voucher";

// Ensure the user is logged in as student
requireRole('student');

$userId = $_SESSION['user_id'] ?? 0;
$studentId = $_SESSION['student_id'] ?? 0;

$conn = getDbConnection();

// Verify student is active
$stmtStatus = safeQueryPrepare($conn, "SELECT status FROM students WHERE id = ?");
$stmtStatus->bind_param("i", $studentId);
$stmtStatus->execute();
$studentRow = $stmtStatus->get_result()->fetch_assoc();
$studentActive = ($studentRow && $studentRow['status'] === 'active');

// Current month in both formats for robust matching (legacy data may use "2026-02" format)
$currentMonth = date('F Y');          // "February 2026"
$currentMonthAlt = date('Y-m');       // "2026-02"

// Check eligibility: has the student received an active voucher this month?
$stmtCheck = safeQueryPrepare($conn, 
    "SELECT * FROM voucher_logs WHERE user_id = ? AND (voucher_month = ? OR voucher_month = ?) AND status = 'sent' AND is_active = 1");
$stmtCheck->bind_param("iss", $userId, $currentMonth, $currentMonthAlt);
$stmtCheck->execute();
$existingVoucher = $stmtCheck->get_result()->fetch_assoc();

$isEligible = !$existingVoucher && $studentActive;
$justRequested = false;
$newVoucherCode = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isEligible) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = "Invalid security token. Please try again.";
    } else {
        // Use sendStudentVoucher which handles GWN API + SMS/WhatsApp delivery
        $sent = sendStudentVoucher($studentId, $currentMonth);

        if ($sent) {
            $justRequested = true;
            $isEligible = false;

            // Re-fetch the voucher that was just created
            $stmtCheck->execute();
            $existingVoucher = $stmtCheck->get_result()->fetch_assoc();
            $newVoucherCode = $existingVoucher ? $existingVoucher['voucher_code'] : '';

            // Log activity
            logActivity($conn, $userId, 'voucher_self_request', 
                "Student self-requested voucher {$newVoucherCode} for {$currentMonth}");
        } else {
            $errorMessage = "Unable to generate your voucher right now. Please try again or contact your manager.";
        }
    }
}

// Get voucher history
$stmtHistory = safeQueryPrepare($conn, 
    "SELECT voucher_code, voucher_month, sent_via, status, sent_at, is_active, revoked_at
     FROM voucher_logs 
     WHERE user_id = ? 
     ORDER BY sent_at DESC 
     LIMIT 12");
$stmtHistory->bind_param("i", $userId);
$stmtHistory->execute();
$voucherHistory = $stmtHistory->get_result()->fetch_all(MYSQLI_ASSOC);

include '../../includes/components/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4"><i class="bi bi-wifi me-2"></i>Request WiFi Voucher</h1>
            
            <?php include '../../includes/components/messages.php'; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlEscape($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Voucher Status Card -->
            <div class="row mb-4">
                <div class="col-lg-8 mx-auto">
                    <?php if ($justRequested): ?>
                        <!-- Just requested - show success -->
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Voucher Generated Successfully!</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-shield-check text-success" style="font-size: 3rem;"></i>
                                </div>
                                <p class="lead">Your WiFi voucher has been generated!</p>
                                <div class="alert alert-success d-inline-block">
                                    <h3 class="mb-0"><strong>Code: <?= htmlEscape($newVoucherCode) ?></strong></h3>
                                </div>
                                <p class="text-muted mt-3">
                                    <i class="bi bi-calendar me-1"></i>Month: <?= htmlEscape($currentMonth) ?>
                                </p>
                            </div>
                        </div>

                    <?php elseif (!$isEligible && $existingVoucher): ?>
                        <!-- Already has voucher this month -->
                        <div class="card border-info">
                            <div class="card-header bg-student text-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Voucher Status</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-check2-circle text-info" style="font-size: 3rem;"></i>
                                </div>
                                <p class="lead">You have already received your voucher for <strong><?= htmlEscape($currentMonth) ?></strong></p>
                                <div class="card bg-light d-inline-block p-3 mt-2">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <td class="text-end fw-bold">Voucher Code:</td>
                                            <td class="text-start"><code class="fs-5"><?= htmlEscape($existingVoucher['voucher_code']) ?></code></td>
                                        </tr>
                                        <tr>
                                            <td class="text-end fw-bold">Month:</td>
                                            <td class="text-start"><?= htmlEscape($existingVoucher['voucher_month']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-end fw-bold">Sent Via:</td>
                                            <td class="text-start"><?= htmlEscape($existingVoucher['sent_via']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-end fw-bold">Date:</td>
                                            <td class="text-start"><?= date('M j, Y g:i A', strtotime($existingVoucher['sent_at'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-end fw-bold">Status:</td>
                                            <td class="text-start">
                                                <?php if ($existingVoucher['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Revoked</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                    <?php elseif (!$studentActive): ?>
                        <!-- Account not active -->
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Account Not Active</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-person-x text-warning" style="font-size: 3rem;"></i>
                                </div>
                                <p class="lead">Your account must be activated by your accommodation manager before you can request vouchers.</p>
                                <p class="text-muted">Please contact your manager if you believe this is an error.</p>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Eligible - show request button -->
                        <div class="card border-primary">
                            <div class="card-header bg-student text-white">
                                <h5 class="mb-0"><i class="bi bi-wifi me-2"></i>Request Your Monthly Voucher</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-wifi text-primary" style="font-size: 3rem;"></i>
                                </div>
                                <p class="lead">You are eligible for a WiFi voucher for <strong><?= htmlEscape($currentMonth) ?></strong></p>
                                <p class="text-muted">Click the button below to generate your monthly WiFi voucher.</p>
                                <form method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?= htmlEscape(getCsrfToken()) ?>">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-download me-2"></i>Request Voucher
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Voucher History -->
            <div class="row mb-4">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header bg-student text-white">
                            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Voucher History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($voucherHistory)): ?>
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>No voucher history found.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Voucher Code</th>
                                                <th>Month</th>
                                                <th>Sent Via</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($voucherHistory as $voucher): ?>
                                                <tr>
                                                    <td><code><?= htmlEscape($voucher['voucher_code']) ?></code></td>
                                                    <td><?= htmlEscape($voucher['voucher_month']) ?></td>
                                                    <td><?= htmlEscape($voucher['sent_via']) ?></td>
                                                    <td><?= $voucher['sent_at'] ? date('M j, Y', strtotime($voucher['sent_at'])) : '-' ?></td>
                                                    <td>
                                                        <?php if (!empty($voucher['revoked_at'])): ?>
                                                            <span class="badge bg-danger">Revoked</span>
                                                        <?php elseif ($voucher['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back to Dashboard -->
            <div class="text-center mb-4">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/components/footer.php'; ?>
