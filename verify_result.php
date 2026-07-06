<?php
include_once 'config/database.php';
include_once 'classes/GradeHelper.php';

$database = new Database();
$db = $database->getConnection();

$verification_code = $_GET['code'] ?? '';
$is_valid = false;
$verification = null;
$results = [];
$total_points = 0;
$count = 0;
$published_date = '';

if ($verification_code !== '') {
    $v_query = "SELECT rv.*, s.name as student_name, s.register_number, s.degree_programme 
                FROM result_verifications rv 
                JOIN students s ON rv.student_id = s.id 
                WHERE rv.verification_code = :code AND rv.status = 'active' LIMIT 1";
    $v_stmt = $db->prepare($v_query);
    $v_stmt->bindParam(":code", $verification_code);
    $v_stmt->execute();

    if ($v_stmt->rowCount() > 0) {
        $verification = $v_stmt->fetch(PDO::FETCH_ASSOC);
        $is_valid = true;

        $r_query = "SELECT r.*, sub.name as subject_name, sub.code as subject_code 
                    FROM results r 
                    JOIN subjects sub ON r.subject_id = sub.id 
                    WHERE r.student_id = :student_id AND r.semester = :semester AND r.published = 1 
                    ORDER BY sub.name ASC";
        $r_stmt = $db->prepare($r_query);
        $r_stmt->bindParam(":student_id", $verification['student_id']);
        $r_stmt->bindParam(":semester", $verification['semester'], PDO::PARAM_INT);
        $r_stmt->execute();
        $results = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

        $latest_publish = null;
        $latest_exam = null;

        foreach ($results as $res) {
            $gd = GradeHelper::getGradeData((float)$res['marks']);
            $total_points += $gd['point'];
            $count++;

            if (!empty($res['publish_at'])) {
                if ($latest_publish === null || strtotime($res['publish_at']) > strtotime($latest_publish)) {
                    $latest_publish = $res['publish_at'];
                }
            }
            if (!empty($res['exam_date'])) {
                if ($latest_exam === null || strtotime($res['exam_date']) > strtotime($latest_exam)) {
                    $latest_exam = $res['exam_date'];
                }
            }
        }

        if ($latest_publish !== null) {
            $published_date = date('d M Y, h:i A', strtotime($latest_publish));
        } else if ($latest_exam !== null) {
            $published_date = date('d M Y', strtotime($latest_exam));
        } else {
            $published_date = 'N/A';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; font-family: "Segoe UI", Arial, sans-serif; }
        .verification-container { max-width: 800px; margin: 40px auto; }
        .card-custom { border: none; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .header-valid { background: linear-gradient(135deg, #f8fafc, #f1f5f9); color: #0f172a; padding: 40px 20px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header-invalid { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #991b1b; padding: 40px 20px; text-align: center; border-bottom: 1px solid #fecaca; }
        .header-logo { width: 100px; margin-bottom: 20px; mix-blend-mode: multiply; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        .info-item { background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .info-label { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .info-value { font-size: 16px; font-weight: 700; color: #0f172a; }
        .table-custom th { background: #f8fafc; border-bottom-width: 2px; color: #475569; font-weight: 600; }
        .table-custom td { vertical-align: middle; font-weight: 500; }
        .grade-badge { font-weight: bold; padding: 5px 12px; border-radius: 6px; display: inline-block; min-width: 40px; text-align: center; background: #e2e8f0; color: #1e293b; }
        .gpa-box { background: #1e293b; color: #f8fafc; padding: 12px 24px; border-radius: 8px; display: inline-flex; align-items: center; gap: 12px; font-size: 16px; }
        .gpa-value { font-size: 24px; font-weight: normal; color: #38bdf8; }
    </style>
</head>
<body>

<div class="container verification-container">
    <div class="card card-custom">
        <?php if ($is_valid): ?>
            <div class="header-valid">
                <img src="assets/pdflogo.png" alt="Logo" class="header-logo" onerror="this.style.display='none'">
                <h2><i class="fa-solid fa-circle-check me-2" style="color: #16a34a;"></i>Official Result Verified</h2>
                <p class="mb-0 text-muted">This document has been securely verified by the Department of Examination.</p>
            </div>
            
            <div class="card-body p-4 p-md-5">
                <h5 class="mb-4 text-center border-bottom pb-3">Sri Lanka Technology Campus (Pvt.) Ltd</h5>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Student Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($verification['student_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Registration Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($verification['register_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Degree Programme</div>
                        <div class="info-value"><?php echo htmlspecialchars($verification['degree_programme']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Semester</div>
                        <div class="info-value">Semester <?php echo (int)$verification['semester']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Result Published Date</div>
                        <div class="info-value"><?php echo htmlspecialchars($published_date); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Verification Code</div>
                        <div class="info-value text-success"><i class="fa-solid fa-lock me-1"></i><?php echo htmlspecialchars($verification_code); ?></div>
                    </div>
                </div>

                <h6 class="mb-3 fw-bold">Subject Results</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-custom">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Subject</th>
                                <th class="text-center" style="width: 15%;">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $res): ?>
                                <?php $gd = GradeHelper::getGradeData((float)$res['marks']); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($res['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($res['subject_name']); ?></td>
                                    <td class="text-center">
                                        <span class="grade-badge"><?php echo htmlspecialchars($gd['grade']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-end mt-4">
                    <div class="gpa-box">
                        <span>Final GPA</span>
                        <div class="gpa-value"><?php echo $count ? number_format($total_points / $count, 2) : '0.00'; ?></div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light text-center text-muted py-3" style="font-size: 13px;">
                Verified on <?php echo date('d M Y, h:i A'); ?>
            </div>

        <?php else: ?>
            <div class="header-invalid">
                <i class="fa-solid fa-triangle-exclamation mb-3" style="font-size: 64px;"></i>
                <h2>Invalid Verification Code</h2>
                <p class="mb-0">The code you scanned is either invalid, revoked, or has expired.</p>
            </div>
            <div class="card-body p-5 text-center">
                <p class="text-muted mb-4">Please ensure you have scanned the correct QR code or entered the correct verification link. If you believe this is an error, contact the Department of Examination.</p>
                <div class="p-3 bg-light rounded border text-danger fw-bold">
                    Code Checked: <?php echo htmlspecialchars($verification_code !== '' ? $verification_code : 'None Provided'); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
