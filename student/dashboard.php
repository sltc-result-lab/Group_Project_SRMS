<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: ../index.php?login=student");
    exit;
}

include_once '../config/database.php';
include_once '../classes/GradeHelper.php';

$database = new Database();
$db = $database->getConnection();

$student_id    = $_SESSION['student_id'];
$student_name  = $_SESSION['student_name'];
$student_degree_programme = $_SESSION['student_degree_programme'];
$student_register = $_SESSION['student_register'];

$selected_semester_raw = isset($_GET['semester']) ? $_GET['semester'] : '1';
$view_type = isset($_GET['view']) ? $_GET['view'] : 'results';
$is_all_semesters = ($selected_semester_raw === 'all');
$selected_semester = 1;
if (!$is_all_semesters) {
    $selected_semester = (int)$selected_semester_raw;
    if($selected_semester < 1 || $selected_semester > 6) {
        $selected_semester = 1;
    }
}

/* GET ALL RESULTS FOR GPA PROGRESS AND OVERALL METRICS */
$all_results_query = "SELECT r.*, s.name AS subject_name, s.code AS subject_code
                      FROM results r
                      INNER JOIN subjects s ON r.subject_id = s.id
                      WHERE r.student_id = :student_id
                        AND r.published = 1
                      ORDER BY r.semester ASC, r.exam_date DESC";
$stmt_all = $db->prepare($all_results_query);
$stmt_all->bindParam(":student_id", $student_id);
$stmt_all->execute();
$all_results = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

$all_organized = [];
foreach ($all_results as $res) {
    $sem = $res['semester'];
    $date = $res['exam_date'];
    $sub = $res['subject_id'];
    if (!isset($all_organized[$sem])) $all_organized[$sem] = [];
    if (!isset($all_organized[$sem][$date])) $all_organized[$sem][$date] = [];
    $all_organized[$sem][$date][$sub] = $res;
}

$trend_labels = [];
$trend_gpas = [];
$trend_averages = [];
$previous_gpa = null;
$current_gpa = null;

$overall_total_marks = 0;
$overall_total_points = 0;
$overall_subject_count = 0;
$overall_pass_count = 0;
$overall_fail_count = 0;
$overall_highest_mark = -1;
$overall_strongest_subject = 'N/A';
$overall_lowest_mark = 101;
$overall_weakest_subject = 'N/A';

$overall_grade_counts = [
    'A+' => 0, 'A' => 0, 'A-' => 0,
    'B+' => 0, 'B' => 0, 'B-' => 0,
    'C+' => 0, 'C' => 0, 'C-' => 0,
    'D+' => 0, 'D' => 0, 'E' => 0
];

$best_semester = 'N/A';
$highest_sem_gpa = -1;
$weakest_semester = 'N/A';
$lowest_sem_gpa = 5;

for ($sem = 1; $sem <= 6; $sem++) {
    if (isset($all_organized[$sem]) && !empty($all_organized[$sem])) {
        krsort($all_organized[$sem]); 
        $latest_date = array_key_first($all_organized[$sem]);
        $sem_latest_results = $all_organized[$sem][$latest_date];
        
        $sem_pts = 0;
        $sem_marks = 0;
        $sem_count = 0;
        foreach ($sem_latest_results as $r) {
            $m = (float)$r['marks'];
            $gd = GradeHelper::getGradeData($m);
            $sem_pts += $gd['point'];
            $sem_marks += $m;
            $sem_count++;
            
            // Overall aggregations
            $overall_total_marks += $m;
            $overall_total_points += $gd['point'];
            $overall_subject_count++;
            if ($m >= 50) $overall_pass_count++;
            else $overall_fail_count++;
            
            if (isset($overall_grade_counts[$gd['grade']])) {
                $overall_grade_counts[$gd['grade']]++;
            }
            if ($m > $overall_highest_mark) {
                $overall_highest_mark = $m;
                $overall_strongest_subject = $r['subject_name'];
            }
            if ($m < $overall_lowest_mark) {
                $overall_lowest_mark = $m;
                $overall_weakest_subject = $r['subject_name'];
            }
        }
        $s_gpa = $sem_count > 0 ? round($sem_pts / $sem_count, 2) : 0;
        $s_avg = $sem_count > 0 ? round($sem_marks / $sem_count, 2) : 0;
        $trend_gpas[] = $s_gpa;
        $trend_averages[] = $s_avg;
        $trend_labels[] = "Semester " . $sem;
        
        if ($s_gpa > $highest_sem_gpa) {
            $highest_sem_gpa = $s_gpa;
            $best_semester = "Semester " . $sem;
        }
        if ($s_gpa < $lowest_sem_gpa) {
            $lowest_sem_gpa = $s_gpa;
            $weakest_semester = "Semester " . $sem;
        }
        
        if (!$is_all_semesters) {
            if ($sem < $selected_semester) {
                $previous_gpa = $s_gpa;
            }
            if ($sem == $selected_semester) {
                $current_gpa = $s_gpa;
            }
        }
    }
}

$overall_gpa = $overall_subject_count > 0 ? $overall_total_points / $overall_subject_count : 0;
$overall_average = $overall_subject_count > 0 ? $overall_total_marks / $overall_subject_count : 0;

/* EXISTING SPECIFIC SEMESTER LOGIC */
$subjects = [];
$latest_exam_date = null;
$latest_exam_results = [];
$total_marks = 0;
$subject_count = 0;
$pass_count = 0;
$fail_count = 0;
$total_grade_points = 0;
$chart_labels = [];
$chart_marks  = [];
$strongest_subject = 'N/A';
$lowest_subject = 'N/A';
$highest_mark = -1;
$lowest_mark = 101;
$grade_counts = [
    'A+' => 0, 'A' => 0, 'A-' => 0,
    'B+' => 0, 'B' => 0, 'B-' => 0,
    'C+' => 0, 'C' => 0, 'C-' => 0,
    'D+' => 0, 'D' => 0, 'E' => 0
];
$average = 0;
$gpa = 0;
$gpa_trend_msg = "stable";

if (!$is_all_semesters) {
    $subject_query = "SELECT * FROM subjects 
                      WHERE degree_programme = :degree_programme AND semester = :semester 
                      ORDER BY name";
    $subject_stmt = $db->prepare($subject_query);
    $subject_stmt->bindParam(":degree_programme", $student_degree_programme);
    $subject_stmt->bindParam(":semester", $selected_semester, PDO::PARAM_INT);
    $subject_stmt->execute();
    $subjects = $subject_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($all_organized[$selected_semester]) && !empty($all_organized[$selected_semester])) {
        $latest_exam_date = array_key_first($all_organized[$selected_semester]);
        $latest_exam_results = $all_organized[$selected_semester][$latest_exam_date];
    }

    foreach ($subjects as $subject) {
        if (isset($latest_exam_results[$subject['id']])) {
            $r = $latest_exam_results[$subject['id']];
            $marks = (float)$r['marks'];
            $gradeData = GradeHelper::getGradeData($marks);

            if (isset($grade_counts[$gradeData['grade']])) {
                $grade_counts[$gradeData['grade']]++;
            }

            if ($marks > $highest_mark) {
                $highest_mark = $marks;
                $strongest_subject = $subject['name'];
            }
            if ($marks < $lowest_mark) {
                $lowest_mark = $marks;
                $lowest_subject = $subject['name'];
            }

            $total_marks += $marks;
            $subject_count++;
            $total_grade_points += $gradeData['point'];

            if ($marks >= 50) $pass_count++;
            else $fail_count++;

            $chart_labels[] = $subject['name'];
            $chart_marks[]  = $marks;
        }
    }

    $average = $subject_count > 0 ? $total_marks / $subject_count : 0;
    $gpa     = $subject_count > 0 ? $total_grade_points / $subject_count : 0;

    if ($previous_gpa !== null && $current_gpa !== null) {
        if ($current_gpa > $previous_gpa) {
            $gpa_trend_msg = "improving";
        } elseif ($current_gpa < $previous_gpa) {
            $gpa_trend_msg = "needs attention";
        }
    } elseif ($current_gpa === null) {
        $gpa_trend_msg = "N/A";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - Semester Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body{background:#f4f7fb;font-family:"Segoe UI", Arial, sans-serif;}
        .topbar{background:linear-gradient(90deg,#17212f,#26374f);}
        .hero-box{background:linear-gradient(135deg,#263c96,#3558da);color:#fff;border-radius:16px;padding:28px;box-shadow:0 8px 24px rgba(0,0,0,0.12);}
        .card-clean{border:none;border-radius:16px;box-shadow:0 8px 24px rgba(26,39,68,0.08);}
        .section-title{background:#246df0;color:#fff;border-radius:16px 16px 0 0;padding:16px 20px;font-size:1.2rem;font-weight:600;}
        .stat-card{border:none;border-radius:14px;box-shadow:0 6px 18px rgba(0,0,0,0.06);}
        .grade{font-weight:bold;padding:7px 12px;border-radius:8px;display:inline-block;min-width:48px;text-align:center;color:#fff;}
        .grade-ap{background:#16a34a;}
        .grade-a{background:#4caf50;}
        .grade-b{background:#1d9bf0;}
        .grade-c{background:#f4c430;color:#222;}
        .grade-d{background:#ff7a00;}
        .grade-f{background:#e53935;}
        .btn-export {
            background: #ffffff;
            color: #263c96;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
            color: #1a2a6c;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark topbar">
    <div class="container">
        <a class="navbar-brand" href="#"><i class="fa fa-graduation-cap"></i> Student Dashboard</a>
        <div class="navbar-nav ms-auto">
            <span class="nav-link text-white">
                <i class="fa fa-user"></i>
                <?php echo htmlspecialchars($student_name); ?> (<?php echo htmlspecialchars($student_register); ?>)
            </span>
            <a href="logout.php" class="nav-link text-white"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <div class="hero-box mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="mb-2">Semester-wise Student Result Dashboard</h2>
                <p class="mb-3">Select a semester to view your published results and performance analytics.</p>

                <form method="get" class="row g-2 align-items-center">
                    <div class="col-sm-6 col-md-4">
                        <select name="semester" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $is_all_semesters ? 'selected' : ''; ?>>All Semesters</option>
                            <?php for($i=1; $i<=6; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (!$is_all_semesters && $selected_semester === $i) ? 'selected' : ''; ?>>
                                    Semester <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <select name="view" class="form-select" onchange="this.form.submit()">
                            <option value="results" <?php echo ($view_type === 'results') ? 'selected' : ''; ?>>Results View</option>
                            <option value="analytics" <?php echo ($view_type === 'analytics') ? 'selected' : ''; ?>>Analytics View</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <?php if (!$is_all_semesters && $latest_exam_date): ?>
                    <a href="generate_pdf.php?semester=<?php echo $selected_semester; ?>&exam_date=<?php echo urlencode($latest_exam_date); ?>" class="btn-export">
                        <i class="fa fa-download"></i> Export Semester PDF
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card card-clean mb-4">
        <div class="section-title">Student Information</div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-3"><strong>Name:</strong> <?php echo htmlspecialchars($student_name); ?></div>
                <div class="col-md-3"><strong>Register Number:</strong> <?php echo htmlspecialchars($student_register); ?></div>
                <div class="col-md-3"><strong>Degree Programme:</strong> <?php echo htmlspecialchars($student_degree_programme); ?></div>
                <div class="col-md-3"><strong>Selected:</strong> <?php echo $is_all_semesters ? 'All Semesters' : 'Semester ' . $selected_semester; ?></div>
            </div>
        </div>
    </div>

    <?php if ($is_all_semesters): ?>
        <?php if ($overall_subject_count > 0): ?>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card p-3">
                        <h6 class="text-muted">Total Subjects</h6>
                        <h3><?php echo $overall_subject_count; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card p-3">
                        <h6 class="text-muted">Overall Average</h6>
                        <h3><?php echo number_format($overall_average, 2); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card p-3">
                        <h6 class="text-muted">Overall GPA</h6>
                        <h3><?php echo number_format($overall_gpa, 2); ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card p-3">
                        <h6 class="text-muted">Passed / Failed</h6>
                        <h3><span class="text-success"><?php echo $overall_pass_count; ?></span> / <span class="text-danger"><?php echo $overall_fail_count; ?></span></h3>
                    </div>
                </div>
            </div>

            <?php if ($view_type === 'analytics'): ?>
                <?php include 'analytics_partial.php'; ?>
            <?php else: ?>
                <div class="alert alert-info mt-4">
                    <i class="fa fa-info-circle"></i> Please switch to <strong>Analytics View</strong> to see your overall performance charts and insights, or select a specific semester to view detailed subject results.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info">
                No published results found for any semester.
            </div>
        <?php endif; ?>

    <?php else: ?>
        <?php if ($latest_exam_date): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <h6 class="text-muted">Subjects</h6>
                    <h3><?php echo $subject_count; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <h6 class="text-muted">Average</h6>
                    <h3><?php echo number_format($average, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <h6 class="text-muted">GPA</h6>
                    <h3><?php echo number_format($gpa, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <h6 class="text-muted">Pass Count</h6>
                    <h3><?php echo $pass_count; ?></h3>
                </div>
            </div>
        </div>

        <?php if ($view_type === 'results'): ?>
            <div class="card card-clean mb-4">
                <div class="section-title">Semester Results</div>
                <div class="card-body p-4">
                    <p><strong>Latest Exam Date:</strong> <?php echo date('d M Y', strtotime($latest_exam_date)); ?></p>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Code</th>
                                    <th class="text-center">CA Mark</th>

                                    <th class="text-center">Endterm</th>
                                    <th class="text-center">Final Mark</th>
                                    <th class="text-center">Grade</th>
                                    <th>Status</th>
                                    <th class="text-center">Grade Point</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <?php if (isset($latest_exam_results[$subject['id']])): ?>
                                    <?php
                                        $result = $latest_exam_results[$subject['id']];
                                        $marks = (float)$result['marks'];
                                        $gradeData = GradeHelper::getGradeData($marks);
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['code']); ?></td>
                                        <td class="text-center text-muted"><?php echo $result['ca_mark'] !== null ? htmlspecialchars($result['ca_mark']) : '-'; ?></td>

                                        <td class="text-center text-muted"><?php echo $result['endterm_mark'] !== null ? htmlspecialchars($result['endterm_mark']) : '-'; ?></td>
                                        <td class="text-center fw-bold text-primary"><?php echo htmlspecialchars($marks); ?></td>
                                        <td class="text-center">
                                            <span class="grade <?php echo $gradeData['class']; ?>">
                                                <?php echo $gradeData['grade']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($marks >= 50): ?>
                                                <span class="text-success"><i class="fa fa-check-circle"></i> Pass</span>
                                            <?php else: ?>
                                                <span class="text-danger"><i class="fa fa-times-circle"></i> Fail</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo number_format($gradeData['point'], 1); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($view_type === 'analytics'): ?>
            <?php include 'analytics_partial.php'; ?>
        <?php endif; ?>


    <?php else: ?>
        <div class="alert alert-info">
            No published results found for Semester <?php echo $selected_semester; ?>.
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
<?php if ($is_all_semesters && $overall_subject_count > 0): ?>
const allMarksCtx = document.getElementById('allMarksChart').getContext('2d');
new Chart(allMarksCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($trend_labels); ?>,
        datasets: [{
            label: 'Average Marks',
            data: <?php echo json_encode($trend_averages); ?>,
            backgroundColor: 'rgba(36, 109, 240, 0.6)',
            borderColor: 'rgba(36, 109, 240, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});

const allGpaCtx = document.getElementById('allGpaChart').getContext('2d');
new Chart(allGpaCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($trend_labels); ?>,
        datasets: [
            {
                type: 'line',
                label: 'GPA Trend',
                data: <?php echo json_encode($trend_gpas); ?>,
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                borderColor: '#f59e0b',
                backgroundColor: '#ffffff',
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#f59e0b',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                type: 'bar',
                label: 'GPA',
                data: <?php echo json_encode($trend_gpas); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.9)',
                borderRadius: 4,
                barPercentage: 0.6
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 4
            }
        }
    }
});
<?php elseif (!$is_all_semesters && $latest_exam_date): ?>
const marksCtx = document.getElementById('marksChart').getContext('2d');
new Chart(marksCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Marks',
            data: <?php echo json_encode($chart_marks); ?>,
            backgroundColor: 'rgba(36, 109, 240, 0.6)',
            borderColor: 'rgba(36, 109, 240, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});

const gpaCtx = document.getElementById('gpaChart').getContext('2d');
new Chart(gpaCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($trend_labels); ?>,
        datasets: [{
            label: 'GPA',
            data: <?php echo json_encode($trend_gpas); ?>,
            fill: false,
            tension: 0.3,
            borderWidth: 2,
            backgroundColor: 'rgba(24, 169, 87, 0.3)',
            borderColor: 'rgba(24, 169, 87, 1)'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 4
            }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>