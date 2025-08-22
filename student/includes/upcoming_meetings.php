<?php
// Get upcoming meetings for the student
$current_time = date('Y-m-d H:i:s');
$end_time = date('Y-m-d H:i:s', strtotime('+7 days'));

$meetings = $conn->query("
    SELECT DISTINCT vm.*, u.username as creator_name
    FROM video_meetings vm
    LEFT JOIN meeting_participants mp ON vm.id = mp.meeting_id
    LEFT JOIN users u ON vm.created_by = u.id
    WHERE (vm.start_time BETWEEN '$current_time' AND '$end_time')
    AND (mp.user_id = {$_SESSION['user_id']} OR vm.created_by = {$_SESSION['user_id']})
    ORDER BY vm.start_time ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

if (!empty($meetings)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Upcoming Video Meetings</h6>
            <a href="meetings.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body">
            <div class="list-group list-group-flush">
                <?php foreach ($meetings as $meeting): 
                    $start_time = new DateTime($meeting['start_time']);
                    $is_host = ($meeting['created_by'] == $_SESSION['user_id']);
                ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($meeting['title']); ?></h6>
                            <small class="text-<?php echo $is_host ? 'success' : 'primary'; ?>">
                                <?php echo $is_host ? 'Host' : 'Participant'; ?>
                            </small>
                        </div>
                        <p class="mb-1">
                            <i class="far fa-calendar-alt"></i> 
                            <?php echo $start_time->format('D, M j, g:i A'); ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small>Host: <?php echo htmlspecialchars($meeting['creator_name']); ?></small>
                            <a href="../join_meeting.php?meeting=<?php echo $meeting['meeting_id']; ?>" 
                               class="btn btn-sm btn-primary" target="_blank">
                                Join <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
