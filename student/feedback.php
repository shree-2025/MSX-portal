<?php
require_once __DIR__ . '/includes/header.php';

// Check if form is submitted
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $content_relevance = $_POST['content_relevance'] ?? '';
    $instructor_effectiveness = $_POST['instructor_effectiveness'] ?? '';
    $confidence_application = $_POST['confidence_application'] ?? '';
    $materials_helpfulness = $_POST['materials_helpfulness'] ?? '';
    $suggestions_improvement = trim($_POST['suggestions_improvement'] ?? '');
    
    // Validate input
    if (empty($subject) || empty($content_relevance) || 
        empty($instructor_effectiveness) || empty($confidence_application) || 
        empty($materials_helpfulness)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Insert feedback into database with all fields
        $query = "INSERT INTO feedback (student_id, subject, message, content_relevance, 
                  instructor_effectiveness, confidence_application, materials_helpfulness, suggestions_improvement) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssssss", 
            $_SESSION['user_id'], 
            $subject, 
            $message,
            $content_relevance,
            $instructor_effectiveness,
            $confidence_application,
            $materials_helpfulness,
            $suggestions_improvement
        );
        
        if ($stmt->execute()) {
            $success = true;
            // Clear form
            $subject = $message = '';
        } else {
            $error = 'Failed to submit feedback. Please try again.';
        }
        $stmt->close();
    }
}
?>

<div class="container">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-primary mb-3">Share Your Feedback</h1>
        <p class="lead text-muted">Your opinion helps us improve our courses and teaching methods</p>
        <div class="divider mx-auto bg-primary" style="height: 4px; width: 80px; opacity: 0.2;"></div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Success!</strong> Your feedback has been submitted. Thank you!
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg border-0 rounded-lg overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="row justify-content-center">
                <!-- Left Side - Form -->
                <div class="col-lg-8 col-md-10 col-12 p-5">
                    <form action="feedback.php" method="POST" id="feedbackForm">
                        <div class="form-floating mb-4">
                            <input type="text" class="form-control form-control-lg" id="subject" name="subject" 
                                   placeholder="Enter course name" value="<?php echo htmlspecialchars($subject ?? ''); ?>" required>
                            <label for="subject" class="text-muted">Course Name <span class="text-danger">*</span></label>
                        </div>
                        
                        <div class="form-floating mb-4">
                            <textarea class="form-control" id="message" name="message" 
                                      placeholder="Share your overall feedback about the course..."
                                      style="height: 120px"><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                            <label for="message" class="text-muted">Your Feedback <small class="text-muted">(Optional)</small></label>
                        </div>

                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header bg-light py-3">
                                <h5 class="mb-0 text-primary">
                                    <i class="fas fa-star me-2"></i>Course Feedback
                                </h5>
                            </div>
                            <div class="card-body">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3">1. How would you rate the relevance and clarity of the course content in helping you achieve your learning goals? <span class="text-danger">*</span></label>
                            <div class="rating-container">
                                <?php 
                                $options = [
                                    'Excellent' => ['icon' => 'fas fa-star', 'color' => 'success'],
                                    'Good' => ['icon' => 'fas fa-thumbs-up', 'color' => 'primary'],
                                    'Average' => ['icon' => 'fas fa-equals', 'color' => 'warning'],
                                    'Poor' => ['icon' => 'fas fa-thumbs-down', 'color' => 'danger']
                                ];
                                foreach ($options as $option => $data): 
                                    $checked = (isset($content_relevance) && $content_relevance === $option) ? 'active' : '';
                                ?>
                                    <div class="rating-option">
                                        <input type="radio" id="content_<?php echo strtolower($option); ?>" name="content_relevance" 
                                               value="<?php echo $option; ?>" class="d-none" 
                                               <?php echo $checked ? 'checked' : ''; ?> required>
                                        <label for="content_<?php echo strtolower($option); ?>" class="d-flex flex-column align-items-center text-center p-3 rounded-3 border <?php echo $checked ? 'border-2 border-'.$data['color'] : 'border-1'; ?>" 
                                               style="cursor: pointer; transition: all 0.3s;">
                                            <i class="<?php echo $data['icon']; ?> fa-2x mb-2 text-<?php echo $data['color']; ?>"></i>
                                            <span class="small"><?php echo $option; ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3">2. How effectively did the instructor explain concepts and engage with students during the course? <span class="text-danger">*</span></label>
                            <div class="rating-container">
                                <?php 
                                $options = [
                                    'Excellent' => ['icon' => 'fas fa-star', 'color' => 'success'],
                                    'Good' => ['icon' => 'fas fa-thumbs-up', 'color' => 'primary'],
                                    'Average' => ['icon' => 'fas fa-equals', 'color' => 'warning'],
                                    'Poor' => ['icon' => 'fas fa-thumbs-down', 'color' => 'danger']
                                ];
                                foreach ($options as $option => $data): 
                                    $checked = (isset($instructor_effectiveness) && $instructor_effectiveness === $option) ? 'active' : '';
                                ?>
                                    <div class="rating-option">
                                        <input type="radio" id="instructor_<?php echo strtolower($option); ?>" name="instructor_effectiveness" 
                                               value="<?php echo $option; ?>" class="d-none" 
                                               <?php echo $checked ? 'checked' : ''; ?> required>
                                        <label for="instructor_<?php echo strtolower($option); ?>" class="d-flex flex-column align-items-center text-center p-3 rounded-3 border <?php echo $checked ? 'border-2 border-'.$data['color'] : 'border-1'; ?>" 
                                               style="cursor: pointer; transition: all 0.3s;">
                                            <i class="<?php echo $data['icon']; ?> fa-2x mb-2 text-<?php echo $data['color']; ?>"></i>
                                            <span class="small"><?php echo $option; ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3">3. How confident do you feel in applying what you've learned in real-world scenarios? <span class="text-danger">*</span></label>
                            <div class="rating-container">
                                <?php 
                                $options = [
                                    'Very Confident' => ['icon' => 'fas fa-check-circle', 'color' => 'success'],
                                    'Somewhat Confident' => ['icon' => 'fas fa-check', 'color' => 'primary'],
                                    'Needs More Practice' => ['icon' => 'fas fa-redo', 'color' => 'warning'],
                                    'Not Confident' => ['icon' => 'fas fa-times-circle', 'color' => 'danger']
                                ];
                                foreach ($options as $option => $data): 
                                    $checked = (isset($confidence_application) && $confidence_application === $option) ? 'active' : '';
                                ?>
                                    <div class="rating-option">
                                        <input type="radio" id="confidence_<?php echo strtolower(str_replace(' ', '_', $option)); ?>" name="confidence_application" 
                                               value="<?php echo $option; ?>" class="d-none" 
                                               <?php echo $checked ? 'checked' : ''; ?> required>
                                        <label for="confidence_<?php echo strtolower(str_replace(' ', '_', $option)); ?>" class="d-flex flex-column align-items-center text-center p-3 rounded-3 border <?php echo $checked ? 'border-2 border-'.$data['color'] : 'border-1'; ?>" 
                                               style="cursor: pointer; transition: all 0.3s;">
                                            <i class="<?php echo $data['icon']; ?> fa-2x mb-2 text-<?php echo $data['color']; ?>"></i>
                                            <span class="small"><?php echo $option; ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3">4. Were the training materials (slides, notes, exercises, videos) helpful and easy to follow? <span class="text-danger">*</span></label>
                            <div class="rating-container">
                                <?php 
                                $options = [
                                    'Very Helpful' => ['icon' => 'fas fa-check-circle', 'color' => 'success'],
                                    'Somewhat Helpful' => ['icon' => 'fas fa-check', 'color' => 'primary'],
                                    'Not Helpful' => ['icon' => 'fas fa-times-circle', 'color' => 'danger']
                                ];
                                foreach ($options as $option => $data): 
                                    $checked = (isset($materials_helpfulness) && $materials_helpfulness === $option) ? 'active' : '';
                                ?>
                                    <div class="rating-option">
                                        <input type="radio" id="materials_<?php echo strtolower(str_replace(' ', '_', $option)); ?>" name="materials_helpfulness" 
                                               value="<?php echo $option; ?>" class="d-none" 
                                               <?php echo $checked ? 'checked' : ''; ?> required>
                                        <label for="materials_<?php echo strtolower(str_replace(' ', '_', $option)); ?>" class="d-flex flex-column align-items-center text-center p-3 rounded-3 border <?php echo $checked ? 'border-2 border-'.$data['color'] : 'border-1'; ?>" 
                                               style="cursor: pointer; transition: all 0.3s;">
                                            <i class="<?php echo $data['icon']; ?> fa-2x mb-2 text-<?php echo $data['color']; ?>"></i>
                                            <span class="small"><?php echo $option; ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="suggestions_improvement" class="form-label fw-bold mb-3">5. How satisfied are you with the overall course experience, and what improvements would you suggest?</label>
                            <div class="form-floating">
                                <textarea class="form-control" id="suggestions_improvement" name="suggestions_improvement" 
                                         placeholder="Your suggestions for improvement..."
                                         style="height: 120px"><?php echo htmlspecialchars($suggestions_improvement ?? ''); ?></textarea>
                                <label for="suggestions_improvement" class="text-muted">Your suggestions for improvement...</label>
                            </div>
                            <small class="form-text text-muted mt-2 d-block">
                                <i class="fas fa-info-circle me-1"></i> Your detailed feedback will help us enhance the learning experience for future students.
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 mt-5">
                            <button type="submit" class="btn btn-primary btn-lg py-3 fw-bold">
                                <i class="fas fa-paper-plane me-2"></i> Submit Your Feedback
                                <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                            <p class="text-muted small text-center mt-2">
                                <i class="fas fa-lock me-1"></i> Your feedback is anonymous and will be kept confidential
                            </p>
                        </div>
            </form>
        </div>
    </div>
    
    <!-- Previous Feedback Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Your Previous Feedback</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="feedbackTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Admin Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM feedback WHERE student_id = ? ORDER BY created_at DESC";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $_SESSION['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . date('M d, Y', strtotime($row['created_at'])) . "</td>";
                                echo "<td>" . htmlspecialchars($row['subject']) . "</td>";
                                echo "<td>" . (!empty($row['admin_notes']) ? nl2br(htmlspecialchars($row['admin_notes'])) : 'No response yet') . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' class='text-center'>No feedback submitted yet.</td></tr>";
                        }
                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Initialize DataTables -->
<script>
    $(document).ready(function() {
        $('#feedbackTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            responsive: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search feedback..."
            },
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            initComplete: function() {
                // Remove any existing error messages
                $('.dataTables_wrapper .alert').remove();
            }
        });

        // Handle form submission
        $('#feedbackForm').on('submit', function(e) {
            const form = this;
            // Client-side validation
            let isValid = true;
            $('.is-invalid').removeClass('is-invalid');
            
            // Check required fields
            $(this).find('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('is-invalid');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    });
</script>

<style>
    .rating-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
    }
    
    .rating-option label {
        transition: all 0.3s ease;
    }
    
    .rating-option label:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }
    
    .rating-option input[type="radio"]:checked + label {
        background-color: rgba(var(--bs-primary-rgb), 0.05);
        border-color: var(--bs-primary) !important;
    }
    
    .form-floating > label {
        padding: 1rem 1.5rem;
    }
    
    .form-control, .form-select, .form-control:focus {
        padding: 1rem 1.5rem;
        height: auto;
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
    }
    
    .form-control:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
    }
    
    .btn-primary {
        padding: 0.75rem 2rem;
        border-radius: 0.5rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .divider {
        height: 4px;
        width: 80px;
        background-color: var(--bs-primary);
        opacity: 0.2;
        margin: 1.5rem auto;
        border-radius: 2px;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
