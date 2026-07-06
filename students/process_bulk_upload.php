<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$database = new Database();
$db = $database->getConnection();

if(!isset($_FILES['student_file']) || $_FILES['student_file']['error'] !== 0) {
    header("Location: bulk_upload.php?error=" . urlencode("Please select a valid file."));
    exit;
}

$fileTmpPath = $_FILES['student_file']['tmp_name'];
$fileName = $_FILES['student_file']['name'];
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
        throw new Exception("Uploaded file is empty or missing data rows.");
    }

    $header = array_map(function($v) {
        return strtolower(trim((string)$v));
    }, $rows[0]);

    $expected = ['register_number', 'name', 'email', 'degree_programme', 'date_of_birth'];
    
    if($header !== $expected) {
        throw new Exception("Invalid file header. Required: register_number, name, email, degree_programme, date_of_birth");
    }

    $insertQuery = "INSERT INTO students (register_number, name, email, degree_programme, date_of_birth)
                    VALUES (:register_number, :name, :email, :degree_programme, :date_of_birth)
                    ON DUPLICATE KEY UPDATE 
                        name = VALUES(name),
                        email = VALUES(email),
                        degree_programme = VALUES(degree_programme),
                        date_of_birth = VALUES(date_of_birth)";

    $insertStmt = $db->prepare($insertQuery);

    $db->beginTransaction();

    $insertedCount = 0;

    for($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        $register_number = trim((string)$row[0]);
        $name = trim((string)$row[1]);
        $email = trim((string)$row[2]);
        $degree_programme = trim((string)$row[3]);
        
        $raw_dob = $row[4];
        $date_of_birth = null;

        if($register_number === '' && $name === '' && $degree_programme === '' && $email === '') {
            continue; // Skip completely empty rows
        }

        if($register_number === '' || $name === '' || $degree_programme === '' || $email === '') {
            throw new Exception("Missing required data (register_number, name, email, or degree_programme) in row " . ($i + 1));
        }

        // Process Date of Birth
        if (!empty($raw_dob)) {
            if (is_numeric($raw_dob)) {
                // Handle Excel date serial number
                $dateObj = Date::excelToDateTimeObject($raw_dob);
                $date_of_birth = $dateObj->format('Y-m-d');
            } else {
                // Try parsing string date
                $time = strtotime((string)$raw_dob);
                if ($time !== false) {
                    $date_of_birth = date('Y-m-d', $time);
                } else {
                    $date_of_birth = null;
                }
            }
        }

        $insertStmt->bindParam(':register_number', $register_number);
        $insertStmt->bindParam(':name', $name);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':degree_programme', $degree_programme);
        $insertStmt->bindParam(':date_of_birth', $date_of_birth);
        
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
