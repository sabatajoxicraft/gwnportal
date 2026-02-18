<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';

$pageTitle = "My Devices";
$activePage = "student-devices";

// Ensure the user is logged in as student
requireRole('student');

$studentId = $_SESSION['student_id'] ?? 0;

$conn = getDbConnection();

// Get all student devices (with migration fallback)
$extendedDeviceSql = "SELECT id, device_type, mac_address, created_at, device_name,
                             linked_via, last_seen, IFNULL(is_blocked, 0) AS is_blocked, blocked_reason
                      FROM user_devices
                      WHERE user_id = ?
                      ORDER BY created_at DESC";
$stmt = safeQueryPrepare($conn, $extendedDeviceSql, false);
if (!$stmt) {
    $stmt = safeQueryPrepare($conn, "SELECT id, device_type, mac_address, created_at
                              FROM user_devices
                              WHERE user_id = ?
                              ORDER BY created_at DESC");
}
$devices = [];
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch live GWN client data for student view (read-only)
$gwnError = false;
$gwnClientMap = [];
$page = 1;
$pageSize = 100;
do {
    $data = gwnListClients($page, $pageSize);
    if ($data === false) {
        $gwnError = true;
        break;
    }
    $clients = $data['result'] ?? [];
    foreach ($clients as $client) {
        $mac = strtoupper((string)($client['clientId'] ?? ''));
        if ($mac !== '') {
            $gwnClientMap[$mac] = $client;
        }
    }
    $totalPages = (int)($data['totalPage'] ?? 1);
    $page++;
} while ($page <= $totalPages);

function formatDeviceDataUsage($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $val = (float)$bytes;
    while ($val >= 1024 && $i < count($units) - 1) {
        $val /= 1024;
        $i++;
    }
    return round($val, 2) . ' ' . $units[$i];
}

include '../../includes/components/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>My Devices</h1>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <a href="request-device.php" class="btn btn-success">
                        <i class="fas fa-plus-circle me-2"></i>Request New Device
                    </a>
                </div>
            </div>
            
            <?php include '../../includes/components/messages.php'; ?>
            <?php if ($gwnError): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Live network data is temporarily unavailable. Showing your registered devices only.
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-student text-white">
                    <h5 class="mb-0"><i class="fas fa-laptop me-2"></i>Registered Devices</h5>
                </div>
                <div class="card-body">
                    <?php if (count($devices) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Device</th>
                                        <th>MAC Address</th>
                                        <th>Access</th>
                                        <th>Network</th>
                                        <th>AP / SSID</th>
                                        <th>Data Usage</th>
                                        <th>Date Added</th>
                                        <th>Last Seen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devices as $index => $device):
                                        $deviceMac = strtoupper((string)$device['mac_address']);
                                        $live = $gwnClientMap[$deviceMac] ?? null;
                                        $isBlocked = ((int)($device['is_blocked'] ?? 0) === 1);
                                        $isOnline = ((int)($live['online'] ?? 0) === 1);
                                        $apName = (string)($live['apName'] ?? '—');
                                        $ssid = (string)($live['ssid'] ?? '—');
                                        $lastSeen = (string)($live['lastSeen'] ?? ($device['last_seen'] ?? ''));
                                        $deviceTitle = trim((string)($device['device_name'] ?? $device['device_type']));
                                        if ($deviceTitle === '') {
                                            $deviceTitle = 'Device';
                                        }
                                    ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <i class="fas fa-<?= strpos(strtolower($device['device_type']), 'phone') !== false ? 'mobile-alt' : (strpos(strtolower($device['device_type']), 'laptop') !== false ? 'laptop' : 'desktop') ?> me-2 text-muted"></i>
                                                <div class="fw-semibold"><?= htmlEscape($deviceTitle) ?></div>
                                                <small class="text-muted"><?= htmlEscape($device['device_type']) ?></small>
                                            </td>
                                            <td><code><?= htmlEscape($device['mac_address']) ?></code></td>
                                            <td>
                                                <?php if ($isBlocked): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Blocked</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Allowed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($live): ?>
                                                    <?php if ($isOnline): ?>
                                                        <span class="badge bg-success">Online</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Offline</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark">Unknown</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?= htmlEscape($apName) ?></div>
                                                <small class="text-muted"><?= htmlEscape($ssid) ?></small>
                                            </td>
                                            <td><?= htmlEscape(formatDeviceDataUsage((int)($live['totalBytes'] ?? 0))) ?></td>
                                            <td><?= htmlEscape(date('Y-m-d H:i', strtotime($device['created_at']))) ?></td>
                                            <td>
                                                <?php if (!empty($lastSeen)): ?>
                                                    <?php $lastSeenTs = strtotime($lastSeen); ?>
                                                    <?php if ($lastSeenTs): ?>
                                                        <?= htmlEscape(date('Y-m-d H:i', $lastSeenTs)) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Status Legend -->
                        <div class="mt-4">
                            <h6>Status Legend:</h6>
                            <div class="d-flex gap-3 flex-wrap">
                                <span>
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Allowed</span>
                                    - Device may connect to WiFi
                                </span>
                                <span>
                                    <span class="badge bg-success">Online</span> / <span class="badge bg-secondary">Offline</span>
                                    - Live connection status from GWN
                                </span>
                                <span>
                                    <span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Blocked</span>
                                    - Access suspended by manager/admin
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="text-center py-5">
                            <i class="fas fa-laptop fa-4x text-muted mb-3"></i>
                            <h4>No Devices Registered</h4>
                            <p class="text-muted">You haven't registered any devices yet.</p>
                            <a href="request-device.php" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Request Your First Device
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Information Card -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Device Registration</h6>
                </div>
                <div class="card-body">
                    <h6>What is a MAC Address?</h6>
                    <p>A MAC (Media Access Control) address is a unique identifier assigned to your device's network interface. It's used to authorize your device on the accommodation network.</p>
                    
                    <h6 class="mt-3">How to Find Your MAC Address:</h6>
                    <ul>
                        <li><strong>Windows:</strong> Open Command Prompt and type <code>ipconfig /all</code>, look for "Physical Address"</li>
                        <li><strong>Mac:</strong> System Preferences → Network → Advanced → Hardware, look for "MAC Address"</li>
                        <li><strong>Android:</strong> Settings → About Phone → Status → Wi-Fi MAC address</li>
                        <li><strong>iPhone:</strong> Settings → General → About → Wi-Fi Address</li>
                    </ul>
                    
                    <h6 class="mt-3">Important Notes:</h6>
                    <ul>
                        <li>Each device request must be approved by an administrator</li>
                        <li>Approval typically takes 1-2 business days</li>
                        <li>Make sure to enter the correct MAC address</li>
                        <li>Only register devices you personally own and use</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/components/footer.php'; ?>
