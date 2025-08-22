<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the SQL file
    $sqlFile = __DIR__ . '/../database/migrations/20250820_create_video_meetings_table.sql';
    
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Execute multi query
        if ($conn->multi_query($sql)) {
            do {
                // Store first result set
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            
            if ($conn->error) {
                $errors[] = "Error creating tables: " . $conn->error;
            } else {
                $success = true;
                setFlashMessage('success', 'Video conferencing tables created successfully!');
                header('Location: check_meetings_table.php');
                exit();
            }
        } else {
            $errors[] = "Error executing query: " . $conn->error;
        }
    } else {
        $errors[] = "SQL file not found!";
    }
}

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Video Conferencing Tables</h1>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Database Setup</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5>Errors occurred:</h5>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h5>This will create the following tables:</h5>
                        <ul>
                            <li><code>video_meetings</code> - Stores meeting information</li>
                            <li><code>meeting_participants</code> - Tracks meeting participants</li>
                        </ul>
                        <p class="mb-0">Make sure you have backed up your database before proceeding.</p>
                    </div>
                    
                    <form method="POST" class="mt-4">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to create the video conferencing tables?');">
                            <i class="fas fa-database"></i> Create Tables
                        </button>
                        <a href="check_meetings_table.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Status
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
