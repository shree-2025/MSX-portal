<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireStudent();

$user_id = $_SESSION['user_id'];
$current_time = date('Y-m-d H:i:s');

// Get upcoming meetings
$upcoming_meetings = $conn->query("
    SELECT DISTINCT vm.*, u.username as creator_name
    FROM video_meetings vm
    LEFT JOIN meeting_participants mp ON vm.id = mp.meeting_id
    LEFT JOIN users u ON vm.created_by = u.id
    WHERE vm.start_time > '$current_time'
    AND (mp.user_id = $user_id OR vm.created_by = $user_id)
    ORDER BY vm.start_time ASC
")->fetch_all(MYSQLI_ASSOC);

// Get past meetings (last 30 days)
$past_meetings = $conn->query("
    SELECT DISTINCT vm.*, u.username as creator_name
    FROM video_meetings vm
    LEFT JOIN meeting_participants mp ON vm.id = mp.meeting_id
    LEFT JOIN users u ON vm.created_by = u.id
    WHERE vm.start_time <= '$current_time'
    AND vm.start_time >= DATE_SUB('$current_time', INTERVAL 30 DAY)
    AND (mp.user_id = $user_id OR vm.created_by = $user_id)
    ORDER BY vm.start_time DESC
")->fetch_all(MYSQLI_ASSOC);

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Video Meetings</h1>
        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" 
           data-bs-toggle="modal" data-bs-target="#joinMeetingModal">
            <i class="fas fa-plus fa-sm text-white-50"></i> Join with Code
        </a>
    </div>

    <!-- Upcoming Meetings -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Upcoming Meetings</h6>
        </div>
        <div class="card-body">
            <?php if (empty($upcoming_meetings)): ?>
                <div class="text-center py-4">
                    <i class="far fa-calendar-plus fa-3x text-gray-300 mb-3"></i>
                    <p class="text-muted">No upcoming meetings scheduled</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="upcomingMeetingsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date & Time</th>
                                <th>Duration</th>
                                <th>Host</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_meetings as $meeting): 
                                $start_time = new DateTime($meeting['start_time']);
                                $is_host = ($meeting['created_by'] == $user_id);
                            ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($meeting['title']); ?>
                                        <?php if ($is_host): ?>
                                            <span class="badge bg-primary">Host</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $start_time->format('M j, Y g:i A'); ?></td>
                                    <td><?php echo $meeting['duration']; ?> minutes</td>
                                    <td><?php echo htmlspecialchars($meeting['creator_name']); ?></td>
                                    <td>
                                        <a href="../join_meeting.php?meeting=<?php echo $meeting['meeting_id']; ?>" 
                                           class="btn btn-sm btn-primary" target="_blank">
                                            <i class="fas fa-video"></i> Join
                                        </a>
                                        <?php if ($is_host): ?>
                                            <a href="../admin/meeting_details.php?id=<?php echo $meeting['id']; ?>" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-cog"></i> Manage
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Past Meetings -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Past Meetings (Last 30 Days)</h6>
        </div>
        <div class="card-body">
            <?php if (empty($past_meetings)): ?>
                <div class="text-center py-4">
                    <i class="far fa-calendar-check fa-3x text-gray-300 mb-3"></i>
                    <p class="text-muted">No past meetings found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="pastMeetingsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date & Time</th>
                                <th>Host</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_meetings as $meeting): 
                                $start_time = new DateTime($meeting['start_time']);
                                $end_time = (clone $start_time)->add(new DateInterval("PT{$meeting['duration']}M"));
                                $now = new DateTime();
                                $status = $now > $end_time ? 'Completed' : 'In Progress';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($meeting['title']); ?></td>
                                    <td><?php echo $start_time->format('M j, Y g:i A'); ?></td>
                                    <td><?php echo htmlspecialchars($meeting['creator_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status === 'Completed' ? 'secondary' : 'success'; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Join Meeting Modal -->
<div class="modal fade" id="joinMeetingModal" tabindex="-1" aria-labelledby="joinMeetingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="joinMeetingModalLabel">Join a Meeting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="joinMeetingForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="meetingCode" class="form-label">Meeting Code</label>
                        <input type="text" class="form-control" id="meetingCode" 
                               placeholder="Enter meeting code" required>
                        <div class="form-text">Ask the host for the meeting code</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Join Meeting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#upcomingMeetingsTable, #pastMeetingsTable').DataTable({
        pageLength: 10,
        order: [[1, 'asc']], // Sort by date by default
        responsive: true
    });
    
    // Handle join meeting form submission
    $('#joinMeetingForm').on('submit', function(e) {
        e.preventDefault();
        const meetingCode = $('#meetingCode').val().trim();
        if (meetingCode) {
            window.open(`../join_meeting.php?meeting=${encodeURIComponent(meetingCode)}`, '_blank');
            $('#joinMeetingModal').modal('hide');
            $('#meetingCode').val('');
        }
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
