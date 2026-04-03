<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Get accommodation ID from URL
$accommodation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($accommodation_id <= 0) {
    redirect(BASE_URL . '/admin/accommodations.php', 'Invalid accommodation ID', 'danger');
}

// Get accommodation details
$conn = getDbConnection();
$stmt = safeQueryPrepare($conn, "SELECT a.*, u.first_name, u.last_name, u.email 
                                FROM accommodations a 
                                JOIN users u ON a.owner_id = u.id 
                                WHERE a.id = ?");
$stmt->bind_param("i", $accommodation_id);
$stmt->execute();
$accommodation = $stmt->get_result()->fetch_assoc();

if (!$accommodation) {
    redirect(BASE_URL . '/admin/accommodations.php', 'Accommodation not found', 'danger');
}

$accommodation_name = (string) ($accommodation['name'] ?? $accommodation['NAME'] ?? '');

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
        $coords = parseGoogleMapsCoords($map_url, $map_host);
        $lat       = $coords['lat'];
        $lng       = $coords['lng'];
        $map_query = $coords['map_query'];

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

// Get associated users (managers and students)
$users_stmt = safeQueryPrepare($conn, "SELECT u.*, r.name as role_name 
                                    FROM users u 
                                    JOIN roles r ON u.role_id = r.id
                                    JOIN user_accommodation ua ON u.id = ua.user_id
                                    WHERE ua.accommodation_id = ?
                                    ORDER BY r.name, u.first_name, u.last_name");
$users_stmt->bind_param("i", $accommodation_id);
$users_stmt->execute();
$users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "View Accommodation";
$activePage = "accommodations";

// Include header
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php require_once '../../includes/components/messages.php'; ?>
    
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/accommodations.php">Accommodations</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($accommodation_name, ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= htmlspecialchars($accommodation_name, ENT_QUOTES, 'UTF-8') ?></h2>
        <div>
            <a href="edit-accommodation.php?id=<?= $accommodation_id ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <a href="assign-users.php?id=<?= $accommodation_id ?>" class="btn btn-success">
                <i class="bi bi-person-plus"></i> Assign Users
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Accommodation Details</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th>Name:</th>
                            <td><?= htmlspecialchars($accommodation_name, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php if (!empty($accommodation['address_line1']) || !empty($accommodation['address_line2']) || !empty($accommodation['city']) || !empty($accommodation['province']) || !empty($accommodation['postal_code'])): ?>
                        <tr>
                            <th>Address:</th>
                            <td><?= htmlspecialchars(trim(implode(', ', array_filter([
                                $accommodation['address_line1'] ?? '',
                                $accommodation['address_line2'] ?? '',
                                $accommodation['city'] ?? '',
                                $accommodation['province'] ?? '',
                                $accommodation['postal_code'] ?? ''
                            ]))), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($map_url !== ''): ?>
                        <tr>
                            <th>Map:</th>
                            <td><a href="<?= htmlspecialchars($map_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open Map</a></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($accommodation['max_students'])): ?>
                        <tr>
                            <th>Maximum Students:</th>
                            <td><?= (int)$accommodation['max_students'] ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($accommodation['contact_phone'])): ?>
                        <tr>
                            <th>Contact Phone:</th>
                            <td><?= htmlspecialchars($accommodation['contact_phone'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($accommodation['contact_email'])): ?>
                        <tr>
                            <th>Contact Email:</th>
                            <td><?= htmlspecialchars($accommodation['contact_email'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($accommodation['notes'])): ?>
                        <tr>
                            <th>Notes:</th>
                            <td><?= nl2br(htmlspecialchars($accommodation['notes'], ENT_QUOTES, 'UTF-8')) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Owner:</th>
                            <td>
                                <a href="view-user.php?id=<?= $accommodation['owner_id'] ?>">
                                    <?= htmlspecialchars($accommodation['first_name'] . ' ' . $accommodation['last_name']) ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Owner Email:</th>
                            <td><?= htmlspecialchars($accommodation['email']) ?></td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td><?= date('F j, Y', strtotime($accommodation['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td><?= date('F j, Y', strtotime($accommodation['updated_at'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h3>
                                <?php
                                $manager_count = 0;
                                $student_count = 0;
                                foreach ($users as $user) {
                                    if ($user['role_name'] === 'manager') $manager_count++;
                                    if ($user['role_name'] === 'student') $student_count++;
                                }
                                echo $manager_count;
                                ?>
                            </h3>
                            <p class="text-muted">Managers</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h3><?= $student_count ?></h3>
                            <p class="text-muted">Students</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Map Preview</h5>
                </div>
                <div class="card-body">
                    <?php if ($show_map_previews && $street_view_embed_url !== '' && $small_map_embed_url !== ''): ?>
                        <h6 class="mb-2">Street View</h6>
                        <?php if ($use_sv_js): ?>
                            <div id="sv-canvas-admin" class="mb-3" style="width:100%;height:360px;border-radius:4px;overflow:hidden;"></div>
                        <?php else: ?>
                            <div class="ratio ratio-16x9 mb-3">
                                <iframe src="<?= htmlspecialchars($street_view_embed_url, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        <?php endif; ?>
                        <h6 class="mb-2">Map View (Street Level)</h6>
                        <div class="ratio ratio-16x9">
                            <iframe src="<?= htmlspecialchars($small_map_embed_url, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    <?php elseif ($map_url !== ''): ?>
                        <p class="text-muted small mb-0">Preview is unavailable for this link. <a href="<?= htmlspecialchars($map_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open map</a>.</p>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No map URL available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Associated Users</h5>
        </div>
        <div class="card-body p-0">
            <?php if (count($users) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                    <td>
                                        <span class="badge <?= $user['role_name'] === 'manager' ? 'bg-primary' : 'bg-success' ?>">
                                            <?= ucfirst($user['role_name']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($user['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view-user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p>No users are associated with this accommodation yet.</p>
                    <a href="assign-users.php?id=<?= $accommodation_id ?>" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Assign Users
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $extraScripts = $extraScripts ?? [];
if ($use_sv_js) {
    $sv_lat_val = (float)$lat;
    $sv_lng_val = (float)$lng;
    $extraScripts[] = '<script>
function initAdminStreetView() {
    var pos = {lat: ' . $sv_lat_val . ', lng: ' . $sv_lng_val . '};
    var canvas = document.getElementById("sv-canvas-admin");
    var sv = new google.maps.StreetViewService();
    sv.getPanorama({location: pos, radius: 50, source: google.maps.StreetViewSource.OUTDOOR}, function(data, status) {
        if (status === google.maps.StreetViewStatus.OK) {
            new google.maps.StreetViewPanorama(canvas, {
                pano: data.location.pano,
                pov: {heading: 34, pitch: 10},
                zoom: 1
            });
        } else {
            sv.getPanorama({location: pos, radius: 500, source: google.maps.StreetViewSource.OUTDOOR}, function(d2, s2) {
                if (s2 === google.maps.StreetViewStatus.OK) {
                    new google.maps.StreetViewPanorama(canvas, {
                        pano: d2.location.pano,
                        pov: {heading: 34, pitch: 10},
                        zoom: 1
                    });
                } else {
                    canvas.innerHTML = "<p class=\"text-muted small p-3\">Street View imagery is not available for this location.</p>";
                }
            });
        }
    });
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=' . htmlspecialchars($sv_api_key, ENT_QUOTES, 'UTF-8') . '&callback=initAdminStreetView" async defer></script>';
}
require_once '../../includes/components/footer.php'; ?>
