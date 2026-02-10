<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Handle report generation
$report_data = [];
$report_type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t');      // Last day of current month
$accommodation_id = $_GET['accommodation_id'] ?? 'all';

$conn = getDbConnection();

// Prepare report based on type
if ($report_type) {
    switch ($report_type) {
        case 'user_activity':
            $sql = "SELECT u.first_name, u.last_name, u.email, r.name as role_name, u.created_at, u.status
                   FROM users u
                   JOIN roles r ON u.role_id = r.id
                   WHERE u.created_at BETWEEN ? AND ?
                   ORDER BY u.created_at DESC";
            
            $stmt = safeQueryPrepare($conn, $sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;
            
        case 'accommodation_usage':
            $where = $accommodation_id !== 'all' ? "AND a.id = ?" : "";
            
            $sql = "SELECT a.name as accommodation_name, 
                   COUNT(DISTINCT ua.user_id) as total_users,
                   SUM(CASE WHEN r.name = 'student' THEN 1 ELSE 0 END) as student_count,
                   SUM(CASE WHEN r.name = 'manager' THEN 1 ELSE 0 END) as manager_count
                   FROM accommodations a
                   LEFT JOIN user_accommodation ua ON a.id = ua.accommodation_id
                   LEFT JOIN users u ON ua.user_id = u.id
                   LEFT JOIN roles r ON u.role_id = r.id
                   WHERE 1=1 $where
                   GROUP BY a.id
                   ORDER BY a.name";
            
            $stmt = safeQueryPrepare($conn, $sql);
            if ($accommodation_id !== 'all') {
                $stmt->bind_param("i", $accommodation_id);
            }
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;
            
        case 'onboarding_codes':
            $where = $accommodation_id !== 'all' ? "AND oc.accommodation_id = ?" : "";
            
            $sql = "SELECT 
                   a.name as accommodation_name,
                   COUNT(oc.id) as total_codes,
                   SUM(CASE WHEN oc.status = 'unused' THEN 1 ELSE 0 END) as unused_codes,
                   SUM(CASE WHEN oc.status = 'used' THEN 1 ELSE 0 END) as used_codes,
                   SUM(CASE WHEN oc.status = 'expired' THEN 1 ELSE 0 END) as expired_codes,
                   MIN(oc.created_at) as first_code_date,
                   MAX(oc.created_at) as last_code_date
                   FROM onboarding_codes oc
                   JOIN accommodations a ON oc.accommodation_id = a.id
                   WHERE oc.created_at BETWEEN ? AND ? $where
                   GROUP BY a.id
                   ORDER BY a.name";
            
            $stmt = safeQueryPrepare($conn, $sql);
            if ($accommodation_id !== 'all') {
                $stmt->bind_param("ssi", $start_date, $end_date, $accommodation_id);
            } else {
                $stmt->bind_param("ss", $start_date, $end_date);
            }
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;
    }
}

// Get accommodations for filter
$accom_stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations ORDER BY name");
$accom_stmt->execute();
$accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "Generate Reports";
$activePage = "reports";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
?>

<div class="container mt-4">
    <?php require_once '../../includes/components/messages.php'; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Generate Reports</h2>
    </div>
    
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Report Types</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="?type=user_activity" class="list-group-item list-group-item-action <?= $report_type === 'user_activity' ? 'active' : '' ?>">
                        <i class="bi bi-people me-2"></i> User Activity
                    </a>
                    <a href="?type=accommodation_usage" class="list-group-item list-group-item-action <?= $report_type === 'accommodation_usage' ? 'active' : '' ?>">
                        <i class="bi bi-building me-2"></i> Accommodation Usage
                    </a>
                    <a href="?type=onboarding_codes" class="list-group-item list-group-item-action <?= $report_type === 'onboarding_codes' ? 'active' : '' ?>">
                        <i class="bi bi-ticket-perforated me-2"></i> Onboarding Codes
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <?php if ($report_type): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php
                            switch ($report_type) {
                                case 'user_activity':
                                    echo '<i class="bi bi-people me-2"></i> User Activity Report';
                                    break;
                                case 'accommodation_usage':
                                    echo '<i class="bi bi-building me-2"></i> Accommodation Usage Report';
                                    break;
                                case 'onboarding_codes':
                                    echo '<i class="bi bi-ticket-perforated me-2"></i> Onboarding Codes Report';
                                    break;
                            }
                            ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3 mb-4">
                            <input type="hidden" name="type" value="<?= $report_type ?>">
                            
                            <?php if ($report_type === 'user_activity' || $report_type === 'onboarding_codes'): ?>
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($report_type === 'accommodation_usage' || $report_type === 'onboarding_codes'): ?>
                                <div class="col-md-4">
                                    <label for="accommodation_id" class="form-label">Accommodation</label>
                                    <select class="form-select" id="accommodation_id" name="accommodation_id">
                                        <option value="all" <?= $accommodation_id === 'all' ? 'selected' : '' ?>>All Accommodations</option>
                                        <?php foreach ($accommodations as $accommodation): ?>
                                            <option value="<?= $accommodation['id'] ?>" <?= $accommodation_id == $accommodation['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($accommodation['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                                <a href="reports.php" class="btn btn-secondary ms-2">Reset</a>
                            </div>
                        </form>
                        
                        <?php if (count($report_data) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <?php
                                            // Output table headers based on report type
                                            switch ($report_type) {
                                                case 'user_activity':
                                                    echo '<th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th>';
                                                    break;
                                                case 'accommodation_usage':
                                                    echo '<th>Accommodation</th><th>Total Users</th><th>Students</th><th>Managers</th>';
                                                    break;
                                                case 'onboarding_codes':
                                                    echo '<th>Accommodation</th><th>Total Codes</th><th>Unused</th><th>Used</th><th>Expired</th><th>First Code</th><th>Last Code</th>';
                                                    break;
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <?php
                                                // Output table rows based on report type
                                                switch ($report_type) {
                                                    case 'user_activity':
                                                        echo '<td>' . $row['first_name'] . ' ' . $row['last_name'] . '</td>';
                                                        echo '<td>' . $row['email'] . '</td>';
                                                        echo '<td>' . ucfirst($row['role_name']) . '</td>';
                                                        echo '<td>' . ucfirst($row['status']) . '</td>';
                                                        echo '<td>' . date('Y-m-d H:i', strtotime($row['created_at'])) . '</td>';
                                                        break;
                                                    case 'accommodation_usage':
                                                        echo '<td>' . $row['accommodation_name'] . '</td>';
                                                        echo '<td>' . $row['total_users'] . '</td>';
                                                        echo '<td>' . $row['student_count'] . '</td>';
                                                        echo '<td>' . $row['manager_count'] . '</td>';
                                                        break;
                                                    case 'onboarding_codes':
                                                        echo '<td>' . $row['accommodation_name'] . '</td>';
                                                        echo '<td>' . $row['total_codes'] . '</td>';
                                                        echo '<td>' . $row['unused_codes'] . '</td>';
                                                        echo '<td>' . $row['used_codes'] . '</td>';
                                                        echo '<td>' . $row['expired_codes'] . '</td>';
                                                        echo '<td>' . date('Y-m-d', strtotime($row['first_code_date'])) . '</td>';
                                                        echo '<td>' . date('Y-m-d', strtotime($row['last_code_date'])) . '</td>';
                                                        break;
                                                }
                                                ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-success" onclick="exportTableToCSV('report_<?= $report_type ?>_<?= date('Y-m-d') ?>.csv')">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No data found for the selected report criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-file-earmark-bar-graph" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Select a Report Type</h4>
                        <p class="text-muted">Choose a report type from the left menu to generate reports.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportTableToCSV(filename) {
    const table = document.querySelector('table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Replace HTML entities and escape quotes
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    
    // File download
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php require_once '../../includes/components/footer.php'; ?>
