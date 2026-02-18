<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireManagerLogin();

// Helper function to format month display
function formatVoucherMonth($month) {
    if (empty($month)) return '';
    
    // Try to parse as YYYY-MM format
    if (preg_match('/^(\d{4})-(\d{2})$/', $month)) {
        $timestamp = strtotime($month . '-01');
        return date('M Y', $timestamp); // e.g., "Feb 2026"
    }
    
    // Try to parse as "Month YYYY" format
    $timestamp = strtotime('1 ' . $month);
    if ($timestamp !== false) {
        return date('M Y', $timestamp); // e.g., "Feb 2026"
    }
    
    // If all else fails, return original
    return $month;
}

$voucher_id = $_GET['id'] ?? 0;
$conn = getDbConnection();
$accommodation_id = $_SESSION['accommodation_id'] ?? 0;

// Fetch voucher details with student info
$sql = "SELECT vl.*, u.first_name, u.last_name, u.email, u.phone_number, u.whatsapp_number,
               s.accommodation_id, s.status as student_status,
               CONCAT(u.first_name, ' ', u.last_name) as student_name,
               revoker.first_name as revoker_first_name, revoker.last_name as revoker_last_name
        FROM voucher_logs vl
        JOIN users u ON vl.user_id = u.id
        JOIN students s ON u.id = s.user_id
        LEFT JOIN users revoker ON vl.revoked_by = revoker.id
        WHERE vl.id = ? AND s.accommodation_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $voucher_id, $accommodation_id);
$stmt->execute();
$voucher = $stmt->get_result()->fetch_assoc();

if (!$voucher) {
    redirect(BASE_URL . '/manager/voucher-history.php', 'Voucher not found or access denied.', 'danger');
}

// Fetch device linked to this voucher (if first_used_mac was captured)
$linked_device = null;
if (!empty($voucher['first_used_mac'])) {
    $device_stmt = safeQueryPrepare($conn, "SELECT ud.*, u.first_name, u.last_name
                                            FROM user_devices ud
                                            JOIN users u ON ud.user_id = u.id
                                            WHERE ud.mac_address = ? AND ud.user_id = ?");
    if ($device_stmt) {
        $device_stmt->bind_param("si", $voucher['first_used_mac'], $voucher['user_id']);
        $device_stmt->execute();
        $linked_device = $device_stmt->get_result()->fetch_assoc();
    }
}

// Calculate expiry date (end of voucher month)
$expiry_date = date('Y-m-t', strtotime($voucher['voucher_month']));
$is_expired = strtotime($expiry_date) < time();
$is_revoked = isset($voucher['is_active']) && $voucher['is_active'] == 0;
$can_revoke = $voucher['status'] === 'sent' && !$is_revoked && !$is_expired;

$pageTitle = "Voucher Details";
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php displayFlashMessage(); ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-ticket-detailed me-2"></i>Voucher Details</h2>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($can_revoke): ?>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#revokeModal">
                    <i class="bi bi-x-circle"></i> Revoke Voucher
                </button>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/send-voucher.php?id=<?= $voucher['user_id'] ?>" class="btn btn-primary">
                <i class="bi bi-send"></i> Send New Voucher
            </a>
            <a href="voucher-history.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Voucher Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Voucher Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Voucher Code:</div>
                        <div class="col-sm-8">
                            <code style="font-size: 1.2rem;"><?= htmlspecialchars($voucher['voucher_code']) ?></code>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Month:</div>
                        <div class="col-sm-8"><?= htmlspecialchars(formatVoucherMonth($voucher['voucher_month'])) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Status:</div>
                        <div class="col-sm-8">
                            <?php
                            $badge_class = 'secondary';
                            if ($voucher['status'] === 'sent') $badge_class = 'success';
                            elseif ($voucher['status'] === 'failed') $badge_class = 'danger';
                            elseif ($voucher['status'] === 'pending') $badge_class = 'warning';
                            
                            if ($is_revoked) {
                                $badge_class = 'dark';
                                $status_text = 'Revoked';
                            } else {
                                $status_text = ucfirst($voucher['status']);
                            }
                            ?>
                            <span class="badge bg-<?= $badge_class ?> fs-6"><?= $status_text ?></span>
                            <?php if ($is_expired && !$is_revoked): ?>
                                <span class="badge bg-secondary fs-6 ms-2">Expired</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Sent Via:</div>
                        <div class="col-sm-8">
                            <?php if ($voucher['sent_via'] === 'SMS'): ?>
                                <i class="bi bi-chat-text text-primary"></i> SMS
                            <?php else: ?>
                                <i class="bi bi-whatsapp text-success"></i> WhatsApp
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Sent Date:</div>
                        <div class="col-sm-8">
                            <?php if ($voucher['sent_at']): ?>
                                <?= date('l, F j, Y \a\t g:i A', strtotime($voucher['sent_at'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Not sent</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Expiry Date:</div>
                        <div class="col-sm-8">
                            <?= date('F j, Y', strtotime($expiry_date)) ?>
                            <?php if ($is_expired): ?>
                                <span class="text-danger ms-2">(Expired)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Created At:</div>
                        <div class="col-sm-8"><?= date('F j, Y g:i A', strtotime($voucher['created_at'])) ?></div>
                    </div>
                    
                    <?php if (!empty($voucher['first_used_at'])): ?>
                        <hr>
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="bi bi-check-circle"></i> Voucher Usage Detected</h6>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">First Used:</div>
                                <div class="col-sm-8"><?= date('F j, Y g:i A', strtotime($voucher['first_used_at'])) ?></div>
                            </div>
                            <?php if (!empty($voucher['first_used_mac'])): ?>
                                <div class="row mb-2">
                                    <div class="col-sm-4 fw-bold">MAC Address:</div>
                                    <div class="col-sm-8">
                                        <code><?= htmlspecialchars($voucher['first_used_mac']) ?></code>
                                        <?php if ($linked_device): ?>
                                            <span class="badge bg-success ms-2">
                                                <i class="bi bi-link-45deg"></i> Device Linked
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary ms-2">
                                                <i class="bi bi-hourglass"></i> Awaiting Link
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($linked_device): ?>
                                    <div class="row">
                                        <div class="col-sm-4 fw-bold">Device:</div>
                                        <div class="col-sm-8">
                                            <?= htmlspecialchars($linked_device['device_name'] ?: 'Unnamed Device') ?>
                                            <span class="text-muted">(<?= htmlspecialchars($linked_device['device_type']) ?>)</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($is_revoked): ?>
                        <hr>
                        <div class="alert alert-warning">
                            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Voucher Revoked</h5>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Revoked At:</div>
                                <div class="col-sm-8"><?= date('F j, Y g:i A', strtotime($voucher['revoked_at'])) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Revoked By:</div>
                                <div class="col-sm-8">
                                    <?= htmlspecialchars($voucher['revoker_first_name'] . ' ' . $voucher['revoker_last_name']) ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4 fw-bold">Reason:</div>
                                <div class="col-sm-8"><?= htmlspecialchars($voucher['revoke_reason']) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Student Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Student Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Name:</div>
                        <div class="col-sm-8"><?= htmlspecialchars($voucher['student_name']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Email:</div>
                        <div class="col-sm-8">
                            <a href="mailto:<?= htmlspecialchars($voucher['email']) ?>">
                                <?= htmlspecialchars($voucher['email']) ?>
                            </a>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Phone:</div>
                        <div class="col-sm-8"><?= htmlspecialchars($voucher['phone_number']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">WhatsApp:</div>
                        <div class="col-sm-8"><?= htmlspecialchars($voucher['whatsapp_number']) ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Student Status:</div>
                        <div class="col-sm-8">
                            <span class="badge bg-<?= $voucher['student_status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($voucher['student_status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <div class="col-md-4">
            <!-- QR Code Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">QR Code</h5>
                </div>
                <div class="card-body text-center">
                    <?php
                    // Generate QR code using goqr.me API
                    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($voucher['voucher_code']);
                    ?>
                    <img src="<?= $qr_url ?>" alt="QR Code for <?= htmlspecialchars($voucher['voucher_code']) ?>" 
                         class="img-fluid mb-3" style="max-width: 250px;">
                    <p class="text-muted small">Scan this QR code to quickly access the voucher code</p>
                    <a href="<?= $qr_url ?>" download="voucher_<?= $voucher['voucher_code'] ?>.png" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download"></i> Download QR Code
                    </a>
                </div>
            </div>
            
            <!-- Status Timeline Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Created</h6>
                                <small class="text-muted">
                                    <?= date('M j, Y g:i A', strtotime($voucher['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                        
                        <?php if ($voucher['sent_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Sent via <?= $voucher['sent_via'] ?></h6>
                                    <small class="text-muted">
                                        <?= date('M j, Y g:i A', strtotime($voucher['sent_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($voucher['first_used_at'])): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">First Used</h6>
                                    <small class="text-muted">
                                        <?= date('M j, Y g:i A', strtotime($voucher['first_used_at'])) ?>
                                    </small>
                                    <?php if (!empty($voucher['first_used_mac'])): ?>
                                        <div class="mt-1">
                                            <small class="font-monospace text-muted"><?= htmlspecialchars($voucher['first_used_mac']) ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($linked_device): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Device Linked</h6>
                                    <small class="text-muted">
                                        <?= date('M j, Y g:i A', strtotime($linked_device['created_at'])) ?>
                                    </small>
                                    <div class="mt-1">
                                        <small><?= htmlspecialchars($linked_device['device_name'] ?: 'Unnamed Device') ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_revoked): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-danger"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Revoked</h6>
                                    <small class="text-muted">
                                        <?= date('M j, Y g:i A', strtotime($voucher['revoked_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_expired && !$is_revoked): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-secondary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Expired</h6>
                                    <small class="text-muted">
                                        <?= date('M j, Y', strtotime($expiry_date)) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Modal -->
<div class="modal fade" id="revokeModal" tabindex="-1" aria-labelledby="revokeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="revokeModalLabel">Revoke Voucher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="revoke-voucher.php">
                <?= csrfField() ?>
                <input type="hidden" name="voucher_log_id" value="<?= $voucher['id'] ?>">
                <div class="modal-body">
                    <p>Are you sure you want to revoke voucher <strong><?= htmlspecialchars($voucher['voucher_code']) ?></strong>?</p>
                    <p class="text-muted small">This will also delete the voucher on the GWN Cloud so it can no longer be used.</p>
                    <div class="mb-3">
                        <label for="revoke_reason" class="form-label">Reason for revoking (required)</label>
                        <textarea class="form-control" id="revoke_reason" name="revoke_reason" 
                                  rows="3" required placeholder="Enter reason for revoking this voucher..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        This action cannot be undone. The voucher will be marked as revoked and will no longer be valid.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Revoke Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-item:not(:last-child):before {
    content: '';
    position: absolute;
    left: -22px;
    top: 20px;
    height: calc(100% - 10px);
    width: 2px;
    background: #dee2e6;
}

.timeline-marker {
    position: absolute;
    left: -26px;
    top: 2px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content h6 {
    font-size: 0.9rem;
}
</style>

<?php require_once '../../includes/components/footer.php'; ?>
