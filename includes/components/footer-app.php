<?php
/**
 * App Footer Component
 * Used on: Dashboard, manager pages, student portal, admin panel (all logged-in pages)
 * 
 * Features: Compact, utility-focused, role-based links, system info
 */

$extraScripts = $extraScripts ?? [];
$userRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['full_name'] ?? 'User';
?>
<!-- Main content ends here -->
    
    <footer class="footer-app mt-auto py-3 bg-white border-top shadow-sm">
        <div class="container-fluid">
            <div class="row align-items-center">
                <!-- Left: System Status -->
                <div class="col-lg-4 col-md-6 text-center text-md-start mb-2 mb-lg-0">
                    <small class="text-muted d-flex align-items-center justify-content-center justify-content-md-start flex-wrap">
                        <span class="me-3">
                            <i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i>
                            <span class="ms-1">System Online</span>
                        </span>
                        <span class="text-muted">
                            &copy; <?= date('Y') ?> <?= APP_NAME ?>
                        </span>
                    </small>
                </div>
                
                <!-- Center: Quick Links (Role-based) -->
                <div class="col-lg-4 col-md-6 text-center mb-2 mb-lg-0">
                    <small>
                        <?php if ($userRole === 'admin'): ?>
                            <a href="<?= BASE_URL ?>/admin" class="text-muted text-decoration-none me-3 hover-link">
                                <i class="bi bi-gear-fill me-1"></i>Admin
                            </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($userRole, ['owner', 'manager', 'admin'])): ?>
                            <a href="<?= BASE_URL ?>/help.php" class="text-muted text-decoration-none me-3 hover-link">
                                <i class="bi bi-question-circle-fill me-1"></i>Help
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?= BASE_URL ?>/contact.php" class="text-muted text-decoration-none me-3 hover-link">
                            <i class="bi bi-envelope-fill me-1"></i>Support
                        </a>
                        
                        <?php if ($userRole === 'student'): ?>
                            <a href="<?= BASE_URL ?>/student" class="text-muted text-decoration-none hover-link">
                                <i class="bi bi-house-door-fill me-1"></i>My Portal
                            </a>
                        <?php endif; ?>
                    </small>
                </div>
                
                <!-- Right: User Info & Version -->
                <div class="col-lg-4 col-md-12 text-center text-lg-end">
                    <small class="text-muted d-flex align-items-center justify-content-center justify-content-lg-end flex-wrap">
                        <span class="me-3">
                            <i class="bi bi-person-circle me-1"></i>
                            <span class="fw-semibold"><?= htmlspecialchars($userName) ?></span>
                            <span class="badge bg-<?= $userRole === 'admin' ? 'danger' : ($userRole === 'owner' ? 'primary' : ($userRole === 'manager' ? 'info' : 'secondary')) ?> ms-2">
                                <?= ucfirst($userRole) ?>
                            </span>
                        </span>
                        <span>
                            <i class="bi bi-code-square me-1"></i>
                            v<?= defined('APP_VERSION') ? APP_VERSION : '1.0.0' ?>
                        </span>
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
    
    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(function(alert) {
        if (alert.classList.contains('alert-dismissible')) {
            setTimeout(function() {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        }
    });
    </script>
    
    <!-- Page-specific scripts -->
    <?php if (!empty($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <?= $script ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
