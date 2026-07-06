<?php
session_start();
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include_once '../config/database.php';
include_once '../classes/Student.php';

$database = new Database();
$db = $database->getConnection();
$student = new Student($db);

$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Total students
$stmt = $student->read();
$total_students = $stmt->rowCount();

// Total subjects
$stmt = $db->query("SELECT COUNT(*) as total FROM subjects");
$total_subjects = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Published results
$stmt = $db->query("SELECT COUNT(*) as total FROM results WHERE published = 1");
$published_results = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Pending results
$stmt = $db->query("SELECT COUNT(*) as total FROM results WHERE published = 0");
$pending_results = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Total classes
$stmt = $db->query("SELECT COUNT(DISTINCT degree_programme) as total FROM students");
$total_classes = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// System Summary
$stmt = $db->query("SELECT AVG(marks) as avg_marks FROM results WHERE published = 1");
$overall_average = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_marks'] ?? 0, 2);

$stmt = $db->query("SELECT COUNT(DISTINCT student_id) as total FROM results WHERE published = 1");
$students_with_results = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root{ --bg:#f8fafc; --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; }
        body{ margin:0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background:var(--bg); color:var(--text); }
        .app-wrapper{ display:flex; min-height:100vh; }
        .sidebar{ width:270px; background:linear-gradient(180deg, #0f172a 0%, #1e293b 100%); color:#fff; position:fixed; left:0; top:0; bottom:0; padding:24px 16px; overflow-y:auto; box-shadow:8px 0 30px rgba(2,6,23,0.18); }
        .brand{ display:flex; align-items:center; gap:12px; padding:10px 12px 24px; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:20px; }
        .brand-icon{ width:50px; height:50px; border-radius:14px; background:linear-gradient(135deg, #3b82f6, #1d4ed8); display:flex; align-items:center; justify-content:center; font-size:22px; color:#fff; flex-shrink:0; }
        .brand h4{ margin:0; font-size:18px; font-weight:700; }
        .sidebar-menu{ list-style:none; padding:0; margin:0; }
        .sidebar-menu li{ margin-bottom:8px; }
        .sidebar-menu a{ text-decoration:none; color:#e2e8f0; display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:14px; transition:all 0.25s ease; font-size:15px; font-weight:500; }
        .sidebar-menu a:hover, .sidebar-menu a.active{ background:rgba(59,130,246,0.18); color:#fff; transform:translateX(4px); }
        .main-content{ margin-left:270px; width:calc(100% - 270px); min-height:100vh; }
        .topbar{ background:#fff; border-bottom:1px solid var(--border); padding:18px 28px; display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 16px; position:sticky; top:0; z-index:10; }
        .topbar h2{ margin:0; font-size:28px; font-weight:700; color:var(--text); }
        .admin-pill{ background:#eff6ff; color:#1d4ed8; border:1px solid #dbeafe; padding:10px 16px; border-radius:999px; font-weight:600; }
        .logout-btn{ background:#ef4444; border:none; color:#fff; text-decoration:none; padding:10px 16px; border-radius:12px; font-weight:600; }
        .content{ padding:28px; }
        .hero-card{ background:linear-gradient(135deg, #2563eb, #1e40af); border-radius:24px; padding:30px; color:#fff; margin-bottom:24px; box-shadow:0 12px 32px rgba(37,99,235,0.28); }
        .hero-card h3{ margin:0 0 8px; font-size:30px; font-weight:700; }
        .stat-card{ background:var(--card); border-radius:20px; padding:22px; box-shadow:0 8px 30px rgba(15,23,42,0.06); height:100%; }
        .stat-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .stat-label{ color:var(--muted); font-size:14px; margin-bottom:6px; }
        .stat-value{ font-size:32px; font-weight:700; line-height:1; }
        .stat-icon{ width:56px; height:56px; border-radius:16px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:22px; }
        .icon-blue{background:linear-gradient(135deg,#3b82f6,#2563eb);} .icon-green{background:linear-gradient(135deg,#22c55e,#16a34a);} .icon-yellow{background:linear-gradient(135deg,#f59e0b,#d97706);} .icon-red{background:linear-gradient(135deg,#ef4444,#dc2626);}
        .stat-footer{ margin-top:14px; }
        .stat-footer a{ text-decoration:none; color:#1d4ed8; font-weight:600; font-size:14px; }
        .panel-card{ background:#fff; border-radius:22px; box-shadow:0 8px 30px rgba(15,23,42,0.06); margin-bottom:24px; }
        .panel-header{ padding:20px 24px; border-bottom:1px solid var(--border); }
        .panel-header h5{ margin:0; font-weight:700; font-size:18px; }
        .panel-body{ padding:24px; }
        .mini-summary{ display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; }
        .mini-box{ background:#f8fafc; border:1px solid var(--border); border-radius:18px; padding:18px; }
        .mini-box .label{ color:var(--muted); font-size:13px; margin-bottom:8px; }
        .mini-box .value{ font-size:24px; font-weight:700; }
        .quick-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; }
        .quick-action{ display:flex; align-items:center; gap:12px; padding:16px; border:1px solid var(--border); border-radius:18px; text-decoration:none; color:var(--text); background:#f8fafc; font-weight:600; }
        .quick-icon{ width:46px; height:46px; border-radius:14px; color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; }
        .bg-blue{background:#2563eb;} .bg-yellow{background:#f59e0b;} .bg-green{background:#16a34a;} .bg-cyan{background:#0891b2;} .bg-purple{background:#7c3aed;} .bg-slate{background:#475569;}
    </style>
</head>
<body>
<div class="app-wrapper">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div><h4>Result Portal</h4><small>Admin Panel</small></div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
            <li><a href="../students/list.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
            <li><a href="../subjects/list.php"><i class="fa-solid fa-book-open"></i> Subjects</a></li>
            <li><a href="../results/create.php"><i class="fa-solid fa-file-circle-plus"></i> Add Results</a></li>
            <li><a href="../results/list.php"><i class="fa-solid fa-table-list"></i> View Results</a></li>
            <li><a href="../results/publish.php"><i class="fa-solid fa-bullhorn"></i> Publish Results</a></li>
            <li><a href="change-password.php"><i class="fa-solid fa-key"></i> Change Password</a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> Backup Database</a></li>
            <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Admin Dashboard</h2>
                <p style="margin:4px 0 0; color:#64748b; font-size:14px;">Manage students, subjects, results, publications, and reports from one place.</p>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="admin-pill"><i class="fa-solid fa-user-shield me-2"></i><?php echo htmlspecialchars($admin_username); ?></div>
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a>
            </div>
        </div>

        <div class="content">
            <div class="hero-card">
                <h3>Welcome back, <?php echo htmlspecialchars($admin_username); ?>!</h3>
                <p>Here is the latest overview of your Student Result Management System.</p>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div><div class="stat-label">Total Students</div><div class="stat-value"><?php echo $total_students; ?></div></div>
                            <div class="stat-icon icon-blue"><i class="fa-solid fa-user-graduate"></i></div>
                        </div>
                        <div class="stat-footer"><a href="../students/list.php">View students <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div><div class="stat-label">Total Subjects</div><div class="stat-value"><?php echo $total_subjects; ?></div></div>
                            <div class="stat-icon icon-yellow"><i class="fa-solid fa-book"></i></div>
                        </div>
                        <div class="stat-footer"><a href="../subjects/list.php">Manage subjects <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div><div class="stat-label">Published Results</div><div class="stat-value"><?php echo $published_results; ?></div></div>
                            <div class="stat-icon icon-green"><i class="fa-solid fa-check-circle"></i></div>
                        </div>
                        <div class="stat-footer"><a href="../results/list.php">View results <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div><div class="stat-label">Pending Results</div><div class="stat-value"><?php echo $pending_results; ?></div></div>
                            <div class="stat-icon icon-red"><i class="fa-solid fa-clock"></i></div>
                        </div>
                        <div class="stat-footer"><a href="../results/publish.php">Publish now <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
                    </div>
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-header"><h5><i class="fa-solid fa-chart-simple me-2 text-primary"></i>System Summary</h5></div>
                <div class="panel-body">
                    <div class="mini-summary">
                        <div class="mini-box"><div class="label">Degree Programmes Available</div><div class="value"><?php echo $total_classes; ?></div></div>
                        <div class="mini-box"><div class="label">Students With Published Results</div><div class="value"><?php echo $students_with_results; ?></div></div>
                        <div class="mini-box"><div class="label">Overall Average Marks</div><div class="value"><?php echo $overall_average; ?></div></div>
                        <div class="mini-box"><div class="label">Publication Status</div><div class="value"><?php echo $published_results > 0 ? 'Active' : 'Idle'; ?></div></div>
                    </div>
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-header"><h5><i class="fa-solid fa-bolt me-2 text-warning"></i>Quick Actions</h5></div>
                <div class="panel-body">
                    <div class="quick-grid">
                        <a href="../students/create.php" class="quick-action"><div class="quick-icon bg-blue"><i class="fa-solid fa-user-plus"></i></div><div>Add Student</div></a>
                        <a href="../subjects/create.php" class="quick-action"><div class="quick-icon bg-yellow"><i class="fa-solid fa-book-medical"></i></div><div>Add Subject</div></a>
                        <a href="../results/create.php" class="quick-action"><div class="quick-icon bg-green"><i class="fa-solid fa-file-circle-plus"></i></div><div>Add Result</div></a>
                        <a href="../results/publish.php" class="quick-action"><div class="quick-icon bg-cyan"><i class="fa-solid fa-bullhorn"></i></div><div>Publish Results</div></a>
                        <a href="change-password.php" class="quick-action"><div class="quick-icon bg-purple"><i class="fa-solid fa-key"></i></div><div>Change Password</div></a>
                        <a href="backup.php" class="quick-action"><div class="quick-icon bg-slate"><i class="fa-solid fa-database"></i></div><div>Backup Database</div></a>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>