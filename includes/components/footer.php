<?php
/**
 * Common footer component for all pages
 * 
 * @param array $extraScripts Additional scripts to include
 */

$extraScripts = $extraScripts ?? [];
?>
<!-- Main content ends here -->
    
    <footer class="mt-5">
        <div class="container">
            <div class="row py-4">
                <div class="col-md-6">
                    <h5><i class="bi bi-wifi me-2"></i><?= APP_NAME ?></h5>
                    <p class="text-light mb-0">A complete WiFi management solution for student accommodations.</p>
                </div>
                <div class="col-md-3">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="<?= BASE_URL ?>/dashboard.php" class="text-light">Dashboard</a></li>
                            <li><a href="<?= BASE_URL ?>/profile.php" class="text-light">My Profile</a></li>
                        <?php else: ?>
                            <li><a href="<?= BASE_URL ?>/index.php" class="text-light">Home</a></li>
                            <li><a href="<?= BASE_URL ?>/login.php" class="text-light">Login</a></li>
                        <?php endif; ?>
                        <li><a href="<?= BASE_URL ?>/contact.php" class="text-light">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>Support</h6>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-envelope me-2"></i> support@example.com</li>
                        <li><i class="bi bi-telephone me-2"></i> +27 12 345 6789</li>
                    </ul>
                </div>
            </div>
            <div class="row border-top border-light pt-3">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Version <?= defined('APP_VERSION') ? APP_VERSION : '1.0.0' ?></p>
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
    </script>
    
    <!-- Page-specific scripts -->
    <?php if (!empty($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <?= $script ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
