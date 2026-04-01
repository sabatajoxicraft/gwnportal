<?php
/**
 * Minimal vendor autoloader for gwn-portal.
 *
 * Composer is not required on the production server.
 * This file is committed to the repository so that FTP/shared-hosting
 * deploys work without running `composer install`.
 *
 * Packages included:
 *   - PHPMailer/PHPMailer 6.9.1  (MIT)  src/ → PHPMailer\PHPMailer namespace
 */

spl_autoload_register(static function (string $class): void {
    static $classMap = null;
    if ($classMap === null) {
        $base = __DIR__;
        $classMap = [
            'PHPMailer\\PHPMailer\\Exception' => $base . '/phpmailer/phpmailer/src/Exception.php',
            'PHPMailer\\PHPMailer\\PHPMailer'  => $base . '/phpmailer/phpmailer/src/PHPMailer.php',
            'PHPMailer\\PHPMailer\\SMTP'       => $base . '/phpmailer/phpmailer/src/SMTP.php',
        ];
    }
    if (isset($classMap[$class])) {
        require_once $classMap[$class];
    }
});
