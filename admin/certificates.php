<?php
// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Handle certificate issuance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_certificate'])) {
    $userId = $_POST['user_id'] ?? '';
    $courseId = $_POST['course_id'] ?? '';
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $title = 'Course Completion Certificate';
    
    if ($userId && $courseId) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Generate unique certificate number
            $certificateNumber = 'CERT-' . strtoupper(uniqid());
            $filePath = '/certificates/' . $certificateNumber . '.pdf';
            $issueDate = date('Y-m-d');
            
            // Insert certificate record
            $stmt = $conn->prepare("INSERT INTO certificates (user_id, course_id, certificate_number, title, file_path, issue_date, expiry_date, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            
            if ($stmt) {
                $stmt->bind_param('iisssss', $userId, $courseId, $certificateNumber, $title, $filePath, $issueDate, $expiryDate);
                
                if ($stmt->execute()) {
                    $certificateId = $conn->insert_id;
                    
                    // Create certificates directory if it doesn't exist
                    $certDir = $_SERVER['DOCUMENT_ROOT'] . '/certificates';
                    if (!file_exists($certDir)) {
                        mkdir($certDir, 0777, true);
                    }
                    
                    // Generate PDF (placeholder - you'll need to implement actual PDF generation)
                    $pdfContent = "Certificate #$certificateNumber\n";
                    $pdfContent .= "This is to certify that [Student Name] has successfully completed the course.\n";
                    $pdfContent .= "Issued on: $issueDate\n";
                    if ($expiryDate) {
                        $pdfContent .= "Valid until: $expiryDate\n";
                    }
                    
                    // Save PDF (in a real app, you'd use a PDF library like TCPDF or mPDF)
                    $pdfPath = $certDir . '/' . $certificateNumber . '.txt'; // Using .txt as placeholder
                    file_put_contents($pdfPath, $pdfContent);
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $_SESSION['success'] = 'Certificate issued successfully!';
                    header('Location: certificates.php');
                    exit();
                } else {
                    throw new Exception('Failed to issue certificate: ' . $conn->error);
                }
                $stmt->close();
            } else {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Fetch certificates with user and course details
$certificates = [];
$query = "SELECT c.*, u.username, u.email, u.username as student_name, co.title as course_name
          FROM certificates c 
          INNER JOIN users u ON c.user_id = u.id 
          INNER JOIN courses co ON c.course_id = co.id 
          ORDER BY c.issue_date DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

$stmt->bind_param('ii', $perPage, $offset);

if (!$stmt->execute()) {
    die('Execute failed: ' . htmlspecialchars($stmt->error));
}

$result = $stmt->get_result();
$certificates = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count for pagination
$countResult = $conn->query("SELECT COUNT(*) as total FROM certificates");
$totalCertificates = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCertificates / $perPage);

// Fetch students and courses for the issue form
$students = [];
$studentsResult = $conn->query("SELECT id, username, email FROM users WHERE role = 'student' ORDER BY username");
if ($studentsResult) {
    $students = $studentsResult->fetch_all(MYSQLI_ASSOC);
    $studentsResult->free();
}

$courses = [];
$coursesResult = $conn->query("SELECT id, title as course_name FROM courses ORDER BY course_name");
if ($coursesResult) {
    $courses = $coursesResult->fetch_all(MYSQLI_ASSOC);
    $coursesResult->free();
}
?>

<?php 
$pageTitle = 'Certificate Management';
include 'includes/header.php'; 
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Certificate Management</h4>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#issueCertificateModal">
                        <i class="fas fa-certificate me-2"></i> Issue New Certificate
                    </button>
                </div>
            </div>
            <p class="text-muted mt-2">Manage and issue certificates to students</p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <?php unset($_SESSION['success']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Certificate List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="card">
        <div class="card-body">
            <!-- Search and Filter -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="search-box">
                        <div class="position-relative">
                            <input type="text" class="form-control" placeholder="Search certificates...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-md-right">
                        <button class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                        <button class="btn btn-outline-secondary">
                            <i class="fas fa-download mr-1"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-centered table-nowrap mb-0" style="table-layout: fixed; width: 100%;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 15%;">Certificate #</th>
                            <th style="width: 25%;">Student</th>
                            <th style="width: 20%;">Course</th>
                            <th style="width: 15%;">Issue Date</th>
                            <th style="width: 10%;">Status</th>
                            <th style="width: 20%;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $counter = ($page - 1) * $perPage + 1; ?>
                    <?php foreach ($certificates as $cert): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="align-middle"><?= $counter++ ?></td>
                            <td class="align-middle">
                                <span class="font-weight-semibold text-nowrap d-block">
                                    CERT-<?= strtoupper(substr(md5($cert['id']), 0, 9)) ?>
                                </span>
                            </td>
                            <td class="align-middle">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-2">
                                        <div class="avatar-xs">
                                            <span class="avatar-title rounded-circle bg-soft-primary text-primary">
                                                <?= strtoupper(substr($cert['username'], 0, 1)) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="font-size-14 mb-0 text-truncate" title="<?= htmlspecialchars($cert['username']) ?>">
                                            <?= htmlspecialchars($cert['username']) ?>
                                        </h5>
                                        <small class="text-muted d-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cert['email']) ?>">
                                            <?= htmlspecialchars($cert['email']) ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle">
                                <a href="#" class="text-wrap text-left text-dark text-decoration-none" data-bs-toggle="tooltip" title="View Certificate">
                                    <i class="fas fa-book me-1"></i> <?= htmlspecialchars($cert['course_name']) ?>
                                </a>
                            </td>
                            <td class="align-middle">
                                <i class="far fa-calendar-alt mr-1"></i> 
                                <?= date('M j, Y', strtotime($cert['issue_date'])) ?>
                            </td>
                            <td class="align-middle">
                                <span class="badge bg-<?= $cert['status'] === 'active' ? 'success' : ($cert['status'] === 'revoked' ? 'danger' : 'warning') ?>">
                                    <i class="fas fa-<?= $cert['status'] === 'active' ? 'check-circle' : ($cert['status'] === 'revoked' ? 'times-circle' : 'exclamation-circle') ?> me-1"></i> 
                                    <?= ucfirst($cert['status']) ?>
                                </span>
                            </td>
                            <td class="text-center align-middle">
                                <div class="btn-group btn-group-sm">
                                    <a href="/certificates/<?= $cert['certificate_number'] ?>.txt" 
                                       class="btn btn-outline-primary" 
                                       data-bs-toggle="tooltip" 
                                       title="View Certificate"
                                       target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="/certificates/<?= $cert['certificate_number'] ?>.txt" 
                                       class="btn btn-outline-success" 
                                       data-bs-toggle="tooltip" 
                                       title="Download Certificate"
                                       download="certificate_<?= $cert['certificate_number'] ?>.txt">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php if ($cert['status'] === 'active'): ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            data-bs-toggle="tooltip" 
                                            title="Revoke Certificate" 
                                            onclick="confirmRevoke(<?= $cert['id'] ?>)">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

            <!-- Pagination -->
            <div class="row mt-4">
                <div class="col-sm-12 col-md-5">
                    <div class="dataTables_info" role="status" aria-live="polite">
                        Showing <?= (($page - 1) * $perPage) + 1 ?> to 
                        <?= min($page * $perPage, $totalCertificates) ?> of 
                        <?= $totalCertificates ?> entries
                    </div>
                </div>
                <div class="col-sm-12 col-md-7">
                    <div class="dataTables_paginate paging_simple_numbers">
                        <ul class="pagination pagination-rounded justify-content-end mb-0">
                            <?php if ($page > 1): ?>
                                <li class="paginate_button page-item previous">
                                    <a href="?page=<?= $page - 1 ?>" class="page-link">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            $startPage = max(1, $endPage - 4);
                            
                            if ($startPage > 1) {
                                echo '<li class="paginate_button page-item disabled"><a class="page-link">...</a></li>';
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="paginate_button page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a href="?page=<?= $i ?>" class="page-link"><?= $i ?></a>
                                </li>
                            <?php 
                            endfor; 
                            
                            if ($endPage < $totalPages) {
                                echo '<li class="paginate_button page-item disabled"><a class="page-link">...</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="paginate_button page-item next">
                                    <a href="?page=<?= $page + 1 ?>" class="page-link">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>                
</div>

<!-- Issue Certificate Modal -->
<div class="modal fade" id="issueCertificateModal" tabindex="-1" role="dialog" aria-labelledby="issueCertificateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="issueCertificateModalLabel">
                    <i class="fas fa-certificate text-primary mr-2"></i> Issue New Certificate
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Student <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="student_id" name="user_id" required>
                            <option value="" selected disabled>Select a student</option>
                            <?php
                            $students = $conn->query("SELECT id, username, email FROM users WHERE role = 'student' ORDER BY username");
                            while ($student = $students->fetch_assoc()):
                            ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['username']) ?> (<?= htmlspecialchars($student['email']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a student
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="course_id" name="course_id" required>
                            <option value="" selected disabled>Select a course</option>
                            <?php
                            $courses = $conn->query("SELECT id, title FROM courses ORDER BY title");
                            while ($course = $courses->fetch_assoc()):
                            ?>
                                <option value="<?= $course['id'] ?>">
                                    <?= htmlspecialchars($course['title']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a course
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="expiry_date" class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                        <div class="form-text">Leave empty for no expiration</div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="issue_certificate" class="btn btn-primary">
                            <i class="fas fa-paper-plane mr-1"></i> Issue Certificate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Script -->
<script>
    // Initialize tooltips and modals
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize select2 if available
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $(this).closest('.modal'),
                    placeholder: $(this).find('option[value=""]').text() || 'Select an option',
                    allowClear: true
                });
            });
        }
        
        // Form validation
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });
    
    function confirmRevoke(certId) {
        if(confirm('Are you sure you want to revoke this certificate? This action cannot be undone.')) {
            // Add your revoke logic here
            console.log('Revoking certificate ID:', certId);
            // You can make an AJAX call or redirect to a revoke endpoint
            // window.location.href = 'revoke_certificate.php?id=' + certId;
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
