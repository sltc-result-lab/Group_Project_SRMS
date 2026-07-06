<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';
include_once '../classes/GradeHelper.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT 
            s.id as student_id,
            s.name as student_name, 
            s.register_number,
            s.degree_programme,
            r.semester,
            r.exam_date,
            GROUP_CONCAT(
                CONCAT(sub.name, ' (', sub.code, ') CA: ', IFNULL(r.ca_mark, '-'), ', End: ', IFNULL(r.endterm_mark, '-'), ' | Final: ', r.marks) 
                ORDER BY sub.name 
                SEPARATOR '||'
            ) as subject_marks,
            r.published,
            r.publish_at
          FROM students s
          INNER JOIN results r ON s.id = r.student_id
          INNER JOIN subjects sub ON r.subject_id = sub.id
          GROUP BY s.id, r.semester, r.exam_date, r.publish_at
          ORDER BY r.semester ASC, r.exam_date DESC, s.name ASC";

$stmt = $db->prepare($query);
$stmt->execute();

$message = "";
if(isset($_GET['message'])) {
    if($_GET['message'] == 'published') {
        $message = '<div class="alert alert-success">Results published successfully!</div>';
    } else if($_GET['message'] == 'created') {
        $message = '<div class="alert alert-success">Result added successfully!</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Results List - Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        .subject-marks {
            margin-bottom: 5px;
            padding: 5px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../admin/dashboard.php">Admin Dashboard</a>
        <div class="navbar-nav ms-auto">
            <a href="../admin/logout.php" class="nav-link">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>Results List</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="create.php" class="btn btn-primary">Add New Results</a>
            <a href="../admin/dashboard.php" class="btn btn-dark ms-2">Back to Dashboard</a>
        </div>
    </div>

    <?php if($message) echo $message; ?>

    <div class="card">
        <div class="card-body">
            <?php if($stmt->rowCount() > 0): ?>
                <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php echo htmlspecialchars($row['student_name']); ?>
                                (Register No: <?php echo htmlspecialchars($row['register_number']); ?>,
                                Degree Programme: <?php echo htmlspecialchars($row['degree_programme']); ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Semester:</strong> Semester <?php echo (int)$row['semester']; ?></p>
                            <p><strong>Exam Date:</strong> <?php echo date('d M Y', strtotime($row['exam_date'])); ?></p>

<?php foreach(explode('||', $row['subject_marks']) as $mark_str): ?>
                                <?php
                                    $parts = explode(' | Final: ', $mark_str);
                                    if(count($parts) == 2) {
                                        $marks = (float)$parts[1];
                                        $gradeData = GradeHelper::getGradeData($marks);
                                        $display = htmlspecialchars($parts[0]) . ' <span class="badge bg-secondary ms-1">Final: ' . $marks . '</span> <strong class="ms-1 text-primary">[' . $gradeData['grade'] . ']</strong>';
                                    } else {
                                        $display = htmlspecialchars($mark_str);
                                    }
                                ?>
                                <div class="subject-marks"><?php echo $display; ?></div>
                            <?php endforeach; ?>

                            <div class="mt-2">
                                Status:
                                <?php 
                                    if ((int)$row['published'] === 1) {
                                        echo '<span class="badge bg-success">Published</span>';
                                    } elseif (!empty($row['publish_at'])) {
                                        echo '<span class="badge bg-info text-dark"><i class="fa-solid fa-clock me-1"></i> Scheduled for ' . date('d M Y, h:i A', strtotime($row['publish_at'])) . '</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">Pending</span>'; 
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">No results found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>