<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';
include_once '../includes/mailer.php';

$database = new Database();
$db = $database->getConnection();

$message = "";
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Get all unique classes
$class_query = "SELECT DISTINCT degree_programme FROM students ORDER BY degree_programme";
$class_stmt = $db->prepare($class_query);
$class_stmt->execute();
$classes = $class_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk') {
        $degree_programme = trim($_POST['degree_programme'] ?? '');
        $semester = (int) ($_POST['semester'] ?? 0);
        $exam_date = trim($_POST['exam_date'] ?? '');
        $publish_at = !empty($_POST['publish_at']) ? $_POST['publish_at'] : null;

        if ($degree_programme === '' || $semester < 1 || $semester > 6 || $exam_date === '') {
            $message = '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Please select class, semester and exam date.</div>';
        } else {
            if ($publish_at) {
                $query = "UPDATE results r
                          INNER JOIN students s ON r.student_id = s.id
                          SET r.publish_at = :publish_at, r.published = 0
                          WHERE s.degree_programme = :degree_programme
                            AND r.semester = :semester
                            AND r.exam_date = :exam_date
                            AND r.published = 0";
            } else {
                $select_query = "SELECT DISTINCT s.name, s.email, r.semester FROM results r
                                 INNER JOIN students s ON r.student_id = s.id
                                 WHERE s.degree_programme = :degree_programme
                                   AND r.semester = :semester
                                   AND r.exam_date = :exam_date
                                   AND r.published = 0";
                $select_stmt = $db->prepare($select_query);
                $select_stmt->bindParam(':degree_programme', $degree_programme);
                $select_stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
                $select_stmt->bindParam(':exam_date', $exam_date);
                $select_stmt->execute();
                $students_to_email = $select_stmt->fetchAll(PDO::FETCH_ASSOC);

                $query = "UPDATE results r
                          INNER JOIN students s ON r.student_id = s.id
                          SET r.published = 1, r.publish_at = NULL
                          WHERE s.degree_programme = :degree_programme
                            AND r.semester = :semester
                            AND r.exam_date = :exam_date
                            AND r.published = 0";
            }

            $stmt = $db->prepare($query);
            $stmt->bindParam(':degree_programme', $degree_programme);
            $stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
            $stmt->bindParam(':exam_date', $exam_date);
            if ($publish_at) {
                $stmt->bindParam(':publish_at', $publish_at);
            }

            if ($stmt->execute()) {
                $count = $stmt->rowCount();
                if ($count > 0) {
                    if (!$publish_at && isset($students_to_email)) {
                        sendBulkResultEmails($students_to_email);
                    }
                    $statusMsg = $publish_at ? "Successfully scheduled $count results for $publish_at." : "Successfully published $count results.";
                    $message = '<div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>' . $statusMsg . '</div>';
                } else {
                    $message = '<div class="alert alert-warning"><i class="fa-solid fa-circle-info me-2"></i>No unpublished results found matching the criteria.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Failed to publish bulk results.</div>';
            }
        }
    } elseif ($action === 'individual') {
        try {
            $db->beginTransaction();
            $publish_at = !empty($_POST['publish_at_indiv']) ? $_POST['publish_at_indiv'] : null;

            if (isset($_POST['results']) && is_array($_POST['results'])) {
                if ($publish_at) {
                    $update_query = "UPDATE results SET publish_at = :publish_at, published = 0 
                                     WHERE id = :result_id AND published = 0";
                } else {
                    $placeholders = str_repeat('?,', count($_POST['results']) - 1) . '?';
                    $select_query = "SELECT DISTINCT s.name, s.email, r.semester FROM results r 
                                     INNER JOIN students s ON r.student_id = s.id 
                                     WHERE r.id IN ($placeholders) AND r.published = 0";
                    $select_stmt = $db->prepare($select_query);
                    $select_stmt->execute($_POST['results']);
                    $students_to_email = $select_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $update_query = "UPDATE results SET published = 1, publish_at = NULL 
                                     WHERE id = :result_id AND published = 0";
                }
                $update_stmt = $db->prepare($update_query);

                $count = 0;
                foreach ($_POST['results'] as $result_id) {
                    $update_stmt->bindParam(":result_id", $result_id);
                    if ($publish_at) {
                        $update_stmt->bindParam(':publish_at', $publish_at);
                    }
                    if ($update_stmt->execute()) {
                        $count++;
                    }
                }
            }

            $db->commit();
            if (!$publish_at && !empty($students_to_email)) {
                sendBulkResultEmails($students_to_email);
            }
            $statusMsg = $publish_at ? "Successfully scheduled $count individual results for $publish_at." : "Successfully published $count individual results.";
            $message = '<div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>' . $statusMsg . '</div>';

        } catch (Exception $e) {
            $db->rollBack();
            $message = '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Failed to process individual results.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Results - Admin Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

    <!-- Reusing the dashboard admin template styles -->
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
            --purple: #7c3aed;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Base */
        .sidebar {
            width: 270px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            padding: 24px 16px;
            overflow-y: auto;
            box-shadow: 8px 0 30px rgba(2, 6, 23, 0.18);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 20px;
        }

        .brand-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
            flex-shrink: 0;
        }

        .brand h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .brand small {
            color: #cbd5e1;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 8px;
        }

        .sidebar-menu a {
            text-decoration: none;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            transition: all 0.25s ease;
            font-size: 15px;
            font-weight: 500;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(59, 130, 246, 0.18);
            color: #fff;
            transform: translateX(4px);
        }

        /* Main Content */
        .main-content {
            margin-left: 270px;
            width: calc(100% - 270px);
            min-height: 100vh;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }

        .topbar p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-pill {
            background: #eff6ff;
            color: var(--primary-dark);
            border: 1px solid #dbeafe;
            padding: 10px 16px;
            border-radius: 999px;
            font-weight: 600;
        }

        .logout-btn {
            background: #ef4444;
            border: none;
            color: #fff;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 600;
            transition: 0.2s;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: #fff;
        }

        .content {
            padding: 28px;
        }

        .panel-card {
            background: #fff;
            border: none;
            border-radius: 22px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }

        .panel-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 18px;
        }

        .panel-body {
            padding: 24px;
        }

        .hidden {
            display: none;
        }

        /* Custom Customizations */
        .nav-pills .nav-link {
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 600;
            color: var(--muted);
            margin-right: 10px;
            border: 1px solid transparent;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link.active {
            background-color: #eff6ff;
            color: var(--primary-dark);
            border-color: #bfdbfe;
        }

        .nav-pills .nav-link:hover:not(.active) {
            background-color: #f8fafc;
            color: var(--text);
            border-color: var(--border);
        }

        .subject-list {
            padding: 15px;
            background-color: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-top: 15px;
        }

        .custom-form-label {
            font-weight: 600;
            color: var(--text);
        }

        .custom-form-control {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 10px 15px;
        }

        .custom-form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        /* Style Select2 to match custom-form-control */
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 10px !important;
            border: 1px solid #cbd5e1 !important;
            min-height: 43px !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1) !important;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 15px !important;
            padding-right: 15px !important;
            color: var(--text) !important;
        }
        .select2-container--bootstrap-5 .select2-dropdown {
            border-radius: 10px !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08) !important;
            z-index: 1050 !important;
        }
        .select2-container--bootstrap-5 .select2-dropdown .select2-search .select2-search__field {
            border-radius: 8px !important;
            border: 1px solid #cbd5e1 !important;
        }
        .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option {
            padding: 8px 15px !important;
        }
    </style>
</head>

<body>

    <div class="app-wrapper">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <h4>Result Portal</h4>
                    <small>Admin Panel</small>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="../admin/dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                <li><a href="../students/list.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
                <li><a href="../subjects/list.php"><i class="fa-solid fa-book-open"></i> Subjects</a></li>
                <li><a href="create.php"><i class="fa-solid fa-file-circle-plus"></i> Add Results</a></li>
                <li><a href="list.php"><i class="fa-solid fa-table-list"></i> View Results</a></li>
                <li><a href="publish.php" class="active"><i class="fa-solid fa-bullhorn"></i> Publish Results</a></li>
                <li><a href="../admin/change-password.php"><i class="fa-solid fa-key"></i> Change Password</a></li>
                <li><a href="../admin/backup.php"><i class="fa-solid fa-database"></i> Backup Database</a></li>
                <li><a href="../admin/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <div>
                    <h2>Publish Results</h2>
                    <p>Easily publish student outcomes individually or in bulk.</p>
                </div>
                <div class="topbar-right">
                    <div class="admin-pill">
                        <i class="fa-solid fa-user-shield me-2"></i>
                        <?php echo htmlspecialchars($admin_username); ?>
                    </div>
                    <a href="../admin/logout.php" class="logout-btn">
                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout
                    </a>
                </div>
            </div>

            <div class="content">
                <?php if ($message)
                    echo $message; ?>

                <div class="panel-card">
                    <div class="panel-header d-flex align-items-center">
                        <h5><i class="fa-solid fa-share-from-square text-primary me-2"></i> Result Publishing Options
                        </h5>
                    </div>
                    <div class="panel-body">

                        <!-- Navigation Pills for Tabs -->
                        <ul class="nav nav-pills mb-4" id="publishTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="bulk-tab" data-bs-toggle="pill"
                                    data-bs-target="#bulk" type="button" role="tab" aria-controls="bulk"
                                    aria-selected="true">
                                    <i class="fa-solid fa-layer-group me-2"></i>Bulk Publish (Recommended)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="individual-tab" data-bs-toggle="pill"
                                    data-bs-target="#individual" type="button" role="tab" aria-controls="individual"
                                    aria-selected="false">
                                    <i class="fa-solid fa-user-pen me-2"></i>Individual Publish (Advanced)
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="publishTabsContent">

                            <!-- BULK PUBLISH TAB -->
                            <div class="tab-pane fade show active" id="bulk" role="tabpanel" aria-labelledby="bulk-tab">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="mb-4">
                                            <h6 class="text-muted"><i class="fa-solid fa-circle-info me-2"></i>Publish
                                                all unpublished results instantly for an entire class.</h6>
                                        </div>
                                        <form method="post" action="publish.php">
                                            <input type="hidden" name="action" value="bulk">

                                            <div class="mb-3">
                                                <label class="custom-form-label mb-2">Select Degree Programme</label>
                                                <select name="degree_programme" class="form-select custom-form-control" required>
                                                    <option value="">Select a Degree Programme...</option>
                                                    <?php foreach ($classes as $class): ?>
                                                        <option value="<?php echo htmlspecialchars($class); ?>">
                                                            <?php echo htmlspecialchars($class); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="custom-form-label mb-2">Select Semester</label>
                                                <select name="semester" class="form-select custom-form-control"
                                                    required>
                                                    <option value="">Select a Semester...</option>
                                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                                        <option value="<?php echo $i; ?>">Semester <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>

                                            <div class="mb-4">
                                                <label class="custom-form-label mb-2">Exam Date</label>
                                                <input type="date" name="exam_date"
                                                    class="form-control custom-form-control" required>
                                            </div>

                                            <div class="mb-4 p-3 rounded"
                                                style="background-color: #f1f5f9; border: 1px dashed #cbd5e1;">
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox"
                                                        id="scheduleToggleBulk"
                                                        style="width: 40px; height: 20px; margin-left: -2.5em; margin-right: 10px;">
                                                    <label class="form-check-label fw-bold"
                                                        for="scheduleToggleBulk">Schedule Publication (Optional)</label>
                                                </div>
                                                <div id="scheduleTimeContainerBulk" class="mt-2 text-muted"
                                                    style="display: none;">
                                                    <small class="d-block mb-1">Set the exact date and time for results
                                                        to go live automatically.</small>
                                                    <input type="datetime-local" name="publish_at"
                                                        class="form-control custom-form-control mt-1">
                                                </div>
                                            </div>

                                            <button type="submit" class="btn btn-success px-4 py-2"
                                                style="border-radius: 10px; font-weight: 600;">
                                                <i class="fa-solid fa-bolt me-2"></i>Publish All Results
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-lg-5 offset-lg-1 d-none d-lg-block">
                                        <div class="card bg-light border-0" style="border-radius: 16px;">
                                            <div class="card-body p-4 text-center">
                                                <div
                                                    style="width: 80px; height: 80px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px;">
                                                    <i class="fa-solid fa-rocket"></i>
                                                </div>
                                                <h4>Time Saver</h4>
                                                <p class="text-muted mb-0">No more checking individual student records.
                                                    Enter the batch parameters and let the system verify and publish the
                                                    entire classroom's outcomes seamlessly.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- INDIVIDUAL PUBLISH TAB -->
                            <div class="tab-pane fade" id="individual" role="tabpanel" aria-labelledby="individual-tab">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h6 class="text-muted"><i class="fa-solid fa-circle-info me-2"></i>Hand-pick
                                            specific subjects for a particular student.</h6>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="class_select" class="custom-form-label mb-2">1. Select Degree Programme</label>
                                        <select class="form-select custom-form-control" id="class_select">
                                            <option value="">Choose Degree Programme...</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo htmlspecialchars($class); ?>">
                                                    <?php echo htmlspecialchars($class); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="student_select" class="custom-form-label mb-2">2. Select
                                            Student</label>
                                        <select class="form-select custom-form-control" id="student_select" disabled>
                                            <option value="">First Select Degree Programme</option>
                                        </select>
                                    </div>
                                </div>

                                <form method="post" action="publish.php" id="publishForm">
                                    <input type="hidden" name="action" value="individual">

                                    <div id="results_container" class="mt-2 mb-4">
                                        <!-- AJAX Results will load here -->
                                    </div>

                                    <div class="mb-4 p-3 rounded hidden" id="scheduleContainerIndiv"
                                        style="background-color: #f1f5f9; border: 1px dashed #cbd5e1;">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="scheduleToggleIndiv"
                                                style="width: 40px; height: 20px; margin-left: -2.5em; margin-right: 10px;">
                                            <label class="form-check-label fw-bold" for="scheduleToggleIndiv">Schedule
                                                Publication (Optional)</label>
                                        </div>
                                        <div id="scheduleTimeContainerIndiv" class="mt-2 text-muted"
                                            style="display: none;">
                                            <small class="d-block mb-1">Set the exact date and time for results to go
                                                live automatically.</small>
                                            <input type="datetime-local" name="publish_at_indiv"
                                                class="form-control custom-form-control mt-1">
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary px-4 py-2 hidden" id="publishButton"
                                        style="border-radius: 10px; font-weight: 600;">
                                        <i class="fa-solid fa-check-double me-2"></i> Publish Selected Subjects
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2 on student select dropdown
            $('#student_select').select2({
                theme: 'bootstrap-5',
                placeholder: 'First Select Degree Programme',
                width: '100%'
            });

            // Handle changes in degree programme selection
            $('#class_select').on('change', function () {
                const classValue = this.value;
                const $studentSelect = $('#student_select');
                const resultsContainer = document.getElementById('results_container');

                if (classValue) {
                    // Load students for selected class
                    fetch('get_students_by_degree_programme.php?degree_programme=' + encodeURIComponent(classValue))
                        .then(response => response.json())
                        .then(data => {
                            $studentSelect.empty().append('<option value="">Select Student...</option>');
                            data.forEach(student => {
                                // Formatting students: Student Name (Reg No: register_number)
                                $studentSelect.append(`
                                    <option value="${student.id}">
                                        ${student.name} (Reg No: ${student.register_number})
                                    </option>
                                `);
                            });
                            $studentSelect.prop('disabled', false);
                            
                            // Re-initialize select2 with the new placeholder
                            $studentSelect.select2({
                                theme: 'bootstrap-5',
                                placeholder: 'Select Student...',
                                width: '100%'
                            });
                            
                            // Clear previous selection and trigger change to clear unpublished results table
                            $studentSelect.val('').trigger('change');
                        });
                } else {
                    $studentSelect.empty().append('<option value="">First Select Degree Programme</option>');
                    $studentSelect.prop('disabled', true);
                    $studentSelect.select2({
                        theme: 'bootstrap-5',
                        placeholder: 'First Select Degree Programme',
                        width: '100%'
                    });
                    $studentSelect.trigger('change');
                    resultsContainer.innerHTML = '';
                    document.getElementById('publishButton').classList.add('hidden');
                }
            });

            // Handle changes in student selection
            $('#student_select').on('change', function () {
                const studentId = this.value;
                const resultsContainer = document.getElementById('results_container');

                if (studentId) {
                    // Load unpublished results for selected student
                    fetch('get_unpublished_results.php?student_id=' + studentId)
                        .then(response => response.text())
                        .then(data => {
                            resultsContainer.innerHTML = data;
                            updatePublishButton();

                            // Add modern styling hints to checkboxes loaded via AJAX
                            const checkboxes = resultsContainer.querySelectorAll('input[type="checkbox"]');
                            checkboxes.forEach(cb => {
                                cb.classList.add('form-check-input');
                                cb.style.width = '20px';
                                cb.style.height = '20px';
                                cb.style.marginRight = '10px';
                                cb.addEventListener('change', updatePublishButton);
                            });
                        });
                } else {
                    resultsContainer.innerHTML = '';
                    document.getElementById('publishButton').classList.add('hidden');
                    document.getElementById('scheduleContainerIndiv').classList.add('hidden');
                }
            });
        });

        function updatePublishButton() {
            const anyChecked = document.querySelector('input[name="results[]"]:checked');
            const publishButton = document.getElementById('publishButton');
            const scheduleContainerIndiv = document.getElementById('scheduleContainerIndiv');
            if (anyChecked) {
                publishButton.classList.remove('hidden');
                scheduleContainerIndiv.classList.remove('hidden');
            } else {
                publishButton.classList.add('hidden');
                scheduleContainerIndiv.classList.add('hidden');
            }
        }

        document.getElementById('scheduleToggleBulk').addEventListener('change', function () {
            document.getElementById('scheduleTimeContainerBulk').style.display = this.checked ? 'block' : 'none';
            if (!this.checked) document.querySelector('input[name="publish_at"]').value = '';
        });

        document.getElementById('scheduleToggleIndiv').addEventListener('change', function () {
            document.getElementById('scheduleTimeContainerIndiv').style.display = this.checked ? 'block' : 'none';
            if (!this.checked) document.querySelector('input[name="publish_at_indiv"]').value = '';
        });

        // Form submission validation
        document.getElementById('publishForm').addEventListener('submit', function (e) {
            const checkedBoxes = document.querySelectorAll('input[name="results[]"]:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one result to publish.');
            }
        });
    </script>
</body>

</html>