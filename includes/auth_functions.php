<?php
require_once __DIR__ . '/../config/config.php';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is an admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to get user's profile image URL
function getProfileImage($filename = '') {
    if (!empty($filename) && file_exists(UPLOAD_PATH . '/profile_photos/' . $filename)) {
        return BASE_URL . '/uploads/profile_photos/' . $filename;
    }
    return BASE_URL . '/assets/images/default-avatar.png';
}

// Function to get base URL
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
}

// Function to redirect
function redirect($path) {
    // Ensure path starts with a slash
    $path = ltrim($path, '/');
    // Get the base URL without the script name
    $base_url = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['PHP_SELF']);
    // Construct the full URL
    $url = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
    }
    exit();
}

// Function to set flash message
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Function to get flash message
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Function to log activity
function logActivity($user_id, $activity_type, $details = '') {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, ip_address, user_agent, details) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $activity_type, $ip, $user_agent, $details);
    $stmt->execute();
    $stmt->close();
}

// Function to register a new user
function registerUser($username, $email, $password, $full_name, $role = 'student', $referral_code = null) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Clean inputs
        $username = trim($username);
        $email = trim($email);
        $full_name = trim($full_name);
        $referral_code = !empty($referral_code) ? trim($referral_code) : null;
        $referred_by = null;
        
        // Validate username (alphanumeric, underscores, 3-20 chars)
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            throw new Exception('Username must be 3-20 characters long and can only contain letters, numbers, and underscores.');
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (strtolower($row['username']) === strtolower($username)) {
                throw new Exception('Username already exists.');
            } else {
                throw new Exception('Email already registered.');
            }
        }
        $stmt->close();
        
        // Process referral code if provided
        if ($referral_code) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ? AND role = 'student'");
            $stmt->bind_param("s", $referral_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $referred_by = $result->fetch_assoc()['id'];
            } else {
                throw new Exception('Invalid referral code.');
            }
            $stmt->close();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate a unique referral code for the new user
        $user_referral_code = generateUniqueReferralCode($conn);
        
        // Insert new user with referral code
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $username, $email, $hashed_password, $full_name, $role, $user_referral_code, $referred_by);
        
        if (!$stmt->execute()) {
            throw new Exception('Registration failed. Please try again.');
        }
        
        $user_id = $conn->insert_id;
        
        // Check if wallet already exists before creating
        $stmt = $conn->prepare("SELECT id FROM student_wallet WHERE student_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $walletExists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        // Create wallet for the new user if it doesn't exist
        if (!$walletExists) {
            $stmt = $conn->prepare("INSERT INTO student_wallet (student_id, balance) VALUES (?, 0)");
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                error_log("Failed to create wallet: " . $stmt->error);
                // Continue without failing registration if wallet creation fails
            }
            $stmt->close();
        }
        
        // Process referral bonus if applicable
        if ($referred_by) {
            // Award signup bonus to referrer
            $stmt = $conn->prepare("
                INSERT INTO wallet_transactions (wallet_id, amount, type, description, reference_type, reference_id)
                SELECT id, 20, 'credit', 'Referral bonus: New user signup', 'referral', ?
                FROM student_wallet WHERE student_id = ?
            ");
            $stmt->bind_param("ii", $user_id, $referred_by);
            if (!$stmt->execute()) {
                error_log("Failed to award referral bonus: " . $stmt->error);
                // Don't fail the registration if bonus fails
            } else {
                // Update wallet balance
                $stmt = $conn->prepare("
                    UPDATE student_wallet 
                    SET balance = balance + 20 
                    WHERE student_id = ?
                ");
                $stmt->bind_param("i", $referred_by);
                $stmt->execute();
            }
        }
        
        // Log the registration
        logActivity($user_id, 'registration', 'New user registered' . ($referred_by ? ' with referral code ' . $referral_code : ''));
        
        // Commit transaction
        $conn->commit();
        
        // Log the user in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['full_name'] = $full_name;
        
        return [
            'success' => true,
            'message' => 'Registration successful!',
            'user_id' => $user_id,
            'referred_by' => $referred_by
        ];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Generate a unique referral code for a new user
 */
function generateUniqueReferralCode($conn, $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max_attempts = 10;
    
    for ($i = 0; $i < $max_attempts; $i++) {
        $code = '';
        for ($j = 0; $j < $length; $j++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Check if code is unique
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            return $code;
        }
    }
    
    // If we couldn't generate a unique code after max attempts, append a random number
    return $code . rand(10, 99);
}

// Function to log in a user
function loginUser($username, $password) {
    global $conn;
    
    // First try exact username match
    $stmt = $conn->prepare("SELECT id, username, password, role, full_name, is_temp_password, status FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // If not found, try case-insensitive match
    if (!$user) {
        $stmt = $conn->prepare("SELECT id, username, password, role, full_name, is_temp_password, status FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    }
    
    if ($user) {
        // Check if account is active
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is inactive. Please contact support.'];
        }
        
        // Debug logging (remove in production)
        error_log("Login attempt for user: " . $user['username']);
        error_log("Temporary password flag: " . ($user['is_temp_password'] ? 'true' : 'false'));
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username']; // Store actual username from DB
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['needs_password_reset'] = (bool)$user['is_temp_password'];
            
            // Update last login time
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $stmt->close();
            
            // Log the login activity
            logActivity($user['id'], 'user_login');
            
            return [
                'success' => true, 
                'message' => 'Login successful!', 
                'role' => $user['role'],
                'needs_password_reset' => (bool)$user['is_temp_password']
            ];
        } else {
            // Log failed password attempt
            error_log("Failed login attempt for user: " . $user['username'] . " - Invalid password");
        }
    } else {
        error_log("Login failed - User not found: " . $username);
    }
    
    return ['success' => false, 'message' => 'Invalid username or password.'];
}

// Function to log out a user
function logoutUser() {
    // Log the logout activity
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'user_logout');
    }
    
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    redirect('/login.php');
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('danger', 'Please log in to access this page.');
        redirect('/login.php');
    }
}

// Function to require admin role
function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        setFlashMessage('danger', 'Access denied. Admin privileges required.');
        redirect('/index.php');
    }
}

// Function to require student role
function requireStudent() {
    requireLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            setFlashMessage('danger', 'Access denied. Student account required.');
            redirect('/index.php');
        } else {
            // If headers already sent, use JavaScript redirect as fallback
            echo '<script>window.location.href = "/index.php";</script>';
            exit();
        }
    }
}
?>
