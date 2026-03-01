<?php
// filepath: c:\xampp\htdocs\wifi\web\public\owner\view-accommodation.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require owner login
requireOwnerLogin();

$owner_id = $_SESSION['user_id'] ?? 0;
$conn = getDbConnection();

// Handle accommodation switch request
if (isset($_GET['switch_accommodation'])) {
    $switch_accommodation_id = (int)$_GET['switch_accommodation'];
    $stmt_switch = safeQueryPrepare($conn, "SELECT id FROM accommodations WHERE id = ? AND owner_id = ?");

    if ($stmt_switch !== false) {
        $stmt_switch->bind_param("ii", $switch_accommodation_id, $owner_id);
        $stmt_switch->execute();
        $switch_result = $stmt_switch->get_result();

        if ($switch_result->num_rows > 0) {
            $_SESSION['current_accommodation'] = ['id' => $switch_accommodation_id];
            redirect(BASE_URL . '/view-accommodation.php?id=' . $switch_accommodation_id);
        }
    }

    redirect(BASE_URL . '/accommodations/', 'Accommodation not found or you do not have permission to switch to it.', 'danger');
}

// Get accommodation ID from query param
$accommodation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify this accommodation belongs to the owner
$stmt = safeQueryPrepare($conn, "SELECT * FROM accommodations WHERE id = ? AND owner_id = ?");
if ($stmt === false) {
    $error = "Database error. Please try again later.";
} else {
    $stmt->bind_param("ii", $accommodation_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if accommodation exists and belongs to this owner
    if ($result->num_rows === 0) {
        redirect(BASE_URL . '/accommodations/', 'Accommodation not found or you do not have permission to view it.', 'danger');
    }
    
    $accommodation = $result->fetch_assoc();
}
$accommodation_name = (string)($accommodation['name'] ?? $accommodation['NAME'] ?? '');
$map_url = trim((string)($accommodation['map_url'] ?? ''));
$show_map_previews = false;
$street_view_embed_url = '';
$small_map_embed_url = '';

if ($map_url !== '') {
    if (!preg_match('/^https?:\/\//i', $map_url)) {
        $map_url = 'https://' . ltrim($map_url, '/');
    }

    $map_host = strtolower((string)(parse_url($map_url, PHP_URL_HOST) ?? ''));
    $is_google_maps_link = strpos($map_host, 'google.') !== false
        || strpos($map_host, 'goo.gl') !== false
        || strpos($map_host, 'maps.app.goo.gl') !== false;

    if ($is_google_maps_link) {
    $show_map_previews = true;
    $lat = null;
    $lng = null;

    $parse_url = $map_url;
    if (($map_host === 'maps.app.goo.gl' || $map_host === 'goo.gl') && function_exists('curl_init')) {
        $ch = curl_init($map_url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_exec($ch);
        $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        if (!empty($effective) && $effective !== $map_url) {
            $parse_url = $effective;
        }
    }

    $map_query = $parse_url;

    if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $parse_url, $matches)) {
        $lat = $matches[1];
        $lng = $matches[2];
        $map_query = $lat . ',' . $lng;
    } elseif (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $parse_url, $matches)) {
        $lat = $matches[1];
        $lng = $matches[2];
        $map_query = $lat . ',' . $lng;
    } else {
        $parts = parse_url($parse_url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            if (!empty($queryParams['q'])) {
                $map_query = (string)$queryParams['q'];
                if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $map_query, $coordMatch)) {
                    $lat = $coordMatch[1];
                    $lng = $coordMatch[2];
                }
            }
        }
    }

    $small_map_embed_url = 'https://maps.google.com/maps?q=' . rawurlencode($map_query) . '&z=17&output=embed';
    if ($lat !== null && $lng !== null) {
        $street_view_embed_url = 'https://www.google.com/maps?layer=c&cbll=' . rawurlencode($lat . ',' . $lng) . '&cbp=12,0,0,0,0&output=svembed';
    } else {
        $street_view_embed_url = 'https://www.google.com/maps?q=' . rawurlencode($map_query) . '&layer=c&output=embed';
    }
    }
}

$sv_api_key = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
$use_sv_js = $show_map_previews && isset($lat, $lng) && $lat !== null && $lng !== null && $sv_api_key !== '';

// Get managers for this accommodation
$managers = [];
$stmt_managers = safeQueryPrepare($conn, "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.status as manager_status 
                                        FROM users u 
                                        JOIN roles r ON u.role_id = r.id
                                        JOIN user_accommodation ua ON u.id = ua.user_id
                                        WHERE ua.accommodation_id = ? AND r.name = 'manager'");
if ($stmt_managers !== false) {
    $stmt_managers->bind_param("i", $accommodation_id);
    $stmt_managers->execute();
    $managers = $stmt_managers->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get student count
$student_count = 0;
$stmt_students = safeQueryPrepare($conn, "SELECT COUNT(*) as count FROM students WHERE accommodation_id = ?");
if ($stmt_students !== false) {
    $stmt_students->bind_param("i", $accommodation_id);
    $stmt_students->execute();
    $student_count = $stmt_students->get_result()->fetch_assoc()['count'] ?? 0;
}

$address = trim(implode(', ', array_filter([
    $accommodation['address_line1'] ?? '',
    $accommodation['address_line2'] ?? '',
    $accommodation['city'] ?? '',
    $accommodation['province'] ?? '',
    $accommodation['postal_code'] ?? ''
])));
$manager_name = !empty($managers)
    ? trim(($managers[0]['first_name'] ?? '') . ' ' . ($managers[0]['last_name'] ?? ''))
    : '';
$max_students = isset($accommodation['max_students']) && $accommodation['max_students'] !== ''
    ? (int)$accommodation['max_students']
    : 0;
$occupancy_percent = $max_students > 0
    ? min(100, (int)round(($student_count / $max_students) * 100))
    : 0;

$pageTitle = "View Accommodation";
$activePage = "accommodations";
require_once '../includes/components/header.php';
?>

<style>
    .accom-hero {
        background: linear-gradient(135deg, rgba(13, 110, 253, 0.10), rgba(111, 66, 193, 0.10));
        border: 1px solid rgba(13, 110, 253, 0.15);
        border-radius: 14px;
        padding: 16px 18px;
        margin-bottom: 1rem;
    }
    .accom-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .accom-detail-item {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 10px 12px;
    }
    .accom-detail-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        color: #6c757d;
        letter-spacing: 0.3px;
        margin-bottom: 4px;
    }
    .accom-map-frame {
        border: 1px solid #dee2e6;
        border-radius: 10px;
        overflow: hidden;
        background: #f8f9fa;
    }
    .accom-map-note {
        font-size: 12px;
        color: #6c757d;
        margin-top: 8px;
    }
</style>

<div class="container mt-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="accom-hero d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="mb-1"><?= htmlspecialchars($accommodation_name, ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="text-muted small">
                <i class="bi bi-geo-alt me-1"></i>
                <?= $address !== '' ? htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : 'Address not set' ?>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/accommodations/" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Accommodations
        </a>
    </div>

    <!-- Accommodation Switcher Bar Component -->
    <?php include __DIR__ . '/../includes/components/accommodation-switcher-bar.php'; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Accommodation Details</h5>
                </div>
                <div class="card-body">
                    <div class="accom-details-grid mb-3">
                        <div class="accom-detail-item">
                            <span class="accom-detail-label">Name</span>
                            <?= htmlspecialchars($accommodation_name, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="accom-detail-item">
                            <span class="accom-detail-label">Address</span>
                            <?= $address !== '' ? htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : '<em>Not set</em>' ?>
                        </div>
                        <div class="accom-detail-item">
                            <span class="accom-detail-label">Maximum Students</span>
                            <?= $max_students > 0 ? $max_students : '<em>Not set</em>' ?>
                        </div>
                        <div class="accom-detail-item">
                            <span class="accom-detail-label">Primary Manager</span>
                            <?= $manager_name !== '' ? htmlspecialchars($manager_name, ENT_QUOTES, 'UTF-8') : '<em>No manager assigned</em>' ?>
                        </div>
                        <div class="accom-detail-item">
                            <span class="accom-detail-label">Contact Phone</span>
                            <?= !empty($accommodation['contact_phone']) ? htmlspecialchars($accommodation['contact_phone'], ENT_QUOTES, 'UTF-8') : '<em>Not set</em>' ?>
                        </div>
                        <div class="accom-detail-item">
                            <span class="accom-detail-label">Contact Email</span>
                            <?= !empty($accommodation['contact_email']) ? htmlspecialchars($accommodation['contact_email'], ENT_QUOTES, 'UTF-8') : '<em>Not set</em>' ?>
                        </div>
                        <div class="accom-detail-item">
                            <span class="accom-detail-label">Created</span>
                            <?= date('M j, Y', strtotime($accommodation['created_at'])) ?>
                        </div>
                        <div class="accom-detail-item">
                            <span class="accom-detail-label">Map Link</span>
                            <?php if ($map_url !== ''): ?>
                                <a href="<?= htmlspecialchars($map_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open Map</a>
                            <?php else: ?>
                                <em>Not set</em>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="accom-detail-item mb-3">
                        <span class="accom-detail-label">Notes</span>
                        <?= !empty($accommodation['notes']) ? nl2br(htmlspecialchars($accommodation['notes'], ENT_QUOTES, 'UTF-8')) : '<em>Not set</em>' ?>
                    </div>

                    <?php if ($show_map_previews): ?>
                        <div class="mt-4">
                            <div class="row g-3">
                                <div class="col-12 col-lg-8">
                                    <h6 class="mb-2">Street View Preview</h6>
                                    <div class="accom-map-frame">
                                        <?php if ($use_sv_js): ?>
                                            <div id="sv-canvas-owner" style="width:100%;height:360px;"></div>
                                        <?php else: ?>
                                            <div class="ratio ratio-16x9">
                                                <iframe
                                                    src="<?= htmlspecialchars($street_view_embed_url, ENT_QUOTES, 'UTF-8') ?>"
                                                    style="border:0;"
                                                    allowfullscreen
                                                    loading="lazy"
                                                    referrerpolicy="no-referrer-when-downgrade"></iframe>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="accom-map-note">Street View depends on coverage and may be unavailable for some areas.</div>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <h6 class="mb-2">Map View (Street Level)</h6>
                                    <div class="accom-map-frame">
                                        <div class="ratio ratio-4x3">
                                            <iframe
                                                src="<?= htmlspecialchars($small_map_embed_url, ENT_QUOTES, 'UTF-8') ?>"
                                                style="border:0;"
                                                allowfullscreen
                                                loading="lazy"
                                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($map_url !== ''): ?>
                        <div class="alert alert-light border mt-3 mb-0">
                            Map previews are currently available for Google Maps links only. Use <a href="<?= htmlspecialchars($map_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open Map</a>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Students</span>
                        <strong><?= $student_count ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Assigned Managers</span>
                        <strong><?= count($managers) ?></strong>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Occupancy</span>
                            <span><?= $max_students > 0 ? ($student_count . ' / ' . $max_students) : 'Not set' ?></span>
                        </div>
                        <div class="progress" style="height: 9px;">
                            <div class="progress-bar <?= $occupancy_percent >= 90 ? 'bg-danger' : ($occupancy_percent >= 70 ? 'bg-warning' : 'bg-success') ?>" role="progressbar" style="width: <?= $max_students > 0 ? $occupancy_percent : 0 ?>%;" aria-valuenow="<?= $max_students > 0 ? $occupancy_percent : 0 ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="managers.php?accommodation_id=<?= $accommodation_id ?>" class="btn btn-primary">
                            <i class="bi bi-people"></i> Manage Managers
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $extraScripts = $extraScripts ?? [];
if ($use_sv_js) {
    $sv_lat_val = (float)$lat;
    $sv_lng_val = (float)$lng;
    $extraScripts[] = '<script>
function initOwnerStreetView() {
    new google.maps.StreetViewPanorama(document.getElementById("sv-canvas-owner"), {
        position: {lat: ' . $sv_lat_val . ', lng: ' . $sv_lng_val . '},
        pov: {heading: 34, pitch: 10},
        zoom: 1
    });
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=' . htmlspecialchars($sv_api_key, ENT_QUOTES, 'UTF-8') . '&callback=initOwnerStreetView" async defer></script>';
}
require_once '../includes/components/footer.php'; ?>
