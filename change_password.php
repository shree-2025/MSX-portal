<?php
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to change your password.');
    redirect('login.php');
}

$page_title = 'Change Password';
$error = '';
$success = '';
$first_login = isset($_GET['first_login']) && $_GET['first_login'] == 1;

// Set the correct path for includes
$base_path = dirname(__FILE__);
$header = $base_path . '/includes/header.php';
$footer = $base_path . '/includes/footer.php';

// Process password change form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For first login, we don't require current password
    $current_password = $first_login ? '' : ($_POST['current_password'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'New password and confirm password are required.';
    } elseif (!$first_login && empty($current_password)) {
        $error = 'Current password is required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'New password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'New password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $error = 'New password must contain at least one special character.';
    } else {
        // Get user data
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT password, is_temp_password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // For first login, we don't need to verify current password
        $password_verified = $first_login || ($user && password_verify($current_password, $user['password']));
        
        if ($password_verified) {
            // Update password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, is_temp_password = 0, last_password_reset = NOW() WHERE id = ?");
            $stmt->bind_param("si", $new_hashed_password, $user_id);
            
            if ($stmt->execute()) {
                // Clear the needs password reset flag from session
                unset($_SESSION['needs_password_reset']);
                
                // Set success message
                $success = 'Your password has been updated successfully.';
                
                // If this was a first login with temp password, redirect to dashboard
                if ($first_login) {
                    setFlashMessage('success', $success);
                    redirect($_SESSION['role'] === 'admin' ? '/admin/dashboard.php' : '/student/dashboard.php');
                }
            } else {
                $error = 'Failed to update password. Please try again.';
            }
            $stmt->close();
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// Include header
if (file_exists($header)) {
    include_once $header;
} else {
    die('Header file not found!');
}
?>

<div class="auth-card card shadow">
    <div class="card-header">
        <h4 class="mb-0 text-center">
            <i class="fas fa-key me-2"></i>
            <?= $first_login ? 'Set Your Password' : 'Change Your Password' ?>
        </h4>
    </div>
    <div class="card-body p-4">
        <?php if ($first_login): ?>
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <div>
                    This is your first login. Please set a new password to continue.
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="needs-validation" novalidate>
            <?php if (!$first_login): ?>
                <div class="mb-4">
                    <label for="current_password" class="form-label">
                        <i class="fas fa-lock me-1"></i> Current Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="current_password" 
                               name="current_password" required 
                               placeholder="Enter your current password">
                        <button type="button" class="btn btn-outline-secondary" 
                                onclick="togglePassword('current_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="invalid-feedback">
                            Please enter your current password.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <label for="new_password" class="form-label">
                    <i class="fas fa-key me-1"></i> New Password
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                    <input type="password" class="form-control" id="new_password" 
                           name="new_password" required minlength="8"
                           placeholder="Create a strong password">
                    <button type="button" class="btn btn-outline-secondary" 
                            onclick="togglePassword('new_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="invalid-feedback">
                        Password must be at least 8 characters long and meet the requirements below.
                    </div>
                </div>
                
                <!-- Password Strength Meter -->
                <div class="mt-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Password Strength:</small>
                        <small><span id="password-strength-text">-</span></small>
                    </div>
                    <div class="progress" style="height: 5px;">
                        <div id="password-strength-bar" class="progress-bar" role="progressbar" 
                             style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                </div>
                
                <!-- Password Requirements -->
                <div class="password-requirements mt-3">
                    <p class="small text-muted mb-2"><strong>Password must include:</strong></p>
                    <div id="length-req" class="requirement">
                        <i class="fas fa-circle"></i>
                        <span>At least 8 characters</span>
                    </div>
                    <div id="uppercase-req" class="requirement">
                        <i class="fas fa-circle"></i>
                        <span>At least 1 uppercase letter</span>
                    </div>
                    <div id="number-req" class="requirement">
                        <i class="fas fa-circle"></i>
                        <span>At least 1 number</span>
                    </div>
                    <div id="special-req" class="requirement">
                        <i class="fas fa-circle"></i>
                        <span>At least 1 special character</span>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="confirm_password" class="form-label">
                    <i class="fas fa-check-circle me-1"></i> Confirm New Password
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                    <input type="password" class="form-control" id="confirm_password" 
                           name="confirm_password" required minlength="8"
                           placeholder="Confirm your new password">
                    <button type="button" class="btn btn-outline-secondary" 
                            onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="invalid-feedback">
                        Passwords do not match.
                    </div>
                </div>
                <div id="password-match-text" class="form-text small"></div>
            </div>
            
            <div class="d-grid gap-3 mt-4">
                <button type="submit" class="btn btn-primary btn-lg py-2">
                    <i class="fas fa-save me-2"></i>
                    <?= $first_login ? 'Set Password' : 'Update Password' ?>
                </button>
                
                <?php if (!$first_login): ?>
                    <a href="<?= $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php' ?>" 
                       class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Back to Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php 
// Include footer
if (file_exists($footer)) {
    include_once $footer;
} else {
    die('Footer file not found!');
}
?>
