<!DOCTYPE html>
<html>
<head>
    <title>Profile Checklist Widget Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Profile Checklist Widget Test</h1>
        
        <?php
        // Simulate session for testing
        session_start();
        $_SESSION['user_id'] = 7; // Student user
        
        require_once '../includes/config.php';
        require_once '../includes/db.php';
        require_once '../includes/functions.php';
        $conn = getDbConnection();
        
        // Load and display the widget
        require_once '../includes/services/ProfileChecklistService.php';
        include '../includes/components/profile-checklist-widget.php';
        ?>
        
        <div class="card mt-4">
            <div class="card-body">
                <h5>Test Instructions:</h5>
                <ul>
                    <li>Widget should appear above (if completion < 100%)</li>
                    <li>Progress bar should show percentage</li>
                    <li>Incomplete tasks should be listed</li>
                    <li>Click dismiss button to hide widget</li>
                    <li>Refresh page - widget should stay hidden if 100% complete</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
