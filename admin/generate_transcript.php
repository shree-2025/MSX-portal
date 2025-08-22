<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: transcripts.php');
    exit();
}

$transcriptId = (int)$_GET['id'];

// Fetch transcript data
$stmt = $conn->prepare("
    SELECT t.*, u.full_name, u.email, u.created_at as enrollment_date
    FROM transcripts t
    JOIN users u ON t.student_id = u.id
    WHERE t.id = ?
");

$stmt->bind_param('i', $transcriptId);
$stmt->execute();
$transcript = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transcript) {
    header('Location: transcripts.php');
    exit();
}

// Fetch courses for this transcript
$stmt = $conn->prepare("
    SELECT c.title, tc.grade, tc.credits_earned
    FROM transcript_courses tc
    JOIN courses c ON tc.course_id = c.id
    WHERE tc.transcript_id = ?
    ORDER BY c.title
");

$stmt->bind_param('i', $transcriptId);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total credits and GPA
$totalCredits = 0;
$totalGradePoints = 0;
$gradePoints = [
    'A' => 4.0, 'A-' => 3.7,
    'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
    'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
    'D+' => 1.3, 'D' => 1.0, 'F' => 0.0
];

foreach ($courses as $course) {
    $totalCredits += $course['credits_earned'];
    if (isset($gradePoints[$course['grade']])) {
        $totalGradePoints += $gradePoints[$course['grade']] * $course['credits_earned'];
    }
}

$gpa = $totalCredits > 0 ? $totalGradePoints / $totalCredits : 0;

// Generate HTML for the transcript
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Academic Transcript - <?= htmlspecialchars($transcript['full_name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #1a365d; }
        .header p { margin: 5px 0; color: #4a5568; }
        .seal { width: 120px; margin: 0 auto 20px; }
        .student-info { margin: 30px 0; }
        .info-row { display: flex; margin-bottom: 10px; }
        .info-label { font-weight: bold; width: 200px; }
        .info-value { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .footer { margin-top: 50px; text-align: right; }
        .signature-line { border-top: 1px solid #000; width: 200px; display: inline-block; margin-top: 50px; }
        .watermark { 
            position: fixed; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%) rotate(-45deg); 
            font-size: 80px; 
            opacity: 0.1; 
            z-index: -1;
            white-space: nowrap;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="watermark">ACADEMIC TRANSCRIPT</div>
        
        <div class="header">
            <h1>MindSparks</h1>
    <p>Swanand, Ganesh Colony, Amravati,Maharastra,444601</p>
            <p>Phone: +91 9876543210 | Email: mindsparxs@edu.in</p>
        </div>

        <div style="text-align: center; margin: 20px 0;">
            <h2>OFFICIAL ACADEMIC TRANSCRIPT</h2>
        </div>

        <div class="student-info">
            <div class="info-row">
                <div class="info-label">Student Name:</div>
                <div class="info-value"><?= htmlspecialchars($transcript['full_name']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Student ID:</div>
                <div class="info-value">STU-<?= str_pad($transcript['student_id'], 5, '0', STR_PAD_LEFT) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date of Issue:</div>
                <div class="info-value"><?= date('F j, Y', strtotime($transcript['issue_date'])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Program of Study:</div>
                <div class="info-value"><?= htmlspecialchars($transcript['program_name']) ?></div>
            </div>
        </div>

        <h3>Academic Record</h3>
        <table>
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Credits</th>
                    <th>Grade</th>
                    <th>Credits Earned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                <tr>
                    <td><?= strtoupper(substr(str_replace(' ', '', $course['title']), 0, 3)) . rand(100, 999) ?></td>
                    <td><?= htmlspecialchars($course['title']) ?></td>
                    <td><?= $course['credits'] ?></td>
                    <td><?= $course['grade'] ?></td>
                    <td><?= $course['credits_earned'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px;">
            <div style="float: right; text-align: right;">
                <p><strong>Cumulative GPA:</strong> <?= number_format($gpa, 2) ?></p>
                <p><strong>Total Credits Earned:</strong> <?= $totalCredits ?></p>
            </div>
            <div style="clear: both;"></div>
        </div>

        <?php if (!empty($transcript['additional_notes'])): ?>
        <div style="margin-top: 30px;">
            <h4>Additional Notes:</h4>
            <p><?= nl2br(htmlspecialchars($transcript['additional_notes'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="footer">
            <div style="margin-top: 60px;">
                <div class="signature-line"></div>
                <p>Registrar's Signature</p>
                <p>Date: <?= date('m/d/Y') ?></p>
            </div>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Generate PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Save the PDF to server if it doesn't exist
$pdfPath = __DIR__ . "/../uploads/transcripts/" . basename($transcript['file_path']);
if (!file_exists(dirname($pdfPath))) {
    mkdir(dirname($pdfPath), 0777, true);
}
file_put_contents($pdfPath, $dompdf->output());

// Update the file path in database if it's not set
if (empty($transcript['file_path'])) {
    $relativePath = "/uploads/transcripts/transcript-{$transcript['transcript_number']}.pdf";
    $stmt = $conn->prepare("UPDATE transcripts SET file_path = ? WHERE id = ?");
    $stmt->bind_param('si', $relativePath, $transcriptId);
    $stmt->execute();
    $stmt->close();
}

// Remove this line: $conn->close();
?>
