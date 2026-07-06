<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

include_once '../config/database.php';
include_once '../classes/Student.php';

$database = new Database();
$db = $database->getConnection();
$student = new Student($db);

// Delete student
if (isset($_GET['delete']) && isset($_GET['id'])) {
    if ($student->delete($_GET['id'])) {
        $delete_message = '<div class="alert alert-success">Student was deleted successfully.</div>';
    } else {
        $delete_message = '<div class="alert alert-danger">Unable to delete student.</div>';
    }
}

// Get all students
$stmt = $student->read();

// Messages
$message = "";
if (isset($_GET['message'])) {
    if ($_GET['message'] == 'updated') {
        $message = '<div class="alert alert-success">Student was updated successfully.</div>';
    } else if ($_GET['message'] == 'created') {
        $message = '<div class="alert alert-success">Student was created successfully.</div>';
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Students List - Result Management System</title>
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

        <div class="row mb-3">
            <div class="col-md-6">
                <h2>Students List</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="create.php" class="btn btn-primary">
                    <i class="fa fa-plus"></i> Add New Student
                </a>
                <a href="bulk_upload.php" class="btn btn-info ms-2 text-white">
                    <i class="fa fa-upload"></i> Bulk Upload
                </a>
                <a href="../admin/dashboard.php" class="btn btn-dark ms-2">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($message)
            echo $message; ?>
        <?php if (isset($delete_message))
            echo $delete_message; ?>

        <div class="card">
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">

                        <thead>
                            <tr>
                                <th>Register Number</th>
                                <th>Name</th>
                                <th>Degree Programme</th>
                                <th>Added On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            if ($stmt->rowCount() > 0) {
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['register_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['degree_programme']) . "</td>"; // ✅ FIXED
                                    echo "<td>" . date('d M Y', strtotime($row['created_at'])) . "</td>";
                                    echo "<td>";
                                    echo "<a href='edit.php?id={$row['id']}' class='btn btn-sm btn-info me-2'>
                                                <i class='fa fa-edit'></i> Edit
                                              </a>";

                                    echo "<a href='list.php?delete=true&id={$row['id']}'
                                                class='btn btn-sm btn-danger'
                                                onclick='return confirm(\"Are you sure?\")'>
                                                <i class='fa fa-trash'></i> Delete
                                              </a>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center'>No students found.</td></tr>";
                            }
                            ?>
                        </tbody>

                    </table>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>