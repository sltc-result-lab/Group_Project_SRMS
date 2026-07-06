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

$semesterFilter = "";
$params = [':student_id' => $_GET['student_id']];

if(isset($_GET['semester']) && $_GET['semester'] !== '') {
    $semesterFilter = " AND r.semester = :semester ";
    $params[':semester'] = (int)$_GET['semester'];
}

$query = "SELECT r.*, s.name as subject_name, s.code as subject_code 
          FROM results r
          JOIN subjects s ON r.subject_id = s.id
          WHERE r.student_id = :student_id
          $semesterFilter
          ORDER BY r.semester ASC, r.exam_date DESC, s.name ASC";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

if($stmt->rowCount() > 0) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-striped">';
    echo '<thead><tr>';
    echo '<th>Semester</th>';
    echo '<th>Subject</th>';
    echo '<th>Marks</th>';
    echo '<th>Exam Date</th>';
    echo '<th>Status</th>';
    echo '</tr></thead><tbody>';
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>Semester " . (int)$row['semester'] . "</td>";
        echo "<td>" . htmlspecialchars($row['subject_name']) . " (" . htmlspecialchars($row['subject_code']) . ")</td>";
        echo "<td>" . htmlspecialchars($row['marks']) . "</td>";
        echo "<td>" . date('d M Y', strtotime($row['exam_date'])) . "</td>";
        echo "<td>" . ((int)$row['published'] === 1 ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-warning text-dark">Pending</span>') . "</td>";
        echo "</tr>";
    }
    
    echo '</tbody></table>';
    echo '</div>';
} else {
    echo '<div class="alert alert-info">No results found for this student.</div>';
}
?>