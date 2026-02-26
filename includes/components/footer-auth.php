<?php
/**
 * Auth Footer Component
 * Used on: Login, registration, password reset, authentication pages
 * 
 * Features: Minimal, distraction-free, single-line with essential links
 */

$extraScripts = $extraScripts ?? [];
?>
<!-- Main content ends here -->
    
    <footer class="footer-auth mt-auto py-3 bg-light border-top">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <small class="text-muted">
                        &copy; <?= date('Y') ?> <?= APP_NAME ?>
                    </small>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="<?= BASE_URL ?>/help.php" class="text-muted text-decoration-none me-3 small">Help</a>
                    <a href="<?= BASE_URL ?>/contact.php" class="text-muted text-decoration-none me-3 small">Contact</a>
                    <a href="#" class="text-muted text-decoration-none small">Privacy</a>
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
