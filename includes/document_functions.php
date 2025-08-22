<?php
function logDocumentDownload($conn, $userId, $courseId, $documentType, $documentId, $documentName) {
    $stmt = $conn->prepare("CALL log_notification(?, ?, ?, ?, ?, 'download')");
    $stmt->bind_param("iisis", $userId, $courseId, $documentType, $documentId, $documentName);
    return $stmt->execute();
}

function getEnrolledCourses($conn, $userId) {
    $query = "SELECT c.* FROM courses c 
              JOIN student_courses sc ON c.id = sc.course_id 
              WHERE sc.student_id = ? AND sc.status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getCourseDocuments($conn, $courseId, $type) {
    $validTypes = ['syllabus', 'notes', 'assignments', 'tests'];
    if (!in_array($type, $validTypes)) {
        return [];
    }
    
    $table = $type === 'assignments' ? 'assignments' : $type;
    $query = "SELECT * FROM $table WHERE course_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $courseId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function handleFileUpload($file, $uploadDir, $allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png']) {
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($file['name']);
    $targetPath = rtrim($uploadDir, '/') . '/' . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type.'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath, 'name' => $fileName];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file.'];
}
