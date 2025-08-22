<?php
require_once 'config/config.php';

// Admin credentials
$admin_username = 'admin';
$admin_email = 'admin@example.com';
$admin_password = 'Admin@123';
$admin_fullname = 'Administrator';

try {
    // Check if admin already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $admin_username, $admin_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        die("Admin user already exists! Check your database for admin credentials.");
    }
    
    // Hash the password
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    
    // Insert admin user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'admin')");
    $stmt->bind_param("ssss", $admin_username, $admin_email, $hashed_password, $admin_fullname);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully!<br>";
        echo "Username: " . htmlspecialchars($admin_username) . "<br>";
        echo "Password: " . htmlspecialchars($admin_password) . "<br>";
        echo "<a href='login.php'>Go to Login Page</a>";
    } else {
        echo "Error creating admin user: " . $conn->error;
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$conn->close();
?>
