<?php
/**
 * Session Security Configuration
 * 
 * Environment-aware session settings. Load from environment or use secure defaults.
 * This file must be included BEFORE session_start() is called.
 * 
 * @package GWN-Portal
 * @since M1-T3
 */

// Load session timeout settings from environment or use defaults
$sessionTimeout = getenv('SESSION_TIMEOUT') ?: 1800;           // 30 minutes
$sessionRegenerate = getenv('SESSION_REGENERATE_INTERVAL') ?: 3600;  // 1 hour
$cookieSecure = getenv('SESSION_COOKIE_SECURE') ?: 0;          // Set to 1 in production (HTTPS)
$cookieSameSite = getenv('SESSION_COOKIE_SAMESITE') ?: 'Strict';

// Session Security Settings
ini_set('session.cookie_httponly', 1);           // Prevent XSS access to cookies
ini_set('session.cookie_samesite', $cookieSameSite);  // CSRF protection
ini_set('session.use_strict_mode', 1);           // Reject uninitialized session IDs
ini_set('session.cookie_secure', $cookieSecure); // HTTPS only in production
ini_set('session.use_only_cookies', 1);          // No session IDs in URLs
ini_set('session.sid_length', 48);               // Longer session IDs for security
ini_set('session.sid_bits_per_character', 6);    // More entropy per character

// Session timeout: configurable via environment
ini_set('session.gc_maxlifetime', $sessionTimeout);  // Server-side session lifetime
ini_set('session.cookie_lifetime', 0);               // Browser session only (cookie expires on close)

// Store settings as constants for use elsewhere
define('SESSION_TIMEOUT', (int)$sessionTimeout);
define('SESSION_REGENERATE_INTERVAL', (int)$sessionRegenerate);
