<?php
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$roll = $_GET['roll'] ?? '';
$code = $_GET['code'] ?? '';

$is_valid = false;
$student = null;
$latest_exam_date = null;
$expected_code = '';

if (!empty($roll) && !empty($code)) {
    $student_query = "SELECT id, name, degree_programme, register_number FROM students WHERE register_number = :roll LIMIT 1";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(":roll", $roll);
    $student_stmt->execute();
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $exam_query = "SELECT MAX(exam_date) AS latest_exam_date
                       FROM results
                       WHERE student_id = :student_id
                         AND published = 1";
        $exam_stmt = $db->prepare($exam_query);
        $exam_stmt->bindParam(":student_id", $student['id']);
        $exam_stmt->execute();
        $exam_row = $exam_stmt->fetch(PDO::FETCH_ASSOC);
        $latest_exam_date = $exam_row['latest_exam_date'] ?? null;

        if ($latest_exam_date) {
            $expected_code = strtoupper(substr(hash('sha256', $student['id'] . '|' . $student['register_number'] . '|' . $latest_exam_date), 0, 12));
            if ($expected_code === strtoupper($code)) {
                $is_valid = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f7fb;">
<div class="container py-5">
    <div class="card shadow-sm border-0" style="max-width:700px;margin:auto;border-radius:18px;">
        <div class="card-body p-4">
            <h2 class="mb-4">Result Verification Portal</h2>

            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Register Number</label>
                    <input type="text" name="roll" class="form-control" value="<?php echo htmlspecialchars($roll); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Verification Code</label>
                    <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($code); ?>" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Verify Result</button>
                </div>
            </form>

            <?php if (!empty($roll) && !empty($code)): ?>
                <?php if ($is_valid): ?>
                    <div class="alert alert-success">
                        <strong>Valid Result Record</strong><br>
                        Student Name: <?php echo htmlspecialchars($student['name']); ?><br>
                        Register Number: <?php echo htmlspecialchars($student['register_number']); ?><br>
                        Degree Programme: <?php echo htmlspecialchars($student['degree_programme']); ?><br>
                        Latest Published Exam: <?php echo htmlspecialchars(date('d M Y', strtotime($latest_exam_date))); ?><br>
                        Verification Code: <?php echo htmlspecialchars($expected_code); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        Invalid verification details. Please check the register number and code.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>