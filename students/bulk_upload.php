<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

$message = "";
if (isset($_GET['success'])) {
    $message = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> Students successfully uploaded!</div>';
} else if (isset($_GET['error'])) {
    $message = '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' . htmlspecialchars($_GET['error']) . '</div>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Bulk Upload Students - Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
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
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa fa-upload"></i> Bulk Upload Students</h3>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>

                        <div class="alert alert-info">
                            <h5><i class="fa fa-info-circle"></i> Instructions</h5>
                            <p>Upload an Excel (.xlsx, .xls) or CSV file containing student details.</p>
                            <p>The file <strong>must</strong> have the following exact headers in the first row:</p>
                            <ul>
                                <li><code>register_number</code> (Required, Unique)</li>
                                <li><code>name</code> (Required)</li>
                                <li><code>email</code> (Required)</li>
                                <li><code>degree_programme</code> (Required)</li>
                                <li><code>date_of_birth</code> (Optional, format YYYY-MM-DD)</li>
                            </ul>
                            <p>If a register number already exists in the system, that student's details will be updated.</p>
                        </div>

                        <form action="process_bulk_upload.php" method="post" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Select File (Excel/CSV)</label>
                                <input type="file" class="form-control" name="student_file" accept=".xlsx,.xls,.csv" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-upload"></i> Upload Students
                            </button>
                            <a href="list.php" class="btn btn-secondary me-2">
                                <i class="fa fa-list"></i> Back to List
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
