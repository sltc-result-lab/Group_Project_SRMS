<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include_once '../config/database.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$degree_programme = $_POST['degree_programme'] ?? '';
$semester = (int)($_POST['semester'] ?? 0);
$subject_id = (int)($_POST['subject_id'] ?? 0);

if (!$degree_programme || !$semester || !$subject_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

try {
    // We only want to delete results for THIS subject, THIS semester, 
    // and for students belonging to THIS currently viewed class
    $query = "DELETE r FROM results r
              INNER JOIN students s ON r.student_id = s.id
              WHERE r.subject_id = :subject_id 
              AND r.semester = :semester 
              AND s.degree_programme = :degree_programme";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
    $stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
    $stmt->bindParam(':degree_programme', $degree_programme);
    
    $stmt->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Results deleted successfully. You can now re-upload for this subject.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
