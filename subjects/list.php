<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Handle deletion logic
$message = "";
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $check_query = "SELECT id FROM results WHERE subject_id = :id LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":id", $_GET['id']);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        $message = '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Cannot delete subject as it is used in results.</div>';
    } else {
        $query = "DELETE FROM subjects WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $_GET['id']);

        if ($stmt->execute()) {
            $message = '<div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>Subject deleted successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Unable to delete subject.</div>';
        }
    }
}

// Fetch unique degree programmes for filter dropdown
$prog_query = "SELECT DISTINCT degree_programme FROM subjects ORDER BY degree_programme";
$prog_stmt = $db->prepare($prog_query);
$prog_stmt->execute();
$programmes = $prog_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle filter inputs
$degree_programme_filter = $_GET['degree_programme'] ?? '';
$semester_filter = $_GET['semester'] ?? '';

$conditions = [];
$params = [];

if ($degree_programme_filter !== '') {
    $conditions[] = "degree_programme = :degree_programme";
    $params[':degree_programme'] = $degree_programme_filter;
}

if ($semester_filter !== '') {
    $conditions[] = "semester = :semester";
    $params[':semester'] = (int)$semester_filter;
}

$where_clause = '';
if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// Order by semester, degree programme (category), and name
$query = "SELECT * FROM subjects $where_clause ORDER BY semester ASC, degree_programme ASC, name ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group subjects by semester
$subjects_by_semester = [];
foreach ($subjects as $subject) {
    $sem = (int)$subject['semester'];
    $subjects_by_semester[$sem][] = $subject;
}
ksort($subjects_by_semester);

if (isset($_GET['message'])) {
    if ($_GET['message'] == 'created') {
        $message = '<div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>Subject created successfully.</div>';
    } else if ($_GET['message'] == 'updated') {
        $message = '<div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>Subject updated successfully.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects List - Result Management System</title>
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

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            background-color: #f8fafc;
            color: var(--muted);
            border-bottom: 2px solid var(--border);
        }

        .btn-info {
            background-color: #eff6ff;
            color: var(--primary);
            border: 1px solid #bfdbfe;
            font-weight: 600;
        }

        .btn-info:hover {
            background-color: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .btn-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            font-weight: 600;
        }

        .btn-danger:hover {
            background-color: #dc2626;
            color: #fff;
            border-color: #dc2626;
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
            <h2 class="mb-0 fw-bold"><i class="fa-solid fa-book-open text-primary me-2"></i>Subjects List</h2>
        </div>
        <div class="col-md-6 col-sm-12 text-md-end mt-2 mt-md-0">
            <a href="create.php" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 600;">
                <i class="fa-solid fa-plus me-2"></i>Add New Subject
            </a>
            <a href="../admin/dashboard.php" class="btn btn-dark ms-2 px-4 py-2" style="border-radius: 10px; font-weight: 600;">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($message) echo $message; ?>

    <!-- Filters Section -->
    <div class="filter-card">
        <form method="get" action="list.php">
            <div class="row g-3">
                <!-- Degree Programme Filter -->
                <div class="col-md-6 col-sm-12">
                    <label class="form-label fw-bold text-muted small">Degree Programme (Subject Category)</label>
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
                <div class="col-md-6 col-sm-12">
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

    <!-- Subjects Grouped by Semester Accordion -->
    <?php if (!empty($subjects_by_semester)): ?>
        <div class="accordion" id="subjectsAccordion">
            <?php foreach ($subjects_by_semester as $sem_num => $sem_subjects): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSem<?php echo $sem_num; ?>">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSem<?php echo $sem_num; ?>" aria-expanded="true" aria-controls="collapseSem<?php echo $sem_num; ?>">
                            <i class="fa-solid fa-graduation-cap me-2 text-primary"></i>
                            Semester <?php echo $sem_num; ?>
                            <span class="badge bg-secondary ms-3" style="font-size: 12px; font-weight: 500; border-radius: 6px;">
                                <?php echo count($sem_subjects); ?> Subject(s)
                            </span>
                        </button>
                    </h2>
                    <div id="collapseSem<?php echo $sem_num; ?>" class="accordion-collapse collapse show" aria-labelledby="headingSem<?php echo $sem_num; ?>">
                        <div class="accordion-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Subject Name</th>
                                            <th>Code</th>
                                            <th>Degree Programme</th>
                                            <th class="text-center" style="width: 200px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sem_subjects as $subject): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($subject['name']); ?></td>
                                                <td><code><?php echo htmlspecialchars($subject['code']); ?></code></td>
                                                <td><span class="badge bg-light text-dark border px-2 py-1"><?php echo htmlspecialchars($subject['degree_programme']); ?></span></td>
                                                <td class="text-center">
                                                    <a href="edit.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-info px-3">
                                                        <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                                    </a>
                                                    <a href="list.php?delete=true&id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-danger px-3 ms-1" onclick="return confirm('Are you sure you want to delete this subject?')">
                                                        <i class="fa-solid fa-trash-can me-1"></i>Delete
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info py-3" style="border-radius: 12px;">
            <i class="fa-solid fa-circle-info me-2"></i>No subjects found matching the selected filters.
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>