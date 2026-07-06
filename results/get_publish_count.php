<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    exit('Unauthorized');
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$class = $_GET['class'] ?? '';
$semester = (int)($_GET['semester'] ?? 0);
$exam_date = $_GET['exam_date'] ?? '';

if($class === '' || $semester < 1 || $exam_date === '') {
    echo json_encode(['count' => 0]);
    exit;
}

$query = "SELECT COUNT(*) as total
          FROM results r
          INNER JOIN students s ON r.student_id = s.id
          WHERE s.class = :class
            AND r.semester = :semester
            AND r.exam_date = :exam_date
            AND r.published = 0";

$stmt = $db->prepare($query);
$stmt->bindParam(':class', $class);
$stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
$stmt->bindParam(':exam_date', $exam_date);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['count' => (int)$row['total']]);
?>