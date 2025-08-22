<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied: You do not have permission to access this page.');
}

$page_title = 'Rewards Management';
$success = $error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_reward'])) {
        // Add new reward
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $coin_cost = (int)$_POST['coin_cost'];
        // Set a default stock value of 0 if not provided or invalid
        $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int)$_POST['stock'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name) || $coin_cost <= 0) {
            $error = 'Please fill all required fields with valid data.';
        } else {
            $stmt = $conn->prepare("INSERT INTO rewards (name, description, coin_cost, stock, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiii", $name, $description, $coin_cost, $stock, $is_active);
            
            if ($stmt->execute()) {
                $success = 'Reward added successfully!';
            } else {
                $error = 'Failed to add reward. Please try again.';
            }
            $stmt->close();
        }
    } 
    elseif (isset($_POST['update_reward'])) {
        // Update existing reward
        $id = (int)$_POST['reward_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $coin_cost = (int)$_POST['coin_cost'];
        // Set a default stock value of 0 if not provided or invalid
        $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int)$_POST['stock'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE rewards SET name = ?, description = ?, coin_cost = ?, stock = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssiiii", $name, $description, $coin_cost, $stock, $is_active, $id);
        
        if ($stmt->execute()) {
            $success = 'Reward updated successfully!';
        } else {
            $error = 'Failed to update reward. Please try again.';
        }
        $stmt->close();
    }
    elseif (isset($_POST['delete_reward'])) {
        // Delete reward
        $id = (int)$_POST['reward_id'];
        
        $stmt = $conn->prepare("DELETE FROM rewards WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = 'Reward deleted successfully!';
        } else {
            $error = 'Failed to delete reward. It may be in use.';
        }
        $stmt->close();
    }
}

// Get all rewards
$rewards = [];
$result = $conn->query("SELECT * FROM rewards ORDER BY is_active DESC, coin_cost ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rewards[] = $row;
    }
}

// Get reward redemption stats
$redemption_stats = [
    'total_redemptions' => 0,
    'pending_redemptions' => 0,
    'completed_redemptions' => 0,
    'total_coins_spent' => 0
];

try {
    // First check if the reward_redemptions table exists and has the coin_cost column
    $tableCheck = $conn->query("SHOW TABLES LIKE 'reward_redemptions'");
    
    if ($tableCheck->num_rows > 0) {
        $columnCheck = $conn->query("SHOW COLUMNS FROM reward_redemptions LIKE 'coin_cost'");
        $hasCoinCost = $columnCheck->num_rows > 0;
        
        if ($hasCoinCost) {
            $result = $conn->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(coin_cost) as total_coins
                FROM reward_redemptions");
                
            if ($result && $row = $result->fetch_assoc()) {
                $redemption_stats = [
                    'total_redemptions' => $row['total'] ?? 0,
                    'pending_redemptions' => $row['pending'] ?? 0,
                    'completed_redemptions' => $row['completed'] ?? 0,
                    'total_coins_spent' => $row['total_coins'] ?? 0
                ];
            }
        } else {
            // If coin_cost column doesn't exist, just get the counts without it
            $result = $conn->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM reward_redemptions");
                
            if ($result && $row = $result->fetch_assoc()) {
                $redemption_stats = [
                    'total_redemptions' => $row['total'] ?? 0,
                    'pending_redemptions' => $row['pending'] ?? 0,
                    'completed_redemptions' => $row['completed'] ?? 0,
                    'total_coins_spent' => 0 // Default to 0 if column doesn't exist
                ];
            }
        }
    }
} catch (Exception $e) {
    // If there's an error (like table doesn't exist), use default values
    error_log("Error fetching redemption stats: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Rewards Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Rewards Management</li>
    </ol>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Redemptions</h6>
                            <h2 class="mb-0"><?php echo $redemption_stats['total_redemptions']; ?></h2>
                        </div>
                        <i class="fas fa-gift fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Pending</h6>
                            <h2 class="mb-0"><?php echo $redemption_stats['pending_redemptions']; ?></h2>
                        </div>
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Completed</h6>
                            <h2 class="mb-0"><?php echo $redemption_stats['completed_redemptions']; ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Coins Spent</h6>
                            <h2 class="mb-0"><?php echo number_format($redemption_stats['total_coins_spent']); ?></h2>
                        </div>
                        <i class="fas fa-coins fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Add/Edit Reward Form -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo isset($_GET['edit']) ? 'Edit Reward' : 'Add New Reward'; ?></h5>
                </div>
                <div class="card-body">
                    <?php
                    $editing = false;
                    $reward = ['name' => '', 'description' => '', 'coin_cost' => '', 'stock' => '', 'is_active' => 1];
                    
                    if (isset($_GET['edit'])) {
                        $edit_id = (int)$_GET['edit'];
                        $stmt = $conn->prepare("SELECT * FROM rewards WHERE id = ?");
                        $stmt->bind_param("i", $edit_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $reward = $result->fetch_assoc();
                            $editing = true;
                        }
                        $stmt->close();
                    }
                    ?>
                    
                    <form method="post" action="">
                        <input type="hidden" name="reward_id" value="<?php echo $editing ? $reward['id'] : ''; ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Reward Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($reward['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                echo htmlspecialchars($reward['description']); 
                            ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="coin_cost" class="form-label">Coin Cost *</label>
                                <input type="number" class="form-control" id="coin_cost" name="coin_cost" 
                                       min="1" value="<?php echo $reward['coin_cost']; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="stock" class="form-label">Stock (leave empty for unlimited)</label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                       min="1" value="<?php echo $reward['stock']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                   value="1" <?php echo $reward['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if ($editing): ?>
                                <button type="submit" name="update_reward" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Reward
                                </button>
                                <a href="rewards_management.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                            <?php else: ?>
                                <button type="submit" name="add_reward" class="btn btn-success">
                                    <i class="fas fa-plus-circle me-1"></i> Add Reward
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Rewards List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Manage Rewards</h5>
                    <a href="rewards_management.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($rewards)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No rewards found. Add your first reward to get started.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Cost</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rewards as $reward): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($reward['name']); ?></strong>
                                                <?php if (!empty($reward['description'])): ?>
                                                    <p class="text-muted small mb-0">
                                                        <?php echo htmlspecialchars(substr($reward['description'], 0, 50)); ?><?php echo strlen($reward['description']) > 50 ? '...' : ''; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-coins me-1"></i> 
                                                    <?php echo number_format($reward['coin_cost']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($reward['stock'] === null): ?>
                                                    <span class="badge bg-info">Unlimited</span>
                                                <?php else: ?>
                                                    <span class="badge bg-<?php echo $reward['stock'] > 5 ? 'success' : 'danger'; ?>">
                                                        <?php echo $reward['stock']; ?> left
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $reward['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $reward['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?edit=<?php echo $reward['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $reward['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $reward['id']; ?>" tabindex="-1" 
                                                     aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete the reward "<?php echo htmlspecialchars($reward['name']); ?>"?</p>
                                                                <p class="text-danger">This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="post" style="display:inline;">
                                                                    <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                                                    <button type="submit" name="delete_reward" class="btn btn-danger">
                                                                        <i class="fas fa-trash me-1"></i> Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Redemption Requests -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Redemption Requests</h5>
                </div>
                <div class="card-body">
                    <?php
                    $redemptions = [];
                    $result = $conn->query("
                        SELECT rr.*, r.name as reward_name, u.username, u.full_name, u.email
                        FROM reward_redemptions rr
                        JOIN rewards r ON rr.reward_id = r.id
                        JOIN users u ON rr.student_id = u.id
                        ORDER BY rr.created_at DESC
                        LIMIT 10
                    ") or die($conn->error);
                    
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $redemptions[] = $row;
                        }
                    }
                    ?>
                    
                    <?php if (empty($redemptions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No redemption requests found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Reward</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($redemptions as $redemption): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2">
                                                        <span class="avatar-title rounded-circle bg-light text-dark">
                                                            <?php echo strtoupper(substr($redemption['full_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($redemption['full_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($redemption['email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($redemption['reward_name']); ?></strong>
                                                <div class="text-muted small">
                                                    <?php echo number_format($redemption['coin_cost']); ?> coins
                                                </div>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($redemption['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'bg-warning',
                                                    'processing' => 'bg-info',
                                                    'shipped' => 'bg-primary',
                                                    'completed' => 'bg-success',
                                                    'cancelled' => 'bg-danger'
                                                ][$redemption['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($redemption['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                            id="dropdownMenuButton<?php echo $redemption['id']; ?>" 
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $redemption['id']; ?>">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                               data-bs-target="#statusModal<?php echo $redemption['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Update Status
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                               data-bs-target="#detailsModal<?php echo $redemption['id']; ?>">
                                                                <i class="fas fa-info-circle me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="post" action="" class="d-inline">
                                                                <input type="hidden" name="redemption_id" value="<?php echo $redemption['id']; ?>">
                                                                <button type="submit" name="cancel_redemption" class="dropdown-item text-danger" 
                                                                        onclick="return confirm('Are you sure you want to cancel this redemption?');">
                                                                    <i class="fas fa-times-circle me-2"></i>Cancel
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                                
                                                <!-- Status Update Modal -->
                                                <div class="modal fade" id="statusModal<?php echo $redemption['id']; ?>" tabindex="-1" 
                                                     aria-labelledby="statusModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="statusModalLabel">Update Redemption Status</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="post" action="">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="redemption_id" value="<?php echo $redemption['id']; ?>">
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="status" class="form-label">Status</label>
                                                                        <select class="form-select" id="status" name="status" required>
                                                                            <option value="pending" <?php echo $redemption['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                            <option value="processing" <?php echo $redemption['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                                            <option value="shipped" <?php echo $redemption['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                                            <option value="completed" <?php echo $redemption['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                            <option value="cancelled" <?php echo $redemption['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                        </select>
                                                                    </div>
                                                                        
                                                                    <div class="mb-3">
                                                                        <label for="notes" class="form-label">Notes</label>
                                                                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                                                  placeholder="Add any notes or tracking information"><?php 
                                                                            echo htmlspecialchars($redemption['admin_notes'] ?? ''); 
                                                                        ?></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                    <button type="submit" name="update_status" class="btn btn-primary">Save Changes</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Details Modal -->
                                                <div class="modal fade" id="detailsModal<?php echo $redemption['id']; ?>" tabindex="-1" 
                                                     aria-labelledby="detailsModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="detailsModalLabel">Redemption Details</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <h6>Student Information</h6>
                                                                        <p>
                                                                            <strong>Name:</strong> <?php echo htmlspecialchars($redemption['full_name']); ?><br>
                                                                            <strong>Email:</strong> <?php echo htmlspecialchars($redemption['email']); ?><br>
                                                                            <strong>Username:</strong> <?php echo htmlspecialchars($redemption['username']); ?>
                                                                        </p>
                                                                        
                                                                        <h6 class="mt-4">Shipping Information</h6>
                                                                        <p class="text-pre"><?php 
                                                                            echo nl2br(htmlspecialchars($redemption['shipping_address'] ?? 'Not provided')); 
                                                                        ?></p>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <h6>Reward Details</h6>
                                                                        <p>
                                                                            <strong>Reward:</strong> <?php echo htmlspecialchars($redemption['reward_name']); ?><br>
                                                                            <strong>Cost:</strong> <?php echo number_format($redemption['coin_cost']); ?> coins<br>
                                                                            <strong>Date Requested:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($redemption['created_at'])); ?><br>
                                                                            <strong>Status:</strong> 
                                                                            <span class="badge <?php echo $status_class; ?>">
                                                                                <?php echo ucfirst($redemption['status']); ?>
                                                                            </span>
                                                                        </p>
                                                                        
                                                                        <?php if (!empty($redemption['admin_notes'])): ?>
                                                                            <h6 class="mt-4">Admin Notes</h6>
                                                                            <div class="bg-light p-3 rounded">
                                                                                <?php echo nl2br(htmlspecialchars($redemption['admin_notes'])); ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-end mt-3">
                            <a href="redemptions.php" class="btn btn-sm btn-outline-primary">
                                View All Redemptions <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
.text-pre {
    white-space: pre-line;
}
</style>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>
