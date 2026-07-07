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

// Get unique degree programmes for filter dropdown
$prog_query = "SELECT DISTINCT degree_programme FROM students ORDER BY degree_programme";
$prog_stmt = $db->prepare($prog_query);
$prog_stmt->execute();
$programmes = $prog_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle filter inputs
$degree_programme_filter = $_GET['degree_programme'] ?? '';
$semester_filter = $_GET['semester'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_filter = trim($_GET['search'] ?? '');

$conditions = [];
$params = [];

if ($degree_programme_filter !== '') {
    $conditions[] = "s.degree_programme = :degree_programme";
    $params[':degree_programme'] = $degree_programme_filter;
}

if ($semester_filter !== '') {
    $conditions[] = "r.semester = :semester";
    $params[':semester'] = (int)$semester_filter;
}

if ($status_filter !== '') {
    if ($status_filter === 'published') {
        $conditions[] = "r.published = 1";
    } elseif ($status_filter === 'pending') {
        $conditions[] = "r.published = 0";
    }
}

if ($search_filter !== '') {
    $conditions[] = "(s.name LIKE :search OR s.register_number LIKE :search)";
    $params[':search'] = '%' . $search_filter . '%';
}

$where_clause = '';
if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// Sorted: semester ascending, register number ascending, and subject name inside GROUP_CONCAT is also sorted ascending
$query = "SELECT 
            s.id as student_id,
            s.name as student_name, 
            s.register_number,
            s.degree_programme,
            r.semester,
            r.exam_date,
            GROUP_CONCAT(
                CONCAT(sub.name, ' (', sub.code, ') CA: ', IFNULL(r.ca_mark, '-'), ', End: ', IFNULL(r.endterm_mark, '-'), ' | Final: ', r.marks) 
                ORDER BY sub.name ASC 
                SEPARATOR '||'
            ) as subject_marks,
            r.published,
            r.publish_at
          FROM students s
          INNER JOIN results r ON s.id = r.student_id
          INNER JOIN subjects sub ON r.subject_id = sub.id
          $where_clause
          GROUP BY s.id, r.semester, r.exam_date, r.publish_at
          ORDER BY r.semester ASC, s.register_number ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);

// Group results by semester
$results_by_semester = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sem = (int)$row['semester'];
    $results_by_semester[$sem][] = $row;
}
ksort($results_by_semester);

$message = "";
if(isset($_GET['message'])) {
    if($_GET['message'] == 'published') {
        $message = '<div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>Results published successfully!</div>';
    } else if($_GET['message'] == 'created') {
        $message = '<div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>Result added successfully!</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results List - Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #0f172a;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --info: #0891b2;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .filter-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
            background: #fff;
            margin-bottom: 24px;
            padding: 24px;
            border: 1px solid var(--border);
        }

        .student-result-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.01);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }

        .student-result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
        }

        .student-result-card .card-header {
            background-color: #f8fafc;
            border-bottom: 1px solid var(--border);
            padding: 14px 20px;
        }

        .student-result-card .card-body {
            padding: 20px;
        }

        .subject-marks {
            margin-bottom: 8px;
            padding: 12px 18px;
            background-color: #f8fafc;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary);
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
        }

        .subject-marks-details {
            font-weight: 500;
            font-size: 14px;
        }

        .subject-marks-final {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-published {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .badge-pending {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .badge-scheduled {
            background-color: #fef9c3;
            color: #a16207;
            border: 1px solid #fef08a;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .accordion-item {
            border: none;
            margin-bottom: 18px;
            border-radius: 16px !important;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .accordion-button {
            font-weight: 700;
            color: var(--secondary);
            background-color: #fff;
            padding: 18px 24px;
            font-size: 16px;
        }

        .accordion-button:not(.collapsed) {
            background-color: #eff6ff;
            color: var(--primary-dark);
            box-shadow: none;
        }

        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(37, 99, 235, 0.2);
        }

        .accordion-body {
            padding: 24px;
            background-color: #fff;
        }

        .form-label {
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../admin/dashboard.php">
            <i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Result Portal
        </a>
        <div class="navbar-nav ms-auto">
            <a href="../admin/logout.php" class="nav-link">
                <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <!-- Page Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6 col-sm-12">
            <h2 class="mb-0 fw-bold"><i class="fa-solid fa-table-list text-primary me-2"></i>Results List</h2>
        </div>
        <div class="col-md-6 col-sm-12 text-md-end mt-2 mt-md-0">
            <a href="create.php" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 600;">
                <i class="fa-solid fa-plus me-2"></i>Add New Results
            </a>
            <a href="../admin/dashboard.php" class="btn btn-dark ms-2 px-4 py-2" style="border-radius: 10px; font-weight: 600;">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <?php if($message) echo $message; ?>

    <!-- Filters Section -->
    <div class="filter-card">
        <form method="get" action="list.php">
            <div class="row g-3">
                <!-- Degree Programme Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold text-muted small">Degree Programme</label>
                    <select name="degree_programme" class="form-select" style="border-radius: 8px;">
                        <option value="">All Degree Programmes</option>
                        <?php foreach ($programmes as $prog): ?>
                            <option value="<?php echo htmlspecialchars($prog); ?>" <?php echo $degree_programme_filter === $prog ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prog); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Semester Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold text-muted small">Semester</label>
                    <select name="semester" class="form-select" style="border-radius: 8px;">
                        <option value="">All Semesters</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $semester_filter == $i ? 'selected' : ''; ?>>
                                Semester <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Publication Status Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold text-muted small">Publication Status</label>
                    <select name="status" class="form-select" style="border-radius: 8px;">
                        <option value="">All Statuses</option>
                        <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <!-- Search Filter -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold text-muted small">Search Student</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or reg no..." style="border-radius: 8px;" value="<?php echo htmlspecialchars($search_filter); ?>">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary px-4" style="border-radius: 8px; font-weight: 600;">
                        <i class="fa-solid fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary ms-2 px-4" style="border-radius: 8px; font-weight: 600;">
                        <i class="fa-solid fa-arrow-rotate-left me-2"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Grouped by Semester Accordion -->
    <?php if(!empty($results_by_semester)): ?>
        <div class="accordion" id="semesterAccordion">
            <?php foreach ($results_by_semester as $sem_num => $sem_results): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSem<?php echo $sem_num; ?>">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSem<?php echo $sem_num; ?>" aria-expanded="true" aria-controls="collapseSem<?php echo $sem_num; ?>">
                            <i class="fa-solid fa-graduation-cap me-2 text-primary"></i>
                            Semester <?php echo $sem_num; ?>
                            <span class="badge bg-secondary ms-3" style="font-size: 12px; font-weight: 500; border-radius: 6px;">
                                <?php echo count($sem_results); ?> Record(s)
                            </span>
                        </button>
                    </h2>
                    <div id="collapseSem<?php echo $sem_num; ?>" class="accordion-collapse collapse show" aria-labelledby="headingSem<?php echo $sem_num; ?>">
                        <div class="accordion-body">
                            <!-- Loop through student results inside this semester -->
                            <?php foreach ($sem_results as $row): ?>
                                <div class="card student-result-card">
                                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap g-2">
                                        <h5 class="mb-0 fw-bold text-dark" style="font-size: 16px;">
                                            <i class="fa-solid fa-user-graduate text-muted me-2"></i><?php echo htmlspecialchars($row['student_name']); ?>
                                        </h5>
                                        <span class="text-muted fw-bold small">Reg No: <?php echo htmlspecialchars($row['register_number']); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3 small text-muted">
                                            <div class="col-md-6 col-sm-12">
                                                <strong>Degree Programme:</strong> <?php echo htmlspecialchars($row['degree_programme']); ?>
                                            </div>
                                            <div class="col-md-6 col-sm-12 text-md-end mt-1 mt-md-0">
                                                <strong>Exam Date:</strong> <?php echo date('d M Y', strtotime($row['exam_date'])); ?>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <h6 class="text-muted mb-2 small fw-bold"><i class="fa-solid fa-book me-1"></i>Subject Grades</h6>
                                            <?php foreach(explode('||', $row['subject_marks']) as $mark_str): ?>
                                                <?php
                                                    $parts = explode(' | Final: ', $mark_str);
                                                    if(count($parts) == 2) {
                                                        $marks = (float)$parts[1];
                                                        $gradeData = GradeHelper::getGradeData($marks);
                                                        
                                                        $display_details = htmlspecialchars($parts[0]);
                                                        $display = '
                                                            <div class="subject-marks">
                                                                <span class="subject-marks-details">' . $display_details . '</span>
                                                                <span class="subject-marks-final">
                                                                    <span class="badge bg-secondary">Final: ' . $marks . '</span>
                                                                    <span class="badge bg-primary">[' . htmlspecialchars($gradeData['grade']) . ']</span>
                                                                </span>
                                                            </div>';
                                                    } else {
                                                        $display = '<div class="subject-marks">' . htmlspecialchars($mark_str) . '</div>';
                                                    }
                                                    echo $display;
                                                ?>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                            <div class="small">
                                                <strong class="text-muted">Publication Status:</strong>
                                                <?php 
                                                    if ((int)$row['published'] === 1) {
                                                        echo '<span class="badge badge-published ms-2"><i class="fa-solid fa-circle-check me-1"></i>Published</span>';
                                                    } elseif (!empty($row['publish_at'])) {
                                                        echo '<span class="badge badge-scheduled ms-2"><i class="fa-solid fa-clock me-1"></i>Scheduled for ' . date('d M Y, h:i A', strtotime($row['publish_at'])) . '</span>';
                                                    } else {
                                                        echo '<span class="badge badge-pending ms-2"><i class="fa-solid fa-circle-exclamation me-1"></i>Pending</span>'; 
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info py-3" style="border-radius: 12px;">
            <i class="fa-solid fa-circle-info me-2"></i>No results found matching the selected filters.
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>