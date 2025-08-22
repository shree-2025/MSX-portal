<?php
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

$page_title = 'Register';

// If already logged in, show a message with logout option
if (isLoggedIn()) {
    $username = htmlspecialchars($_SESSION['username']);
    $role = htmlspecialchars($_SESSION['role']);
    die("
        <div style='text-align:center; margin-top:50px; font-family:Arial;'>
            <h2>You are already logged in as $username ($role)</h2>
            <p>Please <a href='logout.php'>logout</a> first if you want to register a new account.</p>
            <p>Or go to your <a href='$role/dashboard.php'>dashboard</a>.</p>
        </div>
    ");
}

$error = '';
$success = '';

// Get referral code from URL if present
$referral_code = isset($_GET['ref']) ? sanitize($_GET['ref']) : '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = sanitize($_POST['full_name'] ?? '');
    $referral_code = isset($_POST['referral_code']) ? sanitize($_POST['referral_code']) : '';
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $result = registerUser($username, $email, $password, $full_name, 'student', $referral_code);
        
        if ($result['success']) {
            $success = $result['message'];
            
            // Redirect to dashboard after successful registration
            header("Refresh: 2; url=dashboard.php");
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Coaching Center</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .register-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-logo i {
            font-size: 60px;
            color: #4e73df;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .btn-primary {
            background-color: #4e73df;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
        }
        .register-footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            margin-bottom: 15px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }
        .strength-0 { width: 20%; background-color: #dc3545; }
        .strength-1 { width: 40%; background-color: #ffc107; }
        .strength-2 { width: 60%; background-color: #28a745; }
        .strength-3 { width: 80%; background-color: #17a2b8; }
        .strength-4 { width: 100%; background-color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-logo">
                <i class="fas fa-user-plus"></i>
                <h2>Create an Account</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <p>You will be redirected to the login page shortly...</p>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 3000);
                    </script>
                </div>
            <?php else: ?>
                <form method="POST" action="" id="registerForm">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               pattern="^[a-zA-Z0-9_]{3,20}$"
                               title="3-20 characters, letters, numbers, and underscores only"
                               oninput="validateUsername(this)" required>
                        <div class="invalid-feedback" id="usernameError">
                            Username must be 3-20 characters long and can only contain letters, numbers, and underscores
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referral_code" class="form-label">Referral Code (Optional)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="referral_code" name="referral_code" 
                                   value="<?php echo htmlspecialchars($referral_code); ?>" placeholder="Enter referral code if you have one">
                            <?php if (!empty($referral_code)): ?>
                                <span class="input-group-text bg-success text-white" id="referred-badge">
                                    <i class="fas fa-gift me-1"></i> Referral Applied
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="form-text">Get bonus MSX Coins when you sign up with a referral code</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="form-text"></div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary" id="registerBtn">Register</button>
                    </div>
                    
                    <div class="register-footer mt-3">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthMeter = document.getElementById('passwordStrength');
            let strength = 0;
            
            // Reset classes
            strengthMeter.className = 'password-strength';
            
            if (password.length === 0) {
                strengthMeter.style.display = 'none';
                return;
            }
            
            strengthMeter.style.display = 'block';
            
            // Length check
            if (password.length >= 8) strength++;
            
            // Contains lowercase
            if (password.match(/[a-z]+/)) strength++;
            
            // Contains uppercase
            if (password.match(/[A-Z]+/)) strength++;
            
            // Contains numbers
            if (password.match(/[0-9]+/)) strength++;
            
            // Contains special characters
            if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength++;
            
            // Cap at 4 for our CSS classes (0-4)
            strength = Math.min(strength, 4);
            
            // Update strength meter
            strengthMeter.className = 'strength-' + strength;
        });
        
        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.textContent = 'Passwords match!';
                matchText.style.color = '#28a745';
            } else {
                matchText.textContent = 'Passwords do not match!';
                matchText.style.color = '#dc3545';
            }
        });
        
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Username validation
        function validateUsername(input) {
            const usernameError = document.getElementById('usernameError');
            const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
            
            if (!usernameRegex.test(input.value)) {
                input.classList.add('is-invalid');
                usernameError.style.display = 'block';
                return false;
            } else {
                input.classList.remove('is-invalid');
                usernameError.style.display = 'none';
                return true;
            }
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            let isValid = true;
            
            // Validate username
            if (!validateUsername(username)) {
                e.preventDefault();
                isValid = false;
                username.focus();
            }
            
            // Validate password match
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                confirmPassword.classList.add('is-invalid');
                document.getElementById('passwordMatch').textContent = 'Passwords do not match!';
                document.getElementById('passwordMatch').style.color = '#dc3545';
                isValid = false;
                if (isValid) confirmPassword.focus();
            } else {
                confirmPassword.classList.remove('is-invalid');
            }
            
            // Validate password length
            if (password.value.length < 6) {
                e.preventDefault();
                password.classList.add('is-invalid');
                isValid = false;
                if (isValid) password.focus();
            } else {
                password.classList.remove('is-invalid');
            }
            
            return isValid;
        });
    </script>
</body>
</html>
