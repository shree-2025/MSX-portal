<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/email_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

$page_title = 'Bulk Upload Students';
$success = '';
$error = '';

// Function to generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Simple Excel file parsing function
function parseExcelFile($filePath) {
    $data = [];
    $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    if ($fileType === 'csv') {
        // Handle CSV files
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle)) !== FALSE) {
                $data[] = $row;
            }
            fclose($handle);
        }
    } else if (in_array($fileType, ['xls', 'xlsx'])) {
        // Handle XLS/XLSX files using Simple XML (basic parser)
        // Note: For better support, consider using PhpSpreadsheet library
        if (function_exists('simplexml_load_file')) {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === TRUE) {
                // Read shared strings
                $sharedStrings = [];
                if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
                    $xml = simplexml_load_string($zip->getFromIndex($index));
                    if ($xml) {
                        foreach ($xml->si as $string) {
                            $sharedStrings[] = (string)$string->t;
                        }
                    }
                }
                
                // Read worksheet data
                if (($index = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) {
                    $xml = simplexml_load_string($zip->getFromIndex($index));
                    if ($xml) {
                        foreach ($xml->sheetData->row as $row) {
                            $rowData = [];
                            foreach ($row->c as $cell) {
                                $value = (string)$cell->v;
                                if (isset($cell['t']) && $cell['t'] == 's') {
                                    $value = $sharedStrings[(int)$value] ?? '';
                                }
                                $rowData[] = $value;
                            }
                            $data[] = $rowData;
                        }
                    }
                }
                $zip->close();
            }
        }
    }
    
    return $data;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_file'])) {
    $file = $_FILES['student_file'];
    
    // Check for errors
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check file extension
        if (!in_array($file_ext, ['xlsx', 'xls', 'csv'])) {
            $error = 'Invalid file format. Please upload an Excel file (.xlsx, .xls, or .csv)';
        } else {
            // Parse the file using the function
            $rows = parseExcelFile($file['tmp_name']);
            
            if (empty($rows)) {
                $error = 'No data found in the uploaded file.';
            } else {
                // Assume first row is header
                $header = array_shift($rows);
                $header = array_map('trim', array_map('strtolower', $header));
                
                $expected_header = ['full name *', 'email address *', 'phone number', 'course id *'];
                $expected_lower = array_map('strtolower', $expected_header);
                
                $missing_columns = [];
                foreach ($expected_lower as $col) {
                    if (!in_array($col, $header)) {
                        $missing_columns[] = $col;
                    }
                }
                
                if (!empty($missing_columns)) {
                    $error = 'Missing required columns: ' . implode(', ', $missing_columns) . '. Please use the template.';
                } else {
                    // Map column indices
                    $col_map = [
                        'name' => array_search('full name *', $header),
                        'email' => array_search('email address *', $header),
                        'phone' => array_search('phone number', $header),
                        'course_id' => array_search('course id *', $header)
                    ];
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        $user_stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, status, is_temp_password) VALUES (?, ?, ?, ?, 'student', 'active', 1)");
                        $enroll_stmt = $conn->prepare("INSERT INTO student_courses (student_id, course_id, enrollment_date, status) VALUES (?, ?, NOW(), 'active')");
                        
                        $imported = 0;
                        $skipped = 0;
                        $errors = [];
                        $emails_sent = 0;
                        $emails_failed = 0;
                        
                        foreach ($rows as $index => $row) {
                            // Skip empty rows
                            if (empty(array_filter($row))) continue;
                            
                            $full_name = trim($row[$col_map['name']] ?? '');
                            $email = trim(strtolower($row[$col_map['email']] ?? ''));
                            $phone = trim($row[$col_map['phone']] ?? '');
                            $course_id = trim($row[$col_map['course_id']] ?? '');
                            
                            // Validate required fields
                            if (empty($full_name) || empty($email) || empty($course_id)) {
                                $errors[] = "Row " . ($index + 2) . ": Missing required fields";
                                $skipped++;
                                continue;
                            }
                            
                            // Validate email
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $errors[] = "Row " . ($index + 2) . ": Invalid email: $email";
                                $skipped++;
                                continue;
                            }
                            
                            // Check if email exists
                            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                            $check->bind_param("s", $email);
                            $check->execute();
                            if ($check->get_result()->num_rows > 0) {
                                $errors[] = "Row " . ($index + 2) . ": Email '$email' already exists";
                                $skipped++;
                                continue;
                            }
                            
                            // Validate course
                            $course_check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND status = 'active'");
                            $course_check->bind_param("i", $course_id);
                            $course_check->execute();
                            if ($course_check->get_result()->num_rows === 0) {
                                $errors[] = "Row " . ($index + 2) . ": Invalid Course ID: $course_id";
                                $skipped++;
                                continue;
                            }
                            
                            // Generate password
                            $password = generateRandomString(12);
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Insert user
                            $user_stmt->bind_param("ssss", $full_name, $email, $phone, $hashed_password);
                            if (!$user_stmt->execute()) throw new Exception("Error inserting user");
                            
                            $student_id = $conn->insert_id;
                            
                            // Enroll
                            $enroll_stmt->bind_param("ii", $student_id, $course_id);
                            if (!$enroll_stmt->execute()) throw new Exception("Error enrolling student");
                            
                            // Get course details
                            $course_stmt = $conn->prepare("SELECT title, code FROM courses WHERE id = ?");
                            $course_stmt->bind_param("i", $course_id);
                            $course_stmt->execute();
                            $course = $course_stmt->get_result()->fetch_assoc();
                            $course_stmt->close();
                            
                            // Prepare email data
                            $email_data = [
                                'full_name' => $full_name,
                                'username' => $email,  // Using email as username for consistency
                                'password' => $password,
                                'login_url' => SITE_URL . '/login.php',
                                'site_name' => SITE_NAME,
                                'course_name' => $course['title'] ?? 'N/A',
                                'course_code' => $course['code'] ?? 'N/A',
                                'enrollment_date' => date('F j, Y'),
                                'support_email' => 'support@' . parse_url(SITE_URL, PHP_URL_HOST)
                            ];
                            
                            // Send email
                            if (sendEmailTemplate($email, 'student_registration', $email_data)) {
                                $emails_sent++;
                            } else {
                                $emails_failed++;
                            }
                            
                            $imported++;
                        }
                        
                        $conn->commit();
                        
                        $success = "Imported $imported students. Skipped $skipped. Emails sent: $emails_sent, failed: $emails_failed.";
                        if (!empty($errors)) $error = implode("<br>", $errors);
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Error: " . $e->getMessage();
                    }
                }
            }
        }
    } else {
        $error = 'Error uploading file.';
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <h1>Bulk Upload Students</h1>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="student_file" accept=".csv,.xls,.xlsx" required>
        <button type="submit">Upload</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
