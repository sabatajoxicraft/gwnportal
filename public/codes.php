<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require manager login
requireManagerLogin();

$userId = $_SESSION['user_id'] ?? 0;
$accommodation_id = $_SESSION['accommodation_id'] ?? 0;
$conn = getDbConnection();

// Handle code deletion if requested
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $code_id = $_GET['id'];
    
    // Ensure the code belongs to this manager
    $stmt = $conn->prepare("DELETE FROM onboarding_codes WHERE id = ? AND created_by = ? AND status = 'unused'");
    $stmt->bind_param("ii", $code_id, $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        redirect(BASE_URL . '/codes.php', 'Code deleted successfully.', 'success');
    } else {
        redirect(BASE_URL . '/codes.php', 'Code could not be deleted or does not exist.', 'danger');
    }
}

// Get filter from query param
$filter = $_GET['filter'] ?? 'all';

// Update expired codes
$update_expired = $conn->prepare("UPDATE onboarding_codes SET status = 'expired' 
                                  WHERE expires_at < NOW() AND status = 'unused'");
$update_expired->execute();

// Prepare WHERE clause for filtering
$sql_where = "WHERE accommodation_id = ?";
if ($filter == 'unused') {
    $sql_where .= " AND status = 'unused'";
} else if ($filter == 'used') {
    $sql_where .= " AND status = 'used'";
} else if ($filter == 'expired') {
    $sql_where .= " AND status = 'expired'";
}

// Get codes with proper filtering
$sql = "SELECT * FROM onboarding_codes $sql_where ORDER BY created_at DESC";
$stmt = safeQueryPrepare($conn, $sql);

$codes = [];
if ($stmt !== false) {
    $stmt->bind_param("i", $accommodation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $codes = $result->fetch_all(MYSQLI_ASSOC);
}

// Get code stats for this accommodation
$stats = ['total' => 0, 'unused' => 0, 'used' => 0, 'expired' => 0];
$stmt_stats = safeQueryPrepare($conn, "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused,
    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
    FROM onboarding_codes WHERE accommodation_id = ?");

if ($stmt_stats !== false) {
    $stmt_stats->bind_param("i", $accommodation_id);
    $stmt_stats->execute();
    $result = $stmt_stats->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats = $row;
    }
}

$pageTitle = "Voucher Codes";
$activePage = "codes";
require_once '../includes/components/header.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Onboarding Codes</h2>
        <a href="create-code.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Create New Code
        </a>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    <h5>Total Codes</h5>
                    <h2><?= intval($stats['total']) ?></h2>
                </div>
                <div class="col-md-3 text-center">
                    <h5>Active Codes</h5>
                    <h2 class="text-success"><?= intval($stats['unused']) ?></h2>
                </div>
                <div class="col-md-3 text-center">
                    <h5>Used Codes</h5>
                    <h2 class="text-info"><?= intval($stats['used']) ?></h2>
                </div>
                <div class="col-md-3 text-center">
                    <h5>Expired Codes</h5>
                    <h2 class="text-danger"><?= intval($stats['expired']) ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= ($filter == 'all' || $filter == '') ? 'active' : '' ?>" href="?filter=all">All Codes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter == 'unused' ? 'active' : '' ?>" href="?filter=unused">Active</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter == 'used' ? 'active' : '' ?>" href="?filter=used">Used</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter == 'expired' ? 'active' : '' ?>" href="?filter=expired">Expired</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <?php if (count($codes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Student</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($codes as $code): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($code['code']) ?></strong></td>
                                    <td>
                                        <?php if ($code['status'] == 'unused'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($code['status'] == 'used'): ?>
                                            <span class="badge bg-info">Used</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($code['created_at'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($code['expires_at'])) ?></td>
                                    <td>
                                        <?php if (!empty($code['used_by'])): ?>
                                            <?= htmlspecialchars($code['used_by']) ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($code['status'] == 'unused'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary copy-code" data-code="<?= htmlspecialchars($code['code']) ?>">
                                                    <i class="bi bi-clipboard"></i> Copy
                                                </button>
                                                <a href="?action=delete&id=<?= $code['id'] ?>" class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this code?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-1 text-muted">No codes found</p>
                    
                    <?php if ($filter == 'all'): ?>
                        <p class="text-muted">You haven't created any onboarding codes yet.</p>
                    <?php elseif ($filter == 'unused'): ?>
                        <p class="text-muted">You don't have any active onboarding codes.</p>
                    <?php elseif ($filter == 'used'): ?>
                        <p class="text-muted">None of your codes have been used yet.</p>
                    <?php else: ?>
                        <p class="text-muted">You don't have any expired codes.</p>
                    <?php endif; ?>
                    
                    <a href="create-code.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle"></i> Create New Code
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const copyButtons = document.querySelectorAll('.copy-code');
        copyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const code = this.getAttribute('data-code');
                navigator.clipboard.writeText(code);
                
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check"></i> Copied!';
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 2000);
            });
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once '../includes/components/footer.php'; ?>

