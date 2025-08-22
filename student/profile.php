<?php
require_once __DIR__ . '/includes/header.php';
requireLogin();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Initialize user data with default values
$user = [
    'name' => '',
    'email' => '',
    'profile_photo' => '',
    'full_name' => ''
];

// Get current user data
$stmt = $conn->prepare("SELECT full_name, email, profile_photo FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($userData = $result->fetch_assoc()) {
    $user = array_merge($user, $userData);
    $user['name'] = $user['full_name']; // Ensure backward compatibility
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($name)) {
        $error = 'Name is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if email is already taken
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->bind_param('si', $email, $userId);
        $checkEmail->execute();
        if ($checkEmail->get_result()->num_rows > 0) {
            $error = 'Email is already in use';
        }
    }
    
    // Process password change if requested
    if (empty($error) && (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword))) {
        if (empty($currentPassword)) {
            $error = 'Current password is required to change password';
        } else {
            // Verify current password
            $checkPass = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $checkPass->bind_param('i', $userId);
            $checkPass->execute();
            $storedHash = $checkPass->get_result()->fetch_assoc()['password'];
            
            if (!password_verify($currentPassword, $storedHash)) {
                $error = 'Current password is incorrect';
            } elseif (strlen($newPassword) < 8) {
                $error = 'New password must be at least 8 characters long';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match';
            }
        }
    }
    
        // Handle file upload
    $profilePhoto = $user['profile_photo'];
    if (empty($error) && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        // Define the upload directory relative to the web root
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/New folder (2)/uploads/profile_photos/';
        
        // Create the directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($fileExt, $allowedExts)) {
            $error = 'Only JPG, JPEG, PNG & GIF files are allowed';
        } else {
            // Generate a unique filename
            $newFilename = 'user_' . $userId . '_' . time() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                // Delete old profile photo if it exists and is not the default
                if (!empty($profilePhoto) && $profilePhoto !== 'default.jpg') {
                    $oldFilePath = $uploadDir . basename($profilePhoto);
                    if (file_exists($oldFilePath)) {
                        @unlink($oldFilePath);
                    }
                }
                $profilePhoto = $newFilename;
                
                // Update the session with the new profile photo
                $_SESSION['profile_photo'] = $profilePhoto;
            } else {
                $error = 'Failed to upload profile photo. Please try again.';
                // Debug info (remove in production)
                $error .= ' Upload error: ' . $_FILES['profile_photo']['error'];
                $error .= ' Target path: ' . $targetPath;
            }
        }
    }
    
    // Update user data if no errors
    if (empty($error)) {
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, profile_photo = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $name, $email, $hashedPassword, $profilePhoto, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, profile_photo = ? WHERE id = ?");
            $stmt->bind_param('sssi', $name, $email, $profilePhoto, $userId);
        }
        
        if ($stmt->execute()) {
            $success = 'Profile updated successfully';
            // Refresh user data
            $stmt = $conn->prepare("SELECT full_name, email, profile_photo FROM users WHERE id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error = 'Failed to update profile: ' . $conn->error;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">My Profile</h1>
                <a href="dashboard.php" class="d-none d-sm-inline-block btn btn-sm btn-outline-primary shadow-sm">
                    <i class="fas fa-arrow-left fa-sm"></i> Back to Dashboard
                </a>
            </div>
            
            <!-- Status Messages -->

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            </div>
            
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <!-- Profile Card -->
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="position-relative d-inline-block mb-3">
                                <?php 
                                // Use the updated profile photo from the form submission if available
                                $profilePhoto = !empty($profilePhoto) ? $profilePhoto : ($user['profile_photo'] ?? '');
                                $profilePhotoPath = !empty($profilePhoto) 
                                    ? '/New%20folder%20(2)/uploads/profile_photos/' . $profilePhoto . '?t=' . time() 
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name'] ?? 'U') . '&background=4e73df&color=fff&size=200';
                                ?>
                                <img src="<?= htmlspecialchars($profilePhotoPath) ?>" 
                                     class="img-fluid rounded-circle mb-3" 
                                     alt="Profile Photo"
                                     style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #f8f9fc;"
                                     id="profile-photo-preview">
                                <label class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 shadow-sm" 
                                       style="width: 40px; height: 40px; cursor: pointer;">
                                    <i class="fas fa-camera"></i>
                                    <input type="file" name="profile_photo" id="profile_photo" class="d-none" accept="image/*" onchange="previewImage(this)">
                                </label>
                                <div id="imagePreview" class="d-none"></div>
                            </div>
                            
                            <h4 class="mb-1"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></h4>
                            <p class="text-muted mb-3"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                            <span class="badge bg-primary-soft text-primary">
                                <i class="fas fa-user-graduate me-1"></i> Student
                            </span>
                            
                            <hr class="my-4">
                            
                            <div class="text-start">
                                <h6 class="text-uppercase text-muted mb-3">Account Status</h6>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Email Verified</span>
                                    <span class="text-success">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Member Since</span>
                                    <span class="text-muted"><?= date('M Y', strtotime('2023-01-01')) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <!-- Personal Information -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Personal Information</h6>
                        </div>
                        <div class="card-body">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Form -->
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-uppercase text-muted mb-3">Change Password</h6>
                <p class="text-muted small mb-4">Leave these fields blank to keep your current password</p>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Additional Information -->
<div class="card shadow-sm">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Account Security</h6>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="mb-0">Two-Factor Authentication</h6>
                <p class="text-muted small mb-0">Add an extra layer of security to your account</p>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="card shadow-sm">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Account Security</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-0">Two-Factor Authentication</h6>
                                    <p class="text-muted small mb-0">Add an extra layer of security to your account</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="twoFactorAuth" disabled>
                                    <label class="form-check-label" for="twoFactorAuth">Disabled</label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Login Activity</h6>
                                    <p class="text-muted small mb-0">Last login: <?= date('M j, Y \a\t g:i A', strtotime('now')) ?></p>
                                </div>
                                <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSize = file.size / 1024 / 1024; // in MB
        
        // Check file size (max 2MB)
        if (fileSize > 2) {
            alert('File size should be less than 2MB');
            input.value = ''; // Clear the file input
            return false;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Update the preview image
            const preview = document.getElementById('profile-photo-preview');
            if (preview) {
                preview.src = e.target.result;
            }
            
            // Show a success message or visual feedback
            const previewContainer = document.getElementById('imagePreview');
            if (previewContainer) {
                previewContainer.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        Image selected. Click "Save Changes" to update your profile picture.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                previewContainer.classList.remove('d-none');
            }
        }
        
        reader.readAsDataURL(file);
    }
}

// Show file name when selected
const fileInput = document.getElementById('profile_photo');
if (fileInput) {
    fileInput.addEventListener('change', function() {
        const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
        // You can show the file name somewhere if needed
        console.log('Selected file:', fileName);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
