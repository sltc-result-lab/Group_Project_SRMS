<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';
include_once '../classes/GradeHelper.php';
require_once '../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

$database = new Database();
$db = $database->getConnection();

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$student_degree_programme = $_SESSION['student_degree_programme'];
$student_register = $_SESSION['student_register'];
$exam_date = $_GET['exam_date'] ?? '';
$semester = isset($_GET['semester']) ? (int) $_GET['semester'] : 1;

$query = "SELECT r.*, s.name AS subject_name, s.code AS subject_code
          FROM results r
          INNER JOIN subjects s ON r.subject_id = s.id
          WHERE r.student_id = :student_id
            AND r.semester = :semester
            AND r.published = 1
            AND r.exam_date = :exam_date
          ORDER BY s.name ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":student_id", $student_id);
$stmt->bindParam(":semester", $semester, PDO::PARAM_INT);
$stmt->bindParam(":exam_date", $exam_date);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_marks = 0;
$total_points = 0;
$count = 0;

// Handle Verification Code
$verification_query = "SELECT verification_code FROM result_verifications WHERE student_id = :student_id AND semester = :semester AND status = 'active' LIMIT 1";
$v_stmt = $db->prepare($verification_query);
$v_stmt->bindParam(":student_id", $student_id);
$v_stmt->bindParam(":semester", $semester, PDO::PARAM_INT);
$v_stmt->execute();

$verification_code = "";
if ($v_stmt->rowCount() > 0) {
    $v_row = $v_stmt->fetch(PDO::FETCH_ASSOC);
    $verification_code = $v_row['verification_code'];
} else {
    // Generate new code
    $verification_code = strtoupper(bin2hex(random_bytes(8)));
    $insert_v = "INSERT INTO result_verifications (student_id, semester, verification_code) VALUES (:student_id, :semester, :code)";
    $i_stmt = $db->prepare($insert_v);
    $i_stmt->bindParam(":student_id", $student_id);
    $i_stmt->bindParam(":semester", $semester, PDO::PARAM_INT);
    $i_stmt->bindParam(":code", $verification_code);
    $i_stmt->execute();
}

// Generate QR Code Base64
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_dir = rtrim(dirname($script_dir), '/'); // e.g., /result_publisher or ''
// Build URL
$verification_url = $protocol . "://" . $host . $base_dir . "/verify_result.php?code=" . $verification_code;

$qrOptions = new QROptions([
    'version' => 5,
    'outputType' => QRCode::OUTPUT_MARKUP_SVG,
    'eccLevel' => QRCode::ECC_L,
]);
$qrcode = new QRCode($qrOptions);
$qrImage = $qrcode->render($verification_url);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Semester Transcript PDF</title>
    <style>
        @page {
            margin: 2cm;
        }

        body {
            font-family: "Times New Roman", Times, serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .header-logo {
            width: 80px;
            margin-bottom: 10px;
        }

        .header-title {
            font-size: 24px;
            font-weight: normal;
            margin: 0 0 10px 0;
        }

        .header-divider {
            border: 0;
            border-top: 1px solid #000;
            margin: 0 0 5px 0;
        }

        .department {
            font-size: 16px;
            margin: 5px 0;
        }

        .degree {
            font-size: 16px;
            font-weight: bold;
            margin: 5px 0;
        }

        .sheet-title {
            font-size: 16px;
            margin: 5px 0 20px 0;
        }

        .student-info {
            margin-bottom: 30px;
        }

        .student-info table {
            width: auto;
            border-collapse: collapse;
            margin: 0;
        }

        .student-info td {
            padding: 5px 15px 5px 0;
            font-size: 14px;
            font-weight: bold;
            border: none;
        }

        .qr-code {
            margin-top: 40px;
            text-align: right;
        }

        .qr-code svg {
            width: 100px;
            height: 100px;
        }

        .qr-code .qr-text {
            font-size: 10px;
            margin-top: 5px;
            font-family: Arial, sans-serif;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .results-table th,
        .results-table td {
            border: 1px solid #000;
            padding: 8px 12px;
            text-align: left;
            font-size: 14px;
        }

        .results-table th {
            font-weight: bold;
            background-color: #fff;
        }

        .text-center {
            text-align: center !important;
        }

        .gpa-section {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
        }

        @media print {
            .print-btn {
                display: none;
            }

            body {
                padding: 0;
                margin: 0;
            }
        }

        .print-btn button {
            padding: 8px 16px;
            background-color: #0d6efd;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }

        .print-btn button:hover {
            background-color: #0b5ed7;
        }

        .print-btn button.btn-back {
            background-color: #6c757d;
        }

        .print-btn button.btn-back:hover {
            background-color: #5c636a;
        }
    </style>
</head>

<body>
    <div class="print-btn" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <button class="btn-back" onclick="window.location.href='dashboard.php'">Back</button>
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>
    <div class="header-section">
        <!-- Place your logo in the assets directory or update the src path to your actual logo -->
        <img src="../assets/pdflogo.png" alt="Logo" class="header-logo" onerror="this.style.display='none'">
        <h1 class="header-title">Sri Lanka Technology Campus (Pvt.) Ltd</h1>
        <hr class="header-divider">
        <div class="department">Department of Examination</div>
        <div class="degree"><?php echo htmlspecialchars($student_degree_programme); ?></div>
        <div class="sheet-title">Student Result Sheet</div>
    </div>

    <div class="student-info">
        <table>
            <tr>
                <td>Student Name</td>
                <td>: <?php echo htmlspecialchars($student_name); ?></td>
            </tr>
            <tr>
                <td>Registration Number</td>
                <td>: <?php echo htmlspecialchars($student_register); ?></td>
            </tr>
            <tr>
                <td>Semester</td>
                <td>: Semester <?php echo $semester; ?></td>
            </tr>
        </table>
    </div>

    <table class="results-table">
        <thead>
            <tr>
                <th>Subject</th>
                <th style="width: 20%;">Code</th>
                <th class="text-center" style="width: 20%;">Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $gd = GradeHelper::getGradeData((float) $row['marks']);
                $total_points += $gd['point'];
                $count++;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($gd['grade']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="gpa-section">
        Final GPA : <?php echo $count ? number_format($total_points / $count, 2) : '0.00'; ?>
    </div>

    <div class="qr-code">
        <img src="<?php echo $qrImage; ?>" alt="QR Code" style="width: 100px; height: 100px;">
        <div class="qr-text">Scan to Verify</div>
        <div class="qr-text" style="font-size: 8px;"><?php echo htmlspecialchars($verification_code); ?></div>
    </div>

</body>

</html>