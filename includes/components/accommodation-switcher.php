<?php
/**
 * Accommodation Switcher Component
 * Shows accommodation selector for managers with multiple accommodations
 * Include this at the top of any manager page that needs accommodation filtering
 */

// Only show for managers
if ($_SESSION['user_role'] !== 'manager') {
    return;
}

$userId = $_SESSION['user_id'] ?? 0;
$currentAccommodationId = $_SESSION['accommodation_id'] ?? 0;

// Get all accommodations for this manager
$managerAccommodations = [];
$currentAccommodation = null;

if ($userId) {
    $conn = getDbConnection();
    
    $stmtAllAccom = safeQueryPrepare($conn, "SELECT a.id, a.name FROM accommodations a 
                                              JOIN user_accommodation ua ON a.id = ua.accommodation_id 
                                              WHERE ua.user_id = ? ORDER BY a.name");
    if ($stmtAllAccom) {
        $stmtAllAccom->bind_param("i", $userId);
        $stmtAllAccom->execute();
        $managerAccommodations = $stmtAllAccom->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get current accommodation details
        foreach ($managerAccommodations as $accom) {
            if ($accom['id'] == $currentAccommodationId) {
                $currentAccommodation = $accom;
                break;
            }
        }
        
        // If no accommodation selected, use the first one
        if (!$currentAccommodation && count($managerAccommodations) > 0) {
            $currentAccommodation = $managerAccommodations[0];
            $_SESSION['accommodation_id'] = $currentAccommodation['id'];
            $currentAccommodationId = $currentAccommodation['id'];
        }
    }
}

// Only show switcher if manager has multiple accommodations
if (count($managerAccommodations) > 1):
?>
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted d-block">Currently Managing:</small>
                        <strong class="text-primary">
                            <i class="bi bi-building me-2"></i><?= htmlspecialchars($currentAccommodation['name'] ?? 'No accommodation') ?>
                        </strong>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-shuffle me-1"></i>Switch
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach ($managerAccommodations as $accom): ?>
                                <li>
                                    <a class="dropdown-item <?= $accom['id'] == $currentAccommodationId ? 'active' : '' ?>" 
                                       href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?switch_accommodation=<?= $accom['id'] ?>">
                                        <i class="bi bi-building me-2"></i><?= htmlspecialchars($accom['name']) ?>
                                        <?= $accom['id'] == $currentAccommodationId ? '<i class="bi bi-check-lg ms-2"></i>' : '' ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
