<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Could not connect to the database.");
    }
    
    // Build SQL Dump
    $sql_dump = "-- Student Result Management System Database Backup\n";
    $sql_dump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Get tables
    $tables = array();
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    foreach ($tables as $table) {
        $sql_dump .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
        
        $createTableResult = $db->query("SHOW CREATE TABLE `" . $table . "`");
        $createTableRow = $createTableResult->fetch(PDO::FETCH_NUM);
        $sql_dump .= $createTableRow[1] . ";\n\n";
        
        $rowsResult = $db->query("SELECT * FROM `" . $table . "`");
        $columnCount = $rowsResult->columnCount();
        
        while ($row = $rowsResult->fetch(PDO::FETCH_NUM)) {
            $sql_dump .= "INSERT INTO `" . $table . "` VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                if (isset($row[$j])) {
                    $sql_dump .= $db->quote($row[$j]);
                } else {
                    $sql_dump .= 'NULL';
                }
                if ($j < ($columnCount - 1)) {
                    $sql_dump .= ',';
                }
            }
            $sql_dump .= ");\n";
        }
        $sql_dump .= "\n";
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Download file
    $filename = 'student_result_backup_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql_dump));
    echo $sql_dump;
    exit;

} catch (Exception $e) {
    // If an error occurred, render a nice HTML page with the error details
    $error_message = $e->getMessage();
    $admin_username = $_SESSION['admin_username'] ?? 'Admin';
    
    // Render the error page using standard layout
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Backup Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                margin: 0;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background: var(--bg);
                color: var(--text);
            }
            .app-wrapper {
                display: flex;
                min-height: 100vh;
            }
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
            }
            .topbar h2 {
                margin: 0;
                font-size: 24px;
                font-weight: 700;
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
            }
            .panel-header h5 {
                margin: 0;
                font-weight: 700;
                font-size: 18px;
            }
            .panel-body {
                padding: 24px;
            }
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
                    <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                    <li><a href="analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
                    <li><a href="../students/list.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
                    <li><a href="../subjects/list.php"><i class="fa-solid fa-book-open"></i> Subjects</a></li>
                    <li><a href="../results/create.php"><i class="fa-solid fa-file-circle-plus"></i> Add Results</a></li>
                    <li><a href="../results/list.php"><i class="fa-solid fa-table-list"></i> View Results</a></li>
                    <li><a href="../results/publish.php"><i class="fa-solid fa-bullhorn"></i> Publish Results</a></li>
                    <li><a href="change-password.php"><i class="fa-solid fa-key"></i> Change Password</a></li>
                    <li><a href="backup.php" class="active"><i class="fa-solid fa-database"></i> Backup Database</a></li>
                    <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                </ul>
            </aside>
            <main class="main-content">
                <div class="topbar">
                    <div>
                        <h2>Backup Database</h2>
                        <p style="margin:4px 0 0; color:#64748b; font-size:14px;">Create and download database backups.</p>
                    </div>
                    <div class="topbar-right">
                        <div class="admin-pill">
                            <i class="fa-solid fa-user-shield me-2"></i><?php echo htmlspecialchars($admin_username); ?>
                        </div>
                        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a>
                    </div>
                </div>
                <div class="content">
                    <div class="panel-card">
                        <div class="panel-header">
                            <h5 class="text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Backup Failed</h5>
                        </div>
                        <div class="panel-body">
                            <p>An error occurred while generating the database backup:</p>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                            <a href="dashboard.php" class="btn btn-primary px-4 py-2" style="border-radius:10px; font-weight:600;">
                                <i class="fa-solid fa-arrow-left me-2"></i>Return to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
    </html>
    <?php
}
