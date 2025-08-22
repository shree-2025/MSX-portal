<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['question_file'])) {
    setFlashMessage('error', 'Invalid request');
    header('Location: tests.php');
    exit();
}

$test_id = (int)$_POST['test_id'];

// Check if file was uploaded without errors
if ($_FILES['question_file']['error'] !== UPLOAD_ERR_OK) {
    $error = 'Error uploading file. Error code: ' . $_FILES['question_file']['error'];
    setFlashMessage('error', $error);
    header("Location: manage_questions.php?test_id=$test_id");
    exit();
}

// Check file type
$file_type = strtolower(pathinfo($_FILES['question_file']['name'], PATHINFO_EXTENSION));
$allowed_types = ['xlsx', 'xls', 'csv'];

if (!in_array($file_type, $allowed_types)) {
    setFlashMessage('error', 'Invalid file type. Only Excel (XLSX, XLS) and CSV files are allowed.');
    header("Location: manage_questions.php?test_id=$test_id");
    exit();
}

// Include PHPExcel library
require_once __DIR__ . '/../vendor/autoload.php';

// Create a new PHPExcel object
$input_file = $_FILES['question_file']['tmp_name'];

try {
    if ($file_type === 'csv') {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
    } else {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($input_file);
    }
    
    $spreadsheet = $reader->load($input_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    if (count($rows) <= 1) {
        throw new Exception('The file is empty or contains only headers');
    }
    
    // Get header row (first row)
    $headers = array_map('strtolower', array_shift($rows));
    
    // Required columns
    $required_columns = ['question_text', 'question_type', 'marks'];
    $missing_columns = [];
    
    foreach ($required_columns as $col) {
        if (!in_array($col, $headers)) {
            $missing_columns[] = $col;
        }
    }
    
    if (!empty($missing_columns)) {
        throw new Exception('Missing required columns: ' . implode(', ', $missing_columns));
    }
    
    // Map headers to column indices
    $column_map = [];
    foreach ($headers as $index => $header) {
        $column_map[$header] = $index;
    }
    
    $valid_question_types = ['mcq', 'true_false', 'short_answer', 'essay'];
    $questions_added = 0;
    $errors = [];
    
    // Start transaction
    $conn->begin_transaction();
    
    foreach ($rows as $row_num => $row) {
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        try {
            $question_text = trim($row[$column_map['question_text']] ?? '');
            $question_type = strtolower(trim($row[$column_map['question_type']] ?? ''));
            $marks = (int)($row[$column_map['marks']] ?? 1);
            
            // Validate required fields
            if (empty($question_text)) {
                throw new Exception("Row " . ($row_num + 2) . ": Question text is required");
            }
            
            if (!in_array($question_type, $valid_question_types)) {
                throw new Exception("Row " . ($row_num + 2) . ": Invalid question type. Must be one of: " . 
                    implode(', ', $valid_question_types));
            }
            
            if ($marks <= 0) {
                throw new Exception("Row " . ($row_num + 2) . ": Marks must be greater than 0");
            }
            
            // Process based on question type
            $options = null;
            $correct_answer = null;
            $explanation = trim($row[$column_map['explanation'] ?? -1] ?? '');
            
            if ($question_type === 'mcq') {
                $options = [];
                $correct_option = -1;
                
                // Get options (option1, option2, etc.)
                for ($i = 1; $i <= 10; $i++) {
                    $option_key = 'option' . $i;
                    if (isset($column_map[$option_key]) && !empty($row[$column_map[$option_key]])) {
                        $options[] = trim($row[$column_map[$option_key]]);
                    }
                }
                
                if (count($options) < 2) {
                    throw new Exception("Row " . ($row_num + 2) . ": At least 2 options are required for MCQ");
                }
                
                // Get correct answer (can be index or value)
                if (isset($column_map['correct_option'])) {
                    $correct_option_index = (int)($row[$column_map['correct_option']] ?? 1) - 1; // Convert to 0-based index
                    if (isset($options[$correct_option_index])) {
                        $correct_answer = $options[$correct_option_index];
                    }
                } elseif (isset($column_map['correct_answer'])) {
                    $correct_answer = trim($row[$column_map['correct_answer']] ?? '');
                    if (!in_array($correct_answer, $options)) {
                        $correct_answer = null;
                    }
                }
                
                if ($correct_answer === null) {
                    // Default to first option if no valid correct answer found
                    $correct_answer = $options[0];
                }
                
                $options = json_encode($options);
                
            } elseif ($question_type === 'true_false') {
                if (isset($column_map['correct_answer'])) {
                    $correct_answer = strtolower(trim($row[$column_map['correct_answer']] ?? ''));
                    if (!in_array($correct_answer, ['true', 'false', 't', 'f', '1', '0'])) {
                        $correct_answer = 'true'; // Default to true if invalid
                    } else {
                        $correct_answer = in_array($correct_answer, ['true', 't', '1']) ? 'true' : 'false';
                    }
                } else {
                    $correct_answer = 'true'; // Default to true if not specified
                }
            } else {
                // For short_answer and essay types
                $correct_answer = '';
            }
            
            // Insert question into database
            $stmt = $conn->prepare("
                INSERT INTO test_questions 
                (test_id, question_text, question_type, options, correct_answer, marks, explanation)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "issssis",
                $test_id,
                $question_text,
                $question_type,
                $options,
                $correct_answer,
                $marks,
                $explanation
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Row " . ($row_num + 2) . ": Failed to save question - " . $stmt->error);
            }
            
            $questions_added++;
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            continue; // Continue with next row
        }
    }
    
    if ($questions_added > 0) {
        // Update test's total marks
        $update_stmt = $conn->prepare("
            UPDATE tests 
            SET total_marks = (
                SELECT COALESCE(SUM(marks), 0) 
                FROM test_questions 
                WHERE test_id = ?
            ),
            updated_at = NOW()
            WHERE id = ?
        ");
        
        $update_stmt->bind_param("ii", $test_id, $test_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update test total marks: " . $update_stmt->error);
        }
        
        // Commit transaction if we have at least one successful question
        $conn->commit();
        
        $message = "Successfully added $questions_added question(s)";
        if (!empty($errors)) {
            $message .= ". " . count($errors) . " question(s) had errors.";
        }
        setFlashMessage('success', $message);
        
    } else {
        $conn->rollback();
        throw new Exception("No valid questions were found in the file.");
    }
    
    // If there were any errors, add them to the session to display
    if (!empty($errors)) {
        $_SESSION['import_errors'] = $errors;
    }
    
} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    setFlashMessage('error', 'Error processing file: ' . $e->getMessage());
}

header("Location: manage_questions.php?test_id=$test_id");
exit();
