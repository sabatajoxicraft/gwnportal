<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require manager login
requireManagerLogin();

$accommodation_id = $_SESSION['accommodation_id'] ?? 0;
$conn = getDbConnection();

// Get student ID from query string
$student_id = $_GET['student_id'] ?? ($_GET['id'] ?? 0);

// Verify student belongs to this manager and fetch user details
$stmt = safeQueryPrepare($conn, "SELECT s.id, s.status, s.created_at, s.user_id, s.accommodation_id,
                                a.name AS accommodation_name,
                                u.first_name, u.last_name, u.email, u.phone_number, u.whatsapp_number, u.preferred_communication
                         FROM students s
                         JOIN users u ON s.user_id = u.id
                         LEFT JOIN accommodations a ON a.id = s.accommodation_id
                         WHERE s.id = ? AND s.accommodation_id = ?");
$stmt->bind_param("ii", $student_id, $accommodation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect(BASE_URL . '/students.php', 'Student not found or does not belong to your accommodation.', 'danger');
}

$student = $result->fetch_assoc();

// --- POST: manually link a live GWN client to this student ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'link_live_client') {
    require_once '../includes/python_interface.php';
    requireCsrfToken();

    $macRaw     = trim($_POST['mac_address'] ?? '');
    $deviceType = trim($_POST['device_type'] ?? 'Other');
    $currentUserId  = $_SESSION['user_id'] ?? 0;
    $redirectTarget = BASE_URL . '/student-details.php?id=' . $student_id;

    if (empty($macRaw)) {
        redirect($redirectTarget, 'Invalid MAC address.', 'danger');
    }

    $macAddress = formatMacAddress($macRaw);
    if (!$macAddress) {
        redirect($redirectTarget, 'Invalid MAC address format.', 'danger');
    }

    // Fetch student name for GWN rename label
    $nameStmt = safeQueryPrepare($conn, "SELECT first_name, last_name FROM users WHERE id = ?");
    $nameStmt->bind_param("i", $student['user_id']);
    $nameStmt->execute();
    $studentInfo = $nameStmt->get_result()->fetch_assoc();
    $nameStmt->close();

    if (!$studentInfo) {
        redirect($redirectTarget, 'Student user record not found.', 'danger');
    }

    $studentUserId = (int)$student['user_id'];

    // Upsert user_devices — mirrors network-clients.php link_device behavior
    $existStmt = safeQueryPrepare($conn, "SELECT id FROM user_devices WHERE mac_address = ?");
    $existStmt->bind_param("s", $macAddress);
    $existStmt->execute();
    $existingDev = $existStmt->get_result()->fetch_assoc();
    $existStmt->close();

    if ($existingDev) {
        $updStmt = safeQueryPrepare($conn, "UPDATE user_devices SET user_id = ?, device_type = ?, linked_via = 'manual' WHERE mac_address = ?", false);
        if (!$updStmt) {
            $updStmt = safeQueryPrepare($conn, "UPDATE user_devices SET user_id = ?, device_type = ? WHERE mac_address = ?");
        }
        if (!$updStmt) {
            redirect($redirectTarget, 'Unable to update device record.', 'danger');
        }
        $updStmt->bind_param("iss", $studentUserId, $deviceType, $macAddress);
        if (!$updStmt->execute()) {
            $updStmt->close();
            redirect($redirectTarget, 'Failed to update device record.', 'danger');
        }
        $updStmt->close();
    } else {
        $insStmt = safeQueryPrepare($conn, "INSERT INTO user_devices (user_id, device_type, mac_address, linked_via) VALUES (?, ?, ?, 'manual')", false);
        if (!$insStmt) {
            $insStmt = safeQueryPrepare($conn, "INSERT INTO user_devices (user_id, device_type, mac_address) VALUES (?, ?, ?)");
        }
        if (!$insStmt) {
            redirect($redirectTarget, 'Unable to create device record.', 'danger');
        }
        $insStmt->bind_param("iss", $studentUserId, $deviceType, $macAddress);
        if (!$insStmt->execute()) {
            $insStmt->close();
            redirect($redirectTarget, 'Failed to create device record.', 'danger');
        }
        $insStmt->close();
    }

    // Attempt GWN Cloud rename
    $clientLabel  = substr($studentInfo['first_name'] . ' ' . $studentInfo['last_name'] . ' - ' . $deviceType, 0, 64);
    $renameResult = gwnEditClientName($macAddress, $clientLabel);
    $renameNote   = ($renameResult && isset($renameResult['retCode']) && $renameResult['retCode'] === 0)
        ? ' Client renamed on GWN Cloud.'
        : ' (GWN rename skipped or unavailable.)';

    logActivity($conn, $currentUserId, 'link_device',
        "Manually linked MAC {$macAddress} to user ID {$studentUserId} via student details page." . $renameNote,
        $_SERVER['REMOTE_ADDR'] ?? '');

    redirect($redirectTarget,
        'Device ' . htmlspecialchars($macAddress) . ' linked to ' .
        htmlspecialchars($studentInfo['first_name'] . ' ' . $studentInfo['last_name']) . '.' . $renameNote,
        'success');
}

// Get voucher history
$stmt_vouchers = safeQueryPrepare($conn, "SELECT * FROM voucher_logs WHERE user_id = ? ORDER BY sent_at DESC");
$stmt_vouchers->bind_param("i", $student['user_id']);
$stmt_vouchers->execute();
$vouchers = $stmt_vouchers->get_result()->fetch_all(MYSQLI_ASSOC);

// Get devices registered for this student with full details
$devices = [];
$device_history = [];

$stmt_devices = safeQueryPrepare($conn, "SELECT ud.*, 
                                    u_blocked.username as blocked_by_username,
                                    u_unblocked.username as unblocked_by_username
                               FROM user_devices ud
                               LEFT JOIN users u_blocked ON ud.blocked_by = u_blocked.id
                               LEFT JOIN users u_unblocked ON ud.unblocked_by = u_unblocked.id
                               WHERE ud.user_id = ?
                               ORDER BY ud.created_at DESC");
if ($stmt_devices) {
    $stmt_devices->bind_param("i", $student['user_id']);
    $stmt_devices->execute();
    $devices = $stmt_devices->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get device block/unblock history
$history_stmt = safeQueryPrepare($conn, "SELECT dbl.*, u.username as performed_by_username
                                    FROM device_block_log dbl
                                    LEFT JOIN users u ON dbl.performed_by = u.id
                                    WHERE dbl.user_id = ?
                                    ORDER BY dbl.performed_at DESC
                                    LIMIT 50");
if ($history_stmt) {
    $history_stmt->bind_param("i", $student['user_id']);
    $history_stmt->execute();
    $device_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get GWN client status for each device
require_once '../includes/python_interface.php';
$clientService = new ClientService();
foreach ($devices as &$device) {
    $clientInfo = $clientService->getClientDetails($device['mac_address']);
    if ($clientService->responseSuccessful($clientInfo)) {
        $payload = isset($clientInfo['result']) ? $clientInfo['result'] : $clientInfo;
        $device['gwn_online'] = isset($payload['online']) ? (int)$payload['online'] : 0;
        $device['gwn_name'] = isset($payload['name']) ? $payload['name'] : '';
        $device['gwn_ip'] = isset($payload['ipv4']) ? $payload['ipv4'] : '';
        $device['gwn_ssid'] = isset($payload['ssid']) ? $payload['ssid'] : '';
        $device['gwn_last_seen'] = isset($payload['lastSeen']) ? $payload['lastSeen'] : '';
        $device['gwn_rssi'] = isset($payload['rssi']) ? $payload['rssi'] : null;
        $device['gwn_os'] = isset($payload['dhcpOs']) ? $payload['dhcpOs'] : '';
        $device['gwn_manufacturer'] = isset($payload['dhcpManufacture']) ? $payload['dhcpManufacture'] : '';
        $device['gwn_channel'] = isset($payload['channelClassStr']) ? $payload['channelClassStr'] : '';
        $device['gwn_tx_bytes'] = isset($payload['txBytes']) ? $payload['txBytes'] : 0;
        $device['gwn_rx_bytes'] = isset($payload['rxBytes']) ? $payload['rxBytes'] : 0;
    } else {
        $device['gwn_online'] = 0;
        $device['gwn_name'] = '';
        $device['gwn_ip'] = '';
        $device['gwn_ssid'] = '';
        $device['gwn_last_seen'] = '';
        $device['gwn_rssi'] = null;
        $device['gwn_os'] = '';
        $device['gwn_manufacturer'] = '';
        $device['gwn_channel'] = '';
        $device['gwn_tx_bytes'] = 0;
        $device['gwn_rx_bytes'] = 0;
    }
}
unset($device);

function studentDetailsNormalizeMatchValue($value) {
    $normalized = strtolower(trim((string)$value));
    if ($normalized === '') {
        return '';
    }

    return preg_replace('/[^a-z0-9]+/', ' ', $normalized);
}

function studentDetailsMatchesAccommodation($ssid, $accommodationName) {
    $ssid = studentDetailsNormalizeMatchValue($ssid);
    $accommodationName = studentDetailsNormalizeMatchValue($accommodationName);

    if ($ssid === '' || $accommodationName === '') {
        return false;
    }

    if (strpos($ssid, $accommodationName) !== false || strpos($accommodationName, $ssid) !== false) {
        return true;
    }

    foreach (array_filter(explode(' ', $accommodationName), static function ($token) {
        return strlen($token) >= 4;
    }) as $token) {
        if (strpos($ssid, $token) !== false) {
            return true;
        }
    }

    return false;
}

function studentDetailsSuggestDeviceType($os, $deviceName = '', $manufacturer = '') {
    $haystack = strtolower(trim((string)$os . ' ' . (string)$deviceName . ' ' . (string)$manufacturer));

    if ($haystack === '') {
        return 'Other';
    }

    if (strpos($haystack, 'ipad') !== false || strpos($haystack, 'tablet') !== false) {
        return 'Tablet';
    }

    if (
        strpos($haystack, 'iphone') !== false ||
        strpos($haystack, 'android') !== false ||
        strpos($haystack, 'phone') !== false ||
        strpos($haystack, 'ios') !== false
    ) {
        return 'Phone';
    }

    if (
        strpos($haystack, 'desktop') !== false ||
        strpos($haystack, 'workstation') !== false
    ) {
        return 'PC';
    }

    if (
        strpos($haystack, 'windows') !== false ||
        strpos($haystack, 'mac') !== false ||
        strpos($haystack, 'linux') !== false ||
        strpos($haystack, 'laptop') !== false ||
        strpos($haystack, 'notebook') !== false
    ) {
        return 'Laptop';
    }

    return 'Other';
}

// --- Detect vouchers used with no MAC recorded (operator review candidates) ---
$unresolvedVouchers = array_filter($vouchers, function($v) {
    return !empty($v['first_used_at']) && empty($v['first_used_mac']);
});

$liveCandidates            = [];
$linkedElsewhereCandidates = [];
$liveCandidatesError       = false;

if (!empty($unresolvedVouchers)) {
    // Build normalized MAC → user_id map globally so MACs linked to any
    // student anywhere are detected — not just within this accommodation.
    $linkedMacStmt = safeQueryPrepare($conn,
        "SELECT mac_address, user_id FROM user_devices");
    $linkedMacStmt->execute();
    $linkedMacRows = $linkedMacStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $linkedMacStmt->close();

    $linkedMacMap = []; // normalized MAC (no separators, uppercase) => user_id
    foreach ($linkedMacRows as $row) {
        $norm = strtoupper(str_replace(['-', ':'], '', $row['mac_address']));
        $linkedMacMap[$norm] = (int)$row['user_id'];
    }

    // MACs already registered to this student — exclude entirely from candidates
    $thisStudentMacs = [];
    foreach ($devices as $d) {
        $thisStudentMacs[] = strtoupper(str_replace(['-', ':'], '', $d['mac_address']));
    }

    // Fetch all live GWN clients (paginated)
    $gwnPage = 1;
    $gwnRaw  = [];
    do {
        $gwnData = gwnListClients($gwnPage, 100);
        if ($gwnData === false) {
            $liveCandidatesError = true;
            break;
        }
        $gwnRaw    = array_merge($gwnRaw, $gwnData['result'] ?? []);
        $gwnTotalP = $gwnData['totalPage'] ?? 1;
        $gwnPage++;
    } while ($gwnPage <= $gwnTotalP);

    if (!$liveCandidatesError) {
        foreach ($gwnRaw as $client) {
            $rawMac = $client['clientId'] ?? '';
            if (empty($rawMac)) continue;

            $norm         = strtoupper(str_replace(['-', ':'], '', $rawMac));
            $formattedMac = formatMacAddress($rawMac) ?: $rawMac;

            // Already registered to this student — skip entirely
            if (in_array($norm, $thisStudentMacs, true)) continue;

            $linkedUserId       = $linkedMacMap[$norm] ?? null;
            $isLinkedElsewhere  = ($linkedUserId !== null && $linkedUserId !== (int)$student['user_id']);

            $entry = [
                'mac_raw'            => $rawMac,
                'mac_formatted'      => $formattedMac,
                'name'               => $client['name'] ?? '',
                'ssid'               => $client['ssid'] ?? '',
                'apName'             => $client['apName'] ?? '',
                'online'             => (int)($client['online'] ?? 0),
                'lastSeen'           => $client['lastSeen'] ?? '',
                'dhcpOs'             => $client['dhcpOs'] ?? '',
                'dhcpManufacture'    => $client['dhcpManufacture'] ?? '',
                'rssi'               => isset($client['rssi']) ? (int)$client['rssi'] : null,
                'is_linked_elsewhere'=> $isLinkedElsewhere,
                'linked_user_id'     => $linkedUserId,
                'accommodation_match'=> studentDetailsMatchesAccommodation($client['ssid'] ?? '', $student['accommodation_name'] ?? ''),
                'suggested_device_type' => studentDetailsSuggestDeviceType(
                    $client['dhcpOs'] ?? '',
                    $client['name'] ?? '',
                    $client['dhcpManufacture'] ?? ''
                ),
            ];
            if ($isLinkedElsewhere) {
                $linkedElsewhereCandidates[] = $entry;
            } else {
                $liveCandidates[] = $entry;
            }
        }

        $candidateSorter = static function ($a, $b) {
            $matchCompare = ((int)$b['accommodation_match']) <=> ((int)$a['accommodation_match']);
            if ($matchCompare !== 0) {
                return $matchCompare;
            }

            $onlineCompare = ((int)$b['online']) <=> ((int)$a['online']);
            if ($onlineCompare !== 0) {
                return $onlineCompare;
            }

            $aSeen = !empty($a['lastSeen']) ? strtotime($a['lastSeen']) : 0;
            $bSeen = !empty($b['lastSeen']) ? strtotime($b['lastSeen']) : 0;
            return $bSeen <=> $aSeen;
        };

        usort($liveCandidates, $candidateSorter);
        usort($linkedElsewhereCandidates, $candidateSorter);
    }
}

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

// Replace the direct CSS include and HTML header with this:
$pageTitle = "Student Details";
require_once '../includes/components/header.php';
?>
<!-- Rest of your HTML content -->

    <div class="container mt-4">
        <?php displayFlashMessage(); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Student Details</h2>
            <div>
                <a href="send-voucher.php?id=<?= $student_id ?>" class="btn btn-success me-2">
                    <i class="bi bi-send"></i> Send Voucher
                </a>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Students
                </a>
            </div>
        </div>
        
        <!-- Accommodation Switcher Bar Component -->
        <?php include __DIR__ . '/../includes/components/accommodation-switcher-bar.php'; ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Personal Information</h5>
                        <span class="badge <?php 
                            if ($student['status'] == 'active') echo 'bg-success';
                            elseif ($student['status'] == 'pending') echo 'bg-warning';
                            else echo 'bg-danger';
                        ?>"><?= ucfirst($student['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Full Name</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Email</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= htmlspecialchars($student['email']) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Phone Number</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= htmlspecialchars($student['phone_number']) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">WhatsApp Number</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= htmlspecialchars($student['whatsapp_number'] ?? 'Not provided') ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Preferred Communication</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= $student['preferred_communication'] ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Registration Date</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= date('M j, Y', strtotime($student['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group">
                            <?php if ($student['status'] != 'active'): ?>
                                <a href="students.php?action=activate&id=<?= $student_id ?>" class="btn btn-outline-success">
                                    <i class="bi bi-check-circle"></i> Activate
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($student['status'] != 'inactive'): ?>
                                <a href="students.php?action=deactivate&id=<?= $student_id ?>" class="btn btn-outline-warning">
                                    <i class="bi bi-pause-circle"></i> Deactivate
                                </a>
                            <?php endif; ?>
                            
                            <a href="students.php?action=delete&id=<?= $student_id ?>" class="btn btn-outline-danger" 
                               onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Registered Devices (<?= count($devices) ?>)</h5>
                    </div>
                    <?php if (!empty($devices)): ?>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Status</th>
                                            <th>Device</th>
                                            <th>MAC Address</th>
                                            <th>Network Info</th>
                                            <th>Added</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($devices as $device): ?>
                                            <tr class="<?= $device['is_blocked'] ? 'table-danger' : '' ?>">
                                                <td>
                                                    <?php if ($device['is_blocked']): ?>
                                                        <span class="badge bg-danger" title="Access restricted - device cannot connect">
                                                            <i class="bi bi-slash-circle"></i> Restricted
                                                        </span>
                                                    <?php elseif ($device['gwn_online']): ?>
                                                        <span class="badge bg-success" title="Connected and active">
                                                            <i class="bi bi-circle-fill"></i> Connected
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary" title="Not currently connected">
                                                            <i class="bi bi-circle"></i> Offline
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="fs-4 me-2"><?= getDeviceEmoji($device['device_type'], $device['gwn_os'], $device['gwn_manufacturer']) ?></span>
                                                        <div>
                                                            <strong><?= htmlspecialchars($device['device_name'] ?: 'Unnamed Device') ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= htmlspecialchars($device['device_type']) ?></small>
                                                            <?php if ($device['gwn_os']): ?>
                                                                <br><small class="text-info"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($device['gwn_os']) ?></small>
                                                            <?php endif; ?>
                                                            <?php if ($device['gwn_manufacturer']): ?>
                                                                <br><small class="text-secondary"><?= htmlspecialchars($device['gwn_manufacturer']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code class="text-dark" title="<?= htmlspecialchars($device['mac_address']) ?>">
                                                        <?= htmlspecialchars(substr($device['mac_address'], 0, 8)) ?>...
                                                    </code>
                                                    <br>
                                                    <small class="text-muted" title="<?php 
                                                        $linked_map = [
                                                            'auto' => 'Automatically detected from voucher',
                                                            'manual' => 'Manually added by administrator',
                                                            'request' => 'Added from student request'
                                                        ];
                                                        echo htmlspecialchars($linked_map[$device['linked_via']] ?? $device['linked_via']);
                                                    ?>">
                                                        <i class="bi bi-<?= $device['linked_via'] === 'auto' ? 'magic' : ($device['linked_via'] === 'manual' ? 'person-plus' : 'hand-thumbs-up') ?>"></i> 
                                                        <?= ucfirst($device['linked_via']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($device['gwn_online']): ?>
                                                        <?php if ($device['gwn_ssid']): ?>
                                                            <div class="mb-1">
                                                                <i class="bi bi-wifi text-success"></i>
                                                                <strong><?= htmlspecialchars($device['gwn_ssid']) ?></strong>
                                                                <?php if ($device['gwn_channel']): ?>
                                                                    <small class="text-muted">(<?= htmlspecialchars($device['gwn_channel']) ?>)</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($device['gwn_rssi'] !== null): 
                                                            // Calculate signal strength
                                                            $rssi = $device['gwn_rssi'];
                                                            if ($rssi >= -50) {
                                                                $signal_class = 'success';
                                                                $signal_label = 'Excellent';
                                                                $signal_bars = 4;
                                                            } elseif ($rssi >= -60) {
                                                                $signal_class = 'info';
                                                                $signal_label = 'Good';
                                                                $signal_bars = 3;
                                                            } elseif ($rssi >= -70) {
                                                                $signal_class = 'warning';
                                                                $signal_label = 'Fair';
                                                                $signal_bars = 2;
                                                            } elseif ($rssi >= -80) {
                                                                $signal_class = 'danger';
                                                                $signal_label = 'Weak';
                                                                $signal_bars = 1;
                                                            } else {
                                                                $signal_class = 'danger';
                                                                $signal_label = 'Very Weak';
                                                                $signal_bars = 0;
                                                            }
                                                        ?>
                                                            <div class="mb-1">
                                                                <span class="badge bg-<?= $signal_class ?>" title="Signal: <?= $rssi ?>dBm">
                                                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                                                        <i class="bi bi-reception-<?= $i < $signal_bars ? $i+1 : '0' ?>"></i>
                                                                    <?php endfor; ?>
                                                                    <?= $signal_label ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($device['gwn_ip']): ?>
                                                            <small class="text-muted d-block">
                                                                <i class="bi bi-hdd-network"></i> <?= htmlspecialchars($device['gwn_ip']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($device['gwn_tx_bytes'] || $device['gwn_rx_bytes']): 
                                                            $total_bytes = $device['gwn_tx_bytes'] + $device['gwn_rx_bytes'];
                                                            $formatted = $total_bytes >= 1073741824 ? round($total_bytes / 1073741824, 2) . ' GB' :
                                                                        ($total_bytes >= 1048576 ? round($total_bytes / 1048576, 2) . ' MB' :
                                                                        ($total_bytes >= 1024 ? round($total_bytes / 1024, 2) . ' KB' : $total_bytes . ' B'));
                                                        ?>
                                                            <small class="text-muted d-block">
                                                                <i class="bi bi-arrow-down-up"></i> <?= $formatted ?> used
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if ($device['gwn_last_seen']): ?>
                                                            <small class="text-muted">
                                                                <i class="bi bi-clock-history"></i> 
                                                                <span class="time-ago" data-time="<?= htmlspecialchars($device['gwn_last_seen']) ?>">
                                                                    Last seen <?= date('M j, g:ia', strtotime($device['gwn_last_seen'])) ?>
                                                                </span>
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-muted"><i class="bi bi-question-circle"></i> Never connected</small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="time-ago" data-time="<?= $device['created_at'] ?>">
                                                        <?= date('M j, Y', strtotime($device['created_at'])) ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted"><?= date('g:ia', strtotime($device['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php if ($device['is_blocked']): ?>
                                                            <button type="button" class="btn btn-success" 
                                                                    onclick="unblockDevice(<?= $device['id'] ?>, '<?= addslashes($device['mac_address']) ?>')" 
                                                                    title="Unblock device">
                                                                <i class="bi bi-unlock"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-warning" 
                                                                    onclick="blockDevice(<?= $device['id'] ?>, '<?= addslashes($device['mac_address']) ?>')" 
                                                                    title="Block device">
                                                                <i class="bi bi-slash-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-info" 
                                                                onclick="editDeviceName(<?= $device['id'] ?>, '<?= addslashes($device['device_name'] ?: '') ?>')" 
                                                                title="Edit device name">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                                            <a href="https://www.gwn.cloud/app/ap/monitor/client" 
                                                               target="_blank" 
                                                               class="btn btn-secondary" 
                                                               title="View in GWN Cloud Client Monitor (MAC: <?= htmlspecialchars($device['mac_address']) ?>)"
                                                               onclick="navigator.clipboard.writeText('<?= htmlspecialchars($device['mac_address']) ?>').then(() => { 
                                                                   alert('MAC address copied to clipboard: <?= htmlspecialchars($device['mac_address']) ?>'); 
                                                               });">
                                                                <i class="bi bi-cloud"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php if ($device['is_blocked'] && $device['blocked_reason']): ?>
                                                <tr class="table-danger">
                                                    <td colspan="6" class="small">
                                                        <i class="bi bi-info-circle me-1"></i>
                                                        <strong>Block reason:</strong> <?= htmlspecialchars($device['blocked_reason']) ?>
                                                        <?php if ($device['blocked_by_username']): ?>
                                                            <span class="text-muted"> — Blocked by <?= htmlspecialchars($device['blocked_by_username']) ?> on <?= date('M j, Y H:i', strtotime($device['blocked_at'])) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-body text-center py-5">
                            <i class="bi bi-phone-slash text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Devices Yet</h5>
                            <p class="text-muted mb-0">Devices will appear here automatically when the student uses their WiFi voucher.</p>
                            <p class="text-muted small">Devices are detected and registered when students connect to the network.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($device_history) > 0): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Device Action History</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Action</th>
                                            <th>Device (MAC)</th>
                                            <th>Reason</th>
                                            <th>By User</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($device_history as $history): ?>
                                            <tr>
                                                <td class="text-nowrap">
                                                    <?= date('M j, Y', strtotime($history['performed_at'])) ?><br>
                                                    <small class="text-muted"><?= date('H:i:s', strtotime($history['performed_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($history['action'] === 'block'): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-slash-circle"></i> Blocked
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle"></i> Unblocked
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code><?= htmlspecialchars($history['mac_address']) ?></code>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($history['reason'] ?: '—') ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($history['performed_by_username'] ?: 'System') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($unresolvedVouchers)): ?>
            <div class="col-md-12">
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-search me-1"></i>
                            Live Client Review — Unlinked Voucher Usage
                            <span class="badge bg-warning text-dark ms-1"><?= count($unresolvedVouchers) ?> voucher<?= count($unresolvedVouchers) !== 1 ? 's' : '' ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Manual review required.</strong>
                            This student has <?= count($unresolvedVouchers) ?> voucher<?= count($unresolvedVouchers) !== 1 ? 's' : '' ?> that were used but no device MAC address was recorded.
                            Review the live GWN clients below and link the correct device only if you can positively identify it.
                            <strong>Do not link a device unless you are certain it belongs to this student.</strong>
                        </div>

                        <?php if ($liveCandidatesError): ?>
                            <div class="alert alert-danger mb-0">
                                <i class="bi bi-exclamation-circle me-1"></i>
                                Could not retrieve live clients from GWN Cloud. Try reloading the page later.
                            </div>
                        <?php elseif (empty($liveCandidates) && empty($linkedElsewhereCandidates)): ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                No live GWN clients are currently visible, or all visible clients are already registered to this student.
                            </div>
                        <?php else: ?>
                            <?php if (!empty($liveCandidates)): ?>
                            <p class="text-muted small mb-2">
                                <strong><?= count($liveCandidates) ?></strong> unlinked GWN client<?= count($liveCandidates) !== 1 ? 's' : '' ?> available to link.
                                Rows whose SSID looks like <strong><?= htmlspecialchars($student['accommodation_name'] ?: 'this accommodation') ?></strong> are sorted to the top.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Status</th>
                                            <th>MAC Address</th>
                                            <th>GWN Name</th>
                                            <th>SSID / AP</th>
                                            <th>OS / Manufacturer</th>
                                            <th>Signal</th>
                                            <th>Last Seen</th>
                                            <th>Link Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($liveCandidates as $cand): ?>
                                            <tr class="<?= $cand['is_linked_elsewhere'] ? 'table-secondary' : '' ?>">
                                                <td>
                                                    <?php if ($cand['online']): ?>
                                                        <span class="badge bg-success"><i class="bi bi-circle-fill"></i> Online</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><i class="bi bi-circle"></i> Offline</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code class="text-dark"><?= htmlspecialchars($cand['mac_formatted']) ?></code>
                                                </td>
                                                <td>
                                                    <?= $cand['name'] ? htmlspecialchars($cand['name']) : '<span class="text-muted">—</span>' ?>
                                                </td>
                                                <td>
                                                    <?php if ($cand['ssid']): ?>
                                                        <div><i class="bi bi-wifi text-success"></i> <?= htmlspecialchars($cand['ssid']) ?></div>
                                                        <?php if (!empty($cand['accommodation_match'])): ?>
                                                            <small class="badge bg-light text-dark border mt-1">Accommodation match</small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if ($cand['apName']): ?>
                                                        <small class="text-muted"><i class="bi bi-router"></i> <?= htmlspecialchars($cand['apName']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!$cand['ssid'] && !$cand['apName']): ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($cand['dhcpOs']): ?>
                                                        <div><small><?= htmlspecialchars($cand['dhcpOs']) ?></small></div>
                                                    <?php endif; ?>
                                                    <?php if ($cand['dhcpManufacture']): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($cand['dhcpManufacture']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!$cand['dhcpOs'] && !$cand['dhcpManufacture']): ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($cand['rssi'] !== null):
                                                        $rssi = $cand['rssi'];
                                                        if ($rssi >= -50)     { $sc = 'success'; $sl = 'Excellent'; }
                                                        elseif ($rssi >= -60) { $sc = 'info';    $sl = 'Good'; }
                                                        elseif ($rssi >= -70) { $sc = 'warning'; $sl = 'Fair'; }
                                                        elseif ($rssi >= -80) { $sc = 'danger';  $sl = 'Weak'; }
                                                        else                  { $sc = 'danger';  $sl = 'Very Weak'; }
                                                    ?>
                                                        <span class="badge bg-<?= $sc ?>" title="<?= $rssi ?>dBm"><?= $sl ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($cand['online']): ?>
                                                        <span class="text-success small">Now</span>
                                                    <?php elseif ($cand['lastSeen']): ?>
                                                        <small class="text-muted">
                                                            <span class="time-ago" data-time="<?= htmlspecialchars($cand['lastSeen']) ?>">
                                                                <?= htmlspecialchars(date('M j, g:ia', strtotime($cand['lastSeen']))) ?>
                                                            </span>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($cand['is_linked_elsewhere']): ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-person-check"></i> Another student
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark border">
                                                            <i class="bi bi-link-45deg"></i> Unlinked
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $confirmMsg = $cand['is_linked_elsewhere']
                                                        ? 'WARNING: This MAC is already linked to another student.\n\nReassigning it will remove it from that student.\n\nOnly proceed if you are certain this device now belongs to this student.'
                                                        : 'Link ' . addslashes($cand['mac_formatted']) . ' to this student?\n\nOnly proceed if you are certain this device belongs to them.';
                                                    $btnClass = $cand['is_linked_elsewhere'] ? 'btn-outline-secondary' : 'btn-outline-primary';
                                                    $btnIcon  = $cand['is_linked_elsewhere'] ? 'bi-arrow-repeat' : 'bi-person-plus';
                                                    $btnLabel = $cand['is_linked_elsewhere'] ? 'Reassign' : 'Link';
                                                    ?>
                                                    <form method="POST"
                                                          action="<?= BASE_URL ?>/student-details.php?id=<?= $student_id ?>"
                                                          onsubmit="return confirm('<?= htmlspecialchars($confirmMsg, ENT_QUOTES) ?>');">
                                                        <input type="hidden" name="action"      value="link_live_client">
                                                        <input type="hidden" name="mac_address" value="<?= htmlspecialchars($cand['mac_raw']) ?>">
                                                        <input type="hidden" name="device_type" value="<?= htmlspecialchars($cand['suggested_device_type']) ?>">
                                                        <input type="hidden" name="csrf_token"  value="<?= getCsrfToken() ?>">
                                                        <button type="submit" class="btn btn-sm <?= $btnClass ?>">
                                                            <i class="bi <?= $btnIcon ?>"></i> <?= $btnLabel ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                No unlinked GWN clients found. Check below if any already-linked devices appear.
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($linkedElsewhereCandidates)): ?>
                            <details class="mt-3">
                                <summary class="fw-semibold text-warning-emphasis" style="cursor:pointer">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                                    <?= count($linkedElsewhereCandidates) ?> client<?= count($linkedElsewhereCandidates) !== 1 ? 's' : '' ?> already linked to another student — expand only for manual review / reassignment
                                </summary>
                                <div class="alert alert-warning py-2 mt-2 mb-2 small">
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    <strong>Caution:</strong> These MACs are already assigned to a different student. Reassigning will remove them from that student. Only proceed after confirming device ownership.
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Status</th>
                                                <th>MAC Address</th>
                                                <th>GWN Name</th>
                                                <th>SSID / AP</th>
                                                <th>OS / Manufacturer</th>
                                                <th>Signal</th>
                                                <th>Last Seen</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($linkedElsewhereCandidates as $cand): ?>
                                                <tr class="table-secondary">
                                                    <td>
                                                        <?php if ($cand['online']): ?>
                                                            <span class="badge bg-success"><i class="bi bi-circle-fill"></i> Online</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary"><i class="bi bi-circle"></i> Offline</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><code class="text-dark"><?= htmlspecialchars($cand['mac_formatted']) ?></code></td>
                                                    <td><?= $cand['name'] ? htmlspecialchars($cand['name']) : '<span class="text-muted">—</span>' ?></td>
                                                    <td>
                                                        <?php if ($cand['ssid']): ?>
                                                            <div><i class="bi bi-wifi text-success"></i> <?= htmlspecialchars($cand['ssid']) ?></div>
                                                            <?php if (!empty($cand['accommodation_match'])): ?>
                                                                <small class="badge bg-light text-dark border mt-1">Accommodation match</small>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if ($cand['apName']): ?><small class="text-muted"><i class="bi bi-router"></i> <?= htmlspecialchars($cand['apName']) ?></small><?php endif; ?>
                                                        <?php if (!$cand['ssid'] && !$cand['apName']): ?><span class="text-muted">—</span><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($cand['dhcpOs']): ?><div><small><?= htmlspecialchars($cand['dhcpOs']) ?></small></div><?php endif; ?>
                                                        <?php if ($cand['dhcpManufacture']): ?><small class="text-muted"><?= htmlspecialchars($cand['dhcpManufacture']) ?></small><?php endif; ?>
                                                        <?php if (!$cand['dhcpOs'] && !$cand['dhcpManufacture']): ?><span class="text-muted">—</span><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($cand['rssi'] !== null):
                                                            $rssi = $cand['rssi'];
                                                            if ($rssi >= -50)     { $sc = 'success'; $sl = 'Excellent'; }
                                                            elseif ($rssi >= -60) { $sc = 'info';    $sl = 'Good'; }
                                                            elseif ($rssi >= -70) { $sc = 'warning'; $sl = 'Fair'; }
                                                            elseif ($rssi >= -80) { $sc = 'danger';  $sl = 'Weak'; }
                                                            else                  { $sc = 'danger';  $sl = 'Very Weak'; }
                                                        ?>
                                                            <span class="badge bg-<?= $sc ?>" title="<?= $rssi ?>dBm"><?= $sl ?></span>
                                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($cand['online']): ?>
                                                            <span class="text-success small">Now</span>
                                                        <?php elseif ($cand['lastSeen']): ?>
                                                            <small class="text-muted">
                                                                <span class="time-ago" data-time="<?= htmlspecialchars($cand['lastSeen']) ?>">
                                                                    <?= htmlspecialchars(date('M j, g:ia', strtotime($cand['lastSeen']))) ?>
                                                                </span>
                                                            </small>
                                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form method="POST"
                                                              action="<?= BASE_URL ?>/student-details.php?id=<?= $student_id ?>"
                                                              onsubmit="return confirm('WARNING: This MAC is already linked to another student.\n\nReassigning will remove it from that student. Only proceed after confirming device ownership and completing any required manual reassignment.');">
                                                            <input type="hidden" name="action"      value="link_live_client">
                                                            <input type="hidden" name="mac_address" value="<?= htmlspecialchars($cand['mac_raw']) ?>">
                                                            <input type="hidden" name="device_type" value="<?= htmlspecialchars($cand['suggested_device_type']) ?>">
                                                            <input type="hidden" name="csrf_token"  value="<?= getCsrfToken() ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                                <i class="bi bi-arrow-repeat"></i> Reassign
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-md-12">
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Voucher History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($vouchers) > 0): ?>
                            <?php
                            // Detect duplicate active vouchers for the same month
                            $activeByMonth = [];
                            foreach ($vouchers as $v) {
                                if (!isset($v['is_active']) || $v['is_active']) {
                                    $month = $v['voucher_month'];
                                    $activeByMonth[$month] = ($activeByMonth[$month] ?? 0) + 1;
                                }
                            }
                            $duplicateMonths = array_filter($activeByMonth, fn($count) => $count > 1);
                            if (!empty($duplicateMonths)): ?>
                                <div class="alert alert-warning d-flex align-items-start">
                                    <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                                    <div>
                                        <strong>Duplicate vouchers detected!</strong> This student has multiple active vouchers for:
                                        <?php foreach ($duplicateMonths as $month => $count): ?>
                                            <span class="badge bg-warning text-dark ms-1"><?= htmlspecialchars($month) ?> (<?= $count ?>)</span>
                                        <?php endforeach; ?>
                                        <br><small>Revoke the extra vouchers to clean up. Each student should have only 1 active voucher per month.</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Voucher Code</th>
                                            <th>Sent Via</th>
                                            <th>Status</th>
                                            <th>First Used</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vouchers as $voucher): ?>
                                            <tr class="<?= (isset($voucher['is_active']) && !$voucher['is_active']) ? 'table-secondary' : '' ?>">
                                                <td><?= htmlspecialchars(formatVoucherMonth($voucher['voucher_month'])) ?></td>
                                                <td class="font-monospace"><?= htmlspecialchars($voucher['voucher_code']) ?></td>
                                                <td><?= htmlspecialchars($voucher['sent_via']) ?></td>
                                                <td>
                                                    <?php if (isset($voucher['is_active']) && !$voucher['is_active']): ?>
                                                        <span class="badge bg-secondary">Revoked</span>
                                                    <?php elseif ($voucher['status'] === 'sent'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($voucher['status'] === 'failed'): ?>
                                                        <span class="badge bg-danger">Failed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($voucher['first_used_at'])): ?>
                                                        <span class="badge bg-success">Used</span>
                                                        <div class="small text-muted"><?= date('M j, Y g:i A', strtotime($voucher['first_used_at'])) ?></div>
                                                        <?php if (!empty($voucher['first_used_mac'])): ?>
                                                            <div class="small font-monospace text-muted"><?= htmlspecialchars($voucher['first_used_mac']) ?></div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Not detected yet</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $voucher['sent_at'] ? date('M j, Y', strtotime($voucher['sent_at'])) : 'Pending' ?></td>
                                                <td>
                                                    <?php if ((!isset($voucher['is_active']) || $voucher['is_active']) && $voucher['status'] === 'sent'): ?>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="revokeVoucher(<?= $voucher['id'] ?>, '<?= htmlspecialchars(addslashes($voucher['voucher_code']), ENT_QUOTES) ?>')"
                                                                    title="Revoke this voucher">
                                                                <i class="bi bi-x-circle"></i> Revoke
                                                            </button>
                                                            <button type="button" class="btn btn-outline-warning" 
                                                                    onclick="replaceVoucher(<?= $voucher['id'] ?>, '<?= htmlspecialchars(addslashes($voucher['voucher_code']), ENT_QUOTES) ?>')"
                                                                    title="Replace with correct device limit (<?= GWN_ALLOWED_DEVICES ?>)">
                                                                <i class="bi bi-arrow-repeat"></i> Replace
                                                            </button>
                                                        </div>
                                                    <?php elseif (isset($voucher['is_active']) && !$voucher['is_active'] && !empty($voucher['revoke_reason'])): ?>
                                                        <small class="text-muted" title="<?= htmlspecialchars($voucher['revoke_reason']) ?>">
                                                            <i class="bi bi-info-circle"></i> <?= htmlspecialchars(mb_strimwidth($voucher['revoke_reason'], 0, 30, '...')) ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No vouchers have been sent to this student yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// Helper functions for friendly display
function getSignalStrength(rssi) {
    if (rssi === null || rssi === undefined) return { bars: 0, label: 'Unknown', color: 'secondary' };
    
    // RSSI typically ranges from -30 (excellent) to -90 (unusable)
    if (rssi >= -50) return { bars: 4, label: 'Excellent', color: 'success' };
    if (rssi >= -60) return { bars: 3, label: 'Good', color: 'info' };
    if (rssi >= -70) return { bars: 2, label: 'Fair', color: 'warning' };
    if (rssi >= -80) return { bars: 1, label: 'Weak', color: 'danger' };
    return { bars: 0, label: 'Very Weak', color: 'danger' };
}

function formatTimeAgo(dateString) {
    if (!dateString) return 'Never';
    
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
    if (seconds < 2592000) return Math.floor(seconds / 604800) + ' weeks ago';
    return date.toLocaleDateString();
}

function formatLinkedVia(linkedVia) {
    const map = {
        'auto': 'Automatically detected from voucher',
        'manual': 'Manually added by administrator',
        'request': 'Added from student request'
    };
    return map[linkedVia] || linkedVia;
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getDeviceTypeEmoji(deviceType, os, manufacturer) {
    const type = (deviceType || '').toLowerCase();
    const osName = (os || '').toLowerCase();
    
    if (type.includes('phone') || osName.includes('android') || osName.includes('ios')) return '📱';
    if (type.includes('laptop') || type.includes('computer')) return '💻';
    if (type.includes('tablet') || osName.includes('ipad')) return '📱';
    if (type.includes('watch')) return '⌚';
    if (type.includes('tv') || type.includes('chromecast')) return '📺';
    if (manufacturer && manufacturer.toLowerCase().includes('apple')) return '🍎';
    return '📡';
}

// Voucher replace function (revoke old + create new with correct device limit)
function replaceVoucher(voucherLogId, voucherCode) {
    if (!confirm('Replace voucher ' + voucherCode + '?\n\nThis will:\n1. Revoke the current voucher\n2. Create a new one with the correct device limit (<?= GWN_ALLOWED_DEVICES ?>)\n3. Send the new code to the student\n\nContinue?')) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= BASE_URL ?>/manager/replace-voucher.php';
    
    const fields = {
        'voucher_log_id': voucherLogId,
        'return_to_student': '1',
        'csrf_token': '<?= getCsrfToken() ?>'
    };
    
    for (const [key, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Voucher revoke function
function revokeVoucher(voucherLogId, voucherCode) {
    const reason = prompt('Enter reason for revoking voucher ' + voucherCode + ':');
    if (reason === null || reason.trim() === '') return;
    
    if (confirm('Are you sure you want to revoke voucher ' + voucherCode + '? This will delete it from the GWN Cloud.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/manager/revoke-voucher.php';
        
        const fields = {
            'voucher_log_id': voucherLogId,
            'revoke_reason': reason.trim(),
            'csrf_token': '<?= getCsrfToken() ?>'
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Device management functions
function blockDevice(deviceId, macAddress) {
    const reason = prompt('Enter reason for blocking this device:');
    if (reason === null) return; // User cancelled
    
    if (confirm('Are you sure you want to block device ' + macAddress + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/manager/device-actions.php';
        
        const fields = {
            'action': 'block',
            'device_id': deviceId,
            'mac_address': macAddress,
            'reason': reason,
            'student_id': '<?= $student_id ?>',
            'user_id': '<?= $student["user_id"] ?>',
            '<?= csrfFieldName() ?>': '<?= csrfFieldValue() ?>'
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}

function unblockDevice(deviceId, macAddress) {
    if (confirm('Are you sure you want to unblock device ' + macAddress + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/manager/device-actions.php';
        
        const fields = {
            'action': 'unblock',
            'device_id': deviceId,
            'mac_address': macAddress,
            'student_id': '<?= $student_id ?>',
            'user_id': '<?= $student["user_id"] ?>',
            '<?= csrfFieldName() ?>': '<?= csrfFieldValue() ?>'
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editDeviceName(deviceId, currentName) {
    const newName = prompt('Enter new device name:', currentName);
    if (newName === null || newName === currentName) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= BASE_URL ?>/manager/device-actions.php';
    
    const fields = {
        'action': 'rename',
        'device_id': deviceId,
        'device_name': newName,
        'student_id': '<?= $student_id ?>',
        'user_id': '<?= $student["user_id"] ?>',
        '<?= csrfFieldName() ?>': '<?= csrfFieldValue() ?>'
    };
    
    for (const [key, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Initialize time-ago displays on page load
document.addEventListener('DOMContentLoaded', function() {
    const timeElements = document.querySelectorAll('.time-ago');
    timeElements.forEach(function(element) {
        const timeStr = element.getAttribute('data-time');
        if (timeStr) {
            const friendlyTime = formatTimeAgo(timeStr);
            if (friendlyTime !== element.textContent) {
                element.innerHTML = '<i class="bi bi-clock-history"></i> ' + friendlyTime;
                element.setAttribute('title', new Date(timeStr).toLocaleString());
            }
        }
    });
});
</script>

<?php 
function getDeviceIcon($deviceType) {
    $type = strtolower($deviceType);
    if (strpos($type, 'phone') !== false) return 'phone';
    if (strpos($type, 'laptop') !== false || strpos($type, 'computer') !== false) return 'laptop';
    if (strpos($type, 'tablet') !== false) return 'tablet';
    if (strpos($type, 'tv') !== false) return 'tv';
    if (strpos($type, 'watch') !== false) return 'smartwatch';
    if (strpos($type, 'console') !== false || strpos($type, 'gaming') !== false) return 'controller';
    return 'device-hdd';
}

function getDeviceEmoji($deviceType, $os = '', $manufacturer = '') {
    $type = strtolower($deviceType);
    $osName = strtolower($os);
    $mfg = strtolower($manufacturer);
    
    // Check OS first for most accurate matching
    if (strpos($osName, 'android') !== false || strpos($osName, 'ios') !== false || strpos($osName, 'iphone') !== false) {
        return '📱';
    }
    if (strpos($osName, 'windows') !== false || strpos($osName, 'macos') !== false || strpos($osName, 'linux') !== false) {
        return '💻';
    }
    if (strpos($osName, 'ipad') !== false || strpos($osName, 'tablet') !== false) {
        return '📱';
    }
    
    // Check manufacturer
    if (strpos($mfg, 'apple') !== false) {
        return '🍎';
    }
    
    // Check device type
    if (strpos($type, 'phone') !== false || strpos($type, 'mobile') !== false) {
        return '📱';
    }
    if (strpos($type, 'laptop') !== false || strpos($type, 'computer') !== false || strpos($type, 'desktop') !== false) {
        return '💻';
    }
    if (strpos($type, 'tablet') !== false) {
        return '📱';
    }
    if (strpos($type, 'watch') !== false) {
        return '⌚';
    }
    if (strpos($type, 'tv') !== false || strpos($type, 'chromecast') !== false) {
        return '📺';
    }
    if (strpos($type, 'console') !== false || strpos($type, 'gaming') !== false) {
        return '🎮';
    }
    
    return '📡';
}

require_once '../includes/components/footer.php'; 
?>

