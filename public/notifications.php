<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$conn = getDbConnection();

// Require login
if (!isLoggedIn()){
    redirect(BASE_URL.'/login.php','Please login to view notifications','warning');
}

$userId = $_SESSION['user_id'];

// Fetch notifications for this user (assumes a notifications table exists with columns: id, recipient_id, sender_id, message, type, created_at, read_status)
$stmt = safeQueryPrepare($conn, "SELECT n.*, u.username as sender_username 
                                 FROM notifications n 
                                 LEFT JOIN users u ON n.sender_id = u.id 
                                 WHERE n.recipient_id = ? 
                                 ORDER BY n.created_at DESC");
if ($stmt !== false) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $notifications = [];
}

require_once '../includes/components/header.php';
?>
<div class="container mt-4">
    <h2>Notifications</h2>
    <?php if (count($notifications) > 0): ?>
        <ul class="list-group">
            <?php foreach ($notifications as $notification): ?>
                <li class="list-group-item <?= $notification['read_status'] == 0 ? 'list-group-item-info' : '' ?>">
                    <div>
                        <strong><?= htmlspecialchars($notification['message']) ?></strong>
                    </div>
                    <small>
                        From: <?= htmlspecialchars($notification['sender_username'] ?? 'System') ?>
                        on <?= date("M j, Y g:i A", strtotime($notification['created_at'])) ?>
                    </small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="alert alert-info">No notifications found.</div>
    <?php endif; ?>
</div>
<?php require_once '../includes/components/footer.php'; ?>
