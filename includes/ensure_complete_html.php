<?php
/**
 * Utility function to check if a page has proper header and footer
 * This can be included at the top of each page to ensure proper structure
 */

// Check if the output buffer is active
if (ob_get_level() == 0) {
    ob_start();
}

// Flag to track if header/footer are included
$GLOBALS['_header_included'] = false;
$GLOBALS['_footer_included'] = false;

// Override the require_once function to track header/footer inclusion
function custom_require_once($filename) {
    if (strpos($filename, 'header.php') !== false) {
        $GLOBALS['_header_included'] = true;
    } elseif (strpos($filename, 'footer.php') !== false) {
        $GLOBALS['_footer_included'] = true;
    }
    
    return require_once($filename);
}

// Register shutdown function to check for header/footer
register_shutdown_function(function() {
    // If footer wasn't included, include it
    if (!$GLOBALS['_footer_included']) {
        require_once dirname(__FILE__) . '/components/footer.php';
    }
});
?>
