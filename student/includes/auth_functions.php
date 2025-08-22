<?php
/**
 * Check if a user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the logged-in user is an admin
 * @return bool True if user is an admin, false otherwise
 */
function is_admin() {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if the logged-in user is a student
 * @return bool True if user is a student, false otherwise
 */
function is_student() {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

/**
 * Redirect to login page if user is not logged in
 * @return void
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Redirect to login page with a return URL
 * @param string $return_url The URL to return to after login
 * @return void
 */
function redirect_to_login($return_url = '') {
    $login_url = 'login.php';
    if (!empty($return_url)) {
        $login_url .= '?return=' . urlencode($return_url);
    }
    header("Location: $login_url");
    exit();
}

/**
 * Verify if the current user has access to a specific course
 * @param mysqli $conn Database connection
 * @param int $course_id Course ID to check
 * @param int $user_id User ID (defaults to current session user)
 * @return bool True if user has access, false otherwise
 */
function has_course_access($conn, $course_id, $user_id = null) {
    if ($user_id === null && !is_logged_in()) {
        return false;
    }
    
    $user_id = $user_id ?? $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT 1 
        FROM student_courses 
        WHERE student_id = ? AND course_id = ?
    ");
    $stmt->bind_param('ii', $user_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}
?>
