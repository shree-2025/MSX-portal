<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid meeting ID');
    header('Location: meetings.php');
    exit();
}

$meeting_id = (int)$_GET['id'];

// Get meeting details
$stmt = $conn->prepare("
    SELECT vm.*, u.username as creator_name
    FROM video_meetings vm
    LEFT JOIN users u ON vm.host_id = u.id
    WHERE vm.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $meeting_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();

if (!$meeting) {
    setFlashMessage('error', 'Meeting not found');
    header('Location: meetings.php');
    exit();
}

// Get participants
$participants = $conn->query("
    SELECT mp.*, u.username, u.email
    FROM meeting_participants mp
    JOIN users u ON mp.user_id = u.id
    WHERE mp.meeting_id = $meeting_id
    ORDER BY mp.role DESC, u.username
")->fetch_all(MYSQLI_ASSOC);

// Calculate meeting status
$start_time = new DateTime($meeting['start_time']);
$end_time = (clone $start_time)->add(new DateInterval("PT{$meeting['duration']}M"));
$now = new DateTime();
$is_live = ($now >= $start_time && $now <= $end_time);
$has_ended = ($now > $end_time);

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars($meeting['title']); ?></h1>
        <div>
            <a href="meetings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Meetings
            </a>
            <a href="../join_meeting.php?meeting=<?php echo $meeting['meeting_id']; ?>" 
               class="btn btn-primary" target="_blank">
                <i class="fas fa-video"></i> Join Meeting
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Meeting Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <p><strong>Date & Time:</strong> <?php echo $start_time->format('l, F j, Y \a\t g:i A'); ?></p>
                        <p><strong>Duration:</strong> <?php echo $meeting['duration']; ?> minutes</p>
                        <p><strong>Created by:</strong> <?php echo htmlspecialchars($meeting['creator_name']); ?></p>
                        <p><strong>Meeting ID:</strong> <code><?php echo $meeting['meeting_id']; ?></code></p>
                        
                        <?php if (!empty($meeting['description'])): ?>
                            <div class="mt-4">
                                <h5>Description</h5>
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($meeting['description'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <h5>Meeting Link</h5>
                            <div class="input-group">
                                <input type="text" class="form-control" id="meetingLink" 
                                       value="<?php echo getBaseUrl(); ?>/join_meeting.php?meeting=<?php echo $meeting['meeting_id']; ?>" 
                                       readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyMeetingLink()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Participants (<?php echo count($participants); ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($participants)): ?>
                        <p class="text-muted">No participants yet</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($participants as $participant): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($participant['username']); ?>
                                    <span class="badge bg-<?php echo $participant['status'] === 'joined' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($participant['status']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyMeetingLink() {
    const copyText = document.getElementById("meetingLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");
    
    // Show tooltip or alert
    alert("Meeting link copied to clipboard!");
}
</script>

<?php include_once 'includes/footer.php'; ?>
