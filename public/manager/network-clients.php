<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';

requireRole(['manager', 'admin']);

$conn = getDbConnection();
$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['user_role'] ?? '';
$isManager = ($userRole === 'manager');
$accommodationId = $_SESSION['accommodation_id'] ?? 0;

// Manager workflow moved to student-details.php (student-centric device sections)
if ($isManager) {
    redirect(BASE_URL . '/students.php', 'Use each student\'s details page to view/link device client details.', 'info');
}

// Handle "Link to Student" POST action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_device') {
    requireCsrfToken();

    $macAddress = trim($_POST['mac_address'] ?? '');
    $studentUserId = (int)($_POST['student_user_id'] ?? 0);
    $deviceType = trim($_POST['device_type'] ?? 'Other');

    if (empty($macAddress) || $studentUserId <= 0) {
        redirect(BASE_URL . '/manager/network-clients.php', 'Invalid MAC address or student selection.', 'danger');
    }

    $macAddress = formatMacAddress($macAddress);
    if (!$macAddress) {
        redirect(BASE_URL . '/manager/network-clients.php', 'Invalid MAC address format.', 'danger');
    }

    // Verify student belongs to manager's accommodation (if manager)
    if ($isManager) {
        $checkStmt = safeQueryPrepare($conn, "SELECT s.user_id FROM students s WHERE s.user_id = ? AND s.accommodation_id = ?");
        $checkStmt->bind_param("ii", $studentUserId, $accommodationId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            $checkStmt->close();
            redirect(BASE_URL . '/manager/network-clients.php', 'Student not found in your accommodation.', 'danger');
        }
        $checkStmt->close();
    }

    // Get student name for GWN rename
    $nameStmt = safeQueryPrepare($conn, "SELECT first_name, last_name FROM users WHERE id = ?");
    $nameStmt->bind_param("i", $studentUserId);
    $nameStmt->execute();
    $studentInfo = $nameStmt->get_result()->fetch_assoc();
    $nameStmt->close();

    if (!$studentInfo) {
        redirect(BASE_URL . '/manager/network-clients.php', 'Student not found.', 'danger');
    }

    // Insert or update user_devices
    $existStmt = safeQueryPrepare($conn, "SELECT id FROM user_devices WHERE mac_address = ?");
    $existStmt->bind_param("s", $macAddress);
    $existStmt->execute();
    $existing = $existStmt->get_result()->fetch_assoc();
    $existStmt->close();

    if ($existing) {
        $updStmt = safeQueryPrepare($conn, "UPDATE user_devices SET user_id = ?, device_type = ?, linked_via = 'manual' WHERE mac_address = ?", false);
        if (!$updStmt) {
            $updStmt = safeQueryPrepare($conn, "UPDATE user_devices SET user_id = ?, device_type = ? WHERE mac_address = ?");
        }
        if (!$updStmt) {
            redirect(BASE_URL . '/manager/network-clients.php', 'Unable to update device record.', 'danger');
        }
        $updStmt->bind_param("iss", $studentUserId, $deviceType, $macAddress);
        $updStmt->execute();
        $updStmt->close();
    } else {
        $insStmt = safeQueryPrepare($conn, "INSERT INTO user_devices (user_id, device_type, mac_address, linked_via) VALUES (?, ?, ?, 'manual')", false);
        if (!$insStmt) {
            $insStmt = safeQueryPrepare($conn, "INSERT INTO user_devices (user_id, device_type, mac_address) VALUES (?, ?, ?)");
        }
        if (!$insStmt) {
            redirect(BASE_URL . '/manager/network-clients.php', 'Unable to create device record.', 'danger');
        }
        $insStmt->bind_param("iss", $studentUserId, $deviceType, $macAddress);
        $insStmt->execute();
        $insStmt->close();
    }

    // Attempt to rename client on GWN Cloud
    $clientName = substr($studentInfo['first_name'] . ' ' . $studentInfo['last_name'] . ' - ' . $deviceType, 0, 64);
    $renameResult = gwnEditClientName($macAddress, $clientName);
    $renameNote = ($renameResult && isset($renameResult['retCode']) && $renameResult['retCode'] === 0)
        ? ' Client renamed on GWN Cloud.'
        : ' (GWN rename skipped or unavailable.)';

    logActivity($conn, $userId, 'link_device', "Linked MAC {$macAddress} to user ID {$studentUserId}" . $renameNote, $_SERVER['REMOTE_ADDR'] ?? '');
    redirect(BASE_URL . '/manager/network-clients.php', 'Device linked to ' . htmlspecialchars($studentInfo['first_name'] . ' ' . $studentInfo['last_name']) . '.' . $renameNote, 'success');
}

// Handle block/unblock actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['block_device', 'unblock_device'], true)) {
    requireCsrfToken();

    $action = $_POST['action'];
    $deviceId = (int)($_POST['device_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $defaultReason = $action === 'block_device' ? 'Blocked by manager/admin' : 'Access restored by manager/admin';
    if ($reason === '') {
        $reason = $defaultReason;
    }

    if ($deviceId <= 0) {
        redirect(BASE_URL . '/manager/network-clients.php', 'Invalid device selection.', 'danger');
    }

    $deviceStmt = safeQueryPrepare($conn,
        "SELECT ud.id, ud.user_id, ud.mac_address, IFNULL(ud.is_blocked, 0) as is_blocked,
                u.first_name, u.last_name, s.accommodation_id
         FROM user_devices ud
         JOIN users u ON u.id = ud.user_id
         LEFT JOIN students s ON s.user_id = ud.user_id
         WHERE ud.id = ?
         LIMIT 1");
    if (!$deviceStmt) {
        redirect(BASE_URL . '/manager/network-clients.php', 'Device management migration is not applied yet.', 'danger');
    }
    $deviceStmt->bind_param("i", $deviceId);
    $deviceStmt->execute();
    $device = $deviceStmt->get_result()->fetch_assoc();
    $deviceStmt->close();

    if (!$device) {
        redirect(BASE_URL . '/manager/network-clients.php', 'Device not found.', 'danger');
    }

    if ($isManager && (int)$device['accommodation_id'] !== (int)$accommodationId) {
        redirect(BASE_URL . '/manager/network-clients.php', 'You can only manage devices in your accommodation.', 'danger');
    }

    $studentUserId = (int)$device['user_id'];
    $mac = $device['mac_address'];
    $studentName = trim(($device['first_name'] ?? '') . ' ' . ($device['last_name'] ?? ''));

    if ($action === 'block_device') {
        if ((int)$device['is_blocked'] === 1) {
            redirect(BASE_URL . '/manager/network-clients.php', "Device {$mac} is already blocked.", 'warning');
        }
        $result = blockDevice($deviceId, $mac, $studentUserId, $reason, $userId);
        $actionLabel = 'blocked';
    } else {
        if ((int)$device['is_blocked'] === 0) {
            redirect(BASE_URL . '/manager/network-clients.php', "Device {$mac} is not currently blocked.", 'warning');
        }
        $result = unblockDevice($deviceId, $mac, $studentUserId, $reason, $userId);
        $actionLabel = 'restored';
    }

    // Notify admins of manager/admin enforcement action
    $adminStmt = safeQueryPrepare($conn, "SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin'");
    if ($adminStmt) {
        $adminStmt->execute();
        $adminRows = $adminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $adminStmt->close();
        foreach ($adminRows as $admin) {
            $adminId = (int)$admin['id'];
            if ($adminId === $userId) {
                continue;
            }
            createNotification(
                $adminId,
                ucfirst($userRole) . " {$actionLabel} device {$mac} for {$studentName}. Reason: {$reason}",
                'info',
                $userId
            );
        }
    }

    $status = (!empty($result['success']) ? 'success' : 'danger');
    $message = !empty($result['message']) ? $result['message'] : 'Device action failed.';
    redirect(BASE_URL . '/manager/network-clients.php', $message, $status);
}

// --- Fetch all GWN clients (paginated) ---
$gwnClients = [];
$gwnError = false;

$page = 1;
$pageSize = 100;
do {
    $data = gwnListClients($page, $pageSize);
    if ($data === false) {
        $gwnError = true;
        break;
    }
    $clients = $data['result'] ?? [];
    $gwnClients = array_merge($gwnClients, $clients);
    $totalPages = $data['totalPage'] ?? 1;
    $page++;
} while ($page <= $totalPages);

// --- Fetch registered devices with student info ---
if ($isManager) {
    $deviceSql = "SELECT ud.*, u.first_name, u.last_name, s.accommodation_id
                  FROM user_devices ud
                  JOIN users u ON ud.user_id = u.id
                  JOIN students s ON s.user_id = u.id
                  WHERE s.accommodation_id = ?";
    $devStmt = safeQueryPrepare($conn, $deviceSql);
    $devStmt->bind_param("i", $accommodationId);
} else {
    $deviceSql = "SELECT ud.*, u.first_name, u.last_name, s.accommodation_id
                  FROM user_devices ud
                  JOIN users u ON ud.user_id = u.id
                  JOIN students s ON s.user_id = u.id";
    $devStmt = safeQueryPrepare($conn, $deviceSql);
}
$devStmt->execute();
$registeredDevices = $devStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$devStmt->close();

// Build MAC → student lookup
$macToStudent = [];
foreach ($registeredDevices as $dev) {
    $mac = strtoupper($dev['mac_address']);
    $macToStudent[$mac] = $dev;
}

// --- Merge GWN clients with portal data ---
$mergedClients = [];
foreach ($gwnClients as $client) {
    $mac = strtoupper($client['clientId'] ?? '');
    $matched = isset($macToStudent[$mac]);
    $student = $matched ? $macToStudent[$mac] : null;

    // For managers, skip unmatched clients that don't belong to their accommodation
    // But show all clients since unmatched ones could be anyone's device on the network

    $mergedClients[] = [
        'mac' => $mac,
        'name' => $client['name'] ?? '',
        'ipv4' => $client['ipv4'] ?? '',
        'apName' => $client['apName'] ?? '',
        'ssid' => $client['ssid'] ?? '',
        'band' => $client['channelClassStr'] ?? '',
        'online' => (int)($client['online'] ?? 0),
        'totalBytes' => (int)($client['totalBytes'] ?? 0),
        'dhcpOs' => $client['dhcpOs'] ?? '',
        'iconId' => $client['iconId'] ?? 'others',
        'lastSeen' => $client['lastSeen'] ?? '',
        'matched' => $matched,
        'studentName' => $student ? trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) : '',
        'studentUserId' => $student ? (int)$student['user_id'] : 0,
        'deviceType' => $student ? (string)($student['device_type'] ?? '') : '',
        'deviceId' => $student ? (int)($student['id'] ?? 0) : 0,
        'isBlocked' => $student ? (int)($student['is_blocked'] ?? 0) : 0,
        'blockReason' => $student ? (string)($student['blocked_reason'] ?? '') : '',
    ];
}

// --- Stats ---
$totalClients = count($mergedClients);
$onlineCount = count(array_filter($mergedClients, fn($c) => $c['online'] === 1));
$offlineCount = $totalClients - $onlineCount;
$matchedCount = count(array_filter($mergedClients, fn($c) => $c['matched']));
$unmatchedCount = $totalClients - $matchedCount;
$blockedCount = count(array_filter($mergedClients, fn($c) => $c['isBlocked'] === 1));

// --- Fetch students list for "Link to Student" modal ---
if ($isManager) {
    $studentListSql = "SELECT u.id, u.first_name, u.last_name
                       FROM users u JOIN students s ON s.user_id = u.id
                       WHERE s.accommodation_id = ? AND s.status = 'active'
                       ORDER BY u.first_name, u.last_name";
    $slStmt = safeQueryPrepare($conn, $studentListSql);
    $slStmt->bind_param("i", $accommodationId);
} else {
    $studentListSql = "SELECT u.id, u.first_name, u.last_name
                       FROM users u JOIN students s ON s.user_id = u.id
                       WHERE s.status = 'active'
                       ORDER BY u.first_name, u.last_name";
    $slStmt = safeQueryPrepare($conn, $studentListSql);
}
$slStmt->execute();
$studentList = $slStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$slStmt->close();

// Fetch device block history (if migration exists)
$blockLogs = [];
$blockLogAvailable = false;
if ($isManager) {
    $logSql = "SELECT dbl.*, u.first_name, u.last_name,
                      actor.first_name AS actor_first_name, actor.last_name AS actor_last_name
               FROM device_block_log dbl
               JOIN users u ON u.id = dbl.user_id
               JOIN users actor ON actor.id = dbl.performed_by
               JOIN students s ON s.user_id = dbl.user_id
               WHERE s.accommodation_id = ?
               ORDER BY dbl.performed_at DESC
               LIMIT 50";
    $logStmt = safeQueryPrepare($conn, $logSql, false);
    if ($logStmt) {
        $logStmt->bind_param("i", $accommodationId);
    }
} else {
    $logSql = "SELECT dbl.*, u.first_name, u.last_name,
                      actor.first_name AS actor_first_name, actor.last_name AS actor_last_name
               FROM device_block_log dbl
               JOIN users u ON u.id = dbl.user_id
               JOIN users actor ON actor.id = dbl.performed_by
               ORDER BY dbl.performed_at DESC
               LIMIT 50";
    $logStmt = safeQueryPrepare($conn, $logSql, false);
}
if (!empty($logStmt)) {
    $logStmt->execute();
    $blockLogs = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $logStmt->close();
    $blockLogAvailable = true;
}

// Filter
$filter = $_GET['filter'] ?? 'all';

// Helper: format bytes
function formatDataUsage($bytes) {
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

// Helper: device icon
function getDeviceIcon($iconId) {
    return match ($iconId) {
        'mobile-phone' => 'bi-phone',
        'pc' => 'bi-pc-display',
        'tablet' => 'bi-tablet',
        'laptop' => 'bi-laptop',
        default => 'bi-device-hdd',
    };
}

$pageTitle = "Network Clients";
$activePage = "network-clients";
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-router me-2"></i>Network Clients</h2>
        <div>
            <button class="btn btn-outline-secondary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <?php include '../../includes/components/messages.php'; ?>

    <?php if ($gwnError): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>GWN Cloud API unavailable.</strong> Showing registered devices from the portal database only.
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row text-center">
                <div class="col">
                    <h5>Total Clients</h5>
                    <h2><?= $totalClients ?></h2>
                </div>
                <div class="col">
                    <h5>Online</h5>
                    <h2 class="text-success"><?= $onlineCount ?></h2>
                </div>
                <div class="col">
                    <h5>Offline</h5>
                    <h2 class="text-secondary"><?= $offlineCount ?></h2>
                </div>
                <div class="col">
                    <h5>Matched</h5>
                    <h2 class="text-primary"><?= $matchedCount ?></h2>
                </div>
                <div class="col">
                    <h5>Unmatched</h5>
                    <h2 class="text-warning"><?= $unmatchedCount ?></h2>
                </div>
                <div class="col">
                    <h5>Blocked</h5>
                    <h2 class="text-danger"><?= $blockedCount ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs + Search -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-tabs card-header-tabs me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">All</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'online' ? 'active' : '' ?>" href="?filter=online">Online</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'offline' ? 'active' : '' ?>" href="?filter=offline">Offline</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'matched' ? 'active' : '' ?>" href="?filter=matched">Matched</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'unmatched' ? 'active' : '' ?>" href="?filter=unmatched">Unmatched</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'blocked' ? 'active' : '' ?>" href="?filter=blocked">Blocked</a>
                </li>
            </ul>
            <div style="min-width: 250px;">
                <input type="text" id="searchBox" class="form-control form-control-sm" placeholder="Search name or MAC...">
            </div>
        </div>
        <div class="card-body">
            <?php if ($totalClients > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="clientsTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Device Name</th>
                                <th>MAC Address</th>
                                <th>GWN Name</th>
                                <th>AP</th>
                                <th>SSID</th>
                                <th>Band</th>
                                <th>Status</th>
                                <th>Data Usage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mergedClients as $client):
                                // Apply server-side filter
                                if ($filter === 'online' && $client['online'] !== 1) continue;
                                if ($filter === 'offline' && $client['online'] !== 0) continue;
                                if ($filter === 'matched' && !$client['matched']) continue;
                                if ($filter === 'unmatched' && $client['matched']) continue;
                                if ($filter === 'blocked' && $client['isBlocked'] !== 1) continue;
                            ?>
                                <tr class="client-row" 
                                    data-name="<?= htmlspecialchars(strtolower($client['studentName'] . ' ' . $client['name'])) ?>" 
                                    data-mac="<?= htmlspecialchars(strtolower($client['mac'])) ?>">
                                    <td>
                                        <?php if ($client['matched']): ?>
                                            <span class="text-success fw-semibold">
                                                <i class="bi bi-person-check me-1"></i><?= htmlspecialchars($client['studentName']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-warning">
                                                <i class="bi bi-person-question me-1"></i>Unknown
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="bi <?= getDeviceIcon($client['iconId']) ?> me-1"></i>
                                        <?= htmlspecialchars($client['dhcpOs'] ?: 'Unknown') ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($client['mac']) ?></code></td>
                                    <td><?= htmlspecialchars($client['name']) ?></td>
                                    <td><?= htmlspecialchars($client['apName']) ?></td>
                                    <td><?= htmlspecialchars($client['ssid']) ?></td>
                                    <td>
                                        <?php if ($client['band']): ?>
                                            <span class="badge bg-info text-dark"><?= htmlspecialchars($client['band']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($client['isBlocked'] === 1): ?>
                                            <span class="badge bg-danger">Blocked</span>
                                            <?php if (!empty($client['blockReason'])): ?>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars($client['blockReason']) ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($client['online']): ?>
                                            <span class="badge bg-success">Online</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatDataUsage($client['totalBytes']) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if (!$client['matched']): ?>
                                                <button class="btn btn-outline-primary"
                                                        data-bs-toggle="modal" data-bs-target="#linkModal"
                                                        data-mac="<?= htmlspecialchars($client['mac']) ?>"
                                                        data-gwn-name="<?= htmlspecialchars($client['name']) ?>"
                                                        data-os="<?= htmlspecialchars($client['dhcpOs']) ?>">
                                                    <i class="bi bi-link-45deg me-1"></i>Link
                                                </button>
                                            <?php else: ?>
                                                <?php if ($client['isBlocked'] === 1): ?>
                                                    <button class="btn btn-outline-success"
                                                            onclick='return submitDeviceAction("unblock_device", <?= (int)$client['deviceId'] ?>, <?= json_encode($client["studentName"]) ?>, <?= json_encode($client["mac"]) ?>);'>
                                                        <i class="bi bi-unlock me-1"></i>Restore
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-danger"
                                                            onclick='return submitDeviceAction("block_device", <?= (int)$client['deviceId'] ?>, <?= json_encode($client["studentName"]) ?>, <?= json_encode($client["mac"]) ?>);'>
                                                        <i class="bi bi-slash-circle me-1"></i>Block
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                                <a href="https://www.gwn.cloud/app/ap/monitor/client" 
                                                   target="_blank" 
                                                   class="btn btn-outline-secondary" 
                                                   title="View in GWN Cloud Client Monitor (MAC: <?= htmlspecialchars($client['mac']) ?>)"
                                                   onclick="navigator.clipboard.writeText('<?= htmlspecialchars($client['mac']) ?>'); return true;">
                                                    <i class="bi bi-cloud"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-router text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">No Clients Found</h5>
                    <p class="text-muted">No network clients are currently visible from the GWN Cloud API.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($blockLogAvailable): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Block / Restore History</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($blockLogs)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Student</th>
                                    <th>MAC</th>
                                    <th>Action</th>
                                    <th>Reason</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blockLogs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($log['performed_at']))) ?></td>
                                        <td><?= htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?></td>
                                        <td><code><?= htmlspecialchars($log['mac_address']) ?></code></td>
                                        <td>
                                            <?php if (($log['action'] ?? '') === 'block'): ?>
                                                <span class="badge bg-danger">Blocked</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Restored</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($log['reason'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars(trim(($log['actor_first_name'] ?? '') . ' ' . ($log['actor_last_name'] ?? ''))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No block/restore actions recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Link to Student Modal -->
<div class="modal fade" id="linkModal" tabindex="-1" aria-labelledby="linkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="link_device">
                <?= csrfField() ?>
                <input type="hidden" name="mac_address" id="linkMac">
                <div class="modal-header">
                    <h5 class="modal-title" id="linkModalLabel"><i class="bi bi-link-45deg me-2"></i>Link Device to Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" class="form-control" id="linkMacDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GWN Device Name</label>
                        <input type="text" class="form-control" id="linkGwnName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                        <select name="student_user_id" class="form-select" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($studentList as $s): ?>
                                <option value="<?= (int)$s['id'] ?>">
                                    <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Device Type</label>
                        <select name="device_type" class="form-select" id="linkDeviceType">
                            <option value="Phone">Phone</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Tablet">Tablet</option>
                            <option value="PC">PC</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-link-45deg me-1"></i>Link Device</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="post" id="deviceActionForm" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" id="deviceActionType">
    <input type="hidden" name="device_id" id="deviceActionDeviceId">
    <input type="hidden" name="reason" id="deviceActionReason">
</form>

<script>
// Populate modal fields when "Link" button is clicked
document.getElementById('linkModal')?.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    document.getElementById('linkMac').value = btn.dataset.mac;
    document.getElementById('linkMacDisplay').value = btn.dataset.mac;
    document.getElementById('linkGwnName').value = btn.dataset.gwnName || '—';

    // Auto-select device type based on OS
    const os = (btn.dataset.os || '').toLowerCase();
    const typeSelect = document.getElementById('linkDeviceType');
    if (os.includes('android') || os.includes('ios') || os.includes('iphone')) {
        typeSelect.value = 'Phone';
    } else if (os.includes('windows') || os.includes('mac') || os.includes('linux')) {
        typeSelect.value = 'Laptop';
    } else if (os.includes('ipad')) {
        typeSelect.value = 'Tablet';
    } else {
        typeSelect.value = 'Other';
    }
});

function submitDeviceAction(action, deviceId, studentName, mac) {
    const isBlock = action === 'block_device';
    const promptText = isBlock
        ? `Block WiFi access for ${studentName} (${mac}). Enter reason:`
        : `Restore WiFi access for ${studentName} (${mac}). Enter note (optional):`;

    let reason = window.prompt(promptText, '');
    if (reason === null) {
        return false;
    }
    reason = reason.trim();
    if (isBlock && reason.length === 0) {
        alert('Reason is required to block access.');
        return false;
    }
    if (!isBlock && reason.length === 0) {
        reason = 'Access restored';
    }

    document.getElementById('deviceActionType').value = action;
    document.getElementById('deviceActionDeviceId').value = deviceId;
    document.getElementById('deviceActionReason').value = reason;
    document.getElementById('deviceActionForm').submit();
    return false;
}

// Client-side search filter
document.getElementById('searchBox')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.client-row').forEach(function (row) {
        const name = row.dataset.name || '';
        const mac = row.dataset.mac || '';
        row.style.display = (name.includes(q) || mac.includes(q)) ? '' : 'none';
    });
});
</script>

<?php require_once '../../includes/components/footer.php'; ?>
