<?php
require_once __DIR__ . '/includes/header.php';
requireAdmin();

// Handle feedback update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feedback'])) {
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if ($feedback_id > 0) {
        $query = "UPDATE feedback SET admin_notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $admin_notes, $feedback_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Feedback notes updated successfully.';
        } else {
            $_SESSION['error'] = 'Failed to update feedback. Please try again.';
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = 'Invalid feedback ID.';
    }
    
    // Redirect to avoid form resubmission
    header('Location: feedback_management.php');
    exit();
}

// Get search parameter
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT f.*, u.full_name, u.email 
          FROM feedback f 
          JOIN users u ON f.student_id = u.id 
          WHERE 1=1";
          
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (f.subject LIKE ? OR f.message LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

$query .= " ORDER BY f.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Student Feedback</h1>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Search</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="form-inline">
                <div class="form-group mr-3 mb-2">
                    <label for="search" class="mr-2">Search:</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Search feedback...">
                </div>
                <button type="submit" class="btn btn-primary mb-2">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="feedback_management.php" class="btn btn-secondary mb-2 ml-2">
                    <i class="fas fa-sync-alt"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Feedback List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Feedback List</h6>
            <div>
                <span class="badge badge-pill badge-warning">Pending: 
                    <?php 
                    $count = $conn->query("SELECT COUNT(*) FROM feedback WHERE status = 'pending'")->fetch_row()[0];
                    echo $count;
                    ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="feedbackTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $statusClass = [
                                    'pending' => 'warning',
                                    'reviewed' => 'info',
                                    'resolved' => 'success'
                                ][$row['status']] ?? 'secondary';
                                
                                // Truncate message for display
                                $truncatedMessage = strlen($row['message']) > 100 
                                    ? substr($row['message'], 0, 100) . '...' 
                                    : $row['message'];
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($row['full_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                    <td class="text-center">
                                        <a href="feedback_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No feedback found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" role="dialog" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" id="updateFeedbackForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackModalLabel">Feedback Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="feedback_id" id="feedback_id">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Student Name:</strong> <span id="studentName"></span></p>
                            <p class="mb-1"><strong>Email:</strong> <span id="studentEmail"></span></p>
                        </div>
                        <div class="col-md-6 text-md-right">
                            <p class="mb-1"><strong>Submitted:</strong> <span id="submittedDate"></span></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modalSubject"><strong>Subject:</strong></label>
                        <input type="text" class="form-control" id="modalSubject" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="modalMessage"><strong>Message:</strong></label>
                        <textarea class="form-control" id="modalMessage" rows="3" readonly></textarea>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3 text-muted">Feedback Responses</h6>
                            
                            <div class="form-group">
                                <label class="mb-1"><small class="font-weight-bold text-uppercase text-muted">Content Relevance & Clarity:</small></label>
                                <div id="contentRelevance" class="font-weight-bold"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="mb-1"><small class="font-weight-bold text-uppercase text-muted">Instructor Effectiveness:</small></label>
                                <div id="instructorEffectiveness" class="font-weight-bold"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="mb-1"><small class="font-weight-bold text-uppercase text-muted">Confidence in Application:</small></label>
                                <div id="confidenceApplication" class="font-weight-bold"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="mb-1"><small class="font-weight-bold text-uppercase text-muted">Training Materials Helpfulness:</small></label>
                                <div id="materialsHelpfulness" class="font-weight-bold"></div>
                            </div>
                            
                            <div class="form-group mb-0">
                                <label class="mb-1"><small class="font-weight-bold text-uppercase text-muted">Suggestions for Improvement:</small></label>
                                <div id="suggestionsImprovement" class="font-italic"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status"><strong>Status:</strong></label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes"><strong>Admin Notes:</strong></label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                 placeholder="Add your response or notes here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="update_feedback" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Initialize DataTables -->
<script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#feedbackTable').DataTable({
            "order": [[0, "desc"]],
            "pageLength": 10,
            "responsive": true,
            "language": {
                "search": "_INPUT_",
                "searchPlaceholder": "Search feedback..."
            },
            "columnDefs": [
                { "orderable": false, "targets": [3] } // Disable sorting on actions column
            ]
        });
        
        // Handle view feedback button click (using event delegation for dynamic content)
        $(document).on('click', '.view-feedback', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var feedbackId = $button.data('id');
            
            // Show loading state
            $('#feedbackModal .modal-body').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading feedback details...</p></div>');
            
            // Fetch feedback details via AJAX
            console.log('Fetching feedback details for ID:', feedbackId);
            $.ajax({
                url: 'ajax/get_feedback_details.php',
                type: 'GET',
                dataType: 'json',
                data: { id: feedbackId },
                beforeSend: function() {
                    console.log('Sending AJAX request...');
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var feedback = response.data;
                        
                        // Update student info
                        $('#studentName').text(feedback.full_name || 'N/A');
                        $('#studentEmail').text(feedback.email || 'N/A');
                        $('#submittedDate').text(feedback.created_at_formatted || 'N/A');
                        
                        // Update feedback details
                        $('#modalSubject').val(feedback.subject || 'No subject');
                        $('#modalMessage').val(feedback.message || 'No message provided.');
                        
                        // Update feedback responses
                        $('#contentRelevance').text(feedback.content_relevance || 'N/A');
                        $('#instructorEffectiveness').text(feedback.instructor_effectiveness || 'N/A');
                        $('#confidenceApplication').text(feedback.confidence_application || 'N/A');
                        $('#materialsHelpfulness').text(feedback.materials_helpfulness || 'N/A');
                        $('#suggestionsImprovement').text(feedback.suggestions_improvement || 'N/A');
                        
                        // Update admin notes and feedback ID
                        $('#admin_notes').val(feedback.admin_notes || '');
                        $('#feedback_id').val(feedback.id);
                        
                        // Show the modal
                        $('#feedbackModal').modal('show');
                    } else {
                        alert('Failed to load feedback details. Please try again.');
                        console.error('Error loading feedback:', response.message || 'Unknown error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status,
                        statusText: xhr.statusText
                    });
                    alert('An error occurred while fetching feedback details. Please check the console (F12) for more details.');
                },
                complete: function() {
                    // Re-enable the button and restore its original content
                    $button.prop('disabled', false).html('<i class="fas fa-eye"></i> View');
                }
            });
        });
        
        // Handle form submission
        $('#updateFeedbackForm').on('submit', function(e) {
            // Form will be submitted normally since we're not using AJAX for the form
            // This handler is just for any client-side validation if needed
            return true;
        });
    });
</script>

<style>
    .rating-stars {
        color: #ffc107;
        font-size: 1.2em;
    }
    .badge-pill {
        padding: 0.35em 0.65em;
        font-size: 0.75em;
        font-weight: 600;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
