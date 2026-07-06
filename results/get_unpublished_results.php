<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    exit('Unauthorized');
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if(!isset($_GET['student_id'])) {
    exit('Student ID required');
}

$student_id = $_GET['student_id'];

$student_query = "SELECT * FROM students WHERE id = :student_id";
$student_stmt = $db->prepare($student_query);
$student_stmt->bindParam(":student_id", $student_id);
$student_stmt->execute();
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT r.*, s.name as subject_name, s.code as subject_code 
          FROM results r
          JOIN subjects s ON r.subject_id = s.id
          WHERE r.student_id = :student_id 
            AND r.published = 0
          ORDER BY r.semester ASC, r.exam_date DESC, s.name ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(":student_id", $student_id);
$stmt->execute();

if($stmt->rowCount() > 0) {
    echo '<div class="card student-card">';
    echo '<div class="card-header bg-info text-white">';
    echo '<h5 class="mb-0">Unpublished Results for: ' . htmlspecialchars($student['name']) . 
         ' (Register No: ' . htmlspecialchars($student['register_number']) . ')</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    
    while($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '<div class="border rounded p-3 mb-3">';
        echo '<div><strong>Semester:</strong> Semester ' . (int)$result['semester'] . '</div>';
        echo '<div><strong>Exam Date:</strong> ' . date('d M Y', strtotime($result['exam_date'])) . '</div>';
        echo '<div><strong>Subject:</strong> ' . htmlspecialchars($result['subject_name']) . ' (' . htmlspecialchars($result['subject_code']) . ')</div>';
        echo '<div><strong>Marks:</strong> ' . htmlspecialchars($result['marks']) . '</div>';
        echo '<div class="mt-2">';
        echo '<label><input type="checkbox" name="results[]" value="' . $result['id'] . '" onchange="updatePublishButton()"> Select</label>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div></div>';
} else {
    echo '<div class="alert alert-info">No unpublished results found for this student.</div>';
}
?>