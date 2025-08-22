<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

// Get the current page name for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MindSparxs</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/assets/images/favicon.ico">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Scrollbar -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simplebar/6.0.0/simplebar.min.css">
    
    <!-- Custom styles -->
    <!-- Custom styles -->
    <style>
        :root {
            --primary: #4e73df;
            --primary-light: #e8f0fe;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
            --sidebar-width: 250px;
            --topbar-height: 70px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fc;
            color: #333;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: #fff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            padding: 1.5rem 0;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 1000;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.5rem;
            margin-right: -0.5rem;
            max-height: calc(100vh - 150px);
            /* Custom scrollbar for WebKit browsers */
            scrollbar-width: thin;
            scrollbar-color: #b7b9cc #f8f9fc;
        }
        
        /* Custom scrollbar for WebKit browsers (Chrome, Safari, Edge) */
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: #f8f9fc;
            border-radius: 3px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background-color: #b7b9cc;
            border-radius: 3px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background-color: #9e9e9e;
        }
        
        /* For Firefox */
        .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: #b7b9cc #f8f9fc;
        }
        
        /* Ensure the sidebar stays scrollable when content is too long */
        @media (max-height: 600px) {
            .sidebar {
                overflow-y: visible;
            }
            
            .sidebar-nav {
                max-height: calc(100vh - 150px);
                overflow-y: auto;
                padding-bottom: 20px;
            }
        }
        
        /* Custom scrollbar for WebKit browsers (Chrome, Safari, newer versions of Edge) */
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: #f8f9fc;
            border-radius: 3px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background-color: #b7b9cc;
            border-radius: 3px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background-color: #9e9e9e;
        }
        
        /* Custom scrollbar for Firefox */
        .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: #b7b9cc #f1f1f1;
        }

        .sidebar-brand {
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            color: var(--dark);
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .sidebar-brand i {
            margin-right: 0.5rem;
            font-size: 1.5rem;
        }

        .sidebar-divider {
            height: 1px;
            background-color: #e3e6f0;
            margin: 0.25rem 1.5rem 0.75rem;
        }

        .sidebar .nav-link {
            color: var(--secondary);
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: 0.35rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
            color: #b7b9cc;
        }

        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .sidebar .nav-link:hover i,
        .sidebar .nav-link.active i {
            color: var(--primary);
        }

        .sidebar-heading {
            padding: 0 1.5rem;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            color:rgb(5, 5, 5);
            letter-spacing: 0.13em;
            margin: 1.5rem 0 0.5rem;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 0;
            transition: var(--transition);
        }

        /* Top Navigation */
        .topbar {
            height: var(--topbar-height);
            background: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .topbar .search-bar {
            position: relative;
            width: 300px;
        }

        .topbar .search-bar input {
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border-radius: 20px;
            border: 1px solid #d1d3e2;
            width: 100%;
            font-size: 0.9rem;
        }

        .topbar .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #b7b9cc;
        }

        .topbar .user-menu {
            display: flex;
            align-items: center;
        }

        .topbar .user-menu .dropdown-toggle {
            display: flex;
            align-items: center;
            color: #333;
            text-decoration: none;
            font-weight: 600;
        }

        .topbar .user-menu .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 0.5rem;
            object-fit: cover;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            border-radius: 0.35rem;
            font-weight: 600;
            padding: 0.5rem 1.25rem;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }

        /* Tables */
        .table {
            color: #333;
        }

        .table thead th {
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.04em;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #eaecf4;
        }

        /* Badges */
        .badge {
            font-weight: 600;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            border-radius: 0.25rem;
        }

        .bg-success {
            background-color: var(--success) !important;
        }

        .bg-warning {
            background-color: var(--warning) !important;
        }

        .bg-danger {
            background-color: var(--danger) !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .topbar .search-bar {
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="dashboard.php" class="sidebar-brand">
            <i class="fas fa-graduation-cap"></i>
            <span>MindSparxs</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <div class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <div class="sidebar-heading">Management</div>
            
            <li class="nav-item">
                <a href="students.php" class="nav-link <?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-users"></i> Students
                </a>
            </li>
            
          
            
            <li class="nav-item">
                <a href="courses.php" class="nav-link <?php echo $current_page == 'courses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-book"></i> Courses
                </a>
            </li>
            <li class="nav-item">
                <a href="assignments.php" class="nav-link <?php echo $current_page == 'assignments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-book"></i> Assignments
                </a>
            </li>
  <li class="nav-item">
            <a href="tests.php?action=add" class="nav-link <?php echo $current_page == 'edit_test.php' && !isset($_GET['id']) ? 'active' : ''; ?>">
                            <i class="fas fa-fw fa-plus-circle"></i> Create New Test
                        </a>
            </li>
            
            <li class="nav-item">
            
            <a href="certificates.php" class="nav-link <?php echo $current_page == 'certificates.php' ? 'active' : ''; ?>">
                                <i class="fas fa-fw fa-certificate"></i> Issue Certificate
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="transcripts.php" class="nav-link <?php echo $current_page == 'transcripts.php' ? 'active' : ''; ?>">
                                <i class="fas fa-fw fa-scroll"></i> Issue Transcript
                            </a>
            </li>
            <li class="nav-item">
                <a href="feedback_management.php" class="nav-link <?php echo $current_page == 'feedback_management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-comments"></i> Feedback
                </a>
            </li>
            
            <li class="nav-item">
                <a href="rewards_management.php" class="nav-link <?php echo $current_page == 'rewards_management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-fw fa-gift"></i> Rewards Management
                </a>
            </li>
            <li class="nav-item mt-auto">
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
            <button class="btn btn-link d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <form action="search.php" method="GET" class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" name="q" class="form-control" placeholder="Search for students, courses, assignments..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            </form>
            
            <div class="user-menu">
                <div class="dropdown
                    <a href="#" class="dropdown-toggle" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username'] ?? 'Admin') ?>" alt="User" class="user-avatar">
                        <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="container-fluid">
