<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

// Generate a random meeting ID
function generateMeetingId() {
    return 'meet_' . bin2hex(random_bytes(8));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $duration = (int)($_POST['duration'] ?? 60);
    $max_participants = (int)($_POST['max_participants'] ?? 100);
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurring_pattern = $is_recurring ? ($_POST['recurring_pattern'] ?? 'weekly') : null;
    
    // Basic validation
    if (empty($title) || empty($start_time)) {
        setFlashMessage('error', 'Title and start time are required.');
    } else {
        $meeting_id = generateMeetingId();
        $start_datetime = date('Y-m-d H:i:s', strtotime($start_time));
        
        $stmt = $conn->prepare("INSERT INTO video_meetings 
            (title, description, meeting_id, host_id, start_time, duration, max_participants, is_recurring, recurring_pattern)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        $stmt->bind_param("sssssiiis", 
            $title, 
            $description, 
            $meeting_id, 
            $_SESSION['user_id'],
            $start_datetime,
            $duration,
            $max_participants,
            $is_recurring,
            $recurring_pattern
        );
        
        if ($stmt->execute()) {
            $new_meeting_id = $conn->insert_id;
            // Log the meeting creation
            error_log("Created new meeting with ID: $new_meeting_id, Meeting ID: $meeting_id");
            setFlashMessage('success', 'Meeting created successfully!');
            header("Location: meeting_details.php?id=$new_meeting_id");
            exit();
        } else {
            error_log("Failed to create meeting: " . $conn->error);
            setFlashMessage('error', 'Failed to create meeting. Please try again.');
        }
    }
}

// Get all meetings
$meetings = $conn->query("
    SELECT vm.*, u.username as creator_name 
    FROM video_meetings vm
    LEFT JOIN users u ON vm.host_id = u.id
    ORDER BY vm.start_time DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Video Meetings</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMeetingModal">
            <i class="fas fa-plus"></i> New Meeting
        </button>
    </div>
    
    <?php if (empty($meetings)): ?>
        <div class="card shadow mb-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-video fa-3x text-gray-300 mb-3"></i>
                <h4>No meetings scheduled yet</h4>
                <p class="text-muted">Get started by creating your first video meeting.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMeetingModal">
                    <i class="fas fa-plus"></i> Create Meeting
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Scheduled Meetings</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="meetingsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date & Time</th>
                                <th>Duration</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meetings as $meeting): 
                                $start_time = new DateTime($meeting['start_time']);
                                $end_time = (clone $start_time)->add(new DateInterval("PT{$meeting['duration']}M"));
                                $now = new DateTime();
                                $status = '';
                                
                                if ($start_time > $now) {
                                    $status = '<span class="badge bg-info">Scheduled</span>';
                                } elseif ($end_time > $now) {
                                    $status = '<span class="badge bg-success">Live Now</span>';
                                } else {
                                    $status = '<span class="badge bg-secondary">Completed</span>';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($meeting['title']); ?></strong>
                                        <?php if ($meeting['is_recurring']): ?>
                                            <span class="badge bg-primary">Recurring</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $start_time->format('M j, Y g:i A'); ?>
                                    </td>
                                    <td><?php echo $meeting['duration']; ?> minutes</td>
                                    <td><?php echo htmlspecialchars($meeting['creator_name']); ?></td>
                                    <td><?php echo $status; ?></td>
                                    <td>
                                        <a href="meeting_details.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="../join_meeting.php?meeting=<?php echo $meeting['meeting_id']; ?>" class="btn btn-sm btn-success" target="_blank">
                                            <i class="fas fa-video"></i> Join
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- New Meeting Modal -->
<div class="modal fade" id="newMeetingModal" tabindex="-1" aria-labelledby="newMeetingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="newMeetingForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="newMeetingModalLabel">Schedule New Meeting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Meeting Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_time" class="form-label">Start Time *</label>
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" required 
                                   min="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="duration" class="form-label">Duration (minutes) *</label>
                            <select class="form-select" id="duration" name="duration">
                                <option value="30">30 minutes</option>
                                <option value="60" selected>1 hour</option>
                                <option value="90">1.5 hours</option>
                                <option value="120">2 hours</option>
                                <option value="180">3 hours</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="max_participants" class="form-label">Maximum Participants</label>
                            <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                   min="2" max="300" value="100">
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_recurring" name="is_recurring">
                        <label class="form-check-label" for="is_recurring">This is a recurring meeting</label>
                    </div>
                    
                    <div id="recurringOptions" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Recurring Pattern</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recurring_pattern" 
                                       id="daily" value="daily" checked>
                                <label class="form-check-label" for="daily">
                                    Daily
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recurring_pattern" 
                                       id="weekly" value="weekly">
                                <label class="form-check-label" for="weekly">
                                    Weekly
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recurring_pattern" 
                                       id="monthly" value="monthly">
                                <label class="form-check-label" for="monthly">
                                    Monthly
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Schedule Meeting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
// Show/hide recurring options
const isRecurringCheckbox = document.getElementById('is_recurring');
const recurringOptions = document.getElementById('recurringOptions');

isRecurringCheckbox.addEventListener('change', function() {
    recurringOptions.style.display = this.checked ? 'block' : 'none';
});

// Initialize DataTable
$(document).ready(function() {
    $('#meetingsTable').DataTable({
        order: [[1, 'asc']], // Sort by date by default
        pageLength: 10,
        responsive: true
    });
});
</script>
