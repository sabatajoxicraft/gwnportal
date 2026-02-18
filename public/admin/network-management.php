<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';

requireRole('admin');

$conn = getDbConnection();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Admin block/unblock actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['block_device', 'unblock_device'], true)) {
    requireCsrfToken();

    $action = $_POST['action'];
    $deviceId = (int)($_POST['device_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') {
        $reason = $action === 'block_device' ? 'Blocked by admin' : 'Access restored by admin';
    }

    if ($deviceId <= 0) {
        redirect(BASE_URL . '/admin/network-management.php', 'Invalid device selection.', 'danger');
    }

    $deviceStmt = safeQueryPrepare($conn,
        "SELECT id, user_id, mac_address, IFNULL(is_blocked, 0) as is_blocked
         FROM user_devices
         WHERE id = ?
         LIMIT 1", false);
    if (!$deviceStmt) {
        redirect(BASE_URL . '/admin/network-management.php', 'Device management migration is not applied yet.', 'danger');
    }
    $deviceStmt->bind_param("i", $deviceId);
    $deviceStmt->execute();
    $device = $deviceStmt->get_result()->fetch_assoc();
    $deviceStmt->close();

    if (!$device) {
        redirect(BASE_URL . '/admin/network-management.php', 'Device not found.', 'danger');
    }

    if ($action === 'block_device') {
        if ((int)$device['is_blocked'] === 1) {
            redirect(BASE_URL . '/admin/network-management.php', 'Device is already blocked.', 'warning');
        }
        $result = blockDevice($deviceId, (string)$device['mac_address'], (int)$device['user_id'], $reason, $userId);
    } else {
        if ((int)$device['is_blocked'] === 0) {
            redirect(BASE_URL . '/admin/network-management.php', 'Device is not currently blocked.', 'warning');
        }
        $result = unblockDevice($deviceId, (string)$device['mac_address'], (int)$device['user_id'], $reason, $userId);
    }

    $status = !empty($result['success']) ? 'success' : 'danger';
    $message = !empty($result['message']) ? $result['message'] : 'Action failed.';
    redirect(BASE_URL . '/admin/network-management.php', $message, $status);
}

function gwnRows($data) {
    if (!is_array($data)) {
        return [];
    }
    if (isset($data['result']) && is_array($data['result'])) {
        return $data['result'];
    }
    if (isset($data['list']) && is_array($data['list'])) {
        return $data['list'];
    }
    if (array_keys($data) === range(0, count($data) - 1)) {
        return $data;
    }
    return [];
}

function bytesToText($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $value = (float)$bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return round($value, 2) . ' ' . $units[$i];
}

// Fetch live GWN clients
$gwnError = false;
$gwnClients = [];
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
    $totalPages = (int)($data['totalPage'] ?? 1);
    $page++;
} while ($page <= $totalPages);

// Local registered devices map
$registeredDevices = [];
$deviceSql = "SELECT ud.id AS device_id, ud.user_id, ud.device_type, ud.mac_address,
                     IFNULL(ud.is_blocked, 0) AS is_blocked, ud.blocked_reason,
                     u.first_name, u.last_name, a.name AS accommodation_name
              FROM user_devices ud
              JOIN users u ON u.id = ud.user_id
              LEFT JOIN students s ON s.user_id = ud.user_id
              LEFT JOIN accommodations a ON a.id = s.accommodation_id";
$devStmt = safeQueryPrepare($conn, $deviceSql, false);
if ($devStmt) {
    $devStmt->execute();
    $registeredDevices = $devStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $devStmt->close();
} else {
    // Fallback for pre-migration environments
    $fallbackSql = "SELECT ud.id AS device_id, ud.user_id, ud.device_type, ud.mac_address,
                           u.first_name, u.last_name, a.name AS accommodation_name
                    FROM user_devices ud
                    JOIN users u ON u.id = ud.user_id
                    LEFT JOIN students s ON s.user_id = ud.user_id
                    LEFT JOIN accommodations a ON a.id = s.accommodation_id";
    $devStmt = safeQueryPrepare($conn, $fallbackSql);
    if ($devStmt) {
        $devStmt->execute();
        $registeredDevices = $devStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $devStmt->close();
    }
}

$macToDevice = [];
foreach ($registeredDevices as $device) {
    $macToDevice[strtoupper((string)$device['mac_address'])] = $device;
}

$mergedClients = [];
foreach ($gwnClients as $client) {
    $mac = strtoupper((string)($client['clientId'] ?? ''));
    $local = $macToDevice[$mac] ?? null;
    $mergedClients[] = [
        'mac' => $mac,
        'name' => (string)($client['name'] ?? ''),
        'ipv4' => (string)($client['ipv4'] ?? ''),
        'apName' => (string)($client['apName'] ?? ''),
        'ssid' => (string)($client['ssid'] ?? ''),
        'os' => (string)($client['dhcpOs'] ?? ''),
        'online' => (int)($client['online'] ?? 0),
        'totalBytes' => (int)($client['totalBytes'] ?? 0),
        'matched' => (bool)$local,
        'deviceId' => $local ? (int)($local['device_id'] ?? 0) : 0,
        'studentName' => $local ? trim(($local['first_name'] ?? '') . ' ' . ($local['last_name'] ?? '')) : '',
        'accommodationName' => $local ? (string)($local['accommodation_name'] ?? '—') : '—',
        'isBlocked' => $local ? (int)($local['is_blocked'] ?? 0) : 0,
    ];
}

$totalClients = count($mergedClients);
$onlineCount = count(array_filter($mergedClients, fn($c) => $c['online'] === 1));
$blockedCount = count(array_filter($mergedClients, fn($c) => $c['isBlocked'] === 1));

$apData = gwnListAPs();
$ssidData = gwnListSSIDs();
$networkDetail = gwnGetNetworkDetail();
$apStats = gwnGetNetworkStats('ap');
$ssidStats = gwnGetNetworkStats('ssid');
$apRows = gwnRows($apData);
$ssidRows = gwnRows($ssidData);

$pageTitle = "Network Management";
$activePage = "network-management";
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-diagram-3 me-2"></i>Admin Network Management</h2>
        <a href="<?= BASE_URL ?>/students.php" class="btn btn-outline-secondary">
            <i class="bi bi-people me-1"></i>Manage Students
        </a>
    </div>

    <?php include '../../includes/components/messages.php'; ?>

    <?php if ($gwnError): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            GWN API is currently unavailable. Showing cached/local data where possible.
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">Clients</h6>
                    <h3 class="mb-0"><?= $totalClients ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">Online</h6>
                    <h3 class="text-success mb-0"><?= $onlineCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">Blocked</h6>
                    <h3 class="text-danger mb-0"><?= $blockedCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">APs</h6>
                    <h3 class="mb-0"><?= count($apRows) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">SSIDs</h6>
                    <h3 class="mb-0"><?= count($ssidRows) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted">Matched</h6>
                    <h3 class="text-primary mb-0"><?= count(array_filter($mergedClients, fn($c) => $c['matched'])) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-laptop me-2"></i>All Network Clients</h5>
            <input type="text" id="clientSearch" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search MAC, user, AP, SSID">
        </div>
        <div class="card-body">
            <?php if (!empty($mergedClients)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="adminClientsTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Accommodation</th>
                                <th>MAC</th>
                                <th>IP</th>
                                <th>AP</th>
                                <th>SSID</th>
                                <th>OS</th>
                                <th>Status</th>
                                <th>Data Usage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mergedClients as $client): ?>
                                <tr class="admin-client-row"
                                    data-search="<?= htmlspecialchars(strtolower($client['studentName'] . ' ' . $client['mac'] . ' ' . $client['apName'] . ' ' . $client['ssid'])) ?>">
                                    <td>
                                        <?php if ($client['matched']): ?>
                                            <span class="text-success fw-semibold"><?= htmlspecialchars($client['studentName']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Unmatched</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($client['accommodationName']) ?></td>
                                    <td><code><?= htmlspecialchars($client['mac']) ?></code></td>
                                    <td><?= htmlspecialchars($client['ipv4']) ?></td>
                                    <td><?= htmlspecialchars($client['apName']) ?></td>
                                    <td><?= htmlspecialchars($client['ssid']) ?></td>
                                    <td><?= htmlspecialchars($client['os'] ?: '—') ?></td>
                                    <td>
                                        <?php if ($client['isBlocked'] === 1): ?>
                                            <span class="badge bg-danger">Blocked</span>
                                        <?php elseif ($client['online'] === 1): ?>
                                            <span class="badge bg-success">Online</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= bytesToText($client['totalBytes']) ?></td>
                                    <td>
                                        <?php if ($client['matched'] && $client['deviceId'] > 0): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if ($client['isBlocked'] === 1): ?>
                                                    <button class="btn btn-outline-success"
                                                            onclick='return submitAdminDeviceAction("unblock_device", <?= (int)$client['deviceId'] ?>, <?= json_encode($client["studentName"]) ?>, <?= json_encode($client["mac"]) ?>);'>
                                                        <i class="bi bi-unlock me-1"></i>Restore
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-danger"
                                                            onclick='return submitAdminDeviceAction("block_device", <?= (int)$client['deviceId'] ?>, <?= json_encode($client["studentName"]) ?>, <?= json_encode($client["mac"]) ?>);'>
                                                        <i class="bi bi-slash-circle me-1"></i>Block
                                                    </button>
                                                <?php endif; ?>
                                                <a href="https://www.gwn.cloud/app/ap/monitor/client" 
                                                   target="_blank" 
                                                   class="btn btn-outline-secondary" 
                                                   title="View in GWN Cloud Client Monitor (MAC: <?= htmlspecialchars($client['mac']) ?>)"
                                                   onclick="navigator.clipboard.writeText('<?= htmlspecialchars($client['mac']) ?>'); return true;">
                                                    <i class="bi bi-cloud"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">No local user</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No clients returned by GWN Cloud.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-broadcast me-2"></i>Access Points (<?= count($apRows) ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($apRows)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>MAC</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($apRows, 0, 20) as $ap): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)($ap['name'] ?? $ap['apName'] ?? '—')) ?></td>
                                            <td><code><?= htmlspecialchars((string)($ap['mac'] ?? $ap['apMac'] ?? '—')) ?></code></td>
                                            <td><?= htmlspecialchars((string)($ap['status'] ?? $ap['state'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No AP data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-wifi me-2"></i>SSIDs (<?= count($ssidRows) ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($ssidRows)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Enabled</th>
                                        <th>Security</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($ssidRows, 0, 20) as $ssid): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)($ssid['name'] ?? $ssid['ssid'] ?? '—')) ?></td>
                                            <td><?= htmlspecialchars((string)($ssid['enable'] ?? $ssid['enabled'] ?? '—')) ?></td>
                                            <td><?= htmlspecialchars((string)($ssid['securityType'] ?? $ssid['security'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No SSID data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Raw Network Statistics (Debug)</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <h6>Network Detail</h6>
                    <pre class="small bg-light p-2 rounded"><?= htmlEscape(json_encode($networkDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </div>
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <h6>AP Stats</h6>
                    <pre class="small bg-light p-2 rounded"><?= htmlEscape(json_encode($apStats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </div>
                <div class="col-lg-4">
                    <h6>SSID Stats</h6>
                    <pre class="small bg-light p-2 rounded"><?= htmlEscape(json_encode($ssidStats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="post" id="adminDeviceActionForm" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" id="adminDeviceActionType">
    <input type="hidden" name="device_id" id="adminDeviceId">
    <input type="hidden" name="reason" id="adminDeviceReason">
</form>

<script>
document.getElementById('clientSearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.admin-client-row').forEach(function (row) {
        row.style.display = (row.dataset.search || '').includes(q) ? '' : 'none';
    });
});

function submitAdminDeviceAction(action, deviceId, studentName, mac) {
    const isBlock = action === 'block_device';
    const promptText = isBlock
        ? `Block WiFi access for ${studentName} (${mac}). Enter reason:`
        : `Restore WiFi access for ${studentName} (${mac}). Enter note (optional):`;
    let reason = window.prompt(promptText, '');
    if (reason === null) return false;
    reason = reason.trim();
    if (isBlock && reason.length === 0) {
        alert('Reason is required to block access.');
        return false;
    }
    if (!isBlock && reason.length === 0) {
        reason = 'Access restored';
    }
    document.getElementById('adminDeviceActionType').value = action;
    document.getElementById('adminDeviceId').value = deviceId;
    document.getElementById('adminDeviceReason').value = reason;
    document.getElementById('adminDeviceActionForm').submit();
    return false;
}
</script>

<?php require_once '../../includes/components/footer.php'; ?>
