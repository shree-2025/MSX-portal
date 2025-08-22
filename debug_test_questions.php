<?php
require_once __DIR__ . '/config/config.php';

// Get the latest test attempt
$test_id = 1; // Change this to your test ID
$query = "SELECT * FROM test_questions WHERE test_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $test_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Test Questions and Correct Answers</h2>";
while ($row = $result->fetch_assoc()) {
    echo "<div style='border:1px solid #ccc; padding:10px; margin:10px 0;'>";
    echo "<strong>Question ID:</strong> " . $row['id'] . "<br>";
    echo "<strong>Type:</strong> " . htmlspecialchars($row['question_type']) . "<br>";
    echo "<strong>Question:</strong> " . htmlspecialchars($row['question_text']) . "<br>";
    
    if ($row['question_type'] === 'mcq') {
        $options = json_decode($row['options'] ?? '[]', true);
        echo "<strong>Options:</strong><br>";
        foreach ($options as $i => $option) {
            echo "- " . htmlspecialchars($option);
            if ($option === $row['correct_answer']) {
                echo " <span style='color:green;'>(Correct Answer)</span>";
            }
            echo "<br>";
        }
    } else {
        echo "<strong>Correct Answer:</strong> " . htmlspecialchars($row['correct_answer']) . "<br>";
    }
    
    echo "<strong>Correct Answer (raw):</strong> " . htmlspecialchars($row['correct_answer']) . "<br>";
    echo "<strong>Answer Length:</strong> " . strlen($row['correct_answer'] ?? '') . " characters<br>";
    echo "</div>";
}
?>
