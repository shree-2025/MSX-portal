<?php
function getUnreadNotifications($conn, $limit = 10) {
    $query = "SELECT n.*, u.full_name as user_name, c.title as course_title 
              FROM notifications n
              JOIN users u ON n.user_id = u.id
              JOIN courses c ON n.course_id = c.id
              WHERE n.is_read = FALSE
              ORDER BY n.created_at DESC
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function markAsRead($conn, $notificationId) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
    $stmt->bind_param("i", $notificationId);
    return $stmt->execute();
}

function getNotificationCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = FALSE");
    $row = $result->fetch_assoc();
    return $row['count'];
}
?>

<!-- Notification Dropdown -->
<li class="nav-item dropdown no-arrow mx-1">
    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-bell fa-fw"></i>
        <!-- Counter - Alerts -->
        <span class="badge badge-danger badge-counter"><?php echo getNotificationCount($conn); ?>+</span>
    </a>
    <!-- Dropdown - Alerts -->
    <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in"
        aria-labelledby="alertsDropdown">
        <h6 class="dropdown-header">
            Notifications
        </h6>
        <?php 
        $notifications = getUnreadNotifications($conn, 5);
        if (empty($notifications)): ?>
            <a class="dropdown-item d-flex align-items-center" href="#">
                <div class="mr-3">
                    <div class="icon-circle bg-primary">
                        <i class="fas fa-check text-white"></i>
                    </div>
                </div>
                <div>
                    <div class="small text-gray-500">No new notifications</div>
                </div>
            </a>
        <?php else: 
            foreach ($notifications as $notification): 
                $icon = '';
                $color = '';
                switch($notification['document_type']) {
                    case 'assignment':
                        $icon = 'fa-tasks';
                        $color = 'warning';
                        break;
                    case 'syllabus':
                        $icon = 'fa-file-alt';
                        $color = 'info';
                        break;
                    case 'notes':
                        $icon = 'fa-sticky-note';
                        $color = 'success';
                        break;
                    case 'test':
                        $icon = 'fa-file-alt';
                        $color = 'danger';
                        break;
                }
                $timeAgo = timeAgo($notification['created_at']);
        ?>
            <a class="dropdown-item d-flex align-items-center" href="#" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                <div class="mr-3">
                    <div class="icon-circle bg-<?php echo $color; ?>">
                        <i class="fas <?php echo $icon; ?> text-white"></i>
                    </div>
                </div>
                <div>
                    <div class="small text-gray-500"><?php echo $timeAgo; ?></div>
                    <span class="font-weight-bold"><?php echo htmlspecialchars($notification['user_name']); ?></span>
                    downloaded <?php echo htmlspecialchars($notification['document_type']); ?>: 
                    <?php echo htmlspecialchars($notification['document_name']); ?>
                    <br>
                    <small>Course: <?php echo htmlspecialchars($notification['course_title']); ?></small>
                </div>
            </a>
        <?php endforeach; 
        endif; ?>
        <a class="dropdown-item text-center small text-gray-500" href="notifications.php">Show All Notifications</a>
    </div>
</li>

<script>
function markAsRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    });
}
</script>
