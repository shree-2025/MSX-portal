<?php
require_once __DIR__ . '/includes/header.php';

// Get counts for dashboard cards
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
$result = $conn->query($query);
$total_students = $result->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM courses";
$result = $conn->query($query);
$total_courses = $result->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM assignments";
$result = $conn->query($query);
$total_assignments = $result->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM tests";
$result = $conn->query($query);
$total_tests = $result->fetch_assoc()['total'];

// Get recent activity logs
$query = "SELECT al.*, u.username, u.full_name 
          FROM activity_logs al 
          JOIN users u ON al.user_id = u.id 
          ORDER BY al.created_at DESC 
          LIMIT 5";
$recent_activities = $conn->query($query);

// Get student enrollment data for the chart (last 6 months)
$enrollment_data = [];
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i month"));
    $months[] = date('M Y', strtotime($month . '-01'));
    
    $query = "SELECT COUNT(*) as count FROM users 
              WHERE role = 'student' 
              AND DATE_FORMAT(created_at, '%Y-%m') = '$month'";
    $result = $conn->query($query);
    $enrollment_data[] = $result->fetch_assoc()['count'];
}
?>

<!-- Begin Page Content -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Dashboard Overview</h1>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>!</p>
        </div>
        <div>
            <!-- <a href="meetings.php" class="btn btn-primary me-2">
                <i class="fas fa-video me-1"></i> Video Meetings
            </a>
            <a href="meetings.php?action=create" class="btn btn-success">
                <i class="fas fa-plus me-1"></i> Create Meeting
            </a> -->
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <!-- Students Card -->
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-users text-primary"></i>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">+12%</span>
                        </div>
                    </div>
                    <h5 class="text-muted mb-1">Total Students</h5>
                    <h2 class="mb-0"><?= number_format($total_students) ?></h2>
                    <p class="text-muted mb-0 small"><span class="text-success">+24</span> this month</p>
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0">
                    <a href="students.php" class="btn btn-link p-0 text-primary text-decoration-none">
                        View all <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Courses Card -->
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-book-open text-info"></i>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-danger">-2%</span>
                        </div>
                    </div>
                    <h5 class="text-muted mb-1">Total Courses</h5>
                    <h2 class="mb-0"><?= number_format($total_courses) ?></h2>
                    <p class="text-muted mb-0 small"><span class="text-danger">-1</span> this month</p>
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0">
                    <a href="courses.php" class="btn btn-link p-0 text-primary text-decoration-none">
                        View all <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Assignments Card -->
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-tasks text-warning"></i>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">+8%</span>
                        </div>
                    </div>
                    <h5 class="text-muted mb-1">Assignments</h5>
                    <h2 class="mb-0"><?= number_format($total_assignments) ?></h2>
                    <p class="text-muted mb-0 small"><span class="text-success">+5</span> this week</p>
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0">
                    <a href="assignments.php" class="btn btn-link p-0 text-primary text-decoration-none">
                        View all <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Tests Card -->
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-clipboard-check text-danger"></i>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">+15%</span>
                        </div>
                    </div>
                    <h5 class="text-muted mb-1">Tests & Exams</h5>
                    <h2 class="mb-0"><?= number_format($total_tests) ?></h2>
                    <p class="text-muted mb-0 small"><span class="text-success">+3</span> upcoming</p>
                </div>
                <div class="card-footer bg-transparent border-top-0 pt-0">
                    <a href="tests.php" class="btn btn-link p-0 text-primary text-decoration-none">
                        View all <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-4 mb-4">
        <!-- Student Enrollment Chart - Full Width -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Student Enrollment Overview</h5>
                </div>
                <div class="card-body p-4">
                    <div style="height: 400px;">
                        <canvas id="enrollmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        
    </div>

    <!-- Recent Activity & Quick Actions -->
    <div class="row g-4">
        <!-- Recent Activity -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activity</h5>
                        <a href="activity-logs.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                            <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                <div class="list-group-item border-0 py-3">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm">
                                                <span class="avatar-title rounded-circle bg-primary bg-opacity-10 text-primary">
                                                    <?= strtoupper(substr($activity['full_name'] ?? 'U', 0, 1)) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?= htmlspecialchars($activity['full_name'] ?? 'User') ?></h6>
                                                <small class="text-muted">
                                                    <?= date('M j, g:i A', strtotime($activity['created_at'])) ?>
                                                </small>
                                            </div>
                                            <p class="mb-0 text-muted">
                                                <?= htmlspecialchars($activity['action'] ?? '') ?>
                                                <?php if (!empty($activity['details'])): ?>
                                                    <span class="text-primary"><?= htmlspecialchars($activity['details']) ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="text-muted mb-2">
                                    <i class="fas fa-inbox fa-3x"></i>
                                </div>
                                <p class="mb-0">No recent activities found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="students.php?action=add" class="btn btn-outline-primary text-start">
                            <i class="fas fa-user-plus me-2"></i> Add New Student
                        </a>
                        <a href="courses.php?action=add" class="btn btn-outline-primary text-start">
                            <i class="fas fa-plus-circle me-2"></i> Create New Course
                        </a>
                        <a href="assignments.php?action=add" class="btn btn-outline-primary text-start">
                            <i class="fas fa-tasks me-2"></i> Create Assignment
                        </a>
                        <a href="tests.php?action=add" class="btn btn-outline-primary text-start">
                            <i class="fas fa-edit me-2"></i> Schedule Test
                        </a>
                        <a href="announcements.php?action=add" class="btn btn-outline-primary text-start">
                            <i class="fas fa-bullhorn me-2"></i> Post Announcement
                        </a>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
          
        </div>
    </div>
</div>

    <!-- Chart.js Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enrollment Chart
            const ctx = document.getElementById('enrollmentChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($months) ?>,
                    datasets: [{
                        label: 'New Students',
                        data: <?= json_encode($enrollment_data) ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointHoverBorderColor: '#fff',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                size: 14,
                                weight: '600'
                            },
                            bodyFont: {
                                size: 13
                            },
                            padding: 12,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                color: '#6c757d'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#6c757d',
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
                    

<?php require_once __DIR__ . '/includes/footer.php'; ?>
