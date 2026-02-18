<?php
require_once '../includes/config.php';
$pageTitle = "Icon Test";
require_once '../includes/components/header.php';
require_once '../includes/components/navigation.php';
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <h4>Bootstrap Icons Test</h4>
        </div>
        <div class="card-body">
            <h5>Common Icons</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-house fs-4 me-2"></i>
                        <span>bi-house (Home)</span>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-person fs-4 me-2"></i>
                        <span>bi-person (Person)</span>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-gear fs-4 me-2"></i>
                        <span>bi-gear (Settings)</span>
                    </div>
                </div>
            </div>
            
            <h5 class="mt-4">Accommodations Icons</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-building fs-4 me-2"></i>
                        <span>bi-building</span>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-house-door fs-4 me-2"></i>
                        <span>bi-house-door</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <p class="mb-0">If the icons above display correctly, your Bootstrap Icons are working. If not, please check your internet connection or the Bootstrap Icons CDN.</p>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
