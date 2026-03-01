<?php
/**
 * Public Footer Component
 * Used on: Landing page, marketing pages, public-facing content
 * 
 * Features: Rich branding, multiple columns, social links, newsletter
 */

$extraScripts = $extraScripts ?? [];
?>
<!-- Main content ends here -->
    
    <footer class="footer-public bg-dark text-light mt-auto">
        <div class="container py-5">
            <div class="row g-4">
                <!-- Branding Column -->
                <div class="col-lg-4 col-md-6">
                    <div class="mb-4">
                        <h4 class="text-white mb-1">
                            <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="Joxicraft logo" width="32" height="32" class="me-2 footer-logo"><?= APP_NAME ?>
                        </h4>
                        <p class="text-light-emphasis small mb-3">Powered by Joxicraft</p>
                        <p class="text-light-emphasis mb-4">
                            A complete WiFi management solution for student accommodations. 
                            Simplifying network access and management for institutions across South Africa.
                        </p>
                        <div class="d-flex gap-3">
                            <a href="#" class="text-white fs-4" title="Facebook" aria-label="Facebook">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="#" class="text-white fs-4" title="Twitter" aria-label="Twitter">
                                <i class="bi bi-twitter"></i>
                            </a>
                            <a href="#" class="text-white fs-4" title="LinkedIn" aria-label="LinkedIn">
                                <i class="bi bi-linkedin"></i>
                            </a>
                            <a href="#" class="text-white fs-4" title="Instagram" aria-label="Instagram">
                                <i class="bi bi-instagram"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links Column -->
                <div class="col-lg-2 col-md-6">
                    <h6 class="text-white fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/index.php" class="text-light-emphasis text-decoration-none hover-link">
                                <i class="bi bi-chevron-right small me-1"></i>Home
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/login.php" class="text-light-emphasis text-decoration-none hover-link">
                                <i class="bi bi-chevron-right small me-1"></i>Login
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/contact.php" class="text-light-emphasis text-decoration-none hover-link">
                                <i class="bi bi-chevron-right small me-1"></i>Contact Us
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/help.php" class="text-light-emphasis text-decoration-none hover-link">
                                <i class="bi bi-chevron-right small me-1"></i>Help Center
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Features Column -->
                <div class="col-lg-3 col-md-6">
                    <h6 class="text-white fw-bold mb-3">Features</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check2 text-success me-2"></i>
                            <span class="text-light-emphasis">Device Management</span>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check2 text-success me-2"></i>
                            <span class="text-light-emphasis">Voucher System</span>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check2 text-success me-2"></i>
                            <span class="text-light-emphasis">Student Portal</span>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check2 text-success me-2"></i>
                            <span class="text-light-emphasis">Multi-Accommodation</span>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check2 text-success me-2"></i>
                            <span class="text-light-emphasis">Real-time Monitoring</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Contact Column -->
                <div class="col-lg-3 col-md-6">
                    <h6 class="text-white fw-bold mb-3">Get in Touch</h6>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="bi bi-geo-alt-fill text-primary me-2"></i>
                            <span class="text-light-emphasis">Pikwane House, 61 Currey Street<br>Kimberley CBD, Kimberley<br>Northern Cape, South Africa, 8301</span>
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-envelope-fill text-primary me-2"></i>
                            <a href="mailto:support@joxicraft.co.za" class="text-light-emphasis text-decoration-none hover-link">
                                support@joxicraft.co.za
                            </a>
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-telephone-fill text-primary me-2"></i>
                            <a href="tel:+27530101113" class="text-light-emphasis text-decoration-none hover-link">
                                0530101113
                            </a>
                        </li>
                        <li class="mb-3">
                            <i class="bi bi-whatsapp text-primary me-2"></i>
                            <a href="https://wa.me/27787426676" class="text-light-emphasis text-decoration-none hover-link">
                                +27 78 742 6676
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Bottom Bar -->
            <hr class="border-secondary my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="mb-0 text-light-emphasis">
                        &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="#" class="text-light-emphasis text-decoration-none hover-link me-3">Privacy Policy</a>
                    <a href="#" class="text-light-emphasis text-decoration-none hover-link me-3">Terms of Service</a>
                    <span class="text-light-emphasis">
                        <i class="bi bi-code-square me-1"></i>
                        Version <?= defined('APP_VERSION') ? APP_VERSION : '1.0.0' ?>
                    </span>
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
