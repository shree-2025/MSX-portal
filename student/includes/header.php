<?php
// Start output buffering at the very beginning
if (!headers_sent()) {
    ob_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Ensure user is logged in and is a student
requireStudent();

// Get the current page name for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get the current page name for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Student Dashboard -MindSparxs</title>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom styles -->
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --transition-speed: 0.3s;
            --topbar-height: 60px;
        }
        
        /* Sidebar Styles */
        .sidebar {
            min-height: 100vh;
            background: #4e73df;
            color: white;
            width: var(--sidebar-width);
            position: fixed;
            z-index: 1030;
            top: 0;
            left: 0;
            overflow: hidden;
            padding: 20px 0 0 0;
            transition: all var(--transition-speed) ease-in-out;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transform: translateX(0);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-nav-container {
            flex: 1;
            overflow-y: scroll; /* Changed to scroll to always show scrollbar */
            overflow-x: hidden;
            padding: 0 0.5rem 1rem 0;
            margin-right: 0;
            max-height: calc(100vh - 120px);
            width: 100%;
            /* Force scrollbar to be always visible */
            scrollbar-width: thin;
            -ms-overflow-style: -ms-autohiding-scrollbar;
            scrollbar-color: rgba(255, 255, 255, 0.5) transparent;
        }
        
        /* Ensure proper scrolling on mobile */
        @media (max-width: 991.98px) {
            .sidebar-nav-container {
                max-height: calc(100vh - 100px);
                padding-bottom: 20px;
            }
        }
        
        /* Webkit Scrollbar - Always Visible */
        .sidebar-nav-container::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        .sidebar-nav-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 0;
            margin: 0;
        }
        
        .sidebar-nav-container::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 5px;
            border: 2px solid #4e73df;
        }
        
        .sidebar-nav-container::-webkit-scrollbar-thumb:hover {
            background-color: #ffffff;
        }
        
        /* Firefox Scrollbar */
        .sidebar-nav-container {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
        }
        
        /* Mobile styles */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1050;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .overlay.show {
                display: block;
                opacity: 1;
            }
        }
        .sidebar a {
            padding: 10px 15px;
            text-decoration: none;
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            transition: all var(--transition-speed) ease;
            white-space: nowrap;
        }
        .sidebar a:hover, .sidebar a.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            transition: all var(--transition-speed) ease-in-out;
            background-color: #f8f9fc;
        }
        /* Top Navigation */
        .topbar {
            background: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 0.75rem 1.5rem;
            margin: -20px -20px 20px -20px;
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        
        /* Toggle Button */
        #sidebarToggle {
            background: none;
            border: none;
            color: #4e73df;
            font-size: 1.25rem;
            margin-right: 1rem;
            cursor: pointer;
            padding: 0.25rem 0.75rem;
            border-radius: 0.35rem;
        }
        
        #sidebarToggle:hover {
            background-color: rgba(78, 115, 223, 0.1);
        }
        
        /* Collapsed Sidebar */
        body.sidebar-toggled .sidebar {
            width: var(--sidebar-collapsed-width);
            overflow: hidden;
        }
        
        body.sidebar-toggled .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        body.sidebar-toggled .sidebar .nav-link span,
        body.sidebar-toggled .sidebar .sidebar-brand span,
        body.sidebar-toggled .sidebar .sidebar-heading,
        body.sidebar-toggled .sidebar .nav-item .collapse {
            display: none;
        }
        
        body.sidebar-toggled .sidebar .nav-link {
            text-align: center;
            padding: 0.75rem 1rem;
            width: var(--sidebar-collapsed-width);
        }
        
        body.sidebar-toggled .sidebar .nav-link i {
            margin-right: 0;
            font-size: 1.25rem;
        }
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                z-index: 1050;
                box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .overlay.show {
                display: block;
                opacity: 1;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            body.sidebar-toggled .sidebar {
                transform: translateX(-100%);
            }
            
            .overlay {
                display: none;
                position: fixed;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.4);
                z-index: 1035;
                opacity: 0;
                transition: all 0.3s ease-in-out;
            }
            
            .overlay.show {
                display: block;
                opacity: 1;
            }
        }
        .sidebar-brand {
            padding: 15px;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            color: #fff !important;
            margin-bottom: 20px;
            display: block;
        }
        .sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin: 1rem 0;
        }
        .sidebar-heading {
            padding: 0 1rem;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
            letter-spacing: 0.13em;
        }
        .sidebar .nav-item {
            margin-bottom: 5px;
        }
        .nav-link {
            border-radius: 0.35rem;
            margin: 0 10px;
        }
        .badge-counter {
            position: absolute;
            transform: scale(0.7);
            transform-origin: top right;
            right: 0.35rem;
            margin-top: -0.5rem;
        }
        .nav-icon {
            margin-right: 0.5rem;
            width: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Overlay for Mobile -->
    <div class="overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-brand text-center">
    <i class="fas fa-graduation-cap me-2"></i>
    <img src="../assets/images/dlogo.png" alt="Sparxs Logo" class="login-logo-img" style="height: 40px;">
</a>
        <div class="sidebar-divider"></div>
        
        <div class="sidebar-nav-container">
            <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <div class="sidebar-heading">My Courses</div>
            
            <?php
            // Get student's enrolled courses
            $student_id = $_SESSION['user_id'];
            $query = "SELECT c.* FROM courses c 
                     JOIN student_courses sc ON c.id = sc.course_id 
                     WHERE sc.student_id = ? AND sc.status = 'active'
                     ORDER BY sc.enrollment_date DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $enrolled_courses = $stmt->get_result();
            
            if ($enrolled_courses->num_rows > 0) {
                while($course = $enrolled_courses->fetch_assoc()) {
                    $is_active = ($current_page == 'course_materials.php' && isset($_GET['id']) && $_GET['id'] == $course['id']);
                    echo '<li class="nav-item">';
                    echo '<a href="course_materials.php?id=' . $course['id'] . '" class="nav-link ' . ($is_active ? 'active' : '') . '">';
                    echo '<i class="fas fa-fw fa-book"></i> ' . htmlspecialchars($course['title'] ?? 'Untitled Course');
                    echo '</a>';
                    echo '</li>';
                }
            } else {
                echo '<li class="nav-item">';
                echo '<a href="#" class="nav-link text-muted">';
                echo '<i class="fas fa-fw fa-info-circle"></i> No courses enrolled';
                echo '</a>';
                echo '</li>';
            }
            $stmt->close();
            ?>
            
            <div class="sidebar-divider"></div>
            
            <div class="sidebar-heading"> My Resources</div>
            
            <li class="nav-item">
                <a href="study_materials.php" class="nav-link <?php echo $current_page == 'study_materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-book-open"></i> My Study Materials
                </a>
            </li>
            
            <li class="nav-item">
                <a href="assignments.php" class="nav-link <?php echo $current_page == 'assignments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-tasks"></i> My Assignments
                    <?php
                    // Count pending assignments
                    $query = "SELECT COUNT(*) as count FROM assignments a 
                             JOIN assignment_submissions s ON a.id = s.assignment_id 
                             WHERE s.student_id = ? AND s.status = 'submitted' AND s.marks_obtained IS NULL";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $student_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $pending_count = $result['count'];
                    $stmt->close();
                    
                    if ($pending_count > 0) {
                        // Removed the echo statement that was causing premature output
                    }
                    ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="#testCollapse" class="nav-link" data-bs-toggle="collapse" 
                   aria-expanded="<?php echo in_array($current_page, ['tests.php', 'view_test.php', 'test_result.php']) ? 'true' : 'false'; ?>" 
                   aria-controls="testCollapse">
                    <i class="fas fa-fw fa-file-alt"></i> My Tests
                    <?php
                    // Count upcoming tests and pending results
                    // First check if test_attempts table exists and has required columns
                    $query = "SELECT 1 FROM information_schema.tables 
                             WHERE table_schema = DATABASE() 
                             AND table_name = 'test_attempts' LIMIT 1";
                    $table_exists = $conn->query($query)->num_rows > 0;
                    
                    $upcoming_count = 0;
                    $recent_results = 0;
                    $total_badges = 0;
                    
                    if ($table_exists) {
                        // Check if completed_at column exists
                        $query = "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                                 WHERE TABLE_SCHEMA = DATABASE() 
                                 AND TABLE_NAME = 'test_attempts' 
                                 AND COLUMN_NAME = 'completed_at' LIMIT 1";
                        $has_completed_at = $conn->query($query)->num_rows > 0;
                        
                        // Build query based on available columns
                        $query = "SELECT 
                                    SUM(IF(t.start_time > NOW() AND ta.status = 'in_progress', 1, 0)) as upcoming,
                                    SUM(IF(ta.status = 'completed' " . 
                                    ($has_completed_at ? "AND ta.completed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)" : "") . 
                                    ", 1, 0)) as recent_results
                                  FROM test_attempts ta
                                  JOIN tests t ON ta.test_id = t.id
                                  WHERE ta.user_id = ?";
                        
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bind_param("i", $student_id);
                            if ($stmt->execute()) {
                                $result = $stmt->get_result()->fetch_assoc();
                                $upcoming_count = $result['upcoming'] ?? 0;
                                $recent_results = $result['recent_results'] ?? 0;
                                $total_badges = $upcoming_count + $recent_results;
                            }
                            $stmt->close();
                        }
                    }
                    if ($total_badges > 0) {
                        echo '<span class="badge bg-warning badge-counter">' . $total_badges . '</span>';
                    }
                    ?>
                    <i class="fas fa-fw fa-caret-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['tests.php', 'view_test.php', 'test_result.php']) ? 'show' : ''; ?>" id="testCollapse">
                    <div class="bg-dark p-2">
                        <a href="tests.php" class="nav-link <?php echo $current_page == 'tests.php' ? 'active' : ''; ?>">
                            <i class="fas fa-fw fa-list"></i> Available Tests
                            <?php if ($upcoming_count > 0): ?>
                                <span class="badge bg-warning badge-counter"><?php echo $upcoming_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="test_results.php" class="nav-link <?php echo $current_page == 'test_results.php' || $current_page == 'test_result.php' ? 'active' : ''; ?>">
                            <i class="fas fa-fw fa-chart-bar"></i> My Results
                            <?php if ($recent_results > 0): ?>
                                <span class="badge bg-info badge-counter"><?php echo $recent_results; ?> new</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </li>
            
            <!-- <li class="nav-item">
                <a href="courses.php" class="nav-link <?php echo $current_page == 'courses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-book-open"></i> Study Materials
                </a>
            </li> -->
            
            <div class="sidebar-divider"></div>
            
            <div class="sidebar-heading"> My Profile</div>
            
            <li class="nav-item">
                <a href="#documentsCollapse" class="nav-link" data-bs-toggle="collapse" 
                   aria-expanded="<?php echo in_array($current_page, ['certificates.php', 'transcripts.php']) ? 'true' : 'false'; ?>" 
                   aria-controls="documentsCollapse">
                    <i class="fas fa-fw fa-file-certificate"></i> My Documents
                    <i class="fas fa-fw fa-caret-down float-end mt-1"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['certificates.php', 'transcripts.php']) ? 'show' : ''; ?>" id="documentsCollapse">
                    <ul class="nav flex-column ms-4">
                        <li class="nav-item">
                            <a href="certificates.php" class="nav-link <?php echo $current_page == 'certificates.php' ? 'active' : ''; ?>">
                                <i class="fas fa-fw fa-certificate"></i> My Certificates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="transcripts.php" class="nav-link <?php echo $current_page == 'transcripts.php' ? 'active' : ''; ?>">
                                <i class="fas fa-fw fa-scroll"></i> My Transcripts
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-user"></i> My Profile
                </a>
            </li>
            
            <li class="nav-item">
                <a href="feedback.php" class="nav-link <?php echo $current_page == 'feedback.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-comment-dots"></i> My Feedback
                </a>
            </li>
            
            <li class="nav-item">
                <a href="referral.php" class="nav-link <?php echo $current_page == 'referral.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-coins text-warning"></i> My Coins & Rewards
                    <?php
                    // Show coin balance in the sidebar
                    if (isset($conn)) {
                        $query = "SELECT balance FROM student_wallet WHERE student_id = ?";
                        $stmt = $conn->prepare($query);
                        if ($stmt) {
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            if ($stmt->execute()) {
                                $result = $stmt->get_result();
                                if ($row = $result->fetch_assoc()) {
                                    echo '<span class="badge bg-warning text-dark ms-auto">' . number_format($row['balance']) . '</span>';
                                }
                            }
                            $stmt->close();
                        }
                    }
                    ?>
                </a>
            </li>
            
           
            
            <li class="nav-item">
                <a href="../logout.php" class="nav-link">
                    <i class="fas fa-fw fa-sign-out-alt"></i> Logout
                </a>
            </li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="topbar">
            <button id="sidebarToggle" class="d-md-none">
                <i class="fas fa-bars"></i>
            </button>
            <div class="ms-auto d-flex align-items-center">
                <span class="me-3 d-none d-md-inline">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
                </span>
                <!-- Add any other topbar elements here -->
            </div>
        </div>
        
        
        <!-- Page Content -->
        <div class="container-fluid">
