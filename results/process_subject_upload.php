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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: create.php");
    exit;
}

$degree_programme = $_POST['degree_programme'];
$semester = (int)$_POST['semester'];
$exam_dates = $_POST['exam_dates'] ?? [];
$subject_codes = $_POST['subject_codes'] ?? [];

$allowed = ['xlsx', 'xls', 'csv'];

require_once '../classes/GradeHelper.php';

$insertQuery = "INSERT INTO results (student_id, subject_id, semester, ca_mark, endterm_mark, marks, gpa, grade, exam_date, published)
                VALUES (:student_id, :subject_id, :semester, :ca_mark, :endterm_mark, :marks, :gpa, :grade, :exam_date, 0)";
$insertStmt = $db->prepare($insertQuery);

$updateQuery = "UPDATE results SET ca_mark = :ca_mark, endterm_mark = :endterm_mark, marks = :marks, gpa = :gpa, grade = :grade, exam_date = :exam_date, published = 0 
                WHERE student_id = :student_id AND subject_id = :subject_id AND semester = :semester";
$updateStmt = $db->prepare($updateQuery);

$checkQuery = "SELECT id FROM results WHERE student_id = :student_id AND subject_id = :subject_id AND semester = :semester";
$checkStmt = $db->prepare($checkQuery);

$studentQuery = "SELECT id FROM students WHERE register_number = :register_number AND degree_programme = :degree_programme LIMIT 1";
$studentStmt = $db->prepare($studentQuery);

$db->beginTransaction();

try {
    $insertedCount = 0;
    
    if (empty($_FILES['result_files']['name'])) {
        throw new Exception("No files were submitted in the request.");
    }

    foreach ($_FILES['result_files']['name'] as $subject_id => $name) {
        if ($_FILES['result_files']['error'][$subject_id] === 0) {
            
            $exam_date = $exam_dates[$subject_id] ?? '';
            if(empty($exam_date)) {
                throw new Exception("Exam date is required for the uploaded file: $name.");
            }

            $fileTmpPath = $_FILES['result_files']['tmp_name'][$subject_id];
            $fileExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowed)) {
                throw new Exception("Invalid file format for $name. Please upload xlsx, xls or csv only.");
            }

            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows) || count($rows) < 2) {
                throw new Exception("Uploaded file $name is empty or contains only headers.");
            }

            $header = array_map(fn($v) => strtolower(trim((string)$v)), $rows[0]);
            
            $expected_header = ['register_number', 'ca_mark', 'endterm_mark', 'final_mark'];
            
            if ($header !== $expected_header) {
                throw new Exception("Validation failed for $name. Incorrect Excel format. Required columns are exactly: Register_number, CA_mark, Endterm_mark, Final_mark (in that exact order).");
            }

            $rollIdx = 0;
            $caIdx = 1;
            $endIdx = 2;
            $marksIdx = 3;

            $processedSubjectsArray = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                if (!isset($row[$rollIdx]) || !isset($row[$marksIdx])) continue;

                $register_number = trim((string)$row[$rollIdx]);
                $marks = trim((string)$row[$marksIdx]);
                
                $ca_mark = ($caIdx !== false && isset($row[$caIdx])) ? trim((string)$row[$caIdx]) : '';
                $endterm_mark = ($endIdx !== false && isset($row[$endIdx])) ? trim((string)$row[$endIdx]) : '';

                if ($register_number === '' && $marks === '') continue;

                if ($register_number === '') {
                    throw new Exception("Missing roll number in row " . ($i + 1) . " of $name");
                }
                if ($marks === '') {
                    throw new Exception("Missing final_mark in row " . ($i + 1) . " of $name");
                }

                if (!is_numeric($marks) || $marks < 0 || $marks > 100) {
                    throw new Exception("Invalid numeric mark value ($marks) in row " . ($i + 1) . " of $name");
                }

                $studentStmt->bindParam(':register_number', $register_number);
                $studentStmt->bindParam(':degree_programme', $degree_programme);
                $studentStmt->execute();
                $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    throw new Exception("Student with register number '$register_number' not found in degree programme '$degree_programme' (File: $name, Row: " . ($i + 1) . ")");
                }

                $student_id = $student['id'];
                $marksValue = (float)$marks;
                
                $caVal = ($ca_mark !== '' && is_numeric($ca_mark)) ? (float)$ca_mark : null;
                $endVal = ($endterm_mark !== '' && is_numeric($endterm_mark)) ? (float)$endterm_mark : null;
                
                $gradeData = GradeHelper::getGradeData($marksValue);
                $gpaValue = $gradeData['point'];
                $gradeValue = $gradeData['grade'];

                // Check for existing result to update
                $checkStmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                $checkStmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
                $checkStmt->bindParam(':semester', $semester, PDO::PARAM_INT);
                $checkStmt->execute();

                if ($checkStmt->rowCount() > 0) {
                    $updateStmt->bindParam(':ca_mark', $caVal);
                    $updateStmt->bindParam(':endterm_mark', $endVal);
                    $updateStmt->bindParam(':marks', $marksValue);
                    $updateStmt->bindParam(':gpa', $gpaValue);
                    $updateStmt->bindParam(':grade', $gradeValue);
                    $updateStmt->bindParam(':exam_date', $exam_date);
                    $updateStmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                    $updateStmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
                    $updateStmt->bindParam(':semester', $semester, PDO::PARAM_INT);
                    $updateStmt->execute();
                } else {
                    $insertStmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                    $insertStmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
                    $insertStmt->bindParam(':semester', $semester, PDO::PARAM_INT);
                    $insertStmt->bindParam(':ca_mark', $caVal);
                    $insertStmt->bindParam(':endterm_mark', $endVal);
                    $insertStmt->bindParam(':marks', $marksValue);
                    $insertStmt->bindParam(':gpa', $gpaValue);
                    $insertStmt->bindParam(':grade', $gradeValue);
                    $insertStmt->bindParam(':exam_date', $exam_date);
                    $insertStmt->execute();
                }

                $insertedCount++;
            }
            // Mark this subject as successfully processed
            $processedSubjectsArray[] = $subject_id;
        } elseif ($_FILES['result_files']['error'][$subject_id] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("Error uploading file for subject ID $subject_id. PHP Upload Error Code: " . $_FILES['result_files']['error'][$subject_id]);
        }
    }

    if ($insertedCount === 0) {
        throw new Exception("No valid rows were processed. Did you actually assign a file to this upload form?");
    }

    $db->commit();
    
    $isAjax = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Results uploaded successfully!',
            'processed_subjects' => $processedSubjectsArray
        ]);
        exit;
    }

    header("Location: create.php?tab=upload&message=upload_success");
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    $isAjax = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }

    header("Location: create.php?tab=upload&error=" . urlencode($e->getMessage()));
    exit;
}
?>
