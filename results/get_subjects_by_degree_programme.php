<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    exit('Unauthorized');
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if(!isset($_GET['degree_programme']) || !isset($_GET['semester'])) {
    exit('Degree Programme and Semester parameters are required');
}

$degree_programme = $_GET['degree_programme'];
$semester = (int)$_GET['semester'];

$query = "SELECT s.id, s.name, s.code, s.semester, 
          (SELECT COUNT(*) FROM results r 
           INNER JOIN students st ON r.student_id = st.id 
           WHERE r.subject_id = s.id AND r.semester = s.semester AND st.degree_programme = :dp2) as result_count
          FROM subjects s 
          WHERE s.degree_programme = :degree_programme AND s.semester = :semester
          ORDER BY s.name";

$stmt = $db->prepare($query);
$stmt->bindParam(':degree_programme', $degree_programme);
$stmt->bindParam(':dp2', $degree_programme);
$stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
$stmt->execute();

$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($subjects);
?>