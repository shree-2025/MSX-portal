<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

// Get list of active courses for the instructions
$courses = [];
$result = $conn->query("SELECT id, CONCAT(code, ' - ', title) as name FROM courses WHERE status = 'active' ORDER BY code");
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

// Start output buffering
ob_start();

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper encoding in Excel
fputs($output, "\xEF\xBB\xBF");

// Add header row
fputcsv($output, ['Full Name *', 'Email Address *', 'Phone Number', 'Course ID *']);

// Add example rows
fputcsv($output, ['John Doe', 'john.doe@example.com', '1234567890', '1']);
fputcsv($output, ['Jane Smith', 'jane.smith@example.com', '0987654321', '2']);

// Add empty line before instructions
fputcsv($output, []);

// Add instructions
fputcsv($output, ['INSTRUCTIONS:']);
fputcsv($output, ['1. Fill in student information in the rows above.']);
fputcsv($output, ['2. Required fields are marked with *']);
fputcsv($output, ['3. For Course ID, use one of the following values:']);

// Add course list
fputcsv($output, ['', 'Course ID', 'Course Name']);
foreach ($courses as $course) {
    fputcsv($output, ['', $course['id'], $course['name']]);
}

fputcsv($output, ['4. Delete the example rows (2-3) before uploading']);
fputcsv($output, ['5. Do not modify the header row']);

// Get the CSV content
$csvContent = ob_get_clean();

// Set headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=student_upload_template.csv');
header('Pragma: no-cache');
header('Expires: 0');

// Output the CSV content
echo $csvContent;
exit;


// Close XML structure
$xml .= '</Table>
</Worksheet>
<Worksheet ss:Name="Instructions">
<Table>
<Row><Cell><Data ss:Type="String">INSTRUCTIONS</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">1. Fill in student information in the columns below.</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">2. Do not modify the header row (Row 1).</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">3. Required fields: Full Name, Email</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">4. Email must be unique for each student.</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">5. Phone numbers should include country code if necessary.</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">6. Delete the example rows (2-3) before saving your data.</Data></Cell></Row>
<Row><Cell><Data ss:Type="String">7. Save the file in .xlsx format before uploading.</Data></Cell></Row>
</Table>
</Worksheet>
</Workbook>';

// Create a temporary file
$tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
file_put_contents($tempFile, $xml);

// Output the file
readfile($tempFile);

// Clean up
unlink($tempFile);
exit;
