<?php
require_once __DIR__ . '/includes/header.php';

$student_id = $_SESSION['user_id'];

// Get student's profile photo and name
$query = "SELECT full_name, profile_photo FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Set default avatar if no profile photo
$profile_photo = !empty($student['profile_photo']) 
    ? 'uploads/profile_photos/' . $student['profile_photo'] 
    : 'https://ui-avatars.com/api/?name=' . urlencode($student['full_name'] ?? 'Student') . '&background=4f46e5&color=fff&size=128';

// Get student's enrolled courses
$query = "SELECT c.* FROM courses c 
          JOIN student_courses sc ON c.id = sc.course_id 
          WHERE sc.student_id = ? AND sc.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_courses = count($enrolled_courses);
$stmt->close();

// Get pending assignments count
$query = "SELECT COUNT(*) as total FROM assignments a 
          JOIN assignment_submissions s ON a.id = s.assignment_id 
          WHERE s.student_id = ? AND s.status = 'submitted' AND s.marks_obtained IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$pending_assignments = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
?>

<!-- Begin Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Enrolled Courses Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_courses; ?> Courses</div>
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Enrolled</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="#" class="text-primary">View All Courses <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Pending Assignments Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_assignments; ?> Pending</div>
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Assignments</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="assignments.php" class="text-warning">View Assignments <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Upcoming Tests Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="h5 mb-0 font-weight-bold text-gray-800">3 Upcoming</div>
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Tests</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="tests.php" class="text-danger">View Tests <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Documents -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Academic Documents</h6>
            <div>
                <a href="certificates.php" class="btn btn-sm btn-outline-primary mr-2">
                    <i class="fas fa-certificate mr-1"></i> All Certificates
                </a>
                <a href="transcripts.php" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-scroll mr-1"></i> All Transcripts
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Recent Certificates -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-left-primary">
                        <div class="card-header bg-light">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Certificates</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get recent certificates with proper column names
                            $recentCerts = $conn->query("SELECT c.*, co.title as course_name 
                                                       FROM certificates c 
                                                       JOIN courses co ON c.course_id = co.id 
                                                       WHERE c.user_id = $student_id 
                                                       ORDER BY c.issue_date DESC LIMIT 3");
                            
                            if ($recentCerts && $recentCerts->num_rows > 0): 
                                while($cert = $recentCerts->fetch_assoc()):
                            ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary text-white rounded p-2 me-3">
                                        <i class="fas fa-certificate"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($cert['course_name']) ?></h6>
                                        <small class="text-muted">Issued: <?= date('M d, Y', strtotime($cert['issue_date'])) ?></small>
                                    </div>
                                    <a href="certificates.php" class="btn btn-sm btn-outline-primary">View</a>
                                </div>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-certificate fa-2x text-gray-300 mb-2"></i>
                                    <p class="text-muted">No certificates found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Log -->
                <div class="col-md-6">
                    <div class="card h-100 border-left-info">
                        <div class="card-header bg-light">
                            <h6 class="m-0 font-weight-bold text-info">Recent Activity</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php
                                // Get recent activity (last 5 activities)
                                $recentActivity = [];
                                
                                // Get recent assignment submissions
                                $assignments = $conn->query("
                                    SELECT 'assignment' as type, a.title, s.submitted_at as activity_date, 
                                           CONCAT('Submitted: ', a.title) as description
                                    FROM assignment_submissions s
                                    JOIN assignments a ON s.assignment_id = a.id
                                    WHERE s.student_id = $student_id
                                    ORDER BY s.submitted_at DESC LIMIT 5
                                ")->fetch_all(MYSQLI_ASSOC);
                                $recentActivity = array_merge($recentActivity, $assignments);
                                
                                // Get recent test attempts
                                $tests = $conn->query("
                                    SELECT 'test' as type, t.title, a.submitted_at as activity_date,
                                           CONCAT('Attempted: ', t.title, ' - Score: ', COALESCE(a.obtained_marks, 'Pending')) as description
                                    FROM test_attempts a
                                    JOIN tests t ON a.test_id = t.id
                                    WHERE a.student_id = $student_id AND a.status = 'evaluated'
                                    ORDER BY a.submitted_at DESC LIMIT 5
                                ")->fetch_all(MYSQLI_ASSOC);
                                $recentActivity = array_merge($recentActivity, $tests);
                                
                                // Sort all activities by date
                                usort($recentActivity, function($a, $b) {
                                    return strtotime($b['activity_date']) - strtotime($a['activity_date']);
                                });
                                
                                // Display only the 5 most recent activities
                                $recentActivity = array_slice($recentActivity, 0, 5);
                                
                                if (!empty($recentActivity)):
                                    foreach ($recentActivity as $activity):
                                        $icon = $activity['type'] === 'assignment' ? 'fa-tasks' : 'fa-question-circle';
                                        $timeAgo = time_elapsed_string($activity['activity_date']);
                                ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <i class="fas <?= $icon ?> text-info me-2"></i>
                                                <?= htmlspecialchars($activity['title']) ?>
                                            </h6>
                                            <small class="text-muted"><?= $timeAgo ?></small>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($activity['description']) ?></p>
                                    </div>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-history fa-2x text-gray-300 mb-2"></i>
                                        <p class="text-muted">No recent activity</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Transcripts -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-left-success">
                        <div class="card-header bg-light">
                            <h6 class="m-0 font-weight-bold text-success">Recent Transcripts</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $recentTranscripts = $conn->query("SELECT * FROM transcripts 
                                                            WHERE student_id = $student_id 
                                                            ORDER BY created_at DESC LIMIT 3");
                            
                            if ($recentTranscripts->num_rows > 0): 
                                while($transcript = $recentTranscripts->fetch_assoc()):
                            ?>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success text-white rounded p-2 me-3">
                                            <i class="fas fa-scroll"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">Transcript #<?= htmlspecialchars($transcript['transcript_number']) ?></div>
                                            <small class="text-muted">Issued: <?= date('M j, Y', strtotime($transcript['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    <a href="../admin/generate_transcript.php?id=<?= $transcript['id'] ?>" 
                                       class="btn btn-sm btn-outline-success" 
                                       target="_blank">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-scroll fa-2x text-gray-300 mb-2"></i>
                                    <p class="text-muted">No transcripts available yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrolled Courses -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">My Enrolled Courses</h6>
        </div>
        <div class="card-body">
            <?php if (count($enrolled_courses) > 0): ?>
                <div class="row">
                    <?php foreach ($enrolled_courses as $course): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>
                                        <?php echo strlen($course['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary"><?php echo ucfirst($course['status']); ?></span>
                                        <a href="course_materials.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            View Details <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-book-open fa-3x text-gray-300 mb-3"></i>
                    <p class="text-muted">You are not enrolled in any courses yet.</p>
                    <a href="courses.php" class="btn btn-primary">Browse Courses</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Announcements -->
   
<!-- /.container-fluid -->

<?php 
/**
 * Format a timestamp as a relative time string (e.g., "2 hours ago")
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
