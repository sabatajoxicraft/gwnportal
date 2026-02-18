<?php
/**
 * Global Accommodation Context Handler
 * Include this early in config.php to handle accommodation switching globally
 */

// Ensure functions are available
if (!function_exists('safeQueryPrepare')) {
    require_once __DIR__ . '/functions.php';
}

// Run for managers and owners (roles that manage multiple accommodations)
$handlerRole = $_SESSION['user_role'] ?? '';
if (in_array($handlerRole, ['manager', 'owner'])) {
    $userId = $_SESSION['user_id'] ?? 0;
    
    if ($userId) {
        $conn = getDbConnection();
        
        // Handle accommodation switch from any page
        if (isset($_GET['switch_accommodation']) && !empty($_GET['switch_accommodation'])) {
            $requestedAccomId = (int)$_GET['switch_accommodation'];
            
            // Verify user has access to this accommodation
            if ($handlerRole === 'manager') {
                $verifyStmt = safeQueryPrepare($conn, "SELECT accommodation_id FROM user_accommodation WHERE user_id = ? AND accommodation_id = ?");
            } else {
                // Owner: check accommodations.owner_id
                $verifyStmt = safeQueryPrepare($conn, "SELECT id AS accommodation_id FROM accommodations WHERE owner_id = ? AND id = ?");
            }
            if ($verifyStmt) {
                $verifyStmt->bind_param("ii", $userId, $requestedAccomId);
                $verifyStmt->execute();
                if ($verifyStmt->get_result()->num_rows > 0) {
                    $_SESSION['accommodation_id'] = $requestedAccomId;
                    
                    // Redirect to clean URL (remove switch_accommodation parameter)
                    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
                    if (!empty($_SERVER['QUERY_STRING'])) {
                        parse_str($_SERVER['QUERY_STRING'], $params);
                        unset($params['switch_accommodation']);
                        if (!empty($params)) {
                            $redirect_url .= '?' . http_build_query($params);
                        }
                    }
                    header("Location: " . $redirect_url);
                    exit;
                }
            }
        }
        
        // Load current accommodation context if not set
        if (!isset($_SESSION['accommodation_id']) || empty($_SESSION['accommodation_id'])) {
            if ($handlerRole === 'manager') {
                $stmtAcc = safeQueryPrepare($conn, "SELECT ua.accommodation_id FROM user_accommodation ua WHERE ua.user_id = ? LIMIT 1");
            } else {
                $stmtAcc = safeQueryPrepare($conn, "SELECT id AS accommodation_id FROM accommodations WHERE owner_id = ? LIMIT 1");
            }
            if ($stmtAcc) {
                $stmtAcc->bind_param("i", $userId);
                $stmtAcc->execute();
                $rowAcc = $stmtAcc->get_result()->fetch_assoc();
                if ($rowAcc) {
                    $_SESSION['accommodation_id'] = (int)$rowAcc['accommodation_id'];
                }
            }
        }
        
        // Load user's accommodations list for navigation
        if ($handlerRole === 'manager') {
            $stmtAllAccom = safeQueryPrepare($conn, "SELECT a.id, a.name FROM accommodations a 
                                                      JOIN user_accommodation ua ON a.id = ua.accommodation_id 
                                                      WHERE ua.user_id = ? ORDER BY a.name");
        } else {
            $stmtAllAccom = safeQueryPrepare($conn, "SELECT id, name FROM accommodations WHERE owner_id = ? ORDER BY name");
        }
        if ($stmtAllAccom) {
            $stmtAllAccom->bind_param("i", $userId);
            $stmtAllAccom->execute();
            $_SESSION['manager_accommodations'] = $stmtAllAccom->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        // Load current accommodation details
        if (isset($_SESSION['accommodation_id'])) {
            $stmtCurrent = safeQueryPrepare($conn, "SELECT id, name FROM accommodations WHERE id = ?");
            if ($stmtCurrent) {
                $stmtCurrent->bind_param("i", $_SESSION['accommodation_id']);
                $stmtCurrent->execute();
                $_SESSION['current_accommodation'] = $stmtCurrent->get_result()->fetch_assoc();
            }
        }
    }
}
