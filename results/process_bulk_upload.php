<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$database = new Database();
$db = $database->getConnection();

if(!isset($_FILES['result_file']) || $_FILES['result_file']['error'] !== 0) {
    header("Location: bulk_upload.php?error=" . urlencode("Please select a valid file."));
    exit;
}

$fileTmpPath = $_FILES['result_file']['tmp_name'];
$fileName = $_FILES['result_file']['name'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$allowed = ['xlsx', 'xls', 'csv'];
if(!in_array($fileExt, $allowed)) {
    header("Location: bulk_upload.php?error=" . urlencode("Invalid file format. Upload xlsx, xls or csv."));
    exit;
}

try {
    $spreadsheet = IOFactory::load($fileTmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    if(count($rows) < 2) {
        throw new Exception("Uploaded file is empty.");
    }

    $header = array_map(fn($v) => strtolower(trim((string)$v)), $rows[0]);
    $expected = ['register_number', 'degree_programme', 'semester', 'exam_date', 'subject_code', 'ca_mark', 'endterm_mark', 'final_mark'];

    if($header !== $expected) {
        throw new Exception("Invalid file header. Required: register_number, degree_programme, semester, exam_date, subject_code, ca_mark, endterm_mark, final_mark");
    }

    $insertQuery = "INSERT INTO results (student_id, subject_id, semester, ca_mark, endterm_mark, marks, exam_date, published)
                    VALUES (:student_id, :subject_id, :semester, :ca_mark, :endterm_mark, :marks, :exam_date, 0)
                    ON DUPLICATE KEY UPDATE 
                        ca_mark = VALUES(ca_mark),
                        endterm_mark = VALUES(endterm_mark),
                        marks = VALUES(marks),
                        published = 0";

    $insertStmt = $db->prepare($insertQuery);

    $studentQuery = "SELECT id FROM students WHERE register_number = :register_number AND degree_programme = :degree_programme LIMIT 1";
    $studentStmt = $db->prepare($studentQuery);

    $subjectQuery = "SELECT id FROM subjects WHERE code = :subject_code AND degree_programme = :degree_programme AND semester = :semester LIMIT 1";
    $subjectStmt = $db->prepare($subjectQuery);

    $db->beginTransaction();

    $insertedCount = 0;

    for($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        $register_number = trim((string)$row[0]);
        $degree_programme = trim((string)$row[1]);
        $semester = (int)$row[2];
        $exam_date = trim((string)$row[3]);
        $subject_code = trim((string)$row[4]);
        $ca_mark = trim((string)$row[5]);
        $endterm_mark = trim((string)$row[6]);
        $marks = trim((string)$row[7]);

        if($register_number === '' && $degree_programme === '' && $subject_code === '') {
            continue;
        }

        if($register_number === '' || $degree_programme === '' || $semester === 0 || $exam_date === '' || $subject_code === '' || $marks === '') {
            throw new Exception("Missing required data in row " . ($i + 1));
        }

        if($semester < 1 || $semester > 6) {
            throw new Exception("Invalid semester in row " . ($i + 1));
        }

        if(!is_numeric($marks) || $marks < 0 || $marks > 100) {
            throw new Exception("Invalid marks in row " . ($i + 1));
        }

        $studentStmt->bindParam(':register_number', $register_number);
        $studentStmt->bindParam(':degree_programme', $degree_programme);
        $studentStmt->execute();
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if(!$student) {
            throw new Exception("Student not found in row " . ($i + 1) . " (Register No: $register_number, Degree Programme: $degree_programme)");
        }

        $subjectStmt->bindParam(':subject_code', $subject_code);
        $subjectStmt->bindParam(':degree_programme', $degree_programme);
        $subjectStmt->bindParam(':semester', $semester, PDO::PARAM_INT);
        $subjectStmt->execute();
        $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC);

        if(!$subject) {
            throw new Exception("Subject not found in row " . ($i + 1) . " (Code: $subject_code, Degree Programme: $degree_programme, Semester: $semester)");
        }

        $student_id = $student['id'];
        $subject_id = $subject['id'];
        $marksValue = (float)$marks;

        $ca_mark_val = $ca_mark === '' ? null : (float)$ca_mark;
        $endterm_mark_val = $endterm_mark === '' ? null : (float)$endterm_mark;

        $insertStmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $insertStmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
        $insertStmt->bindParam(':semester', $semester, PDO::PARAM_INT);
        $insertStmt->bindParam(':ca_mark', $ca_mark_val);
        $insertStmt->bindParam(':endterm_mark', $endterm_mark_val);
        $insertStmt->bindParam(':marks', $marksValue);
        $insertStmt->bindParam(':exam_date', $exam_date);
        $insertStmt->execute();

        $insertedCount++;
    }

    $db->commit();

    header("Location: bulk_upload.php?success=1");
    exit;

} catch(Exception $e) {
    if($db->inTransaction()) {
        $db->rollBack();
    }

    header("Location: bulk_upload.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>